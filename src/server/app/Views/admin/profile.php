<?php

/**
 * Own profile: details, language and password.
 *
 * @var App\Core\View $this
 * @var array<string, mixed> $profile
 * @var array{files: int, active: int, protected: int, bytes: int, downloads: int} $stats
 * @var int $downloads
 * @var int $quotaUsed
 */

use App\Core\I18n;
use App\Models\User;

$this->title = $this->t('profile.title');
$quota = $profile['quota_bytes'] === null ? null : (int) $profile['quota_bytes'];
?>
<div class="mx-auto max-w-3xl space-y-6">
    <div class="flex items-center gap-4">
        <span class="flex size-12 items-center justify-center rounded-2xl bg-brand-50 text-base font-semibold text-brand-700">
            <?= $this->e(User::initials($profile)) ?>
        </span>
        <div>
            <h2 class="text-2xl font-semibold tracking-tight text-slate-900"><?= $this->e(User::name($profile)) ?></h2>
            <p class="mt-0.5 text-sm text-slate-500">
                <?= $this->e((string) $profile['username']) ?> ·
                <?= $this->te($profile['role'] === 'admin' ? 'users.role_admin' : 'users.role_uploader') ?>
            </p>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <div class="stat">
            <p class="stat-label"><?= $this->te('profile.stats_files') ?></p>
            <p class="stat-value"><?= $this->e($this->number($stats['files'])) ?></p>
        </div>
        <div class="stat">
            <p class="stat-label"><?= $this->te('profile.stats_downloads') ?></p>
            <p class="stat-value"><?= $this->e($this->number($downloads)) ?></p>
        </div>
        <div class="stat">
            <p class="stat-label"><?= $this->te('profile.stats_storage') ?></p>
            <p class="stat-value"><?= $this->e($this->bytes($quotaUsed)) ?></p>
            <?php if ($quota !== null && $quota > 0): ?>
                <div class="progress mt-3">
                    <span style="width: <?= (int) min(100, round($quotaUsed / max(1, $quota) * 100)) ?>%"></span>
                </div>
                <p class="stat-foot"><?= $this->te('users.quota_used', ['used' => $this->bytes($quotaUsed), 'total' => $this->bytes($quota)]) ?></p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Account -->
    <form method="post" action="<?= $this->e($this->url('/admin/profile')) ?>" class="card">
        <?= $this->csrf() ?>
        <input type="hidden" name="action" value="profile">

        <div class="card-header">
            <h3 class="card-title"><?= $this->te('profile.section_account') ?></h3>
        </div>

        <div class="grid gap-5 p-5 sm:grid-cols-2 sm:p-6">
            <div>
                <label class="label" for="display_name"><?= $this->te('users.display_name') ?></label>
                <input type="text" name="display_name" id="display_name" maxlength="120" class="input"
                       value="<?= $this->e((string) ($profile['display_name'] ?? '')) ?>">
            </div>

            <div>
                <label class="label" for="email"><?= $this->te('users.email') ?></label>
                <input type="email" name="email" id="email" maxlength="190" required
                       class="input<?= $this->hasError('email') ? ' input-error' : '' ?>"
                       value="<?= $this->e((string) $profile['email']) ?>">
                <?php if ($this->hasError('email')): ?>
                    <p class="error-text"><?= $this->icon('alert-triangle', 'size-3.5') ?><?= $this->e((string) $this->error('email')) ?></p>
                <?php endif; ?>
            </div>

            <div>
                <label class="label" for="locale"><?= $this->te('users.locale') ?></label>
                <select name="locale" id="locale" class="select">
                    <option value=""><?= $this->te('users.locale_auto') ?></option>
                    <?php foreach (I18n::localeNames() as $code => $name): ?>
                        <option value="<?= $this->e($code) ?>" <?= (string) ($profile['locale'] ?? '') === $code ? 'selected' : '' ?>>
                            <?= $this->e($name) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="label"><?= $this->te('users.last_login') ?></label>
                <p class="mt-2.5 text-sm text-slate-600">
                    <?php if ($profile['last_login_at'] === null): ?>
                        <?= $this->te('users.never_logged_in') ?>
                    <?php else: ?>
                        <?= $this->e($this->date((string) $profile['last_login_at'])) ?>
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <div class="flex justify-end border-t border-slate-200 px-5 py-4 sm:px-6">
            <button type="submit" class="btn btn-primary">
                <?= $this->icon('check') ?><?= $this->te('common.save_changes') ?>
            </button>
        </div>
    </form>

    <!-- Password -->
    <form method="post" action="<?= $this->e($this->url('/admin/profile')) ?>" class="card">
        <?= $this->csrf() ?>
        <input type="hidden" name="action" value="password">

        <div class="card-header">
            <h3 class="card-title"><?= $this->te('profile.section_password') ?></h3>
        </div>

        <div class="space-y-5 p-5 sm:p-6">
            <div class="sm:max-w-sm">
                <label class="label" for="current_password"><?= $this->te('profile.current_password') ?></label>
                <input type="password" name="current_password" id="current_password" autocomplete="current-password"
                       class="input<?= $this->hasError('current_password') ? ' input-error' : '' ?>">
                <?php if ($this->hasError('current_password')): ?>
                    <p class="error-text"><?= $this->icon('alert-triangle', 'size-3.5') ?><?= $this->e((string) $this->error('current_password')) ?></p>
                <?php endif; ?>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div x-data="revealable">
                    <label class="label" for="new_password"><?= $this->te('profile.new_password') ?></label>
                    <div class="relative">
                        <input x-ref="input" type="password" name="password" id="new_password" autocomplete="new-password"
                               class="input pr-11<?= $this->hasError('password') ? ' input-error' : '' ?>">
                        <button type="button" class="absolute inset-y-0 right-0 flex w-11 items-center justify-center text-slate-400 hover:text-slate-600" @click="toggle()" tabindex="-1">
                            <span x-show="!shown"><?= $this->icon('eye', 'size-4') ?></span>
                            <span x-cloak x-show="shown"><?= $this->icon('eye-off', 'size-4') ?></span>
                        </button>
                    </div>
                    <?php if ($this->hasError('password')): ?>
                        <p class="error-text"><?= $this->icon('alert-triangle', 'size-3.5') ?><?= $this->e((string) $this->error('password')) ?></p>
                    <?php else: ?>
                        <p class="help"><?= $this->te('users.password_hint') ?></p>
                    <?php endif; ?>
                </div>

                <div>
                    <label class="label" for="password_confirmation"><?= $this->te('profile.new_password_confirm') ?></label>
                    <input type="password" name="password_confirmation" id="password_confirmation" autocomplete="new-password"
                           class="input<?= $this->hasError('password_confirmation') ? ' input-error' : '' ?>">
                    <?php if ($this->hasError('password_confirmation')): ?>
                        <p class="error-text"><?= $this->icon('alert-triangle', 'size-3.5') ?><?= $this->e((string) $this->error('password_confirmation')) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="flex justify-end border-t border-slate-200 px-5 py-4 sm:px-6">
            <button type="submit" class="btn btn-secondary">
                <?= $this->icon('key') ?><?= $this->te('profile.section_password') ?>
            </button>
        </div>
    </form>
</div>
