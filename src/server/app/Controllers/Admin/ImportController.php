<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\I18n;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Url;
use App\Models\FileItem;
use App\Support\Scanner;
use App\Support\Storage;

/**
 * Turns files that arrived over FTP into shareable links.
 */
final class ImportController extends Controller
{
    public function index(Request $request): never
    {
        $this->requireUser();

        $pending = Scanner::pending(300);
        $missing = $this->isAdmin() ? FileItem::missingOnDisk(200) : [];

        $this->renderAdmin('admin/import', [
            'pending' => $pending,
            'missing' => $missing,
            'storagePath' => Storage::root(),
            'storageWritable' => Storage::writable(),
        ]);
    }

    public function store(Request $request): never
    {
        $this->guard($request);
        $user = $this->requireUser();

        $paths = $request->arr('paths');
        $importAll = $request->bool('all');

        if (!$importAll && $paths === []) {
            Session::flash('error', I18n::t('import.nothing_selected'));

            Response::redirect(Url::to('/admin/import'));
        }

        $result = Scanner::importAll((int) $user['id'], $importAll ? [] : $paths, 'ftp');

        if ($result['imported'] === 0) {
            Session::flash('info', I18n::t('import.done_none'));
        } else {
            Session::flash('success', I18n::t('import.done', ['count' => $result['imported']]));
        }

        Response::redirect(Url::to('/admin/files'));
    }
}
