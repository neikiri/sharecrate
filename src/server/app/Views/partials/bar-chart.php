<?php

/**
 * Pure CSS bar chart for daily download counts.
 *
 * @var App\Core\View $this
 * @var array<int, array{date: string, label: string, count: int}> $series
 * @var string|null $emptyText
 */

$max = 0;

foreach ($series as $point) {
    $max = max($max, $point['count']);
}

if ($max === 0) {
    ?>
    <div class="flex h-32 items-center justify-center rounded-xl bg-slate-50 text-sm text-slate-400">
        <?= $this->e($emptyText ?? $this->t('dashboard.chart_empty')) ?>
    </div>
    <?php
    return;
}

$count = count($series);
?>
<div class="flex h-32 items-end gap-[3px]" role="img" aria-label="<?= $this->te('dashboard.chart_title') ?>">
    <?php foreach ($series as $index => $point): ?>
        <?php
        $height = $point['count'] === 0 ? 2 : max(6, (int) round($point['count'] / $max * 100));
        $isToday = $index === $count - 1;
        ?>
        <div class="group relative flex h-full flex-1 items-end">
            <div
                class="w-full rounded-t-[4px] transition-colors <?= $isToday ? 'bg-brand-500' : 'bg-brand-200 group-hover:bg-brand-400' ?>"
                style="height: <?= $height ?>%"
            ></div>
            <div class="pointer-events-none absolute bottom-full left-1/2 z-10 mb-1.5 hidden -translate-x-1/2 whitespace-nowrap rounded-lg bg-slate-900 px-2 py-1 text-[11px] font-medium text-white group-hover:block">
                <?= $this->e($point['label']) ?> · <?= $this->e($this->number($point['count'])) ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<div class="mt-2 flex justify-between text-[11px] text-slate-400">
    <span><?= $this->e($series[0]['label'] ?? '') ?></span>
    <span><?= $this->e($series[$count - 1]['label'] ?? '') ?></span>
</div>
