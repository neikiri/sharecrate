<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\I18n;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Url;
use App\Models\FileItem;
use App\Support\Downloader;
use App\Support\FileTypes;
use App\Support\Thumbnailer;

/**
 * The public side: file landing page, password gate, download stream,
 * inline preview and thumbnails.
 */
final class FileController extends Controller
{
    private const UNLOCK_TTL = 7200;

    private const MAX_PASSWORD_ATTEMPTS = 6;

    private const THROTTLE_WINDOW = 900;

    /** @param array<string, string> $params */
    public function show(Request $request, array $params): never
    {
        $file = $this->resolve($params['alias'] ?? '');
        $this->assertAvailable($file);

        if (FileItem::hasPassword($file) && !$this->isUnlocked($file)) {
            $this->renderPasswordGate($file);
        }

        $view = $this->view();
        $view->layout = 'layouts/public';
        $view->title = FileItem::displayName($file);
        $view->description = t('file.ready') . ' · ' . bytes_human((int) $file['size_bytes']);
        $view->noindex = true;
        $view->canonical = Url::absolute('/' . $file['alias']);

        $extension = (string) ($file['extension'] ?? '');
        $previewKind = FileTypes::previewKind($extension);
        $canPreview = (int) $file['allow_preview'] === 1
            && $previewKind !== 'none'
            && !FileTypes::forceAttachment($extension);

        $view->display('public/file', [
            'file' => $file,
            'previewKind' => $canPreview ? $previewKind : 'none',
            'canPreview' => $canPreview,
            'thumbnail' => $canPreview && FileTypes::isThumbnailable($extension) && Thumbnailer::available(),
        ]);
    }

    /** @param array<string, string> $params */
    public function unlock(Request $request, array $params): never
    {
        $this->guard($request);

        $file = $this->resolve($params['alias'] ?? '');
        $this->assertAvailable($file);

        if (!FileItem::hasPassword($file)) {
            Response::redirect(Url::to('/' . $file['alias']));
        }

        $bucket = RateLimiter::key('file-password', (string) $file['id'], $request->ip());

        if (RateLimiter::tooManyAttempts($bucket, self::MAX_PASSWORD_ATTEMPTS)) {
            $minutes = max(1, (int) ceil(RateLimiter::availableIn($bucket) / 60));

            $this->renderPasswordGate($file, I18n::t('file.password_throttled', ['minutes' => $minutes]), 429);
        }

        $password = (string) $request->input('password', '');

        if ($password === '' || !password_verify($password, (string) $file['password_hash'])) {
            RateLimiter::hit($bucket, self::THROTTLE_WINDOW);

            $this->renderPasswordGate($file, I18n::t('file.password_wrong'), 422);
        }

        RateLimiter::clear($bucket);

        $unlocked = Session::get('_unlocked', []);
        $unlocked = is_array($unlocked) ? $unlocked : [];
        $unlocked[(string) $file['id']] = time();
        Session::put('_unlocked', $unlocked);

        Session::flash('success', I18n::t('file.unlocked'));

        Response::redirect(Url::to('/' . $file['alias']));
    }

    /** @param array<string, string> $params */
    public function download(Request $request, array $params): never
    {
        $file = $this->resolve($params['alias'] ?? '');
        $this->assertAvailable($file);
        $this->assertUnlocked($file);

        Downloader::send($file, false, true);
    }

    /** @param array<string, string> $params */
    public function preview(Request $request, array $params): never
    {
        $file = $this->resolve($params['alias'] ?? '');
        $this->assertAvailable($file, true);
        $this->assertUnlocked($file);

        $extension = (string) ($file['extension'] ?? '');

        if ((int) $file['allow_preview'] !== 1 && !$this->canManageFile($file)) {
            throw HttpException::forbidden();
        }

        if (!FileTypes::isInlinePreviewable($extension) || FileTypes::forceAttachment($extension)) {
            throw HttpException::notFound();
        }

        Downloader::send($file, true, false);
    }

    /** @param array<string, string> $params */
    public function thumbnail(Request $request, array $params): never
    {
        $file = $this->resolve($params['alias'] ?? '');
        $this->assertAvailable($file, true);
        $this->assertUnlocked($file);

        if ((int) $file['allow_preview'] !== 1 && !$this->canManageFile($file)) {
            throw HttpException::forbidden();
        }

        $size = (int) ($request->queryParam('s', 640));
        $thumbnail = Thumbnailer::get($file, $size);

        if ($thumbnail === null) {
            throw HttpException::notFound();
        }

        Downloader::sendThumbnail($thumbnail['path'], $thumbnail['mime']);
    }

    /* ----------------------------------------------------------------
     * Internals
     * ---------------------------------------------------------------- */

    /** @return array<string, mixed> */
    private function resolve(string $alias): array
    {
        $alias = trim($alias);

        if ($alias === '') {
            throw HttpException::notFound();
        }

        $file = FileItem::findByAlias($alias);

        if ($file === null) {
            throw HttpException::notFound();
        }

        return $file;
    }

    /**
     * @param array<string, mixed> $file
     */
    private function assertAvailable(array $file, bool $silent = false): void
    {
        // The owner and administrators can always reach their own file.
        if ($this->canManageFile($file) && \App\Support\Storage::exists((string) $file['path'])) {
            return;
        }

        $state = FileItem::state($file);

        if ($state === 'active') {
            return;
        }

        if ($silent) {
            throw HttpException::notFound();
        }

        throw match ($state) {
            'disabled' => new HttpException(403, 'Disabled', 'errors.file_disabled'),
            'expired' => HttpException::gone('errors.file_expired'),
            'limit' => HttpException::gone('errors.file_limit'),
            default => HttpException::gone('errors.file_missing'),
        };
    }

    /** @param array<string, mixed> $file */
    private function assertUnlocked(array $file): void
    {
        if (!FileItem::hasPassword($file)) {
            return;
        }

        if ($this->isUnlocked($file)) {
            return;
        }

        Response::redirect(Url::to('/' . $file['alias']));
    }

    /** @param array<string, mixed> $file */
    private function isUnlocked(array $file): bool
    {
        if ($this->canManageFile($file)) {
            return true;
        }

        $unlocked = Session::get('_unlocked', []);

        if (!is_array($unlocked)) {
            return false;
        }

        $at = (int) ($unlocked[(string) $file['id']] ?? 0);

        return $at > 0 && $at > time() - self::UNLOCK_TTL;
    }

    /**
     * @param array<string, mixed> $file
     */
    private function renderPasswordGate(array $file, ?string $error = null, int $status = 200): never
    {
        $view = $this->view();
        $view->layout = 'layouts/public';
        $view->title = t('file.password_required');
        $view->noindex = true;

        $view->display('public/password', [
            'file' => $file,
            'passwordError' => $error,
        ], $status);
    }
}
