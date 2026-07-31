<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\I18n;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Url;
use App\Core\View;

abstract class Controller
{
    /** @param array<string, mixed> $shared */
    protected function view(array $shared = []): View
    {
        return new View($shared);
    }

    /**
     * Renders a public page.
     *
     * @param array<string, mixed> $data
     */
    protected function render(string $template, array $data = [], string $layout = 'layouts/public', int $status = 200): never
    {
        $view = $this->view();
        $view->layout = $layout;

        $view->display($template, $data, $status);
    }

    /**
     * Renders a dashboard page.
     *
     * @param array<string, mixed> $data
     */
    protected function renderAdmin(string $template, array $data = [], int $status = 200): never
    {
        $view = $this->view();
        $view->layout = 'layouts/admin';
        $view->noindex = true;

        $view->display($template, $data, $status);
    }

    protected function guard(Request $request): void
    {
        Csrf::check($request);
    }

    /**
     * Flashes validation errors and old input, then goes back to the form.
     *
     * @param array<string, string> $errors
     * @param array<string, mixed> $input
     */
    protected function backWithErrors(array $errors, array $input, string $path): never
    {
        Session::flashErrors($errors);
        Session::flashInput($input);
        Session::flash('error', I18n::t('validation.check_form'));

        Response::redirect(Url::to($path));
    }

    /** @return array<string, mixed> */
    protected function requireUser(): array
    {
        return Auth::requireUser();
    }

    /** @return array<string, mixed> */
    protected function requireAdmin(): array
    {
        return Auth::requireAdmin();
    }

    protected function isAdmin(): bool
    {
        return Auth::isAdmin();
    }

    /**
     * Uploaders only ever see their own files.
     *
     * @param array<string, mixed> $file
     */
    protected function canManageFile(array $file): bool
    {
        if (Auth::isAdmin()) {
            return true;
        }

        $userId = Auth::id();

        return $userId !== null && (int) ($file['owner_id'] ?? 0) === $userId;
    }
}
