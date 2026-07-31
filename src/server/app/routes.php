<?php

/**
 * Route table.
 *
 * Every request that does not map to a real file on disk is sent to the front
 * controller by .htaccess, so all of these paths are virtual.
 */

declare(strict_types=1);

use App\Core\Router;

return static function (Router $router): void {
    /* -----------------------------------------------------------------
     * Public
     * ----------------------------------------------------------------- */
    $router->get('/', 'HomeController@index');
    $router->get('/robots.txt', 'HomeController@robots');
    $router->get('/health', 'HomeController@health');
    $router->get('/legal', 'HomeController@legal');

    $router->get('/lang/{locale:[a-z]{2}}', 'LocaleController@switch');

    // Canonical file routes
    $router->get('/f/{alias}', 'FileController@show');
    $router->post('/f/{alias}', 'FileController@unlock');
    $router->get('/d/{alias}', 'FileController@download');
    $router->get('/p/{alias}', 'FileController@preview');
    $router->get('/t/{alias}', 'FileController@thumbnail');

    /* -----------------------------------------------------------------
     * Authentication
     * ----------------------------------------------------------------- */
    $router->get('/admin/login', 'Auth\LoginController@show');
    $router->post('/admin/login', 'Auth\LoginController@login');
    $router->post('/admin/logout', 'Auth\LoginController@logout');

    /* -----------------------------------------------------------------
     * Dashboard
     * ----------------------------------------------------------------- */
    $router->get('/admin', 'Admin\DashboardController@index');

    $router->get('/admin/files', 'Admin\FilesController@index');
    $router->post('/admin/files/bulk', 'Admin\FilesController@bulk');
    $router->get('/admin/files/{id:\d+}', 'Admin\FilesController@show');
    $router->post('/admin/files/{id:\d+}', 'Admin\FilesController@update');
    $router->post('/admin/files/{id:\d+}/delete', 'Admin\FilesController@destroy');
    $router->post('/admin/files/{id:\d+}/password', 'Admin\FilesController@password');

    $router->get('/admin/upload', 'Admin\UploadController@form');
    $router->post('/admin/upload', 'Admin\UploadController@store');

    $router->get('/admin/import', 'Admin\ImportController@index');
    $router->post('/admin/import', 'Admin\ImportController@store');

    $router->get('/admin/downloads', 'Admin\DownloadsController@index');
    $router->get('/admin/downloads/export', 'Admin\DownloadsController@export');

    $router->get('/admin/users', 'Admin\UsersController@index');
    $router->get('/admin/users/new', 'Admin\UsersController@create');
    $router->post('/admin/users', 'Admin\UsersController@store');
    $router->get('/admin/users/{id:\d+}', 'Admin\UsersController@edit');
    $router->post('/admin/users/{id:\d+}', 'Admin\UsersController@update');
    $router->post('/admin/users/{id:\d+}/delete', 'Admin\UsersController@destroy');

    $router->get('/admin/settings', 'Admin\SettingsController@edit');
    $router->post('/admin/settings', 'Admin\SettingsController@update');

    $router->get('/admin/profile', 'Admin\ProfileController@edit');
    $router->post('/admin/profile', 'Admin\ProfileController@update');

    /* -----------------------------------------------------------------
     * Installer (already configured -> shows a notice)
     * ----------------------------------------------------------------- */
    $router->get('/install', 'InstallController@index');
    $router->post('/install', 'InstallController@handle');

    /* -----------------------------------------------------------------
     * Short links: example.com/<alias>
     * ----------------------------------------------------------------- */
    $router->get('/{alias:[A-Za-z0-9][A-Za-z0-9._-]{0,159}}', 'FileController@show');
    $router->post('/{alias:[A-Za-z0-9][A-Za-z0-9._-]{0,159}}', 'FileController@unlock');
};
