<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\I18n;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Url;
use App\Core\Validator;
use App\Support\Storage;
use App\Support\Thumbnailer;
use PDO;

/**
 * First run wizard: writes .env, creates the schema and the first admin.
 */
final class InstallController extends Controller
{
    public function index(Request $request): never
    {
        if ($request->queryParam('done') === '1' && Config::isConfigured()) {
            $this->page('install/success');
        }

        if (Config::isConfigured()) {
            $this->page('install/already');
        }

        $this->page('install/index', [
            'requirements' => $this->requirements(),
            'defaults' => $this->defaults($request),
            'envContent' => null,
        ]);
    }

    public function handle(Request $request): never
    {
        if (Config::isConfigured()) {
            Response::redirect(Url::to('/install'));
        }

        Csrf::check($request);

        $requirements = $this->requirements();

        if ($this->hasFailedRequirement($requirements)) {
            $this->page('install/index', [
                'requirements' => $requirements,
                'defaults' => $this->defaults($request),
                'envContent' => null,
            ], 422);
        }

        $input = [
            'db_host' => trim((string) $request->input('db_host', 'localhost')),
            'db_port' => trim((string) $request->input('db_port', '3306')),
            'db_name' => trim((string) $request->input('db_name', '')),
            'db_user' => trim((string) $request->input('db_user', '')),
            'db_pass' => (string) $request->raw('db_pass', ''),
            'app_url' => rtrim(trim((string) $request->input('app_url', '')), '/'),
            'site_name' => trim((string) $request->input('site_name', 'ShareCrate')),
            'default_locale' => (string) $request->input('default_locale', 'en'),
            'username' => mb_strtolower(trim((string) $request->input('username', ''))),
            'email' => mb_strtolower(trim((string) $request->input('email', ''))),
            'password' => (string) $request->raw('password', ''),
            'password_confirmation' => (string) $request->raw('password_confirmation', ''),
        ];

        $validator = Validator::make($input)
            ->required('db_host')
            ->required('db_name')
            ->required('db_user')
            ->required('app_url')
            ->regex('app_url', '#^https?://#i')
            ->required('username')
            ->min('username', 3)
            ->regex('username', '/^[a-z0-9._-]+$/')
            ->required('email')
            ->email('email')
            ->required('password')
            ->min('password', 10)
            ->matches('password_confirmation', 'password')
            ->in('default_locale', I18n::AVAILABLE);

        if ($validator->fails()) {
            Session::flashErrors($validator->errors());
            Session::flashInput($request->post);

            $this->page('install/index', [
                'requirements' => $requirements,
                'defaults' => $this->defaults($request),
                'envContent' => null,
            ], 422);
        }

        // 1. Connect
        try {
            $pdo = Database::connect([
                'host' => $input['db_host'],
                'port' => $input['db_port'] === '' ? '3306' : $input['db_port'],
                'name' => $input['db_name'],
                'user' => $input['db_user'],
                'pass' => $input['db_pass'],
                'charset' => 'utf8mb4',
            ]);
        } catch (\Throwable $e) {
            Session::flash('error', I18n::t('install.connection_failed', ['error' => $e->getMessage()]));
            Session::flashInput($request->post);

            $this->page('install/index', [
                'requirements' => $requirements,
                'defaults' => $this->defaults($request),
                'envContent' => null,
            ], 422);
        }

        // 2. Schema
        try {
            $schema = @file_get_contents(ROOT_PATH . '/database/schema.sql');

            if ($schema === false) {
                throw new \RuntimeException('database/schema.sql is missing.');
            }

            Database::runScript($schema, $pdo);
        } catch (\Throwable $e) {
            Session::flash('error', I18n::t('install.schema_failed', ['error' => $e->getMessage()]));

            $this->page('install/index', [
                'requirements' => $requirements,
                'defaults' => $this->defaults($request),
                'envContent' => null,
            ], 500);
        }

        // 3. First administrator
        $this->createAdmin($pdo, $input);

        // 4. Basic settings
        $this->storeSettings($pdo, $input);

        // 5. .env
        $env = Config::render([
            'APP_NAME' => $input['site_name'],
            'APP_URL' => $input['app_url'],
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'APP_BASE_PATH' => $this->guessBasePath($input['app_url']),
            'APP_KEY' => bin2hex(random_bytes(32)),
            'DB_HOST' => $input['db_host'],
            'DB_PORT' => $input['db_port'] === '' ? '3306' : $input['db_port'],
            'DB_NAME' => $input['db_name'],
            'DB_USER' => $input['db_user'],
            'DB_PASS' => $input['db_pass'],
            'DEFAULT_LOCALE' => $input['default_locale'],
        ]);

        $written = @file_put_contents(ROOT_PATH . '/.env', $env) !== false;

        if ($written) {
            @chmod(ROOT_PATH . '/.env', 0640);

            Response::redirect(Url::to('/install', ['done' => '1']));
        }

        Session::flash('error', I18n::t('install.env_failed'));

        $this->page('install/index', [
            'requirements' => $requirements,
            'defaults' => $this->defaults($request),
            'envContent' => $env,
        ], 500);
    }

