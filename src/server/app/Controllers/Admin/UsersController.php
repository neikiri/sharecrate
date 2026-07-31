<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\HttpException;
use App\Core\I18n;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Url;
use App\Core\Validator;
use App\Models\ActivityLog;
use App\Models\User;

final class UsersController extends Controller
{
    public function index(Request $request): never
    {
        $this->requireAdmin();

        $this->renderAdmin('admin/users/index', [
            'users' => User::allWithStats(),
        ]);
    }

    public function create(Request $request): never
    {
        $this->requireAdmin();

        $this->renderAdmin('admin/users/form', [
            'user' => null,
        ]);
    }

    public function store(Request $request): never
    {
        $this->guard($request);
        $this->requireAdmin();

        $data = $this->input($request);
        $validator = $this->validate($data, null, true);

        if ($validator->fails()) {
            $this->backWithErrors($validator->errors(), $request->post, '/admin/users/new');
        }

        $id = User::create([
            'username' => $data['username'],
            'email' => $data['email'],
            'display_name' => $data['display_name'] === '' ? null : $data['display_name'],
            'password' => $data['password'],
            'role' => $data['role'],
            'locale' => $data['locale'] === '' ? null : $data['locale'],
            'is_active' => $data['is_active'],
            'quota_bytes' => $data['quota_bytes'],
        ]);

        ActivityLog::record('user.created', $data['username'], ['id' => $id]);
        Session::flash('success', I18n::t('users.created'));

        Response::redirect(Url::to('/admin/users'));
    }

    /** @param array<string, string> $params */
    public function edit(Request $request, array $params): never
    {
        $this->requireAdmin();
        $user = User::find((int) $params['id']);

        if ($user === null) {
            throw HttpException::notFound();
        }

        $this->renderAdmin('admin/users/form', [
            'user' => $user,
            'storageUsed' => User::storageUsed((int) $user['id']),
        ]);
    }

    /** @param array<string, string> $params */
    public function update(Request $request, array $params): never
    {
        $this->guard($request);
        $current = $this->requireAdmin();

        $id = (int) $params['id'];
        $user = User::find($id);

        if ($user === null) {
            throw HttpException::notFound();
        }

        $data = $this->input($request);
        $validator = $this->validate($data, $id, $data['password'] !== '');

        if ($validator->fails()) {
            $this->backWithErrors($validator->errors(), $request->post, '/admin/users/' . $id);
        }

        $isSelf = $id === (int) $current['id'];

        // Do not let the last administrator lock everyone out.
        if ($isSelf && ($data['role'] !== 'admin' || !$data['is_active'])) {
            Session::flash('error', I18n::t('users.cannot_demote_self'));

            Response::redirect(Url::to('/admin/users/' . $id));
        }

        if ($user['role'] === 'admin' && $data['role'] !== 'admin' && User::adminCount() <= 1) {
            Session::flash('error', I18n::t('users.cannot_delete_last_admin'));

            Response::redirect(Url::to('/admin/users/' . $id));
        }

        User::update($id, [
            'username' => $data['username'],
            'email' => $data['email'],
            'display_name' => $data['display_name'] === '' ? null : $data['display_name'],
            'role' => $data['role'],
            'locale' => $data['locale'] === '' ? null : $data['locale'],
            'is_active' => $data['is_active'] ? 1 : 0,
            'quota_bytes' => $data['quota_bytes'],
            'password' => $data['password'],
        ]);

        ActivityLog::record('user.updated', $data['username'], ['id' => $id]);
        Session::flash('success', I18n::t('users.updated'));

        Response::redirect(Url::to('/admin/users'));
    }

    /** @param array<string, string> $params */
    public function destroy(Request $request, array $params): never
    {
        $this->guard($request);
        $current = $this->requireAdmin();

        $id = (int) $params['id'];
        $user = User::find($id);

        if ($user === null) {
            throw HttpException::notFound();
        }

        if ($id === (int) $current['id']) {
            Session::flash('error', I18n::t('users.cannot_delete_self'));

            Response::redirect(Url::to('/admin/users'));
        }

        if ($user['role'] === 'admin' && User::adminCount() <= 1) {
            Session::flash('error', I18n::t('users.cannot_delete_last_admin'));

            Response::redirect(Url::to('/admin/users'));
        }

        User::delete($id);
        ActivityLog::record('user.deleted', (string) $user['username'], ['id' => $id]);
        Session::flash('success', I18n::t('users.deleted'));

        Response::redirect(Url::to('/admin/users'));
    }

    /**
     * @return array{username: string, email: string, display_name: string, password: string,
     *               password_confirmation: string, role: string, locale: string,
     *               is_active: bool, quota_bytes: int|null}
     */
    private function input(Request $request): array
    {
        $quotaMb = (int) $request->input('quota_mb', 0);

        return [
            'username' => mb_strtolower(trim((string) $request->input('username', ''))),
            'email' => mb_strtolower(trim((string) $request->input('email', ''))),
            'display_name' => trim((string) $request->input('display_name', '')),
            'password' => (string) $request->raw('password', ''),
            'password_confirmation' => (string) $request->raw('password_confirmation', ''),
            'role' => in_array($request->input('role'), User::ROLES, true) ? (string) $request->input('role') : 'uploader',
            'locale' => in_array($request->input('locale'), I18n::AVAILABLE, true) ? (string) $request->input('locale') : '',
            'is_active' => $request->bool('is_active'),
            'quota_bytes' => $quotaMb > 0 ? $quotaMb * 1024 * 1024 : null,
        ];
    }

    /** @param array<string, mixed> $data */
    private function validate(array $data, ?int $ignoreId, bool $requirePassword): Validator
    {
        $validator = Validator::make($data)
            ->required('username')
            ->min('username', 3)
            ->max('username', 64)
            ->regex('username', '/^[a-z0-9._-]+$/', I18n::t('users.username_hint'))
            ->required('email')
            ->email('email')
            ->max('email', 190)
            ->max('display_name', 120)
            ->in('role', User::ROLES);

        if ($requirePassword) {
            $validator->required('password')
                ->min('password', 10)
                ->matches('password_confirmation', 'password', I18n::t('validation.confirmed'));
        }

        if (User::usernameTaken((string) $data['username'], $ignoreId)) {
            $validator->fail('username', I18n::t('users.username_taken'));
        }

        if (User::emailTaken((string) $data['email'], $ignoreId)) {
            $validator->fail('email', I18n::t('users.email_taken'));
        }

        return $validator;
    }
}
