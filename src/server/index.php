<?php

/**
 * ShareCrate - front controller.
 *
 * Apache sends every request that is not a real file or directory here
 * (see .htaccess).
 */

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

App\Core\Kernel::run();
