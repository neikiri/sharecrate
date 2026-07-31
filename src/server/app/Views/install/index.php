<?php

/**
 * Installer.
 *
 * @var App\Core\View $this
 * @var array<int, array{key: string, label: string, ok: bool, required: bool, detail: string}> $requirements
 * @var array<string, string> $defaults
 * @var string|null $envContent
 */

use App\Core\I18n;

$flashes = $this->flashes();
$blocked = false;

foreach ($requirements as $requirement) {
    if ($requirement['required'] && !$requirement['ok']) {
        $blocked = true;
    }
}
?>
<div class="w-full max-w-2xl space-y-5">
    <?php foreach ($flashes as $message): ?>
        <div class="alert <?= $message['type'] === 'error' ? 'alert-error' : 'alert-info' ?>" role="status">
            <?= $this->icon($message['type'] === 'error' ? 'x-circle' : 'info') ?>
            <div class="flex-1"><?= $this->e($message['message']) ?></div>
        </div>
    <?php endforeach; ?>

    <?php if ($envContent !== null): ?>
        <div class="card card-pad">
            <h2 class="card-title"><?= $this->te('install.env_failed') ?></h2>
            <textarea class="textarea mt-3 font-mono text-xs" rows="14" readonly><?= $this->e($envContent) ?></textarea>
        </div>
    <?php endif; ?>

    <!-- Requirements -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><?= $this->te('install.step_requirements') ?></h2>
            <span class="badge <?= $blocked ? 'badge-danger' : 'badge-success' ?>">
                <?= $this->icon($blocked ? 'alert-triangle' : 'check-circle') ?>
                <?= $this->te($blocked ? 'install.requirements_failed' : 'install.requirements_ok') ?>
            </span>
        </div>
        <ul class="divide-y divide-slate-100">
            <?php foreach ($requirements as $requirement): ?>
                <li class="flex items-center gap-3 px-5 py-3">
                    <span class="<?= $requirement['ok'] ? 'text-emerald-500' : ($requirement['required'] ? 'text-rose-500' : 'text-amber-500') ?>">
                        <?= $this->icon($requirement['ok'] ? 'check-circle' : ($requirement['required'] ? 'x-circle' : 'alert-triangle'), 'size-4') ?>
                    </span>
                    <span class="flex-1 text-sm text-slate-700"><?= $this->e($requirement['label']) ?></span>
                    <span class="max-w-[14rem] truncate font-mono text-xs text-slate-400" title="<?= $this->e($requirement['detail']) ?>">
                        <?= $this->e($requirement['detail']) ?>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <form method="post" action="<?= $this->e($this->url('/install')) ?>" class="space-y-5">
        <?= $this->csrf() ?>

        <!-- Database -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title"><?= $this->te('install.step_database') ?></h2>
                <?= $this->icon('database', 'size-4 text-slate-400') ?>
            </div>
            <div class="grid gap-4 p-5 sm:grid-cols-2">
                <div>
                    <label class="label" for="db_host"><?= $this->te('install.db_host') ?></label>
                    <input type="text" name="db_host" id="db_host" class="input<?= $this->hasError('db_host') ? ' input-error' : '' ?>"
                           value="<?= $this->old('db_host', 'localhost') ?>" required>
                </div>
                <div>
                    <label class="label" for="db_port"><?= $this->te('install.db_port') ?></label>
                    <input type="text" name="db_port" id="db_port" class="input" value="<?= $this->old('db_port', '3306') ?>">
                </div>
                <div>
                    <label class="label" for="db_name"><?= $this->te('install.db_name') ?></label>
                    <input type="text" name="db_name" id="db_name" class="input<?= $this->hasError('db_name') ? ' input-error' : '' ?>"
                           value="<?= $this->old('db_name') ?>" required>
                </div>
                <div>
                    <label class="label" for="db_user"><?= $this->te('install.db_user') ?></label>
                    <input type="text" name="db_user" id="db_user" class="input<?= $this->hasError('db_user') ? ' input-error' : '' ?>"
                           value="<?= $this->old('db_user') ?>" required>
                </div>
                <div class="sm:col-span-2">
                    <label class="label" for="db_pass"><?= $this->te('install.db_pass') ?></label>
                    <input type="password" name="db_pass" id="db_pass" class="input" autocomplete="off">
                </div>
            </div>
        </div>

        <!-- Site -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title"><?= $this->te('settings.section_general') ?></h2>
                <?= $this->icon('globe', 'size-4 text-slate-400') ?>
            </div>
            <div class="grid gap-4 p-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="label" for="app_url"><?= $this->te('install.app_url') ?></label>
                    <input type="text" name="app_url" id="app_url" class="input<?= $this->hasError('app_url') ? ' input-error' : '' ?>"
                           value="<?= $this->old('app_url', $defaults['app_url']) ?>" required>
                    <p class="help"><?= $this->te('install.app_url_hint') ?></p>
                </div>
                <div>
                    <label class="label" for="site_name"><?= $this->te('install.site_name') ?></label>
                    <input type="text" name="site_name" id="site_name" class="input"
                           value="<?= $this->old('site_name', $defaults['site_name']) ?>">
                </div>
                <div>
                    <label class="label" for="default_locale"><?= $this->te('install.default_locale') ?></label>
                    <select name="default_locale" id="default_locale" class="select">
                        <?php foreach (I18n::localeNames() as $code => $name): ?>
                            <option value="<?= $this->e($code) ?>" <?= $defaults['default_locale'] === $code ? 'selected' : '' ?>>
                                <?= $this->e($name) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- Admin -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title"><?= $this->te('install.step_admin') ?></h2>
                <?= $this->icon('shield-check', 'size-4 text-slate-400') ?>
            </div>
            <div class="grid gap-4 p-5 sm:grid-cols-2">
                <div>
                    <label class="label" for="username"><?= $this->te('install.admin_username') ?></label>
                    <input type="text" name="username" id="username" class="input<?= $this->hasError('username') ? ' input-error' : '' ?>"
                           value="<?= $this->old('username') ?>" autocapitalize="none" spellcheck="false" required>
                    <?php if ($this->hasError('username')): ?>
                        <p class="error-text"><?= $this->icon('alert-triangle', 'size-3.5') ?><?= $this->e((string) $this->error('username')) ?></p>
                    <?php endif; ?>
                </div>
                <div>
                    <label class="label" for="email"><?= $this->te('install.admin_email') ?></label>
                    <input type="email" name="email" id="email" class="input<?= $this->hasError('email') ? ' input-error' : '' ?>"
                           value="<?= $this->old('email') ?>" required>
                    <?php if ($this->hasError('email')): ?>
                        <p class="error-text"><?= $this->icon('alert-triangle', 'size-3.5') ?><?= $this->e((string) $this->error('email')) ?></p>
                    <?php endif; ?>
                </div>
                <div>
                    <label class="label" for="password"><?= $this->te('install.admin_password') ?></label>
                    <input type="password" name="password" id="password" class="input<?= $this->hasError('password') ? ' input-error' : '' ?>"
                           autocomplete="new-password" required>
                    <?php if ($this->hasError('password')): ?>
                        <p class="error-text"><?= $this->icon('alert-triangle', 'size-3.5') ?><?= $this->e((string) $this->error('password')) ?></p>
                    <?php else: ?>
                        <p class="help"><?= $this->te('users.password_hint') ?></p>
                    <?php endif; ?>
                </div>
                <div>
                    <label class="label" for="password_confirmation"><?= $this->te('install.admin_password_confirm') ?></label>
                    <input type="password" name="password_confirmation" id="password_confirmation"
                           class="input<?= $this->hasError('password_confirmation') ? ' input-error' : '' ?>"
                           autocomplete="new-password" required>
                    <?php if ($this->hasError('password_confirmation')): ?>
                        <p class="error-text"><?= $this->icon('alert-triangle', 'size-3.5') ?><?= $this->e((string) $this->error('password_confirmation')) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary btn-lg w-full" <?= $blocked ? 'disabled' : '' ?>>
            <?= $this->icon('zap') ?>
            <?= $this->te('install.submit') ?>
        </button>
    </form>
</div>
