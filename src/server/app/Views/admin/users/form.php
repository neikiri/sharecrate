<?php

/**
 * Create / edit user.
 *
 * @var App\Core\View $this
 * @var array<string, mixed>|null $user
 * @var int|null $storageUsed
 */

use App\Core\I18n;
use App\Models\User;

$isEdit = $user !== null;
$this->title = $isEdit ? $this->t('users.edit') : $this->t('users.add');

$action = $isEdit
    ? $this->url('/admin/users/' . (int) $user['id'])
    : $this->url('/admin/users');

$value = static function (string $key, mixed $fallback = '') use ($user): string {
    $old = App\Core\Session::old($key);

    if ($old !== null && $old !== '') {
        return (string) $old;
    }

    if ($user !== null && array_key_exists($key, $user) && $user[$key] !== null) {
        return (string) $user[$key];
    }

    return (string) $fallback;
};

$quotaMb = $isEdit && $user['quota_bytes'] !== null
    ? (string) (int) round(((int) $user['quota_bytes']) / 1048576)
    : '';
$isActive = $isEdit ? (int) $user['is_active'] === 1 : true;
$role = $value('role', 'uploader');
?>
<div class="mx-auto max-w-3xl space-y-6">
    <div class="flex items-center gap-4">
        <a href="<?= $this->e($this->url('/admin/users')) ?>" class="btn btn-secondary btn-icon" title="<?= $this->te('common.back') ?>">
            <?= $this->icon('arrow-left', 'size-4') ?>
        </a>
        <div>
            <h2 class="text-2xl font-semibold tracking-tight text-slate-900">
                <?= $this->te($isEdit ? 'users.edit' : 'users.add') ?>
            </h2>
            <?php if ($isEdit): ?>
                <p class="mt-1 text-sm text-slate-500"><?= $this->e(User::name($user)) ?></p>
            <?php endif; ?>
        </div>
    </div>

    <form method="post" action="<?= $this->e($action) ?>" class="card">
        <?= $this->csrf() ?>

        <div class="space-y-5 p-5 sm:p-6">
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label class="label" for="username"><?= $this->te('users.username') ?></label>
                    <input type="text" name="username" id="username" required maxlength="64"
                           class="input<?= $this->hasError('username') ? ' input-error' : '' ?>"
                           value="<?= $this->e($value('username')) ?>"
                           autocapitalize="none" spellcheck="false">
                    <?php if ($this->hasError('username')): ?>
                        <p class="error-text"><?= $this->icon('alert-triangle', 'size-3.5') ?><?= $this->e((string) $this->error('username')) ?></p>
                    <?php else: ?>
                        <p class="help"><?= $this->te('users.username_hint') ?></p>
                    <?php endif; ?>
                </div>

                <div>
                    <label class="label" for="email"><?= $this->te('users.email') ?></label>
                    <input type="email" name="email" id="email" required maxlength="190"
                           class="input<?= $this->hasError('email') ? ' input-error' : '' ?>"
                           value="<?= $this->e($value('email')) ?>">
                    <?php if ($this->hasError('email')): ?>
                        <p class="error-text"><?= $this->icon('alert-triangle', 'size-3.5') ?><?= $this->e((string) $this->error('email')) ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <div>
                <label class="label" for="display_name"><?= $this->te('users.display_name') ?> <span class="font-normal text-slate-400">(<?= $this->te('common.optional') ?>)</span></label>
                <input type="text" name="display_name" id="display_name" maxlength="120" class="input"
                       value="<?= $this->e($value('display_name')) ?>">
            </div>

            <!-- Role -->
            <fieldset>
                <legend class="label"><?= $this->te('users.role') ?></legend>
                <div class="grid gap-3 sm:grid-cols-2">
                    <?php foreach (User::ROLES as $roleOption): ?>
                        <label class="field-group">
                            <input type="radio" name="role" value="<?= $roleOption ?>" class="checkbox mt-0.5 rounded-full"
                                <?= $role === $roleOption ? 'checked' : '' ?>>
                            <span class="min-w-0">
                                <span class="block text-sm font-medium text-slate-800"><?= $this->te('users.role_' . $roleOption) ?></span>
                                <span class="mt-0.5 block text-xs text-slate-500"><?= $this->te('users.role_' . $roleOption . '_hint') ?></span>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label class="label" for="locale"><?= $this->te('users.locale') ?></label>
                    <select name="locale" id="locale" class="select">
                        <option value=""><?= $this->te('users.locale_auto') ?></option>
                        <?php foreach (I18n::localeNames() as $code => $name): ?>
                            <option value="<?= $this->e($code) ?>" <?= $value('locale') === $code ? 'selected' : '' ?>>
                                <?= $this->e($name) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="label" for="quota_mb"><?= $this->te('users.quota') ?></label>
                    <div class="flex items-stretch">
                        <input type="number" name="quota_mb" id="quota_mb" min="0" step="1" class="input rounded-r-none"
                               value="<?= $this->e($quotaMb) ?>" placeholder="0">
                        <span class="flex items-center rounded-r-xl border border-l-0 border-slate-300 bg-slate-50 px-3 text-xs text-slate-500">MB</span>
                    </div>
                    <p class="help">
                        <?= $this->te('users.quota_hint') ?>
                        <?php if ($isEdit && ($storageUsed ?? 0) > 0): ?>
                            · <?= $this->e($this->bytes((int) $storageUsed)) ?>
                        <?php endif; ?>
                    </p>
                </div>
            </div>

            <!-- Password -->
            <div class="rounded-xl border border-slate-300 bg-slate-50/60 p-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div x-data="revealable">
                        <label class="label" for="password"><?= $this->te('users.password') ?></label>
                        <div class="relative">
                            <input x-ref="input" type="password" name="password" id="password"
                                   class="input pr-11<?= $this->hasError('password') ? ' input-error' : '' ?>"
                                   autocomplete="new-password" <?= $isEdit ? '' : 'required' ?>>
                            <button type="button" class="absolute inset-y-0 right-0 flex w-11 items-center justify-center text-slate-400 hover:text-slate-600" @click="toggle()" tabindex="-1">
                                <span x-show="!shown"><?= $this->icon('eye', 'size-4') ?></span>
                                <span x-cloak x-show="shown"><?= $this->icon('eye-off', 'size-4') ?></span>
                            </button>
                        </div>
                        <?php if ($this->hasError('password')): ?>
                            <p class="error-text"><?= $this->icon('alert-triangle', 'size-3.5') ?><?= $this->e((string) $this->error('password')) ?></p>
                        <?php else: ?>
                            <p class="help"><?= $this->te($isEdit ? 'users.password_keep' : 'users.password_hint') ?></p>
                        <?php endif; ?>
                    </div>

                    <div>
                        <label class="label" for="password_confirmation"><?= $this->te('users.password_confirm') ?></label>
                        <input type="password" name="password_confirmation" id="password_confirmation"
                               class="input<?= $this->hasError('password_confirmation') ? ' input-error' : '' ?>"
                               autocomplete="new-password" <?= $isEdit ? '' : 'required' ?>>
                        <?php if ($this->hasError('password_confirmation')): ?>
                            <p class="error-text"><?= $this->icon('alert-triangle', 'size-3.5') ?><?= $this->e((string) $this->error('password_confirmation')) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <label class="field-group">
                <input type="checkbox" name="is_active" value="1" class="checkbox mt-0.5" <?= $isActive ? 'checked' : '' ?>>
                <span class="min-w-0">
                    <span class="block text-sm font-medium text-slate-800"><?= $this->te('users.is_active') ?></span>
                </span>
            </label>
        </div>

        <div class="flex justify-end gap-2 border-t border-slate-200 px-5 py-4 sm:px-6">
            <a href="<?= $this->e($this->url('/admin/users')) ?>" class="btn btn-secondary"><?= $this->te('common.cancel') ?></a>
            <button type="submit" class="btn btn-primary">
                <?= $this->icon('check') ?>
                <?= $this->te($isEdit ? 'common.save_changes' : 'users.add') ?>
            </button>
        </div>
    </form>
</div>
