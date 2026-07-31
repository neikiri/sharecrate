<?php

/**
 * Pager that keeps the current filters in the query string.
 *
 * @var App\Core\View $this
 * @var array{total: int, page: int, pages: int, per_page: int} $result
 */

if ($result['pages'] <= 1) {
    return;
}

$page = $result['page'];
$pages = $result['pages'];
$from = ($page - 1) * $result['per_page'] + 1;
$to = min($result['total'], $page * $result['per_page']);

$window = [];
for ($i = max(1, $page - 2); $i <= min($pages, $page + 2); $i++) {
    $window[] = $i;
}
?>
<div class="flex flex-col gap-3 border-t border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
    <p class="text-xs text-slate-500">
        <?= $this->te('common.showing', ['from' => $from, 'to' => $to, 'total' => $result['total']]) ?>
    </p>

    <nav class="flex items-center gap-1" aria-label="<?= $this->te('common.page_of', ['page' => $page, 'pages' => $pages]) ?>">
        <?php if ($page > 1): ?>
            <a href="<?= $this->e($this->queryUrl(['page' => $page - 1])) ?>" class="btn btn-secondary btn-sm btn-icon" rel="prev" title="<?= $this->te('common.previous') ?>">
                <?= $this->icon('chevron-left') ?>
            </a>
        <?php endif; ?>

        <?php if (($window[0] ?? 1) > 1): ?>
            <a href="<?= $this->e($this->queryUrl(['page' => 1])) ?>" class="btn btn-ghost btn-sm">1</a>
            <?php if (($window[0] ?? 1) > 2): ?>
                <span class="px-1 text-slate-400">…</span>
            <?php endif; ?>
        <?php endif; ?>

        <?php foreach ($window as $number): ?>
            <?php if ($number === $page): ?>
                <span class="btn btn-sm bg-slate-900 text-white"><?= $number ?></span>
            <?php else: ?>
                <a href="<?= $this->e($this->queryUrl(['page' => $number])) ?>" class="btn btn-ghost btn-sm"><?= $number ?></a>
            <?php endif; ?>
        <?php endforeach; ?>

        <?php $last = $window[count($window) - 1] ?? $pages; ?>
        <?php if ($last < $pages): ?>
            <?php if ($last < $pages - 1): ?>
                <span class="px-1 text-slate-400">…</span>
            <?php endif; ?>
            <a href="<?= $this->e($this->queryUrl(['page' => $pages])) ?>" class="btn btn-ghost btn-sm"><?= $pages ?></a>
        <?php endif; ?>

        <?php if ($page < $pages): ?>
            <a href="<?= $this->e($this->queryUrl(['page' => $page + 1])) ?>" class="btn btn-secondary btn-sm btn-icon" rel="next" title="<?= $this->te('common.next') ?>">
                <?= $this->icon('chevron-right') ?>
            </a>
        <?php endif; ?>
    </nav>
</div>
