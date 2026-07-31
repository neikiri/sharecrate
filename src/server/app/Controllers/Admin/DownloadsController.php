<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Geo;
use App\Core\Request;
use App\Core\Response;
use App\Models\Download;
use App\Support\Formatter;

final class DownloadsController extends Controller
{
    public function index(Request $request): never
    {
        $user = $this->requireUser();
        $ownerId = $this->isAdmin() ? null : (int) $user['id'];

        $filters = $this->filters($request, $ownerId);
        $result = Download::paginate($filters);

        $this->renderAdmin('admin/downloads', [
            'filters' => $filters,
            'result' => $result,
            'series' => Download::perDay(30, $filters['file'] ?: null, $ownerId),
            'countries' => Download::byCountry(10, $filters['file'] ?: null, $ownerId),
            'uniqueVisitors' => Download::uniqueVisitors($filters['file'] ?: null, $ownerId),
            'total' => Download::total($filters['file'] ?: null, $ownerId),
        ]);
    }

    public function export(Request $request): never
    {
        $user = $this->requireUser();
        $ownerId = $this->isAdmin() ? null : (int) $user['id'];

        $rows = Download::forExport($this->filters($request, $ownerId), 20000);

        $columns = [
            t('downloads.when'),
            t('files.alias'),
            t('common.name'),
            t('downloads.visitor'),
            t('downloads.location'),
            t('downloads.device'),
            t('downloads.referer'),
            t('downloads.bytes_sent'),
        ];

        $handle = fopen('php://temp', 'w+');

        if ($handle === false) {
            Response::text('Export failed', 500);
        }

        fputcsv($handle, $columns, ';');

        foreach ($rows as $row) {
            fputcsv($handle, [
                Formatter::date((string) $row['created_at']),
                (string) $row['alias'],
                (string) $row['original_name'],
                (string) ($row['ip'] ?? t('downloads.ip_hidden')),
                trim(Geo::name(is_string($row['country']) ? $row['country'] : null) . ' ' . (string) ($row['city'] ?? '')),
                trim((string) ($row['browser'] ?? '') . ' ' . (string) ($row['platform'] ?? '')),
                (string) ($row['referer'] ?? ''),
                (string) ($row['bytes_sent'] ?? ''),
            ], ';');
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        Response::csv($csv, 'downloads-' . gmdate('Y-m-d') . '.csv');
    }

    /**
     * @return array{q: string, country: string, days: int, file: int, page: int, owner: int|null}
     */
    private function filters(Request $request, ?int $ownerId): array
    {
        $days = (int) $request->queryParam('days', 30);

        if (!in_array($days, [0, 1, 7, 30, 90], true)) {
            $days = 30;
        }

        return [
            'q' => (string) $request->queryParam('q', ''),
            'country' => (string) $request->queryParam('country', ''),
            'days' => $days,
            'file' => (int) $request->queryParam('file', 0),
            'page' => (int) $request->queryParam('page', 1),
            'owner' => $ownerId,
        ];
    }
}
