<?php

/**
 * Error page.
 *
 * @var App\Core\View $this
 * @var int $status
 * @var string $heading
 * @var string $message
 * @var Throwable|null $exception
 */

$icon = match ($status) {
    403 => 'shield',
    404 => 'file-question',
    410 => 'timer',
    429 => 'clock',
    default => 'alert-triangle',
};
?>
<div class="card w-full max-w-lg animate-rise p-8 text-center shadow-soft">
    <span class="mx-auto flex size-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-500">
        <?= $this->icon($icon, 'size-6') ?>
    </span>

    <p class="mt-6 font-mono text-sm text-slate-400"><?= $this->te('errors.error_code', ['code' => $status]) ?></p>
    <h1 class="mt-2 text-2xl font-semibold tracking-tight text-slate-900"><?= $this->e($heading) ?></h1>
    <p class="mx-auto mt-3 max-w-sm text-sm leading-relaxed text-slate-600"><?= $this->e($message) ?></p>

    <div class="mt-8 flex flex-col justify-center gap-2 sm:flex-row">
        <a href="<?= $this->e($this->url('/')) ?>" class="btn btn-primary">
            <?= $this->icon('home') ?>
            <?= $this->te('errors.go_home') ?>
        </a>
        <?php if ($this->user() !== null): ?>
            <a href="<?= $this->e($this->url('/admin')) ?>" class="btn btn-secondary">
                <?= $this->icon('grid') ?>
                <?= $this->te('common.dashboard') ?>
            </a>
        <?php endif; ?>
    </div>

    <?php if ($exception !== null): ?>
        <details class="mt-8 text-left">
            <summary class="cursor-pointer text-xs font-medium text-slate-500">Debug</summary>
            <pre class="mt-3 max-h-72 overflow-auto rounded-xl bg-slate-900 p-4 font-mono text-[11px] leading-relaxed text-rose-200"><?= $this->e($exception->getMessage()) ?>

<?= $this->e($exception->getFile() . ':' . $exception->getLine()) ?>

<?= $this->e($exception->getTraceAsString()) ?></pre>
        </details>
    <?php endif; ?>
</div>
