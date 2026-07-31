<?php

/**
 * /admin/ is a real directory so the dashboard also works when mod_rewrite
 * is unavailable for directory indexes. Everything is handed to the front
 * controller, which resolves the route from the request URI.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/index.php';
