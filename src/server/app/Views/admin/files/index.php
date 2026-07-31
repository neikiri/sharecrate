<?php

/**
 * File list with filters and bulk actions.
 *
 * @var App\Core\View $this
 * @var array<string, mixed> $filters
 * @var array{rows: array<int, array<string, mixed>>, total: int, page: int, pages: int, per_page: int} $result
 * @var array<int, array{id: int, username: string}> $owners
 * @var string[] $categories
 */

use App\Models\FileItem;
use App\Support\FileTypes;

$this->title = $this->t('files.title');

$states = ['active', 'protected', 'disabled', 'expired', 'limit'];
$sortOptions = [
    'created_at' => $this->t('files.sort_newest'),
    'downloads' => $this->t('files.sort_most_downloaded'),
    'size' => $this->t('files.sort_largest'),
    'name' => $this->t('common.name'),
];

$stateBadge = [
    'active' => ['badge-success', 'check-circle'],
    'disabled' => ['badge-neutral', 'ban'],
    'expired' => ['badge-warning', 'timer'],
    'limit' => ['badge-warning', 'download'],
    'missing' => ['badge-danger', 'alert-triangle'],
];

$hasFilters = ($filters['q'] ?? '') !== ''
    || ($filters['state'] ?? '') !== ''
    || ($filters['category'] ?? '') !== ''
    || (!empty($filters['owner']) && $owners !== []);
