<?php

/**
 * Public download page for one file.
 *
 * @var App\Core\View $this
 * @var array<string, mixed> $file
 * @var string $previewKind
 * @var bool $canPreview
 * @var bool $thumbnail
 */

use App\Models\FileItem;
use App\Models\User;
use App\Support\FileTypes;

$alias = (string) $file['alias'];
$displayName = FileItem::displayName($file);
$extension = strtolower((string) ($file['extension'] ?? ''));
$category = FileTypes::category($extension);
$shareUrl = $this->absolute('/' . $alias);
$downloadUrl = $this->url('/d/' . $alias);
$previewUrl = $this->url('/p/' . $alias);
$remaining = $file['max_downloads'] === null
    ? null
    : max(0, (int) $file['max_downloads'] - (int) $file['download_count']);
$showOwner = $this->setting('show_file_owner') === '1' && !empty($file['owner_username']);
?>
<section class="aurora relative overflow-hidden">
    <div class="grid-lines pointer-events-none absolute inset-x-0 top-0 h-72"></div>

    <div class="container-narrow relative py-12 sm:py-16">
        <div class="card animate-rise overflow-hidden shadow-soft">
            <!-- Header -->
            <div class="flex flex-col gap-5 border-b border-slate-200 p-6 sm:flex-row sm:items-start sm:p-8">
                <?php $this->partial('partials/file-tile', ['file' => $file, 'tileClass' => 'size-16 text-sm']); ?>

                <div class="min-w-0 flex-1">
                    <p class="text-xs font-medium tracking-wide text-brand-600 uppercase">
                        <?= $this->te('file.ready') ?>
                    </p>
                    <h1 class="mt-1.5 text-2xl font-semibold tracking-tight text-slate-900 break-words sm:text-3xl">
                        <?= $this->e($displayName) ?>
                    </h1>

                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <span class="badge badge-neutral"><?= $this->e(FileTypes::label($category)) ?></span>
                        <span class="badge badge-outline"><?= $this->e($this->bytes((int) $file['size_bytes'])) ?></span>
                        <?php if (FileItem::hasPassword($file)): ?>
                            <span class="badge badge-success"><?= $this->icon('unlock') ?><?= $this->te('common.protected') ?></span>
                        <?php endif; ?>
                        <?php if ($file['expires_at'] !== null): ?>
                            <span class="badge badge-warning">
                                <?= $this->icon('timer') ?>
                                <?= $this->e(App\Support\Formatter::until((string) $file['expires_at'])) ?>
                            </span>
                        <?php endif; ?>
                        <?php if ($remaining !== null): ?>
                            <span class="badge badge-outline">
                                <?= $this->icon('download') ?>
                                <?= $this->te('file.remaining_downloads') ?>: <?= $this->e($this->number($remaining)) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Description -->
            <?php if (!empty($file['description'])): ?>
                <div class="border-b border-slate-200 bg-slate-50/60 px-6 py-5 sm:px-8">
                    <p class="text-sm leading-relaxed whitespace-pre-line text-slate-700">
                        <?= $this->e((string) $file['description']) ?>
                    </p>
                </div>
            <?php endif; ?>

            <!-- Preview -->
            <?php if ($canPreview): ?>
                <div class="border-b border-slate-200 px-6 py-6 sm:px-8">
                    <?php if ($previewKind === 'image'): ?>
                        <a href="<?= $this->e($previewUrl) ?>" target="_blank" rel="noopener"
                           class="block overflow-hidden rounded-xl border border-slate-300 bg-slate-50">
                            <img
                                src="<?= $this->e($thumbnail ? $this->url('/t/' . $alias, ['s' => 1000]) : $previewUrl) ?>"
                                alt="<?= $this->e($displayName) ?>"
                                loading="lazy"
                                decoding="async"
                                class="mx-auto max-h-[26rem] w-auto object-contain"
                            >
                        </a>
                    <?php else: ?>
                        <div x-data="{ show: false }">
                            <button type="button" class="btn btn-secondary w-full sm:w-auto" @click="show = !show">
                                <?= $this->icon('eye') ?>
                                <span x-text="show ? <?= $this->e(json_encode($this->t('file.hide_preview'))) ?> : <?= $this->e(json_encode($this->t('file.show_preview'))) ?>"></span>
                            </button>

                            <template x-if="show">
                                <div class="mt-4 overflow-hidden rounded-xl border border-slate-300 bg-slate-900/[0.02]">
                                    <?php if ($previewKind === 'video'): ?>
                                        <video controls preload="metadata" class="w-full max-h-[28rem] bg-black" src="<?= $this->e($previewUrl) ?>"></video>
                                    <?php elseif ($previewKind === 'audio'): ?>
                                        <audio controls preload="metadata" class="w-full p-4" src="<?= $this->e($previewUrl) ?>"></audio>
                                    <?php else: ?>
                                        <iframe
                                            src="<?= $this->e($previewUrl) ?>"
                                            title="<?= $this->e($displayName) ?>"
                                            class="h-[28rem] w-full bg-white"
                                            referrerpolicy="no-referrer"
                                        ></iframe>
                                    <?php endif; ?>
                                </div>
                            </template>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Actions -->
            <div class="flex flex-col gap-4 p-6 sm:p-8">
                <a href="<?= $this->e($downloadUrl) ?>" class="btn btn-primary btn-lg w-full">
                    <?= $this->icon('download') ?>
                    <?= $this->te('file.download_file') ?>
                    <span class="text-white/70">· <?= $this->e($this->bytes((int) $file['size_bytes'])) ?></span>
                </a>

                <div x-data="copyable(<?= $this->e(json_encode($shareUrl)) ?>)" class="flex items-center gap-2">
                    <div class="code-line min-w-0 flex-1">
                        <?= $this->icon('link', 'size-4 shrink-0 text-slate-400') ?>
                        <span class="truncate"><?= $this->e($shareUrl) ?></span>
                    </div>
                    <button type="button" class="btn btn-secondary btn-icon shrink-0" @click="copy()" aria-label="<?= $this->te('common.copy_link') ?>">
                        <span x-show="!copied"><?= $this->icon('copy', 'size-4') ?></span>
                        <span x-cloak x-show="copied" class="text-emerald-600"><?= $this->icon('check', 'size-4') ?></span>
                    </button>
                </div>
            </div>

            <!-- Meta -->
            <dl class="grid grid-cols-2 gap-px border-t border-slate-200 bg-slate-200 sm:grid-cols-4">
                <div class="bg-white px-5 py-4">
                    <dt class="text-[11px] font-medium tracking-wide text-slate-500 uppercase"><?= $this->te('file.file_size') ?></dt>
                    <dd class="mt-1 text-sm font-medium text-slate-900"><?= $this->e($this->bytes((int) $file['size_bytes'])) ?></dd>
                </div>
                <div class="bg-white px-5 py-4">
                    <dt class="text-[11px] font-medium tracking-wide text-slate-500 uppercase"><?= $this->te('file.file_type') ?></dt>
                    <dd class="mt-1 text-sm font-medium text-slate-900"><?= $this->e($extension === '' ? FileTypes::label($category) : mb_strtoupper($extension)) ?></dd>
                </div>
                <div class="bg-white px-5 py-4">
                    <dt class="text-[11px] font-medium tracking-wide text-slate-500 uppercase"><?= $this->te('file.uploaded') ?></dt>
                    <dd class="mt-1 text-sm font-medium text-slate-900" title="<?= $this->e($this->date((string) $file['created_at'])) ?>">
                        <?= $this->e($this->ago((string) $file['created_at'])) ?>
                    </dd>
                </div>
                <div class="bg-white px-5 py-4">
                    <dt class="text-[11px] font-medium tracking-wide text-slate-500 uppercase"><?= $this->te('file.downloads_so_far') ?></dt>
                    <dd class="mt-1 text-sm font-medium text-slate-900"><?= $this->e($this->number((int) $file['download_count'])) ?></dd>
                </div>
            </dl>
        </div>

        <p class="mt-6 flex items-start gap-2 px-2 text-xs leading-relaxed text-slate-500">
            <?= $this->icon('shield', 'size-4 shrink-0 mt-px text-slate-400') ?>
            <span>
                <?= $this->te('file.scan_note') ?>
                <?php if ($showOwner): ?>
                    · <?= $this->te('file.shared_by', ['name' => (string) ($file['owner_display_name'] ?: $file['owner_username'])]) ?>
                <?php endif; ?>
            </span>
        </p>
    </div>
</section>
