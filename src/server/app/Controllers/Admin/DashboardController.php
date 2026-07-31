<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Request;
use App\Models\ActivityLog;
use App\Models\Download;
use App\Models\FileItem;
use App\Support\Scanner;
use App\Support\Storage;

final class DashboardController extends Controller
{
    public function index(Request $request): never
    {
        $user = $this->requireUser();
        $ownerId = $this->isAdmin() ? null : (int) $user['id'];

        $pending = Scanner::pending(60);
        $missing = $this->isAdmin() ? FileItem::missingOnDisk(200) : [];

        $this->renderAdmin('admin/dashboard', [
            'stats' => FileItem::stats($ownerId),
            'series' => Download::perDay(30, null, $ownerId),
            'downloads30' => Download::sinceCount(30, null, $ownerId),
            'uniqueVisitors' => Download::uniqueVisitors(null, $ownerId),
            'recentFiles' => FileItem::recent(5, $ownerId),
            'topFiles' => FileItem::mostDownloaded(5, $ownerId),
            'countries' => Download::byCountry(6, null, $ownerId),
            'activity' => $this->isAdmin() ? ActivityLog::recent(8) : [],
            'pendingCount' => count($pending),
            'missingCount' => count($missing),
            'freeSpace' => Storage::freeSpace(),
        ]);
    }
}
