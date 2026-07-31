<?php

/**
 * Router for PHP's built in server (npm run serve).
 * Emulates the Apache rewrite and deny rules so local testing behaves
 * like production.
 */

declare(strict_types=1);

$docroot = realpath(__DIR__ . '/../dist');

if ($docroot === false) {
    http_response_code(500);
    echo 'Run "npm run build" first.';

    return true;
}

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

// Mirror the .htaccess deny rules.
if (preg_match('#^/(app|storage|database)(/|$)#', $uri) === 1 || preg_match('#(^|/)\.[^/]#', $uri) === 1) {
    http_response_code(403);
    echo 'Forbidden';

    return true;
}

$target = realpath($docroot . $uri);

if ($target !== false && str_starts_with($target, $docroot)) {
    if (is_file($target)) {
        if (str_ends_with($target, '.php')) {
            require $target;

            return true;
        }

        return false; // let the built in server stream the static file
    }

    if (is_dir($target) && is_file($target . '/index.php')) {
        require $target . '/index.php';

        return true;
    }
}

require $docroot . '/index.php';

return true;
