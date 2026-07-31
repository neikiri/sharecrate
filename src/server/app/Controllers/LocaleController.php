<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\I18n;
use App\Core\Request;
use App\Core\Response;
use App\Core\Url;
use App\Models\User;

final class LocaleController extends Controller
{
    /**
     * @param array<string, string> $params
     */
    public function switch(Request $request, array $params): never
    {
        $locale = strtolower($params['locale'] ?? '');

        if (I18n::supported($locale)) {
            I18n::remember($locale);

            // Signed in users keep the choice on their account too.
            $user = Auth::user();

            if ($user !== null) {
                User::update((int) $user['id'], ['locale' => $locale]);
            }
        }

        $target = $request->queryParam('to');
        $root = $request->root();

        if (is_string($target) && $target !== '' && str_starts_with($target, '/')) {
            Response::redirect(Url::to($target));
        }

        $referer = $request->referer();

        if ($referer !== null && str_starts_with($referer, $root)) {
            Response::redirect($referer);
        }

        Response::redirect(Url::to('/'));
    }
}
