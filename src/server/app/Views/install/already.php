<?php

/**
 * The installer refuses to run once .env exists.
 *
 * @var App\Core\View $this
 */
?>
<div class="card w-full max-w-lg animate-rise p-8 text-center shadow-soft">
    <span class="mx-auto flex size-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-500">
        <?= $this->icon('shield-check', 'size-6') ?>
    </span>

    <h1 class="mt-6 text-xl font-semibold tracking-tight text-slate-900"><?= $this->te('install.already_title') ?></h1>
    <p class="mt-3 text-sm text-slate-600"><?= $this->te('install.already_text') ?></p>

    <div class="mt-6 flex flex-col gap-2 sm:flex-row sm:justify-center">
        <a href="<?= $this->e($this->url('/admin/login')) ?>" class="btn btn-primary">
            <?= $this->icon('log-in') ?><?= $this->te('install.go_login') ?>
        </a>
        <a href="<?= $this->e($this->url('/')) ?>" class="btn btn-secondary">
            <?= $this->icon('home') ?><?= $this->te('errors.go_home') ?>
        </a>
    </div>
</div>
