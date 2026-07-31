<?php

/**
 * Client side toast stack (filled by window.toast()).
 *
 * @var App\Core\View $this
 */
?>
<div class="pointer-events-none fixed inset-x-0 bottom-4 z-[60] flex flex-col items-center gap-2 px-4 sm:bottom-6">
    <template x-for="item in $store.toasts.items" :key="item.id">
        <div class="toast pointer-events-auto animate-pop" role="status">
            <span
                class="flex size-6 shrink-0 items-center justify-center rounded-full"
                :class="item.type === 'error' ? 'bg-rose-100 text-rose-600' : 'bg-emerald-100 text-emerald-600'"
            >
                <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path x-show="item.type !== 'error'" d="m5 12.8 4.3 4.4L19 7"/>
                    <path x-cloak x-show="item.type === 'error'" d="M6 6l12 12M18 6 6 18"/>
                </svg>
            </span>
            <span x-text="item.message"></span>
            <button type="button" class="text-slate-400 hover:text-slate-600" @click="$store.toasts.dismiss(item.id)" aria-label="<?= $this->te('common.close') ?>">
                <?= $this->icon('x', 'size-4') ?>
            </button>
        </div>
    </template>
</div>
