<?php

/**
 * Dashboard overview.
 *
 * @var App\Core\View $this
 * @var array{files: int, active: int, protected: int, bytes: int, downloads: int} $stats
 * @var array<int, array{date: string, label: string, count: int}> $series
 * @var int $downloads30
 * @var int $uniqueVisitors
 * @var array<int, array<string, mixed>> $recentFiles
 * @var array<int, array<string, mixed>> $topFiles
 * @var array<int, array{country: ?string, total: int}> $countries
 * @var array<int, array<string, mixed>> $activity
 * @var int $pendingCount
 * @var int $missingCount
 * @var int|null $freeSpace
 */

use App\Core\Geo;
use App\Models\FileItem;
use App\Models\User;

$this->title = $this->t('dashboard.title');
?>
<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h2 class="text-2xl font-semibold tracking-tight text-slate-900"><?= $this->te('dashboard.title') ?></h2>
            <p class="mt-1 text-sm text-slate-500"><?= $this->te('dashboard.subtitle') ?></p>
        </div>
        <div class="flex gap-2">
            <a href="<?= $this->e($this->url('/admin/upload')) ?>" class="btn btn-primary">
                <?= $this->icon('cloud-upload') ?>
                <?= $this->te('files.add') ?>
            </a>
        </div>
    </div>

    <?php if ($pendingCount > 0): ?>
        <div class="alert alert-info">
            <?= $this->icon('folder-down') ?>
            <div class="flex-1">
                <p class="font-medium"><?= $this->te('dashboard.pending_import') ?></p>
                <p class="mt-0.5 text-sm opacity-90">
                    <?= $this->te('dashboard.pending_import_text') ?>
                    <?= $this->e($this->choice('common.files_count', $pendingCount, ['count' => $this->number($pendingCount)])) ?>.
                </p>
            </div>
            <a href="<?= $this->e($this->url('/admin/import')) ?>" class="btn btn-primary btn-sm shrink-0">
                <?= $this->te('dashboard.pending_import_cta') ?>
            </a>
        </div>
    <?php endif; ?>

    <?php if ($missingCount > 0): ?>
        <div class="alert alert-warning">
            <?= $this->icon('alert-triangle') ?>
            <div class="flex-1">
                <p class="font-medium"><?= $this->te('dashboard.missing_files') ?></p>
                <p class="mt-0.5 text-sm opacity-90"><?= $this->te('dashboard.missing_files_text', ['count' => $missingCount]) ?></p>
            </div>
            <a href="<?= $this->e($this->url('/admin/import')) ?>" class="btn btn-secondary btn-sm shrink-0">
                <?= $this->te('common.details') ?>
            </a>
        </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="stat">
            <div class="flex items-start justify-between">
                <div>
                    <p class="stat-label"><?= $this->te('dashboard.stat_files') ?></p>
                    <p class="stat-value"><?= $this->e($this->number($stats['files'])) ?></p>
                    <p class="stat-foot"><?= $this->te('dashboard.stat_active_files', ['count' => $this->number($stats['active'])]) ?></p>
                </div>
                <span class="flex size-10 items-center justify-center rounded-xl bg-brand-50 text-brand-600"><?= $this->icon('files') ?></span>
            </div>
        </div>

        <div class="stat">
            <div class="flex items-start justify-between">
                <div>
                    <p class="stat-label"><?= $this->te('dashboard.stat_downloads') ?></p>
                    <p class="stat-value"><?= $this->e($this->number($stats['downloads'])) ?></p>
                    <p class="stat-foot"><?= $this->e($this->number($downloads30)) ?> <?= $this->te('dashboard.stat_downloads_30') ?></p>
                </div>
                <span class="flex size-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600"><?= $this->icon('download') ?></span>
            </div>
        </div>

        <div class="stat">
            <div class="flex items-start justify-between">
                <div>
                    <p class="stat-label"><?= $this->te('dashboard.stat_storage') ?></p>
                    <p class="stat-value"><?= $this->e($this->bytes($stats['bytes'])) ?></p>
                    <p class="stat-foot">
                        <?php if ($freeSpace !== null): ?>
                            <?= $this->te('dashboard.stat_free_space', ['size' => $this->bytes($freeSpace)]) ?>
                        <?php endif; ?>
                    </p>
                </div>
                <span class="flex size-10 items-center justify-center rounded-xl bg-amber-50 text-amber-600"><?= $this->icon('hard-drive') ?></span>
            </div>
        </div>

        <div class="stat">
            <div class="flex items-start justify-between">
                <div>
                    <p class="stat-label"><?= $this->te('dashboard.stat_protected') ?></p>
                    <p class="stat-value"><?= $this->e($this->number($stats['protected'])) ?></p>
                    <p class="stat-foot"><?= $this->e($this->number($uniqueVisitors)) ?> <?= $this->te('dashboard.stat_unique') ?></p>
                </div>
                <span class="flex size-10 items-center justify-center rounded-xl bg-violet-50 text-violet-600"><?= $this->icon('lock') ?></span>
            </div>
        </div>
    </div>

    <!-- Chart + countries -->
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="card lg:col-span-2">
            <div class="card-header">
                <div>
                    <h3 class="card-title"><?= $this->te('dashboard.chart_title') ?></h3>
                    <p class="card-sub"><?= $this->e($this->number($downloads30)) ?> <?= $this->te('common.downloads') ?></p>
                </div>
                <a href="<?= $this->e($this->url('/admin/downloads')) ?>" class="btn btn-ghost btn-sm">
                    <?= $this->te('common.view_all') ?>
                    <?= $this->icon('arrow-up-right') ?>
                </a>
            </div>
            <div class="p-5 sm:p-6">
                <?php $this->partial('partials/bar-chart', ['series' => $series]); ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><?= $this->te('dashboard.countries') ?></h3>
            </div>
            <div class="p-5 sm:p-6">
                <?php if ($countries === []): ?>
                    <p class="py-6 text-center text-sm text-slate-400"><?= $this->te('dashboard.chart_empty') ?></p>
                <?php else: ?>
                    <?php $maxCountry = max(array_map(static fn ($c) => $c['total'], $countries)); ?>
                    <ul class="space-y-3">
                        <?php foreach ($countries as $country): ?>
                            <li>
                                <div class="flex items-center justify-between gap-3 text-sm">
                                    <span class="flex min-w-0 items-center gap-2">
                                        <span class="text-base leading-none"><?= Geo::flag($country['country']) ?></span>
                                        <span class="truncate text-slate-700"><?= $this->e(Geo::name($country['country'])) ?></span>
                                    </span>
                                    <span class="font-medium text-slate-900 tabular-nums"><?= $this->e($this->number($country['total'])) ?></span>
                                </div>
                                <div class="progress mt-1.5">
                                    <span style="width: <?= (int) round($country['total'] / max(1, $maxCountry) * 100) ?>%"></span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Files -->
    <div class="grid gap-6 lg:grid-cols-2">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><?= $this->te('dashboard.recent_files') ?></h3>
                <a href="<?= $this->e($this->url('/admin/files')) ?>" class="btn btn-ghost btn-sm">
                    <?= $this->te('common.view_all') ?><?= $this->icon('arrow-up-right') ?>
                </a>
            </div>

            <?php if ($recentFiles === []): ?>
                <?php $this->partial('partials/empty', [
                    'icon' => 'cloud-upload',
                    'title' => $this->t('dashboard.empty_title'),
                    'text' => $this->t('dashboard.empty_text'),
                    'action' => '<a href="' . $this->e($this->url('/admin/upload')) . '" class="btn btn-primary">'
                        . $this->icon('cloud-upload') . $this->te('files.add') . '</a>',
                ]); ?>
            <?php else: ?>
                <ul class="divide-y divide-slate-100">
                    <?php foreach ($recentFiles as $file): ?>
                        <li>
                            <a href="<?= $this->e($this->url('/admin/files/' . $file['id'])) ?>" class="flex items-center gap-3 px-5 py-3.5 transition hover:bg-slate-50 sm:px-6">
                                <?php $this->partial('partials/file-tile', ['file' => $file, 'tileClass' => 'size-10 text-[10px]']); ?>
                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-sm font-medium text-slate-900"><?= $this->e(FileItem::displayName($file)) ?></div>
                                    <div class="mt-0.5 flex items-center gap-2 text-xs text-slate-500">
                                        <span class="truncate font-mono">/<?= $this->e((string) $file['alias']) ?></span>
                                        <span>·</span>
                                        <span class="shrink-0"><?= $this->e($this->bytes((int) $file['size_bytes'])) ?></span>
                                    </div>
                                </div>
                                <div class="shrink-0 text-right">
                                    <div class="text-sm font-medium text-slate-900 tabular-nums"><?= $this->e($this->number((int) $file['download_count'])) ?></div>
                                    <div class="text-[11px] text-slate-400"><?= $this->te('common.downloads') ?></div>
                                </div>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><?= $this->te('dashboard.top_files') ?></h3>
            </div>

            <?php if ($topFiles === []): ?>
                <?php $this->partial('partials/empty', [
                    'icon' => 'bar-chart',
                    'title' => $this->t('dashboard.chart_empty'),
                ]); ?>
            <?php else: ?>
                <?php $maxDownloads = max(array_map(static fn ($f) => (int) $f['download_count'], $topFiles)); ?>
                <ul class="space-y-4 p-5 sm:p-6">
                    <?php foreach ($topFiles as $file): ?>
                        <li>
                            <div class="flex items-center justify-between gap-3">
                                <a href="<?= $this->e($this->url('/admin/files/' . $file['id'])) ?>" class="min-w-0 truncate text-sm font-medium text-slate-800 hover:text-brand-700">
                                    <?= $this->e(FileItem::displayName($file)) ?>
                                </a>
                                <span class="shrink-0 text-sm font-medium text-slate-900 tabular-nums"><?= $this->e($this->number((int) $file['download_count'])) ?></span>
                            </div>
                            <div class="progress mt-1.5">
                                <span style="width: <?= (int) round((int) $file['download_count'] / max(1, $maxDownloads) * 100) ?>%"></span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <!-- Activity -->
    <?php if ($activity !== []): ?>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><?= $this->te('dashboard.recent_activity') ?></h3>
            </div>
            <ul class="divide-y divide-slate-100">
                <?php foreach ($activity as $entry): ?>
                    <?php
                    $action = (string) $entry['action'];
                    $actorName = $entry['username'] === null
                        ? $this->t('activity.system')
                        : (string) ($entry['display_name'] ?: $entry['username']);
                    $text = $this->t('activity.' . $action, ['subject' => (string) ($entry['subject'] ?? '')]);
                    ?>
                    <li class="flex items-center gap-3 px-5 py-3 text-sm sm:px-6">
                        <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-slate-100 text-[11px] font-semibold text-slate-600">
                            <?= $this->e(mb_strtoupper(mb_substr($actorName, 0, 2))) ?>
                        </span>
                        <p class="min-w-0 flex-1 truncate text-slate-600">
                            <span class="font-medium text-slate-900"><?= $this->e($actorName) ?></span>
                            <?= $this->e($text) ?>
                        </p>
                        <time class="shrink-0 text-xs text-slate-400" title="<?= $this->e($this->date((string) $entry['created_at'])) ?>">
                            <?= $this->e($this->ago((string) $entry['created_at'])) ?>
                        </time>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
</div>
