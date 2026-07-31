<?php

/**
 * File detail: settings, link, statistics and download log.
 *
 * @var App\Core\View $this
 * @var array<string, mixed> $file
 * @var array<int, array<string, mixed>> $downloads
 * @var array<int, array{date: string, label: string, count: int}> $series
 * @var int $uniqueVisitors
 * @var array<int, array{country: ?string, total: int}> $countries
 * @var array<int, array{id: int, username: string}> $owners
 */

use App\Core\Geo;
use App\Models\FileItem;
use App\Support\FileTypes;
use App\Support\Formatter;
use App\Support\Storage;
use App\Support\UserAgent;

$this->title = FileItem::displayName($file);

$id = (int) $file['id'];
$alias = (string) $file['alias'];
$state = FileItem::state($file);
$shareUrl = $this->absolute('/' . $alias);
$directUrl = $this->absolute('/d/' . $alias);
$aliasBase = rtrim(preg_replace('#^https?://#', '', $this->absolute('/')) ?: '', '/');
$extension = strtolower((string) ($file['extension'] ?? ''));
$hasPassword = FileItem::hasPassword($file);

$stateBadge = [
    'active' => ['badge-success', 'check-circle'],
    'disabled' => ['badge-neutral', 'ban'],
    'expired' => ['badge-warning', 'timer'],
    'limit' => ['badge-warning', 'download'],
    'missing' => ['badge-danger', 'alert-triangle'],
][$state] ?? ['badge-neutral', 'info'];
?>
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="flex min-w-0 items-start gap-4">
            <a href="<?= $this->e($this->url('/admin/files')) ?>" class="btn btn-secondary btn-icon mt-0.5" title="<?= $this->te('common.back') ?>">
                <?= $this->icon('arrow-left', 'size-4') ?>
            </a>
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="truncate text-xl font-semibold tracking-tight text-slate-900 sm:text-2xl">
                        <?= $this->e(FileItem::displayName($file)) ?>
                    </h2>
                    <span class="badge <?= $stateBadge[0] ?>">
                        <?= $this->icon($stateBadge[1]) ?><?= $this->te('files.state_' . $state) ?>
                    </span>
                    <?php if ($hasPassword): ?>
                        <span class="badge badge-brand"><?= $this->icon('lock') ?><?= $this->te('common.protected') ?></span>
                    <?php endif; ?>
                </div>
                <p class="mt-1 truncate text-sm text-slate-500">
                    <?= $this->e((string) $file['original_name']) ?> · <?= $this->e($this->bytes((int) $file['size_bytes'])) ?>
                </p>
            </div>
        </div>

        <div class="flex gap-2">
            <a href="<?= $this->e($this->url('/' . $alias)) ?>" target="_blank" rel="noopener" class="btn btn-secondary">
                <?= $this->icon('external-link') ?>
                <span class="hidden sm:inline"><?= $this->te('files.open_public') ?></span>
            </a>
            <a href="<?= $this->e($this->url('/d/' . $alias)) ?>" class="btn btn-primary">
                <?= $this->icon('download') ?>
                <span class="hidden sm:inline"><?= $this->te('common.download') ?></span>
            </a>
        </div>
    </div>

    <?php if ($state === 'missing'): ?>
        <div class="alert alert-error">
            <?= $this->icon('alert-triangle') ?>
            <div class="flex-1"><?= $this->te('files.missing_warning', ['path' => (string) $file['path']]) ?></div>
        </div>
    <?php endif; ?>

    <div class="grid gap-6 xl:grid-cols-3">
        <!-- Left: settings -->
        <div class="space-y-6 xl:col-span-2">
            <form method="post" action="<?= $this->e($this->url('/admin/files/' . $id)) ?>" class="card">
                <?= $this->csrf() ?>
                <div class="card-header">
                    <div>
                        <h3 class="card-title"><?= $this->te('files.detail_title') ?></h3>
                        <p class="card-sub"><?= $this->te('files.subtitle') ?></p>
                    </div>
                </div>

                <div class="space-y-5 p-5 sm:p-6">
                    <!-- Alias -->
                    <div x-data="aliasField(<?= $this->e(json_encode($alias)) ?>, <?= $this->e(json_encode($aliasBase)) ?>)">
                        <label class="label" for="alias"><?= $this->te('files.alias') ?></label>
                        <div class="flex items-stretch">
                            <span class="hidden items-center rounded-l-xl border border-r-0 border-slate-300 bg-slate-50 px-3 font-mono text-xs text-slate-500 sm:flex">
                                <?= $this->e($aliasBase) ?>/
                            </span>
                            <input
                                type="text"
                                name="alias"
                                id="alias"
                                x-model="alias"
                                @blur="normalise()"
                                class="input font-mono sm:rounded-l-none<?= $this->hasError('alias') ? ' input-error' : '' ?>"
                                value="<?= $this->e($alias) ?>"
                                maxlength="160"
                                required
                            >
                        </div>
                        <?php if ($this->hasError('alias')): ?>
                            <p class="error-text"><?= $this->icon('alert-triangle', 'size-3.5') ?><?= $this->e((string) $this->error('alias')) ?></p>
                        <?php else: ?>
                            <p class="help"><?= $this->te('files.alias_hint', ['base' => $aliasBase]) ?></p>
                        <?php endif; ?>
                    </div>

                    <!-- Title -->
                    <div>
                        <label class="label" for="title"><?= $this->te('files.title_label') ?> <span class="font-normal text-slate-400">(<?= $this->te('common.optional') ?>)</span></label>
                        <input type="text" name="title" id="title" class="input" maxlength="190"
                               value="<?= $this->e((string) ($file['title'] ?? '')) ?>"
                               placeholder="<?= $this->e((string) $file['original_name']) ?>">
                        <p class="help"><?= $this->te('files.title_hint') ?></p>
                    </div>

                    <!-- Description -->
                    <div>
                        <label class="label" for="description"><?= $this->te('files.description_label') ?></label>
                        <textarea name="description" id="description" class="textarea" rows="3" maxlength="5000"><?= $this->e((string) ($file['description'] ?? '')) ?></textarea>
                        <p class="help"><?= $this->te('files.description_hint') ?></p>
                    </div>

                    <div class="grid gap-5 sm:grid-cols-2">
                        <!-- Expiry -->
                        <div>
                            <label class="label" for="expires_at"><?= $this->te('files.expires_label') ?></label>
                            <input type="datetime-local" name="expires_at" id="expires_at"
                                   class="input<?= $this->hasError('expires_at') ? ' input-error' : '' ?>"
                                   value="<?= $this->e(Formatter::dateTimeLocal($file['expires_at'] === null ? null : (string) $file['expires_at'])) ?>">
                            <p class="help"><?= $this->te('files.expires_hint') ?></p>
                        </div>

                        <!-- Download limit -->
                        <div>
                            <label class="label" for="max_downloads"><?= $this->te('files.max_downloads_label') ?></label>
                            <input type="number" name="max_downloads" id="max_downloads" min="0" step="1"
                                   class="input<?= $this->hasError('max_downloads') ? ' input-error' : '' ?>"
                                   value="<?= $this->e((string) ($file['max_downloads'] ?? '')) ?>" placeholder="0">
                            <p class="help"><?= $this->te('files.max_downloads_hint') ?></p>
                        </div>
                    </div>

                    <?php if ($owners !== []): ?>
                        <div>
                            <label class="label" for="owner_id"><?= $this->te('common.owner') ?></label>
                            <select name="owner_id" id="owner_id" class="select">
                                <option value=""><?= $this->te('common.none') ?></option>
                                <?php foreach ($owners as $owner): ?>
                                    <option value="<?= (int) $owner['id'] ?>" <?= (int) ($file['owner_id'] ?? 0) === (int) $owner['id'] ? 'selected' : '' ?>>
                                        <?= $this->e((string) $owner['username']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <!-- Toggles -->
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="field-group">
                            <input type="checkbox" name="status" value="1" class="checkbox mt-0.5" <?= ($file['status'] ?? 'active') === 'active' ? 'checked' : '' ?>>
                            <span class="min-w-0">
                                <span class="block text-sm font-medium text-slate-800"><?= $this->te('files.status_label') ?></span>
                                <span class="mt-0.5 block text-xs text-slate-500"><?= $this->te('files.status_hint') ?></span>
                            </span>
                        </label>

                        <label class="field-group">
                            <input type="checkbox" name="allow_preview" value="1" class="checkbox mt-0.5" <?= (int) $file['allow_preview'] === 1 ? 'checked' : '' ?>>
                            <span class="min-w-0">
                                <span class="block text-sm font-medium text-slate-800"><?= $this->te('files.allow_preview_label') ?></span>
                                <span class="mt-0.5 block text-xs text-slate-500"><?= $this->te('files.allow_preview_hint') ?></span>
                            </span>
                        </label>
                    </div>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 px-5 py-4 sm:px-6">
                    <button type="submit" name="action" value="refresh" class="btn btn-ghost btn-sm">
                        <?= $this->icon('refresh') ?>
                        <?= $this->te('files.refresh_metadata') ?>
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <?= $this->icon('check') ?>
                        <?= $this->te('common.save_changes') ?>
                    </button>
                </div>
            </form>

            <!-- Password -->
            <form method="post" action="<?= $this->e($this->url('/admin/files/' . $id . '/password')) ?>" class="card">
                <?= $this->csrf() ?>
                <div class="card-header">
                    <div>
                        <h3 class="card-title"><?= $this->te('files.password_label') ?></h3>
                        <p class="card-sub">
                            <?= $hasPassword ? $this->te('files.password_set') : $this->te('files.password_hint') ?>
                        </p>
                    </div>
                    <?php if ($hasPassword): ?>
                        <span class="badge badge-brand"><?= $this->icon('lock') ?><?= $this->te('common.protected') ?></span>
                    <?php endif; ?>
                </div>

                <div class="flex flex-col gap-3 p-5 sm:flex-row sm:items-start sm:p-6">
                    <div class="flex-1" x-data="revealable">
                        <div class="relative">
                            <input x-ref="input" type="password" name="password"
                                   class="input pr-11<?= $this->hasError('password') ? ' input-error' : '' ?>"
                                   autocomplete="new-password"
                                   placeholder="<?= $hasPassword ? $this->te('files.password_change') : $this->te('files.password_label') ?>">
                            <button type="button" class="absolute inset-y-0 right-0 flex w-11 items-center justify-center text-slate-400 hover:text-slate-600" @click="toggle()" tabindex="-1">
                                <span x-show="!shown"><?= $this->icon('eye', 'size-4') ?></span>
                                <span x-cloak x-show="shown"><?= $this->icon('eye-off', 'size-4') ?></span>
                            </button>
                        </div>
                        <?php if ($this->hasError('password')): ?>
                            <p class="error-text"><?= $this->icon('alert-triangle', 'size-3.5') ?><?= $this->e((string) $this->error('password')) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="flex gap-2">
                        <button type="submit" class="btn btn-primary"><?= $this->icon('key') ?><?= $this->te('common.save') ?></button>
                        <?php if ($hasPassword): ?>
                            <button type="submit" name="action" value="remove" class="btn btn-secondary">
                                <?= $this->icon('unlock') ?><?= $this->te('files.password_remove') ?>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </form>

            <!-- Download log -->
            <div class="card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title"><?= $this->te('files.download_log') ?></h3>
                        <p class="card-sub">
                            <?= $this->e($this->choice('common.downloads_count', (int) $file['download_count'], ['count' => $this->number((int) $file['download_count'])])) ?>
                            · <?= $this->e($this->number($uniqueVisitors)) ?> <?= $this->te('files.unique_visitors') ?>
                        </p>
                    </div>
                    <a href="<?= $this->e($this->url('/admin/downloads', ['file' => $id])) ?>" class="btn btn-ghost btn-sm">
                        <?= $this->te('common.view_all') ?><?= $this->icon('arrow-up-right') ?>
                    </a>
                </div>

                <?php if ($downloads === []): ?>
                    <?php $this->partial('partials/empty', [
                        'icon' => 'download',
                        'title' => $this->t('files.download_log_empty'),
                    ]); ?>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="table">
                            <thead>
                            <tr>
                                <th><?= $this->te('downloads.when') ?></th>
                                <th><?= $this->te('downloads.visitor') ?></th>
                                <th class="hidden sm:table-cell"><?= $this->te('downloads.location') ?></th>
                                <th class="hidden md:table-cell"><?= $this->te('downloads.device') ?></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($downloads as $row): ?>
                                <tr>
                                    <td class="whitespace-nowrap text-slate-600" title="<?= $this->e($this->date((string) $row['created_at'])) ?>">
                                        <?= $this->e($this->ago((string) $row['created_at'])) ?>
                                    </td>
                                    <td class="font-mono text-xs text-slate-600">
                                        <?= $this->e((string) ($row['ip'] ?? '—')) ?>
                                    </td>
                                    <td class="hidden sm:table-cell">
                                        <span class="flex items-center gap-1.5 text-slate-600">
                                            <span><?= Geo::flag(is_string($row['country']) ? $row['country'] : null) ?></span>
                                            <span class="truncate"><?= $this->e((string) ($row['city'] ?: Geo::name(is_string($row['country']) ? $row['country'] : null))) ?></span>
                                        </span>
                                    </td>
                                    <td class="hidden md:table-cell text-slate-600">
                                        <span class="flex items-center gap-1.5">
                                            <?= $this->icon(UserAgent::platformIcon(is_string($row['platform']) ? $row['platform'] : null), 'size-3.5 text-slate-400') ?>
                                            <?= $this->e(trim((string) ($row['browser'] ?? '') . ' · ' . (string) ($row['platform'] ?? ''), ' ·')) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right: link + stats -->
        <div class="space-y-6">
            <div class="card card-pad">
                <h3 class="card-title"><?= $this->te('files.detail_link') ?></h3>

                <div class="mt-4 space-y-3">
                    <div x-data="copyable(<?= $this->e(json_encode($shareUrl)) ?>)">
                        <div class="flex items-center gap-2">
                            <div class="code-line min-w-0 flex-1"><span class="truncate"><?= $this->e($shareUrl) ?></span></div>
                            <button type="button" class="btn btn-secondary btn-icon shrink-0" @click="copy()" title="<?= $this->te('common.copy_link') ?>">
                                <span x-show="!copied"><?= $this->icon('copy', 'size-4') ?></span>
                                <span x-cloak x-show="copied" class="text-emerald-600"><?= $this->icon('check', 'size-4') ?></span>
                            </button>
                        </div>
                    </div>

                    <div x-data="copyable(<?= $this->e(json_encode($directUrl)) ?>)">
                        <p class="mb-1.5 text-xs font-medium text-slate-500"><?= $this->te('file.direct_link') ?></p>
                        <div class="flex items-center gap-2">
                            <div class="code-line min-w-0 flex-1"><span class="truncate"><?= $this->e($directUrl) ?></span></div>
                            <button type="button" class="btn btn-secondary btn-icon shrink-0" @click="copy()" title="<?= $this->te('common.copy_link') ?>">
                                <span x-show="!copied"><?= $this->icon('copy', 'size-4') ?></span>
                                <span x-cloak x-show="copied" class="text-emerald-600"><?= $this->icon('check', 'size-4') ?></span>
                            </button>
                        </div>
                    </div>
                </div>

                <dl class="mt-5 space-y-3 border-t border-slate-200 pt-5 text-sm">
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-500"><?= $this->te('files.detail_storage') ?></dt>
                        <dd class="min-w-0 truncate text-right font-mono text-xs text-slate-700" title="<?= $this->e((string) $file['path']) ?>">
                            <?= $this->e((string) $file['path']) ?>
                        </dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-500"><?= $this->te('files.detail_mime') ?></dt>
                        <dd class="text-right font-mono text-xs text-slate-700"><?= $this->e((string) ($file['mime_type'] ?? FileTypes::mime($extension))) ?></dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-500"><?= $this->te('files.detail_source') ?></dt>
                        <dd class="text-right text-slate-700"><?= $this->te('files.source_' . (string) $file['source']) ?></dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-500"><?= $this->te('common.created') ?></dt>
                        <dd class="text-right text-slate-700"><?= $this->e($this->date((string) $file['created_at'])) ?></dd>
                    </div>
                    <?php if ($file['expires_at'] !== null): ?>
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-500"><?= $this->te('common.expires') ?></dt>
                            <dd class="text-right text-slate-700"><?= $this->e($this->date((string) $file['expires_at'])) ?></dd>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($file['owner_username'])): ?>
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-500"><?= $this->te('common.owner') ?></dt>
                            <dd class="text-right text-slate-700"><?= $this->e((string) $file['owner_username']) ?></dd>
                        </div>
                    <?php endif; ?>
                </dl>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><?= $this->te('dashboard.chart_title') ?></h3>
                </div>
                <div class="p-5">
                    <?php $this->partial('partials/bar-chart', ['series' => $series]); ?>

                    <?php if ($countries !== []): ?>
                        <ul class="mt-5 space-y-2 border-t border-slate-200 pt-4 text-sm">
                            <?php foreach ($countries as $country): ?>
                                <li class="flex items-center justify-between gap-2">
                                    <span class="flex min-w-0 items-center gap-2">
                                        <span><?= Geo::flag($country['country']) ?></span>
                                        <span class="truncate text-slate-600"><?= $this->e(Geo::name($country['country'])) ?></span>
                                    </span>
                                    <span class="font-medium text-slate-900 tabular-nums"><?= $this->e($this->number($country['total'])) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Danger zone -->
            <form
                method="post"
                action="<?= $this->e($this->url('/admin/files/' . $id . '/delete')) ?>"
                class="card border-rose-200 p-5"
                data-confirm="<?= $this->te('files.delete_confirm', ['name' => FileItem::displayName($file)]) ?>"
                data-confirm-detail="<?= $this->te('files.delete_confirm_detail') ?>"
                data-confirm-label="<?= $this->te('common.delete') ?>"
            >
                <?= $this->csrf() ?>
                <h3 class="text-sm font-semibold text-rose-700"><?= $this->te('common.delete') ?></h3>

                <label class="mt-3 flex cursor-pointer items-start gap-2.5 text-sm text-slate-600">
                    <input type="checkbox" name="delete_file" value="1" class="checkbox mt-0.5" checked>
                    <span><?= $this->te('files.delete_file_too') ?></span>
                </label>

                <button type="submit" class="btn btn-danger mt-4 w-full">
                    <?= $this->icon('trash') ?>
                    <?= $this->te('common.delete') ?>
                </button>
            </form>
        </div>
    </div>
</div>
