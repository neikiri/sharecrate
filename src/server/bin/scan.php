<?php

/**
 * Imports files that arrived over FTP.
 *
 * Usage:
 *   php bin/scan.php                 list pending files
 *   php bin/scan.php --import        create links for every pending file
 *   php bin/scan.php --import --owner=2
 *
 * Cron example (every 10 minutes):
 *   *\/10 * * * * /usr/bin/php /var/www/example.com/bin/scan.php --import >/dev/null 2>&1
 */

declare(strict_types=1);

use App\Core\Database;
use App\Core\Kernel;
use App\Support\Formatter;
use App\Support\Scanner;
use App\Support\Storage;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script is for the command line only.\n");
}

require dirname(__DIR__) . '/app/bootstrap.php';

Kernel::bootConsole();

$options = getopt('', ['import', 'owner::', 'help']) ?: [];

if (isset($options['help'])) {
    echo "Usage: php bin/scan.php [--import] [--owner=ID]\n";
    exit(0);
}

if (!Storage::writable()) {
    fwrite(STDERR, 'Storage directory is not writable: ' . Storage::root() . PHP_EOL);
    exit(1);
}

$pending = Scanner::pending(1000);

echo 'Storage: ' . Storage::root() . PHP_EOL;
echo 'Pending files: ' . count($pending) . PHP_EOL;

if ($pending === []) {
    exit(0);
}

foreach ($pending as $entry) {
    printf(
        "  %-60s %10s  %s\n",
        mb_strimwidth($entry['path'], 0, 60, '…'),
        Formatter::bytes($entry['size']),
        gmdate('Y-m-d H:i', $entry['modified'])
    );
}

if (!isset($options['import'])) {
    echo PHP_EOL . 'Run again with --import to publish these files.' . PHP_EOL;
    exit(0);
}

$ownerId = isset($options['owner']) && is_string($options['owner']) && $options['owner'] !== ''
    ? (int) $options['owner']
    : (int) (Database::value("SELECT id FROM users WHERE role = 'admin' AND is_active = 1 ORDER BY id ASC LIMIT 1") ?? 0);

$result = Scanner::importAll($ownerId > 0 ? $ownerId : null, [], 'cli');

echo PHP_EOL . 'Imported: ' . $result['imported'] . ', skipped: ' . $result['skipped'] . PHP_EOL;

foreach ($result['files'] as $file) {
    echo '  /' . $file['alias'] . '  <-  ' . $file['name'] . PHP_EOL;
}

exit(0);
