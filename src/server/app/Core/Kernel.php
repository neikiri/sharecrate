<?php

declare(strict_types=1);

namespace App\Core;

use App\Support\Maintenance;

final class Kernel
{
    public static function run(): void
    {
        $request = Request::capture();
        Session::start();

        if (!Config::isConfigured()) {
            self::bootInstaller($request);

            return;
        }

        I18n::boot($request);
        Maintenance::maybeRun();

        $router = new Router();
        $register = require APP_PATH . '/routes.php';
        $register($router);

        $router->dispatch($request);
    }

    /**
     * Before the .env exists, everything is redirected to the installer.
     */
    private static function bootInstaller(Request $request): void
    {
        I18n::bootLight($request);

        if (str_starts_with($request->path(), '/install')) {
            $router = new Router();
            $router->get('/install', 'InstallController@index');
            $router->post('/install', 'InstallController@handle');
            $router->dispatch($request);

            return;
        }

        Response::redirect(Url::to('/install'));
    }

    /**
     * Entry point for the CLI scripts in bin/.
     */
    public static function bootConsole(): void
    {
        if (!Config::isConfigured()) {
            fwrite(STDERR, "The application is not configured yet - run the web installer first.\n");
            exit(1);
        }

        I18n::setLocale(I18n::defaultLocale());
    }
}
