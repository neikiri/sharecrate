<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\HttpException;
use App\Core\I18n;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Url;
use App\Models\ActivityLog;
use App\Models\Download;
use App\Models\FileItem;
use App\Models\Setting;
use App\Models\User;
use App\Support\Alias;
use App\Support\FileTypes;
use App\Support\Formatter;
use App\Support\Scanner;
use App\Support\Storage;
use App\Support\Thumbnailer;

final class FilesController extends Controller
{
    public function index(Request $request): never
    {
        $user = $this->requireUser();

        $filters = [
            'q' => (string) $request->queryParam('q', ''),
            'state' => (string) $request->queryParam('state', ''),
            'category' => (string) $request->queryParam('category', ''),
            'sort' => (string) $request->queryParam('sort', 'created_at'),
            'dir' => (string) $request->queryParam('dir', 'desc'),
            'page' => (int) $request->queryParam('page', 1),
            'owner' => $this->isAdmin() ? $request->queryParam('owner') : (int) $user['id'],
        ];

        $result = FileItem::paginate($filters);

        $this->renderAdmin('admin/files/index', [
            'filters' => $filters,
            'result' => $result,
            'owners' => $this->isAdmin() ? User::options() : [],
            'categories' => FileTypes::categories(),
        ]);
    }

    /** @param array<string, string> $params */
    public function show(Request $request, array $params): never
    {
        $this->requireUser();
        $file = $this->findOwned((int) $params['id']);

        $this->renderAdmin('admin/files/show', [
            'file' => $file,
            'downloads' => Download::forFile((int) $file['id'], 30),
            'series' => Download::perDay(14, (int) $file['id']),
            'uniqueVisitors' => Download::uniqueVisitors((int) $file['id']),
            'countries' => Download::byCountry(5, (int) $file['id']),
            'owners' => $this->isAdmin() ? User::options() : [],
        ]);
    }

    /** @param array<string, string> $params */
    public function update(Request $request, array $params): never
    {
        $this->guard($request);
        $this->requireUser();

        $id = (int) $params['id'];
        $file = $this->findOwned($id);
        $redirect = '/admin/files/' . $id;

        // "Refresh from disk" is a separate button on the same form.
        if ($request->input('action') === 'refresh') {
            Scanner::refresh($id);
            Session::flash('success', I18n::t('files.refreshed'));

            Response::redirect(Url::to($redirect));
        }

        $errors = [];
        $rawAlias = (string) $request->input('alias', '');
        $alias = Alias::slug($rawAlias, true);

        if ($alias === '') {
            $errors['alias'] = I18n::t('validation.required');
        } elseif (!Alias::isValid($alias)) {
            $errors['alias'] = I18n::t('files.alias_invalid');
        } elseif (Alias::isReserved($alias)) {
            $errors['alias'] = I18n::t('files.alias_reserved');
        } elseif (Alias::isTaken($alias, $id)) {
            $errors['alias'] = I18n::t('files.alias_taken');
        }

        $expiresRaw = (string) $request->input('expires_at', '');
        $expiresAt = null;

        if ($expiresRaw !== '') {
            $expiresAt = Formatter::fromDisplayInput($expiresRaw);

            if ($expiresAt === null) {
                $errors['expires_at'] = I18n::t('validation.date');
            }
        }

        $maxDownloads = (int) $request->input('max_downloads', 0);

        if ($maxDownloads < 0 || $maxDownloads > 1000000) {
            $errors['max_downloads'] = I18n::t('validation.between', ['min' => 0, 'max' => 1000000]);
        }

        $title = (string) $request->input('title', '');
        $description = (string) $request->input('description', '');

        if (mb_strlen($title) > 190) {
            $errors['title'] = I18n::t('validation.max', ['max' => 190]);
        }

        if ($errors !== []) {
            $this->backWithErrors($errors, $request->post, $redirect);
        }

        $payload = [
            'alias' => $alias,
            'title' => $title === '' ? null : $title,
            'description' => $description === '' ? null : $description,
            'status' => $request->bool('status') ? 'active' : 'disabled',
            'allow_preview' => $request->bool('allow_preview') ? 1 : 0,
            'expires_at' => $expiresAt,
            'max_downloads' => $maxDownloads > 0 ? $maxDownloads : null,
        ];

        if ($this->isAdmin()) {
            $owner = $request->input('owner_id');
            $payload['owner_id'] = is_numeric($owner) && (int) $owner > 0 ? (int) $owner : null;
        }

        FileItem::update($id, $payload);
        ActivityLog::record('file.updated', $alias, ['id' => $id]);
        Session::flash('success', I18n::t('files.updated'));

        Response::redirect(Url::to('/admin/files/' . $id));
    }

