<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\FileItem;

/**
 * Bridges the FTP workflow: files dropped into the storage directory are
 * discovered here and turned into shareable links.
 */
final class Scanner
{
    /**
     * Files present on disk but not yet in the database.
     *
     * @return array<int, array{path: string, name: string, size: int, modified: int, extension: string, category: string}>
     */
    public static function pending(int $limit = 500): array
    {
        $known = FileItem::knownPathHashes();
        $pending = [];

        foreach (Storage::scan() as $entry) {
            if (isset($known[FileItem::pathHash($entry['path'])])) {
                continue;
            }

            $extension = FileTypes::extension($entry['name']);
            $pending[] = [
                'path' => $entry['path'],
                'name' => $entry['name'],
                'size' => $entry['size'],
                'modified' => $entry['modified'],
                'extension' => $extension,
                'category' => FileTypes::category($extension),
            ];

            if (count($pending) >= $limit) {
                break;
            }
        }

        return $pending;
    }

    public static function pendingCount(): int
    {
        return count(self::pending(1000));
    }

    /**
     * Registers a single file from disk.
     *
     * @param array{alias?: string|null, password?: string|null, expires_at?: string|null,
     *              max_downloads?: int|null, description?: string|null, title?: string|null,
     *              allow_preview?: bool} $options
     * @return array{id: int, alias: string}|null
     */
    public static function import(string $relativePath, ?int $ownerId, array $options = [], string $source = 'ftp'): ?array
    {
        $relativePath = Storage::normalise($relativePath);

        if (!Storage::exists($relativePath)) {
            return null;
        }

        if (FileItem::findByPath($relativePath) !== null) {
            return null;
        }

        $absolute = Storage::path($relativePath);
        $name = basename($relativePath);
        $extension = FileTypes::extension($name);

        $requestedAlias = $options['alias'] ?? null;
        $alias = is_string($requestedAlias) && trim($requestedAlias) !== ''
            ? Alias::unique(Alias::slug(trim($requestedAlias), true))
            : Alias::unique(Alias::fromFilename($name));

        $id = FileItem::create([
            'alias' => $alias,
            'title' => $options['title'] ?? null,
            'description' => $options['description'] ?? null,
            'original_name' => $name,
            'path' => $relativePath,
            'mime_type' => FileTypes::detectMime($absolute, $extension),
            'size_bytes' => (int) (Storage::size($relativePath) ?? 0),
            'password' => $options['password'] ?? null,
            'owner_id' => $ownerId,
            'source' => $source,
            'status' => 'active',
            'allow_preview' => $options['allow_preview'] ?? true,
            'expires_at' => $options['expires_at'] ?? null,
            'max_downloads' => $options['max_downloads'] ?? null,
        ]);

        ActivityLog::record('file.imported', $alias, ['path' => $relativePath, 'source' => $source], $ownerId);

        return ['id' => $id, 'alias' => $alias];
    }

    /**
     * Imports every pending file.
     *
     * @param string[] $onlyPaths limit the import to these relative paths
     * @return array{imported: int, skipped: int, files: array<int, array{id: int, alias: string, name: string}>}
     */
    public static function importAll(?int $ownerId, array $onlyPaths = [], string $source = 'ftp'): array
    {
        $imported = [];
        $skipped = 0;
        $filter = $onlyPaths === [] ? null : array_map(
            static fn ($p) => FileItem::pathHash(Storage::normalise($p)),
            $onlyPaths
        );

        foreach (self::pending(1000) as $entry) {
            if ($filter !== null && !in_array(FileItem::pathHash($entry['path']), $filter, true)) {
                continue;
            }

            $result = self::import($entry['path'], $ownerId, [], $source);

            if ($result === null) {
                $skipped++;

                continue;
            }

            $imported[] = [
                'id' => $result['id'],
                'alias' => $result['alias'],
                'name' => $entry['name'],
            ];
        }

        return [
            'imported' => count($imported),
            'skipped' => $skipped,
            'files' => $imported,
        ];
    }

    /**
     * Refreshes size/mime for rows whose file changed on disk (re-uploaded via FTP).
     */
    public static function refresh(int $fileId): bool
    {
        $file = FileItem::find($fileId);

        if ($file === null || !Storage::exists((string) $file['path'])) {
            return false;
        }

        $relative = (string) $file['path'];
        $absolute = Storage::path($relative);
        $extension = (string) ($file['extension'] ?? '');

        FileItem::update($fileId, [
            'size_bytes' => (int) (Storage::size($relative) ?? 0),
            'mime_type' => FileTypes::detectMime($absolute, $extension),
        ]);

        Thumbnailer::forget($fileId);

        return true;
    }
}
