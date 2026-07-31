<?php

/**
 * Installation finished.
 *
 * @var App\Core\View $this
 */
?>
<div class="card w-full max-w-lg animate-rise p-8 text-center shadow-soft">
    <span class="mx-auto flex size-14 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600">
        <?= $this->icon('check-circle', 'size-7') ?>
    </span>

    <h1 class="mt-6 text-2xl font-semibold tracking-tight text-slate-900"><?= $this->te('install.success_title') ?></h1>
    <p class="mt-3 text-sm text-slate-600"><?= $this->te('install.success_text') ?></p>

    <div class="alert alert-info mt-6 text-left">
        <?= $this->icon('shield') ?>
        <div class="flex-1"><?= $this->te('install.success_cleanup') ?></div>
    </div>

    <a href="<?= $this->e($this->url('/admin/login')) ?>" class="btn btn-primary mt-6 w-full">
        <?= $this->icon('log-in') ?>
        <?= $this->te('install.go_login') ?>
    </a>
</div>
