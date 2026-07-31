<?php

/**
 * Browser upload with drag & drop and per file progress.
 *
 * @var App\Core\View $this
 * @var array{upload_max: int, post_max: int, effective: int, execution_time: int} $limits
 * @var bool $storageWritable
 * @var string $storagePath
 * @var int|null $quota
 * @var int $quotaUsed
 * @var int $defaultExpiryDays
 */

use App\Core\Csrf;

$this->title = $this->t('upload.title');

$config = [
    'endpoint' => $this->url('/admin/upload'),
    'csrf' => Csrf::token(),
    'maxBytes' => $limits['effective'],
    'baseUrl' => $this->absolute('/'),
];
?>
<div class="space-y-6" x-data="uploader(<?= $this->e(json_encode($config, JSON_UNESCAPED_SLASHES)) ?>)">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h2 class="text-2xl font-semibold tracking-tight text-slate-900"><?= $this->te('upload.title') ?></h2>
            <p class="mt-1 text-sm text-slate-500"><?= $this->te('upload.subtitle') ?></p>
        </div>
        <a href="<?= $this->e($this->url('/admin/import')) ?>" class="btn btn-secondary">
            <?= $this->icon('folder-down') ?>
            <?= $this->te('nav.import') ?>
        </a>
    </div>

    <?php if (!$storageWritable): ?>
        <div class="alert alert-error">
            <?= $this->icon('alert-triangle') ?>
            <div class="flex-1"><?= $this->te('import.storage_unwritable', ['path' => $storagePath]) ?></div>
        </div>
    <?php endif; ?>

    <?php if ($quota !== null && $quota > 0): ?>
        <div class="card card-pad">
            <div class="flex items-center justify-between text-sm">
                <span class="font-medium text-slate-700"><?= $this->te('users.quota') ?></span>
                <span class="text-slate-500">
                    <?= $this->te('users.quota_used', ['used' => $this->bytes($quotaUsed), 'total' => $this->bytes($quota)]) ?>
                </span>
            </div>
            <div class="progress mt-2">
                <span style="width: <?= (int) min(100, round($quotaUsed / max(1, $quota) * 100)) ?>%"></span>
            </div>
        </div>
    <?php endif; ?>

    <div class="grid gap-6 lg:grid-cols-3">
        <!-- Dropzone + queue -->
        <div class="space-y-6 lg:col-span-2">
            <div
                class="dropzone"
                :class="dragging ? 'dropzone-active' : ''"
                @dragover.prevent="dragging = true"
                @dragleave.prevent="dragging = false"
                @drop.prevent="onDrop($event)"
            >
                <input type="file" x-ref="input" multiple class="hidden" @change="onChange($event)">

                <span class="flex size-14 items-center justify-center rounded-2xl bg-white text-brand-600 shadow-card">
                    <?= $this->icon('cloud-upload', 'size-6') ?>
                </span>

                <div>
                    <p class="text-base font-medium text-slate-800"><?= $this->te('upload.dropzone') ?></p>
                    <p class="mt-1 text-sm text-slate-500"><?= $this->te('upload.dropzone_or') ?></p>
                </div>

                <button type="button" class="btn btn-primary" @click="pick()">
                    <?= $this->icon('plus') ?>
                    <?= $this->te('upload.browse') ?>
                </button>

                <p class="max-w-md text-xs text-slate-400">
                    <?= $this->te('upload.limit_note', ['size' => $this->bytes($limits['effective'])]) ?>
                </p>
            </div>

            <!-- Queue -->
            <div x-cloak x-show="queue.length > 0" class="card">
                <div class="card-header">
                    <h3 class="card-title"><?= $this->te('upload.queue') ?> <span x-text="`(${queue.length})`"></span></h3>
                    <div class="flex gap-2">
                        <button type="button" class="btn btn-ghost btn-sm" @click="reset()" :disabled="busy">
                            <?= $this->icon('x') ?><?= $this->te('upload.clear') ?>
                        </button>
                        <button type="button" class="btn btn-primary btn-sm" @click="start()" :disabled="busy || pending.length === 0">
                            <span x-show="!busy"><?= $this->te('upload.start') ?></span>
                            <span x-cloak x-show="busy"><?= $this->te('upload.uploading') ?></span>
                        </button>
                    </div>
                </div>

                <ul class="divide-y divide-slate-100">
                    <template x-for="item in queue" :key="item.id">
                        <li class="px-5 py-4 sm:px-6">
                            <div class="flex items-center gap-3">
                                <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-500">
                                    <?= $this->icon('file', 'size-4') ?>
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-slate-900" x-text="item.name"></p>
                                    <p class="text-xs text-slate-500">
                                        <span x-text="item.sizeLabel"></span>
                                        <span x-cloak x-show="item.error" class="text-rose-600"> · <span x-text="item.error"></span></span>
                                    </p>
                                </div>
                                <div class="flex shrink-0 items-center gap-2">
                                    <span class="text-xs font-medium text-slate-500 tabular-nums" x-show="item.status === 'uploading'" x-text="`${item.progress}%`"></span>
                                    <span x-cloak x-show="item.status === 'done'" class="text-emerald-600"><?= $this->icon('check-circle', 'size-4') ?></span>
                                    <span x-cloak x-show="item.status === 'error'" class="text-rose-600"><?= $this->icon('x-circle', 'size-4') ?></span>
                                    <button type="button" class="btn btn-ghost btn-icon" x-show="item.status === 'pending'" @click="remove(item.id)">
                                        <?= $this->icon('x', 'size-4') ?>
                                    </button>
                                </div>
                            </div>
                            <div class="progress mt-2.5" x-show="item.status === 'uploading' || item.status === 'done'">
                                <span :style="`width: ${item.progress}%`"></span>
                            </div>
                        </li>
                    </template>
                </ul>
            </div>

            <!-- Finished uploads -->
            <div x-cloak x-show="done.length > 0" class="card">
                <div class="card-header">
                    <h3 class="card-title"><?= $this->te('upload.done_title') ?></h3>
                    <a href="<?= $this->e($this->url('/admin/files')) ?>" class="btn btn-ghost btn-sm">
                        <?= $this->te('nav.files') ?><?= $this->icon('arrow-up-right') ?>
                    </a>
                </div>

                <ul class="divide-y divide-slate-100">
                    <template x-for="item in done" :key="item.id">
                        <li class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:px-6">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-slate-900" x-text="item.name"></p>
                                <div class="code-line mt-1.5">
                                    <span class="truncate" x-text="item.url"></span>
                                </div>
                            </div>
                            <div class="flex shrink-0 gap-2">
                                <button type="button" class="btn btn-secondary btn-sm" @click="copyLink(item.url)">
                                    <?= $this->icon('copy') ?><?= $this->te('common.copy') ?>
                                </button>
                                <a :href="item.admin_url" class="btn btn-ghost btn-sm">
                                    <?= $this->icon('pencil') ?><?= $this->te('common.edit') ?>
                                </a>
                            </div>
                        </li>
                    </template>
                </ul>
            </div>
        </div>

        <!-- Options -->
        <div class="space-y-6">
            <div class="card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title"><?= $this->te('upload.options') ?></h3>
                        <p class="card-sub"><?= $this->te('upload.options_hint') ?></p>
                    </div>
                </div>

                <div class="space-y-4 p-5 sm:p-6">
                    <div x-show="queue.length <= 1">
                        <label class="label" for="upload-alias"><?= $this->te('files.alias') ?></label>
                        <input type="text" id="upload-alias" class="input font-mono" x-model="options.alias"
                               placeholder="<?= $this->te('common.optional') ?>">
                        <p class="help"><?= $this->te('upload.alias_single_hint') ?></p>
                    </div>

                    <div x-data="revealable">
                        <label class="label" for="upload-password"><?= $this->te('files.password_label') ?></label>
                        <div class="relative">
                            <input x-ref="input" type="password" id="upload-password" class="input pr-11"
                                   x-model="options.password" autocomplete="new-password"
                                   placeholder="<?= $this->te('common.optional') ?>">
                            <button type="button" class="absolute inset-y-0 right-0 flex w-11 items-center justify-center text-slate-400 hover:text-slate-600" @click="toggle()" tabindex="-1">
                                <span x-show="!shown"><?= $this->icon('eye', 'size-4') ?></span>
                                <span x-cloak x-show="shown"><?= $this->icon('eye-off', 'size-4') ?></span>
                            </button>
                        </div>
                    </div>

                    <div>
                        <label class="label" for="upload-expires"><?= $this->te('files.expires_label') ?></label>
                        <input type="datetime-local" id="upload-expires" class="input" x-model="options.expires_at">
                        <?php if ($defaultExpiryDays > 0): ?>
                            <p class="help"><?= $this->te('settings.default_expiry_days') ?>: <?= (int) $defaultExpiryDays ?></p>
                        <?php endif; ?>
                    </div>

                    <div>
                        <label class="label" for="upload-max"><?= $this->te('files.max_downloads_label') ?></label>
                        <input type="number" id="upload-max" min="0" step="1" class="input" x-model="options.max_downloads" placeholder="0">
                    </div>

                    <div>
                        <label class="label" for="upload-description"><?= $this->te('files.description_label') ?></label>
                        <textarea id="upload-description" class="textarea" rows="3" x-model="options.description"></textarea>
                    </div>
                </div>
            </div>

            <div class="card card-pad">
                <h3 class="text-sm font-semibold text-slate-900"><?= $this->te('nav.import') ?></h3>
                <p class="mt-2 text-sm leading-relaxed text-slate-600"><?= $this->te('upload.limit_note_ftp') ?></p>
                <div class="code-line mt-3">
                    <?= $this->icon('server', 'size-4 shrink-0 text-slate-400') ?>
                    <span class="truncate"><?= $this->e($storagePath) ?></span>
                </div>
                <dl class="mt-4 space-y-1.5 text-xs text-slate-500">
                    <div class="flex justify-between gap-2">
                        <dt>upload_max_filesize</dt>
                        <dd class="font-mono"><?= $this->e($this->bytes($limits['upload_max'])) ?></dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt>post_max_size</dt>
                        <dd class="font-mono"><?= $this->e($this->bytes($limits['post_max'])) ?></dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt>max_execution_time</dt>
                        <dd class="font-mono"><?= (int) $limits['execution_time'] ?> s</dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>
</div>
