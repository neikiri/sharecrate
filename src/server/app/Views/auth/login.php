<?php

/**
 * Sign in card.
 *
 * @var App\Core\View $this
 */

$flashes = $this->flashes();
?>
<div class="w-full max-w-md">
    <?php foreach ($flashes as $message): ?>
        <div class="alert <?= $message['type'] === 'error' ? 'alert-error' : 'alert-info' ?> mb-4 animate-rise" role="status">
            <?= $this->icon($message['type'] === 'error' ? 'x-circle' : 'info') ?>
            <div class="flex-1"><?= $this->e($message['message']) ?></div>
        </div>
    <?php endforeach; ?>

    <div class="card animate-rise p-7 shadow-soft sm:p-8">
        <h1 class="text-xl font-semibold tracking-tight text-slate-900"><?= $this->te('auth.title') ?></h1>
        <p class="mt-1.5 text-sm text-slate-500"><?= $this->te('auth.subtitle') ?></p>

        <form method="post" action="<?= $this->e($this->url('/admin/login')) ?>" class="mt-7 space-y-5">
            <?= $this->csrf() ?>

            <div>
                <label class="label" for="login"><?= $this->te('auth.login_label') ?></label>
                <input
                    type="text"
                    name="login"
                    id="login"
                    class="input<?= $this->hasError('login') ? ' input-error' : '' ?>"
                    value="<?= $this->old('login') ?>"
                    autocomplete="username"
                    autocapitalize="none"
                    spellcheck="false"
                    autofocus
                    required
                >
                <?php if ($this->hasError('login') && $this->error('login') !== ''): ?>
                    <p class="error-text"><?= $this->icon('alert-triangle', 'size-3.5') ?><?= $this->e((string) $this->error('login')) ?></p>
                <?php endif; ?>
            </div>

            <div x-data="revealable">
                <label class="label" for="password"><?= $this->te('auth.password_label') ?></label>
                <div class="relative">
                    <input
                        x-ref="input"
                        type="password"
                        name="password"
                        id="password"
                        class="input pr-11<?= $this->hasError('password') ? ' input-error' : '' ?>"
                        autocomplete="current-password"
                        required
                    >
                    <button
                        type="button"
                        class="absolute inset-y-0 right-0 flex w-11 items-center justify-center text-slate-400 hover:text-slate-600"
                        @click="toggle()"
                        tabindex="-1"
                        aria-label="<?= $this->te('common.open') ?>"
                    >
                        <span x-show="!shown"><?= $this->icon('eye', 'size-4') ?></span>
                        <span x-cloak x-show="shown"><?= $this->icon('eye-off', 'size-4') ?></span>
                    </button>
                </div>
                <?php if ($this->hasError('password') && $this->error('password') !== ''): ?>
                    <p class="error-text"><?= $this->icon('alert-triangle', 'size-3.5') ?><?= $this->e((string) $this->error('password')) ?></p>
                <?php endif; ?>
            </div>

            <label class="flex cursor-pointer items-center gap-2.5 text-sm text-slate-600">
                <input type="checkbox" name="remember" value="1" class="checkbox">
                <?= $this->te('auth.remember') ?>
            </label>

            <button type="submit" class="btn btn-primary w-full">
                <?= $this->icon('log-in') ?>
                <?= $this->te('auth.submit') ?>
            </button>
        </form>
    </div>

    <p class="mt-6 text-center text-sm">
        <a href="<?= $this->e($this->url('/')) ?>" class="text-slate-500 hover:text-slate-700"><?= $this->te('auth.back_home') ?></a>
    </p>
</div>
