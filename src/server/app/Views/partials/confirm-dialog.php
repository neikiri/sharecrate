<?php

/**
 * Shared confirmation modal used by every [data-confirm] form or link.
 *
 * @var App\Core\View $this
 */
?>
<div id="confirm-dialog" x-data="confirmable" x-cloak x-show="open" class="fixed inset-0 z-[70]">
    <div class="absolute inset-0 bg-slate-900/45 backdrop-blur-sm" @click="cancel()"></div>

    <div class="relative flex min-h-full items-center justify-center p-4">
        <div
            class="card w-full max-w-md animate-pop p-6 shadow-pop"
            role="dialog"
            aria-modal="true"
            @keydown.escape.window="cancel()"
        >
            <div class="flex items-start gap-4">
                <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-rose-50 text-rose-600">
                    <?= $this->icon('alert-triangle') ?>
                </span>
                <div class="min-w-0 flex-1">
                    <h2 class="text-base font-semibold text-slate-900" x-text="message"></h2>
                    <p class="mt-1.5 text-sm text-slate-500" x-show="detail" x-text="detail"></p>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <button type="button" class="btn btn-secondary" @click="cancel()"><?= $this->te('common.cancel') ?></button>
                <button type="button" class="btn btn-danger" @click="accept()" x-text="submitLabel"></button>
            </div>
        </div>
    </div>
</div>
