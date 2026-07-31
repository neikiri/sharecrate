<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Config;
use App\Core\Database;
use App\Core\Geo;
use App\Core\I18n;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Url;
use App\Models\ActivityLog;
use App\Models\Setting;
use App\Support\Formatter;
use App\Support\Maintenance;
use App\Support\Storage;
use App\Support\Thumbnailer;

final class SettingsController extends Controller
{
    private const TIMEZONES = [
        'UTC',
        'Europe/Prague',
        'Europe/Bratislava',
        'Europe/Vienna',
        'Europe/Berlin',
        'Europe/Warsaw',
        'Europe/Budapest',
        'Europe/London',
        'Europe/Paris',
        'Europe/Madrid',
        'Europe/Rome',
        'Europe/Amsterdam',
        'Europe/Zurich',
        'Europe/Stockholm',
        'Europe/Kyiv',
        'America/New_York',
        'America/Chicago',
        'America/Denver',
        'America/Los_Angeles',
        'America/Sao_Paulo',
        'Asia/Dubai',
        'Asia/Tokyo',
        'Asia/Singapore',
        'Australia/Sydney',
    ];

    public function edit(Request $request): never
    {
        $this->requireAdmin();

        $this->renderAdmin('admin/settings', [
            'timezones' => $this->timezones(),
            'system' => $this->systemInfo(),
            'limits' => Storage::limits(),
        ]);
    }

    public function update(Request $request): never
    {
        $this->guard($request);
        $this->requireAdmin();

        if ($request->input('action') === 'maintenance') {
            $report = Maintenance::run();
            Session::flash('success', I18n::t('settings.maintenance_done', ['count' => array_sum($report)]));

            Response::redirect(Url::to('/admin/settings'));
        }

        $timezone = (string) $request->input('timezone', 'Europe/Prague');

        if (!in_array($timezone, timezone_identifiers_list(), true)) {
            $timezone = 'Europe/Prague';
        }

        $aliasStyle = (string) $request->input('alias_style', 'slug');

        if (!in_array($aliasStyle, ['slug', 'slug_random', 'random'], true)) {
            $aliasStyle = 'slug';
        }

        $privacy = (string) $request->input('privacy_ip_mode', 'full');

        if (!in_array($privacy, ['full', 'anonymised', 'none'], true)) {
            $privacy = 'full';
        }

        Setting::setMany([
            'site_name' => mb_substr(trim((string) $request->input('site_name', 'ShareCrate')), 0, 100) ?: 'ShareCrate',
            'site_tagline' => mb_substr(trim((string) $request->input('site_tagline', '')), 0, 190),
            'contact_email' => mb_substr(trim((string) $request->input('contact_email', '')), 0, 190),
            'timezone' => $timezone,
            'alias_style' => $aliasStyle,
            'alias_random_len' => (string) max(3, min(16, (int) $request->input('alias_random_len', 6))),
            'privacy_ip_mode' => $privacy,
            'log_retention_days' => (string) max(0, min(3650, (int) $request->input('log_retention_days', 365))),
            'max_upload_mb' => (string) max(0, min(102400, (int) $request->input('max_upload_mb', 0))),
            'default_expiry_days' => (string) max(0, min(3650, (int) $request->input('default_expiry_days', 0))),
            'allow_uploader_delete' => $request->bool('allow_uploader_delete') ? '1' : '0',
            'show_file_owner' => $request->bool('show_file_owner') ? '1' : '0',
        ]);

        Formatter::resetTimezone();
        ActivityLog::record('settings.updated');
        Session::flash('success', I18n::t('settings.updated'));

        Response::redirect(Url::to('/admin/settings'));
    }

    /** @return string[] */
    private function timezones(): array
    {
        $current = (string) (Setting::get('timezone', 'Europe/Prague') ?? 'Europe/Prague');
        $list = self::TIMEZONES;

        if (!in_array($current, $list, true)) {
            $list[] = $current;
        }

        return $list;
    }

    /** @return array<string, string|bool> */
    private function systemInfo(): array
    {
        $databaseVersion = 'n/a';

        try {
            $databaseVersion = (string) Database::pdo()->getAttribute(\PDO::ATTR_SERVER_VERSION);
        } catch (\Throwable) {
            // keep the placeholder
        }

        return [
            'php' => PHP_VERSION,
            'database' => $databaseVersion,
            'storage_path' => Storage::root(),
            'storage_writable' => Storage::writable(),
            'gd' => Thumbnailer::available(),
            'geo' => Geo::provider(),
            'base_path' => Config::basePath() === '' ? '/' : Config::basePath(),
            'app_url' => (string) (Config::get('APP_URL') ?? '-'),
        ];
    }
}
