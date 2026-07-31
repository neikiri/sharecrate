<?php

/**
 * Global download log.
 *
 * @var App\Core\View $this
 * @var array<string, mixed> $filters
 * @var array{rows: array<int, array<string, mixed>>, total: int, page: int, pages: int, per_page: int} $result
 * @var array<int, array{date: string, label: string, count: int}> $series
 * @var array<int, array{country: ?string, total: int}> $countries
 * @var int $uniqueVisitors
 * @var int $total
 */

use App\Core\Geo;
use App\Support\UserAgent;

$this->title = $this->t('downloads.title');

$periods = [
    1 => $this->t('common.today'),
    7 => $this->t('common.last_7_days'),
    30 => $this->t('common.last_30_days'),
    90 => $this->t('common.last_90_days'),
    0 => $this->t('common.all_time'),
];

$exportQuery = array_filter([
    'q' => $filters['q'] ?: null,
    'country' => $filters['country'] ?: null,
    'days' => $filters['days'] !== 30 ? (string) $filters['days'] : null,
    'file' => $filters['file'] ?: null,
]);
?>
<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h2 class="text-2xl font-semibold tracking-tight text-slate-900"><?= $this->te('downloads.title') ?></h2>
            <p class="mt-1 text-sm text-slate-500"><?= $this->te('downloads.subtitle') ?></p>
        </div>
        <a href="<?= $this->e($this->url('/admin/downloads/export', $exportQuery)) ?>" class="btn btn-secondary">
            <?= $this->icon('download') ?>
            <?= $this->te('downloads.export') ?>
        </a>
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <div class="stat">
            <p class="stat-label"><?= $this->te('common.downloads') ?></p>
            <p class="stat-value"><?= $this->e($this->number($total)) ?></p>
        </div>
        <div class="stat">
            <p class="stat-label"><?= $this->te('files.unique_visitors') ?></p>
            <p class="stat-value"><?= $this->e($this->number($uniqueVisitors)) ?></p>
        </div>
        <div class="stat">
            <p class="stat-label"><?= $this->te('downloads.filter_country') ?></p>
            <p class="stat-value"><?= $this->e($this->number(count($countries))) ?></p>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="card lg:col-span-2">
            <div class="card-header">
                <h3 class="card-title"><?= $this->te('dashboard.chart_title') ?></h3>
            </div>
            <div class="p-5 sm:p-6">
                <?php $this->partial('partials/bar-chart', ['series' => $series]); ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><?= $this->te('dashboard.countries') ?></h3>
            </div>
            <div class="p-5">
                <?php if ($countries === []): ?>
                    <p class="py-4 text-center text-sm text-slate-400"><?= $this->te('downloads.empty') ?></p>
                <?php else: ?>
                    <ul class="space-y-2 text-sm">
                        <?php foreach ($countries as $country): ?>
                            <li class="flex items-center justify-between gap-2">
                                <a href="<?= $this->e($this->queryUrl(['country' => $country['country'], 'page' => null])) ?>" class="flex min-w-0 items-center gap-2 hover:text-brand-700">
                                    <span><?= Geo::flag($country['country']) ?></span>
                                    <span class="truncate text-slate-600"><?= $this->e(Geo::name($country['country'])) ?></span>
                                </a>
                                <span class="font-medium text-slate-900 tabular-nums"><?= $this->e($this->number($country['total'])) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <form method="get" action="<?= $this->e($this->url('/admin/downloads')) ?>" class="card p-4">
        <?php if (!empty($filters['file'])): ?>
            <input type="hidden" name="file" value="<?= (int) $filters['file'] ?>">
        <?php endif; ?>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="lg:col-span-2">
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex w-10 items-center justify-center text-slate-400">
                        <?= $this->icon('search', 'size-4') ?>
                    </span>
                    <input type="search" name="q" value="<?= $this->e((string) $filters['q']) ?>" class="input pl-10"
                           placeholder="<?= $this->te('common.search_placeholder') ?>">
                </div>
            </div>

            <div>
                <select name="days" class="select">
                    <?php foreach ($periods as $value => $label): ?>
                        <option value="<?= (int) $value ?>" <?= (int) $filters['days'] === (int) $value ? 'selected' : '' ?>>
                            <?= $this->e($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="flex gap-2">
                <input type="text" name="country" value="<?= $this->e((string) $filters['country']) ?>" class="input uppercase"
                       maxlength="2" placeholder="<?= $this->te('downloads.filter_country') ?>">
                <button type="submit" class="btn btn-secondary shrink-0"><?= $this->icon('filter') ?></button>
            </div>
        </div>

        <?php if ($filters['q'] !== '' || $filters['country'] !== '' || !empty($filters['file'])): ?>
            <a href="<?= $this->e($this->url('/admin/downloads')) ?>" class="btn btn-ghost btn-sm mt-3">
                <?= $this->icon('x') ?><?= $this->te('common.reset') ?>
            </a>
        <?php endif; ?>
    </form>

    <!-- Log -->
    <div class="card overflow-hidden">
        <?php if ($result['rows'] === []): ?>
            <?php $this->partial('partials/empty', [
                'icon' => 'bar-chart',
                'title' => $this->t('downloads.empty'),
            ]); ?>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                    <tr>
                        <th><?= $this->te('downloads.when') ?></th>
                        <th><?= $this->te('downloads.file') ?></th>
                        <th class="hidden sm:table-cell"><?= $this->te('downloads.visitor') ?></th>
                        <th class="hidden md:table-cell"><?= $this->te('downloads.location') ?></th>
                        <th class="hidden lg:table-cell"><?= $this->te('downloads.device') ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($result['rows'] as $row): ?>
                        <tr>
                            <td class="whitespace-nowrap text-slate-600" title="<?= $this->e($this->date((string) $row['created_at'])) ?>">
                                <?= $this->e($this->ago((string) $row['created_at'])) ?>
                            </td>
                            <td>
                                <div class="flex min-w-0 items-center gap-2.5">
                                    <?php $this->partial('partials/file-tile', ['file' => $row, 'tileClass' => 'size-8 text-[9px]']); ?>
                                    <a href="<?= $this->e($this->url('/admin/files/' . $row['file_id'])) ?>" class="block max-w-[14rem] truncate text-sm font-medium text-slate-900 hover:text-brand-700">
                                        <?= $this->e((string) $row['original_name']) ?>
                                    </a>
                                </div>
                            </td>
                            <td class="hidden font-mono text-xs text-slate-600 sm:table-cell">
                                <?= $this->e((string) ($row['ip'] ?? $this->t('downloads.ip_hidden'))) ?>
                            </td>
                            <td class="hidden md:table-cell">
                                <span class="flex items-center gap-1.5 text-slate-600">
                                    <span><?= Geo::flag(is_string($row['country']) ? $row['country'] : null) ?></span>
                                    <span class="max-w-[10rem] truncate">
                                        <?= $this->e((string) ($row['city'] ?: Geo::name(is_string($row['country']) ? $row['country'] : null))) ?>
                                    </span>
                                </span>
                            </td>
                            <td class="hidden text-slate-600 lg:table-cell">
                                <span class="flex items-center gap-1.5">
                                    <?= $this->icon(UserAgent::platformIcon(is_string($row['platform']) ? $row['platform'] : null), 'size-3.5 text-slate-400') ?>
                                    <span class="truncate"><?= $this->e(trim((string) ($row['browser'] ?? '') . ' · ' . (string) ($row['platform'] ?? ''), ' ·')) ?></span>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php $this->partial('partials/pagination', ['result' => $result]); ?>
        <?php endif; ?>
    </div>
</div>