    /**
     * @param array<string, string> $input
     */
    private function createAdmin(PDO $pdo, array $input): void
    {
        $existing = $pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1');
        $existing->execute([$input['username'], $input['email']]);

        $hash = password_hash($input['password'], PASSWORD_DEFAULT);
        $now = gmdate('Y-m-d H:i:s');

        if ($existing->fetchColumn() !== false) {
            $update = $pdo->prepare(
                "UPDATE users SET password_hash = ?, role = 'admin', is_active = 1, updated_at = ? WHERE username = ?"
            );
            $update->execute([$hash, $now, $input['username']]);

            return;
        }

        $insert = $pdo->prepare(
            "INSERT INTO users (username, email, display_name, password_hash, role, locale, is_active, created_at, updated_at)
             VALUES (?, ?, NULL, ?, 'admin', ?, 1, ?, ?)"
        );
        $insert->execute([
            $input['username'],
            $input['email'],
            $hash,
            $input['default_locale'],
            $now,
            $now,
        ]);
    }

    /** @param array<string, string> $input */
    private function storeSettings(PDO $pdo, array $input): void
    {
        $statement = $pdo->prepare(
            'INSERT INTO settings (setting_key, setting_value, updated_at) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = VALUES(updated_at)'
        );

        $now = gmdate('Y-m-d H:i:s');
        $values = [
            'site_name' => $input['site_name'],
            'timezone' => $input['default_locale'] === 'cs' ? 'Europe/Prague' : 'UTC',
            'installed_at' => $now,
        ];

        foreach ($values as $key => $value) {
            $statement->execute([$key, $value, $now]);
        }
    }

    /**
     * Derives APP_BASE_PATH from the entered site address.
     */
    private function guessBasePath(string $appUrl): string
    {
        $path = parse_url($appUrl, PHP_URL_PATH);

        if (!is_string($path)) {
            return '';
        }

        $path = rtrim($path, '/');

        return $path === '' ? '' : $path;
    }

    /**
     * @return array<int, array{key: string, label: string, ok: bool, required: bool, detail: string}>
     */
    private function requirements(): array
    {
        $storagePath = Storage::root();
        Storage::ensure();

        return [
            [
                'key' => 'php',
                'label' => I18n::t('install.req_php'),
                'ok' => PHP_VERSION_ID >= 80100,
                'required' => true,
                'detail' => PHP_VERSION,
            ],
            [
                'key' => 'pdo',
                'label' => I18n::t('install.req_pdo'),
                'ok' => extension_loaded('pdo_mysql'),
                'required' => true,
                'detail' => extension_loaded('pdo_mysql') ? 'pdo_mysql' : '-',
            ],
            [
                'key' => 'mbstring',
                'label' => I18n::t('install.req_mbstring'),
                'ok' => extension_loaded('mbstring'),
                'required' => true,
                'detail' => extension_loaded('mbstring') ? 'mbstring' : '-',
            ],
            [
                'key' => 'root',
                'label' => I18n::t('install.req_root_writable'),
                'ok' => is_writable(ROOT_PATH),
                'required' => true,
                'detail' => ROOT_PATH,
            ],
            [
                'key' => 'storage',
                'label' => I18n::t('install.req_storage_writable', ['path' => basename($storagePath)]),
                'ok' => Storage::writable(),
                'required' => true,
                'detail' => $storagePath,
            ],
            [
                'key' => 'gd',
                'label' => I18n::t('install.req_gd'),
                'ok' => Thumbnailer::available(),
                'required' => false,
                'detail' => Thumbnailer::available() ? 'gd' : '-',
            ],
        ];
    }

    /** @param array<int, array{ok: bool, required: bool}> $requirements */
    private function hasFailedRequirement(array $requirements): bool
    {
        foreach ($requirements as $requirement) {
            if ($requirement['required'] && !$requirement['ok']) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, string> */
    private function defaults(Request $request): array
    {
        $host = $request->isSecure() ? 'https://' : 'http://';
        $host .= $request->host();

        return [
            'app_url' => rtrim($host . Config::basePath(), '/'),
            'site_name' => $request->host(),
            'default_locale' => I18n::locale(),
        ];
    }

    /** @param array<string, mixed> $data */
    private function page(string $template, array $data = [], int $status = 200): never
    {
        $view = $this->view();
        $view->layout = 'layouts/minimal';
        $view->title = t('install.title');
        $view->noindex = true;

        $view->display($template, $data, $status);
    }
}
