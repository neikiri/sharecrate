<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\I18n;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Url;
use App\Core\Validator;
use App\Models\Download;
use App\Models\FileItem;
use App\Models\User;

final class ProfileController extends Controller
{
    public function edit(Request $request): never
    {
        $user = $this->requireUser();
        $userId = (int) $user['id'];

        $this->renderAdmin('admin/profile', [
            'profile' => $user,
            'stats' => FileItem::stats($userId),
            'downloads' => Download::total(null, $userId),
            'quotaUsed' => User::storageUsed($userId),
        ]);
    }

    public function update(Request $request): never
    {
        $this->guard($request);
        $user = $this->requireUser();
        $userId = (int) $user['id'];

        $action = (string) $request->input('action', 'profile');

        if ($action === 'password') {
            $this->updatePassword($request, $user);
        }

        $email = mb_strtolower(trim((string) $request->input('email', '')));
        $displayName = trim((string) $request->input('display_name', ''));
        $locale = (string) $request->input('locale', '');

        $validator = Validator::make([
            'email' => $email,
            'display_name' => $displayName,
        ])
            ->required('email')
            ->email('email')
            ->max('email', 190)
            ->max('display_name', 120);

        if (User::emailTaken($email, $userId)) {
            $validator->fail('email', I18n::t('users.email_taken'));
        }

        if ($validator->fails()) {
            $this->backWithErrors($validator->errors(), $request->post, '/admin/profile');
        }

        User::update($userId, [
            'email' => $email,
            'display_name' => $displayName === '' ? null : $displayName,
            'locale' => I18n::supported($locale) ? $locale : null,
        ]);

        if (I18n::supported($locale)) {
            I18n::remember($locale);
        }

        Session::flash('success', I18n::t('profile.updated'));

        Response::redirect(Url::to('/admin/profile'));
    }

    /** @param array<string, mixed> $user */
    private function updatePassword(Request $request, array $user): never
    {
        $current = (string) $request->raw('current_password', '');
        $new = (string) $request->raw('password', '');
        $confirm = (string) $request->raw('password_confirmation', '');

        $validator = Validator::make([
            'current_password' => $current,
            'password' => $new,
            'password_confirmation' => $confirm,
        ])
            ->required('current_password')
            ->required('password')
            ->min('password', 10)
            ->matches('password_confirmation', 'password');

        if (!password_verify($current, (string) $user['password_hash'])) {
            $validator->fail('current_password', I18n::t('profile.wrong_current'));
        }

        if ($validator->fails()) {
            $this->backWithErrors($validator->errors(), [], '/admin/profile');
        }

        User::update((int) $user['id'], ['password' => $new]);
        Session::flash('success', I18n::t('profile.password_updated'));

        Response::redirect(Url::to('/admin/profile'));
    }
}
