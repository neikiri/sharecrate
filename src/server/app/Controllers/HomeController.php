<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Url;

final class HomeController extends Controller
{
    public function index(Request $request): never
    {
        $view = $this->view();
        $view->layout = 'layouts/public';
        $view->title = '';
        $view->description = t('home.subheading');
        $view->canonical = Url::absolute('/');

        $view->display('public/home');
    }

    public function legal(Request $request): never
    {
        $view = $this->view();
        $view->layout = 'layouts/public';
        $view->title = t('home.no_listing_title');

        $view->display('public/legal');
    }

    public function robots(Request $request): never
    {
        // Nothing here should ever end up in a search index.
        Response::text("User-agent: *\nDisallow: /\n");
    }

    public function health(Request $request): never
    {
        $database = 'unknown';

        try {
            \App\Core\Database::value('SELECT 1');
            $database = 'ok';
        } catch (\Throwable) {
            $database = 'error';
        }

        Response::json([
            'ok' => $database === 'ok',
            'database' => $database,
            'storage_writable' => \App\Support\Storage::writable(),
            'time' => gmdate('c'),
        ], $database === 'ok' ? 200 : 503);
    }
}
