<?php

/**
 * Housekeeping: prunes old logs, expired tokens and orphaned thumbnails.
 *
 * Cron example (daily at 03:20):
 *   20 3 * * * /usr/bin/php /var/www/example.com/bin/cleanup.php >/dev/null 2>&1
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script is for the command line only.\n");
}

require dirname(__DIR__) . '/app/bootstrap.php';

App\Core\Kernel::bootConsole();

$report = App\Support\Maintenance::run();

foreach ($report as $key => $count) {
    printf("%-18s %d\n", $key, $count);
}

exit(0);