?>
<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h2 class="text-2xl font-semibold tracking-tight text-slate-900"><?= $this->te('files.title') ?></h2>
            <p class="mt-1 text-sm text-slate-500">
                <?= $this->te('files.subtitle') ?>
                · <?= $this->e($this->choice('common.files_count', $result['total'], ['count' => $this->number($result['total'])])) ?>
            </p>
        </div>
        <div class="flex gap-2">
            <a href="<?= $this->e($this->url('/admin/import')) ?>" class="btn btn-secondary">
                <?= $this->icon('folder-down') ?>
                <span class="hidden sm:inline"><?= $this->te('nav.import') ?></span>
            </a>
            <a href="<?= $this->e($this->url('/admin/upload')) ?>" class="btn btn-primary">
                <?= $this->icon('cloud-upload') ?>
                <?= $this->te('files.add') ?>
            </a>
        </div>
    </div>

    <!-- Filters -->
    <form method="get" action="<?= $this->e($this->url('/admin/files')) ?>" class="card p-4">
        <div class="grid gap-3 lg:grid-cols-12">
            <div class="lg:col-span-4">
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex w-10 items-center justify-center text-slate-400">
                        <?= $this->icon('search', 'size-4') ?>
                    </span>
                    <input
                        type="search"
                        name="q"
                        value="<?= $this->e((string) ($filters['q'] ?? '')) ?>"
                        class="input pl-10"
                        placeholder="<?= $this->te('common.search_placeholder') ?>"
                    >
                </div>
            </div>

            <div class="lg:col-span-2">
                <select name="state" class="select">
                    <option value=""><?= $this->te('files.filter_state') ?>: <?= $this->te('common.all') ?></option>
                    <?php foreach ($states as $state): ?>
                        <option value="<?= $state ?>" <?= ($filters['state'] ?? '') === $state ? 'selected' : '' ?>>
                            <?= $this->te('files.state_' . $state) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="lg:col-span-2">
                <select name="category" class="select">
                    <option value=""><?= $this->te('files.filter_category') ?>: <?= $this->te('common.all') ?></option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= $this->e($category) ?>" <?= ($filters['category'] ?? '') === $category ? 'selected' : '' ?>>
                            <?= $this->e(FileTypes::label($category)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if ($owners !== []): ?>
                <div class="lg:col-span-2">
                    <select name="owner" class="select">
                        <option value=""><?= $this->te('files.filter_owner') ?>: <?= $this->te('common.all') ?></option>
                        <?php foreach ($owners as $owner): ?>
                            <option value="<?= (int) $owner['id'] ?>" <?= (int) ($filters['owner'] ?? 0) === (int) $owner['id'] ? 'selected' : '' ?>>
                                <?= $this->e((string) $owner['username']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <div class="<?= $owners !== [] ? 'lg:col-span-2' : 'lg:col-span-4' ?>">
                <select name="sort" class="select">
                    <?php foreach ($sortOptions as $value => $label): ?>
                        <option value="<?= $this->e($value) ?>" <?= ($filters['sort'] ?? '') === $value ? 'selected' : '' ?>>
                            <?= $this->e($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="mt-3 flex items-center gap-2">
            <button type="submit" class="btn btn-secondary btn-sm">
                <?= $this->icon('filter') ?>
                <?= $this->te('common.apply') ?>
            </button>
            <?php if ($hasFilters): ?>
                <a href="<?= $this->e($this->url('/admin/files')) ?>" class="btn btn-ghost btn-sm">
                    <?= $this->icon('x') ?>
                    <?= $this->te('common.reset') ?>
                </a>
            <?php endif; ?>
        </div>
    </form>

    <!-- Table -->
    <form method="post" action="<?= $this->e($this->url('/admin/files/bulk')) ?>" x-data="bulkSelect" class="card overflow-hidden">
        <?= $this->csrf() ?>

        <div x-cloak x-show="count > 0" class="flex flex-wrap items-center gap-3 border-b border-brand-100 bg-brand-50/70 px-5 py-3">
            <span class="text-sm font-medium text-brand-900" x-text="`${count} ✓`"></span>
            <div class="flex flex-wrap gap-2">
                <button type="submit" name="bulk_action" value="activate" class="btn btn-secondary btn-sm">
                    <?= $this->icon('check-circle') ?><?= $this->te('files.bulk_activate') ?>
                </button>
                <button type="submit" name="bulk_action" value="disable" class="btn btn-secondary btn-sm">
                    <?= $this->icon('ban') ?><?= $this->te('files.bulk_disable') ?>
                </button>
                <button
                    type="submit"
                    name="bulk_action"
                    value="delete"
                    class="btn btn-danger-soft btn-sm"
                    data-confirm="<?= $this->te('files.bulk_delete_confirm') ?>"
                    data-confirm-label="<?= $this->te('common.delete') ?>"
                >
                    <?= $this->icon('trash') ?><?= $this->te('files.bulk_delete') ?>
                </button>
            </div>
            <label class="ml-auto flex cursor-pointer items-center gap-2 text-xs text-brand-900">
                <input type="checkbox" name="delete_file" value="1" class="checkbox">
                <?= $this->te('files.delete_file_too') ?>
            </label>
        </div>

        <?php if ($result['rows'] === []): ?>
            <?php $this->partial('partials/empty', [
                'icon' => 'files',
                'title' => $this->t('common.no_results'),
                'text' => $this->t('files.empty'),
                'action' => '<a href="' . $this->e($this->url('/admin/upload')) . '" class="btn btn-primary">'
                    . $this->icon('cloud-upload') . $this->te('files.add') . '</a>',
            ]); ?>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                    <tr>
                        <th class="w-10">
                            <input type="checkbox" class="checkbox" @change="toggleAll($event)" aria-label="<?= $this->te('common.select_all') ?>">
                        </th>
                        <th><?= $this->te('common.name') ?></th>
                        <th class="hidden md:table-cell"><?= $this->te('common.status') ?></th>
                        <th class="hidden sm:table-cell">
                            <a class="th-link" href="<?= $this->e($this->queryUrl(['sort' => 'size', 'dir' => ($filters['sort'] ?? '') === 'size' && ($filters['dir'] ?? '') === 'desc' ? 'asc' : 'desc'])) ?>">
                                <?= $this->te('common.size') ?><?= $this->icon('chevrons-up-down', 'size-3') ?>
                            </a>
                        </th>
                        <th>
                            <a class="th-link" href="<?= $this->e($this->queryUrl(['sort' => 'downloads', 'dir' => ($filters['sort'] ?? '') === 'downloads' && ($filters['dir'] ?? '') === 'desc' ? 'asc' : 'desc'])) ?>">
                                <?= $this->te('common.downloads') ?><?= $this->icon('chevrons-up-down', 'size-3') ?>
                            </a>
                        </th>
                        <th class="hidden lg:table-cell">
                            <a class="th-link" href="<?= $this->e($this->queryUrl(['sort' => 'created_at', 'dir' => ($filters['sort'] ?? '') === 'created_at' && ($filters['dir'] ?? '') === 'desc' ? 'asc' : 'desc'])) ?>">
                                <?= $this->te('common.added') ?><?= $this->icon('chevrons-up-down', 'size-3') ?>
                            </a>
                        </th>
                        <th class="w-px text-right"><?= $this->te('common.actions') ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($result['rows'] as $file): ?>
                        <?php
                        $state = FileItem::state($file);
                        $badge = $stateBadge[$state] ?? $stateBadge['active'];
                        $shareUrl = $this->absolute('/' . $file['alias']);
                        ?>
                        <tr>
                            <td>
                                <input
                                    type="checkbox"
                                    name="ids[]"
                                    value="<?= (int) $file['id'] ?>"
                                    class="checkbox"
                                    data-row-checkbox
                                    x-model="selected"
                                    aria-label="<?= $this->e((string) $file['alias']) ?>"
                                >
                            </td>
                            <td>
                                <div class="flex min-w-0 items-center gap-3">
                                    <?php $this->partial('partials/file-tile', ['file' => $file, 'tileClass' => 'size-9 text-[10px]']); ?>
                                    <div class="min-w-0">
                                        <a href="<?= $this->e($this->url('/admin/files/' . $file['id'])) ?>" class="block max-w-[16rem] truncate text-sm font-medium text-slate-900 hover:text-brand-700 sm:max-w-xs">
                                            <?= $this->e(FileItem::displayName($file)) ?>
                                        </a>
                                        <div class="mt-0.5 flex items-center gap-1.5 text-xs text-slate-500">
                                            <span class="max-w-[14rem] truncate font-mono">/<?= $this->e((string) $file['alias']) ?></span>
                                            <?php if (FileItem::hasPassword($file)): ?>
                                                <span class="text-brand-500" title="<?= $this->te('common.protected') ?>"><?= $this->icon('lock', 'size-3') ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="hidden md:table-cell">
                                <span class="badge <?= $badge[0] ?>">
                                    <?= $this->icon($badge[1]) ?>
                                    <?= $this->te('files.state_' . $state) ?>
                                </span>
                            </td>
                            <td class="hidden text-sm text-slate-600 tabular-nums sm:table-cell">
                                <?= $this->e($this->bytes((int) $file['size_bytes'])) ?>
                            </td>
                            <td>
                                <div class="text-sm font-medium text-slate-900 tabular-nums"><?= $this->e($this->number((int) $file['download_count'])) ?></div>
                                <div class="text-[11px] text-slate-400">
                                    <?php if ($file['last_download_at'] === null): ?>
                                        <?= $this->te('files.no_downloads_yet') ?>
                                    <?php else: ?>
                                        <?= $this->e($this->ago((string) $file['last_download_at'])) ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="hidden text-sm text-slate-600 lg:table-cell" title="<?= $this->e($this->date((string) $file['created_at'])) ?>">
                                <?= $this->e($this->ago((string) $file['created_at'])) ?>
                            </td>
                            <td>
                                <div class="flex items-center justify-end gap-1" x-data="copyable(<?= $this->e(json_encode($shareUrl)) ?>)">
                                    <button type="button" class="btn btn-ghost btn-icon" @click="copy()" title="<?= $this->te('common.copy_link') ?>">
                                        <span x-show="!copied"><?= $this->icon('copy', 'size-4') ?></span>
                                        <span x-cloak x-show="copied" class="text-emerald-600"><?= $this->icon('check', 'size-4') ?></span>
                                    </button>
                                    <a href="<?= $this->e($this->url('/' . $file['alias'])) ?>" target="_blank" rel="noopener" class="btn btn-ghost btn-icon" title="<?= $this->te('files.open_public') ?>">
                                        <?= $this->icon('external-link', 'size-4') ?>
                                    </a>
                                    <a href="<?= $this->e($this->url('/admin/files/' . $file['id'])) ?>" class="btn btn-ghost btn-icon" title="<?= $this->te('common.edit') ?>">
                                        <?= $this->icon('pencil', 'size-4') ?>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php $this->partial('partials/pagination', ['result' => $result]); ?>
        <?php endif; ?>
    </form>
</div>
