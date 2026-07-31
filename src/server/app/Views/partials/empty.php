<?php

/**
 * Empty state block.
 *
 * @var App\Core\View $this
 * @var string $icon
 * @var string $title
 * @var string|null $text
 * @var string|null $action  raw HTML for a call to action
 */
?>
<div class="flex flex-col items-center justify-center px-6 py-16 text-center">
    <span class="flex size-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
        <?= $this->icon($icon ?? 'file-question', 'size-6') ?>
    </span>
    <h3 class="mt-4 text-base font-semibold text-slate-900"><?= $this->e($title) ?></h3>
    <?php if (!empty($text)): ?>
        <p class="mt-1.5 max-w-sm text-sm text-slate-500"><?= $this->e($text) ?></p>
    <?php endif; ?>
    <?php if (!empty($action)): ?>
        <div class="mt-6"><?= $action ?></div>
    <?php endif; ?>
</div>
