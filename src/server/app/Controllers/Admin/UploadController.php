<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Csrf;
use App\Core\I18n;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Url;
use App\Models\ActivityLog;
use App\Models\FileItem;
use App\Models\Setting;
use App\Models\User;
use App\Support\Alias;
use App\Support\FileTypes;
use App\Support\Formatter;
use App\Support\Storage;

final class UploadController extends Controller
{
    public function form(Request $request): never
    {
        $user = $this->requireUser();

        $quota = $user['quota_bytes'] === null ? null : (int) $user['quota_bytes'];

        $this->renderAdmin('admin/upload', [
            'limits' => Storage::limits(),
            'storageWritable' => Storage::writable(),
            'storagePath' => Storage::root(),
            'quota' => $quota,
            'quotaUsed' => $quota === null ? 0 : User::storageUsed((int) $user['id']),
            'defaultExpiryDays' => Setting::int('default_expiry_days', 0),
        ]);
    }

    public function store(Request $request): never
    {
        $user = $this->requireUser();

        // A POST bigger than post_max_size arrives with empty $_POST/$_FILES.
        $contentLength = (int) ($request->server['CONTENT_LENGTH'] ?? 0);
        $postMax = Storage::iniBytes((string) ini_get('post_max_size'));

        if ($request->post === [] && $request->files === [] && $contentLength > 0) {
            $this->fail(I18n::t('upload.error_ini', [
                'size' => bytes_human($postMax > 0 ? $postMax : $contentLength),
            ]), 413);
        }

        Csrf::check($request);

        $upload = $request->file('file');

        if ($upload === null || !isset($upload['error'])) {
            $this->fail(I18n::t('upload.no_file'), 422);
        }

        $error = (int) $upload['error'];

        if ($error !== UPLOAD_ERR_OK) {
            $this->fail($this->uploadErrorMessage($error), 422);
        }

        $size = (int) ($upload['size'] ?? 0);
        $maxBytes = Storage::maxUploadBytes();

        if ($maxBytes > 0 && $size > $maxBytes) {
            $this->fail(I18n::t('upload.error_ini', ['size' => bytes_human($maxBytes)]), 413);
        }

        // Per user quota
        $quota = $user['quota_bytes'] === null ? null : (int) $user['quota_bytes'];

        if ($quota !== null && $quota > 0 && User::storageUsed((int) $user['id']) + $size > $quota) {
            $this->fail(I18n::t('upload.error_quota'), 413);
        }

        if (!Storage::ensure()) {
            $this->fail(I18n::t('upload.error_write'), 500);
        }

        try {
            $relativePath = Storage::storeUpload($upload);
        } catch (\Throwable $e) {
            $this->fail(I18n::t('upload.error_write'), 500);
        }

        $originalName = Storage::safeName((string) ($upload['name'] ?? basename($relativePath)));
        $extension = FileTypes::extension($originalName);

        $requestedAlias = (string) $request->input('alias', '');
        $alias = $requestedAlias !== ''
            ? Alias::unique(Alias::slug($requestedAlias, true))
            : Alias::unique(Alias::fromFilename($originalName));

        $expiresAt = Formatter::fromDisplayInput((string) $request->input('expires_at', ''));
        $defaultDays = Setting::int('default_expiry_days', 0);

        if ($expiresAt === null && $defaultDays > 0) {
            $expiresAt = gmdate('Y-m-d H:i:s', time() + $defaultDays * 86400);
        }

        $maxDownloads = (int) $request->input('max_downloads', 0);
        $password = (string) $request->raw('password', '');

        $id = FileItem::create([
            'alias' => $alias,
            'description' => (string) $request->input('description', ''),
            'original_name' => $originalName,
            'path' => $relativePath,
            'mime_type' => FileTypes::detectMime(Storage::path($relativePath), $extension),
            'size_bytes' => (int) (Storage::size($relativePath) ?? $size),
            'password' => $password !== '' ? $password : null,
            'owner_id' => (int) $user['id'],
            'source' => 'web',
            'status' => 'active',
            'allow_preview' => true,
            'expires_at' => $expiresAt,
            'max_downloads' => $maxDownloads > 0 ? $maxDownloads : null,
        ]);

        ActivityLog::record('file.created', $alias, ['id' => $id, 'size' => $size], (int) $user['id']);

        $payload = [
            'ok' => true,
            'file' => [
                'id' => $id,
                'alias' => $alias,
                'name' => $originalName,
                'size' => $size,
                'size_label' => bytes_human($size),
                'url' => Url::absolute('/' . $alias),
                'download_url' => Url::absolute('/d/' . $alias),
                'admin_url' => Url::to('/admin/files/' . $id),
                'protected' => $password !== '',
            ],
        ];

        if ($request->wantsJson()) {
            Response::json($payload);
        }

        Session::flash('success', I18n::t('upload.success', ['name' => $originalName]));

        Response::redirect(Url::to('/admin/files/' . $id));
    }

    private function fail(string $message, int $status): never
    {
        $request = Request::current();

        if ($request->wantsJson()) {
            Response::json(['ok' => false, 'message' => $message], $status);
        }

        Session::flash('error', $message);

        Response::redirect(Url::to('/admin/upload'));
    }

    private function uploadErrorMessage(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => I18n::t('upload.error_ini', [
                'size' => bytes_human(Storage::maxUploadBytes()),
            ]),
            UPLOAD_ERR_PARTIAL => I18n::t('upload.error_partial'),
            UPLOAD_ERR_NO_FILE => I18n::t('upload.no_file'),
            UPLOAD_ERR_CANT_WRITE, UPLOAD_ERR_NO_TMP_DIR => I18n::t('upload.error_write'),
            default => I18n::t('upload.failed'),
        };
    }
}
