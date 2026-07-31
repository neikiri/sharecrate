<?php

/**
 * ShareCrate - application bootstrap.
 *
 * Every entry point (index.php, admin/index.php, api/index.php, install/index.php
 * and the CLI scripts in bin/) starts here.
 */

declare(strict_types=1);

define('DL_START', microtime(true));
define('APP_PATH', __DIR__);
define('ROOT_PATH', dirname(__DIR__));
define('VIEW_PATH', APP_PATH . '/Views');
define('LOCALE_PATH', APP_PATH . '/locales');

// All timestamps are stored in UTC and formatted for display later on.
date_default_timezone_set('UTC');
mb_internal_encoding('UTF-8');
ini_set('default_charset', 'UTF-8');

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'App\\')) {
        return;
    }

    $relative = str_replace('\\', DIRECTORY_SEPARATOR, substr($class, 4));
    $file = APP_PATH . DIRECTORY_SEPARATOR . $relative . '.php';

    if (is_file($file)) {
        require $file;
    }
});

require APP_PATH . '/helpers.php';

App\Core\Config::load(ROOT_PATH . '/.env');
App\Core\ErrorHandler::register();
