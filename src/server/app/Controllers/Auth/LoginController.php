<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\I18n;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Url;
use App\Models\User;

final class LoginController extends Controller
{
    private const MAX_ATTEMPTS = 8;

    private const WINDOW = 900;

    public function show(Request $request): never
    {
        if (Auth::check()) {
            Response::redirect(Url::to('/admin'));
        }

        $view = $this->view();
        $view->layout = 'layouts/minimal';
        $view->title = t('auth.title');
        $view->noindex = true;

        $view->display('auth/login');
    }

    public function login(Request $request): never
    {
        $this->guard($request);

        $login = (string) $request->input('login', '');
        $password = (string) $request->raw('password', '');
        $remember = $request->bool('remember');

        $ipBucket = RateLimiter::key('login-ip', $request->ip());
        $loginBucket = RateLimiter::key('login-user', mb_strtolower($login));

        foreach ([$ipBucket, $loginBucket] as $bucket) {
            if (RateLimiter::tooManyAttempts($bucket, self::MAX_ATTEMPTS)) {
                $minutes = max(1, (int) ceil(RateLimiter::availableIn($bucket) / 60));
                Session::flash('error', I18n::t('auth.throttled', ['minutes' => $minutes]));
                Session::flashInput(['login' => $login]);

                Response::redirect(Url::to('/admin/login'));
            }
        }

        if ($login === '' || $password === '') {
            Session::flashErrors([
                'login' => $login === '' ? I18n::t('validation.required') : '',
                'password' => $password === '' ? I18n::t('validation.required') : '',
            ]);
            Session::flashInput(['login' => $login]);

            Response::redirect(Url::to('/admin/login'));
        }

        if (!Auth::attempt($login, $password, $remember)) {
            RateLimiter::hit($ipBucket, self::WINDOW);
            RateLimiter::hit($loginBucket, self::WINDOW);

            Session::flash('error', I18n::t('auth.failed'));
            Session::flashInput(['login' => $login]);

            Response::redirect(Url::to('/admin/login'));
        }

        RateLimiter::clear($ipBucket);
        RateLimiter::clear($loginBucket);

        $user = Auth::user() ?? [];

        // Follow the user's own language preference right after signing in.
        if (is_string($user['locale'] ?? null) && I18n::supported((string) $user['locale'])) {
            I18n::remember((string) $user['locale']);
        }

        Session::flash('success', I18n::t('auth.welcome_back', ['name' => User::name($user)]));

        Response::redirect(Auth::intended('/admin'));
    }

    public function logout(Request $request): never
    {
        $this->guard($request);
        Auth::logout();
        Session::flash('success', I18n::t('auth.logged_out'));

        Response::redirect(Url::to('/'));
    }
}