    /** @param array<string, string> $params */
    public function password(Request $request, array $params): never
    {
        $this->guard($request);
        $this->requireUser();

        $id = (int) $params['id'];
        $file = $this->findOwned($id);

        if ($request->input('action') === 'remove') {
            FileItem::setPassword($id, null);
            ActivityLog::record('file.password', (string) $file['alias'], ['action' => 'removed']);
            Session::flash('success', I18n::t('files.password_removed'));

            Response::redirect(Url::to('/admin/files/' . $id));
        }

        $password = (string) $request->raw('password', '');

        if (mb_strlen($password) < 4) {
            $this->backWithErrors(
                ['password' => I18n::t('validation.min', ['min' => 4])],
                [],
                '/admin/files/' . $id
            );
        }

        FileItem::setPassword($id, $password);
        ActivityLog::record('file.password', (string) $file['alias'], ['action' => 'set']);
        Session::flash('success', I18n::t('files.password_saved'));

        Response::redirect(Url::to('/admin/files/' . $id));
    }

    /** @param array<string, string> $params */
    public function destroy(Request $request, array $params): never
    {
        $this->guard($request);
        $user = $this->requireUser();

        $id = (int) $params['id'];
        $file = $this->findOwned($id);

        if (!$this->isAdmin() && !Setting::bool('allow_uploader_delete', true)) {
            throw HttpException::forbidden();
        }

        $deleteFromDisk = $request->bool('delete_file');
        $alias = (string) $file['alias'];

        FileItem::delete($id);
        Thumbnailer::forget($id);

        $removed = false;

        if ($deleteFromDisk) {
            $removed = Storage::delete((string) $file['path']);
        }

        ActivityLog::record('file.deleted', $alias, [
            'id' => $id,
            'file_removed' => $removed,
        ], (int) $user['id']);

        Session::flash('success', I18n::t($removed ? 'files.deleted_with_file' : 'files.deleted'));

        Response::redirect(Url::to('/admin/files'));
    }

    public function bulk(Request $request): never
    {
        $this->guard($request);
        $this->requireUser();

        $action = (string) $request->input('bulk_action', '');
        $ids = array_values(array_filter(array_map('intval', $request->arr('ids')), static fn ($id) => $id > 0));

        if ($ids === [] || !in_array($action, ['activate', 'disable', 'delete'], true)) {
            Session::flash('error', I18n::t('files.bulk_nothing'));

            Response::redirect(Url::to('/admin/files'));
        }

        $affected = 0;

        foreach ($ids as $id) {
            $file = FileItem::find($id);

            if ($file === null || !$this->canManageFile($file)) {
                continue;
            }

            if ($action === 'delete') {
                if (!$this->isAdmin() && !Setting::bool('allow_uploader_delete', true)) {
                    continue;
                }

                FileItem::delete($id);
                Thumbnailer::forget($id);

                if ($request->bool('delete_file')) {
                    Storage::delete((string) $file['path']);
                }
            } else {
                FileItem::update($id, ['status' => $action === 'activate' ? 'active' : 'disabled']);
            }

            $affected++;
        }

        ActivityLog::record('file.bulk', $action, ['count' => $affected]);
        Session::flash('success', I18n::t('files.bulk_done', ['count' => $affected]));

        Response::redirect(Url::to('/admin/files'));
    }

    /**
     * Loads a file and makes sure the current user may touch it.
     *
     * @return array<string, mixed>
     */
    private function findOwned(int $id): array
    {
        $file = FileItem::find($id);

        if ($file === null) {
            throw HttpException::notFound();
        }

        if (!$this->canManageFile($file)) {
            throw HttpException::forbidden('errors.not_owner');
        }

        return $file;
    }
}
