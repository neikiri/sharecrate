<?php

/**
 * FTP import: files on disk that do not have a link yet.
 *
 * @var App\Core\View $this
 * @var array<int, array{path: string, name: string, size: int, modified: int, extension: string, category: string}> $pending
 * @var array<int, array<string, mixed>> $missing
 * @var string $storagePath
 * @var bool $storageWritable
 */

use App\Models\FileItem;

$this->title = $this->t('import.title');
?>
<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h2 class="text-2xl font-semibold tracking-tight text-slate-900"><?= $this->te('import.title') ?></h2>
            <p class="mt-1 text-sm text-slate-500"><?= $this->te('import.subtitle') ?></p>
        </div>
        <a href="<?= $this->e($this->url('/admin/import')) ?>" class="btn btn-secondary">
            <?= $this->icon('refresh') ?>
            <?= $this->te('import.refresh') ?>
        </a>
    </div>

    <div class="card flex flex-col gap-3 card-pad sm:flex-row sm:items-center">
        <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600">
            <?= $this->icon('server') ?>
        </span>
        <div class="min-w-0 flex-1">
            <p class="text-sm font-medium text-slate-900"><?= $this->te('settings.storage_path') ?></p>
            <div class="code-line mt-1.5">
                <span class="truncate"><?= $this->e($storagePath) ?></span>
            </div>
        </div>
        <span class="badge <?= $storageWritable ? 'badge-success' : 'badge-danger' ?> shrink-0">
            <?= $this->icon($storageWritable ? 'check-circle' : 'x-circle') ?>
            <?= $this->te($storageWritable ? 'settings.storage_writable' : 'settings.storage_not_writable') ?>
        </span>
    </div>

    <?php if ($pending === []): ?>
        <div class="card">
            <?php $this->partial('partials/empty', [
                'icon' => 'check-circle',
                'title' => $this->t('import.empty_title'),
                'text' => $this->t('import.empty_text'),
                'action' => '<a href="' . $this->e($this->url('/admin/files')) . '" class="btn btn-secondary">'
                    . $this->icon('files') . $this->te('nav.files') . '</a>',
            ]); ?>
        </div>
    <?php else: ?>
        <form method="post" action="<?= $this->e($this->url('/admin/import')) ?>" x-data="importList" class="card overflow-hidden">
            <?= $this->csrf() ?>

            <div class="card-header">
                <div>
                    <h3 class="card-title">
                        <?= $this->e($this->choice('common.files_count', count($pending), ['count' => $this->number(count($pending))])) ?>
                    </h3>
                    <p class="card-sub"><?= $this->te('import.subtitle') ?></p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary" :disabled="count === 0">
                        <?= $this->icon('check') ?>
                        <?= $this->te('import.import_selected') ?>
                        <span x-cloak x-show="count > 0" x-text="`(${count})`"></span>
                    </button>
                    <button type="submit" name="all" value="1" class="btn btn-secondary">
                        <?= $this->icon('folder-down') ?>
                        <?= $this->te('import.import_all') ?>
                    </button>
                </div>
            </div>

            <div class="table-wrap">
                <table class="table">
                    <thead>
                    <tr>
                        <th class="w-10">
                            <input type="checkbox" class="checkbox" @change="toggleAll($event)" aria-label="<?= $this->te('common.select_all') ?>">
                        </th>
                        <th><?= $this->te('common.name') ?></th>
                        <th class="hidden sm:table-cell"><?= $this->te('files.detail_storage') ?></th>
                        <th><?= $this->te('common.size') ?></th>
                        <th class="hidden md:table-cell"><?= $this->te('import.modified') ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($pending as $entry): ?>
                        <tr>
                            <td>
                                <input
                                    type="checkbox"
                                    name="paths[]"
                                    value="<?= $this->e($entry['path']) ?>"
                                    class="checkbox"
                                    data-import-checkbox
                                    x-model="selected"
                                    aria-label="<?= $this->e($entry['name']) ?>"
                                >
                            </td>
                            <td>
                                <div class="flex min-w-0 items-center gap-3">
                                    <?php $this->partial('partials/file-tile', [
                                        'file' => ['extension' => $entry['extension']],
                                        'tileClass' => 'size-9 text-[10px]',
                                    ]); ?>
                                    <span class="max-w-[18rem] truncate text-sm font-medium text-slate-900"><?= $this->e($entry['name']) ?></span>
                                </div>
                            </td>
                            <td class="hidden sm:table-cell">
                                <span class="block max-w-[18rem] truncate font-mono text-xs text-slate-500"><?= $this->e($entry['path']) ?></span>
                            </td>
                            <td class="text-sm text-slate-600 tabular-nums"><?= $this->e($this->bytes($entry['size'])) ?></td>
                            <td class="hidden text-sm text-slate-600 md:table-cell">
                                <?= $this->e($this->ago(gmdate('Y-m-d H:i:s', $entry['modified']))) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </form>
    <?php endif; ?>

    <?php if ($missing !== []): ?>
        <div class="card">
            <div class="card-header">
                <div>
                    <h3 class="card-title text-rose-700"><?= $this->te('import.missing_title') ?></h3>
                    <p class="card-sub"><?= $this->te('import.missing_text') ?></p>
                </div>
                <span class="badge badge-danger"><?= $this->e($this->number(count($missing))) ?></span>
            </div>

            <div class="table-wrap">
                <table class="table">
                    <thead>
                    <tr>
                        <th><?= $this->te('common.name') ?></th>
                        <th class="hidden sm:table-cell"><?= $this->te('files.detail_storage') ?></th>
                        <th class="w-px"></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($missing as $file): ?>
                        <tr>
                            <td>
                                <a href="<?= $this->e($this->url('/admin/files/' . $file['id'])) ?>" class="text-sm font-medium text-slate-900 hover:text-brand-700">
                                    <?= $this->e(FileItem::displayName($file)) ?>
                                </a>
                                <div class="font-mono text-xs text-slate-500">/<?= $this->e((string) $file['alias']) ?></div>
                            </td>
                            <td class="hidden sm:table-cell">
                                <span class="block max-w-[20rem] truncate font-mono text-xs text-slate-500"><?= $this->e((string) $file['path']) ?></span>
                            </td>
                            <td>
                                <a href="<?= $this->e($this->url('/admin/files/' . $file['id'])) ?>" class="btn btn-ghost btn-sm">
                                    <?= $this->te('common.details') ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>
