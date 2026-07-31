<?php

/**
 * Password gate. Deliberately reveals nothing about the file itself.
 *
 * @var App\Core\View $this
 * @var array<string, mixed> $file
 * @var string|null $passwordError
 */

$alias = (string) $file['alias'];
?>
<section class="aurora relative overflow-hidden">
    <div class="grid-lines pointer-events-none absolute inset-x-0 top-0 h-64"></div>

    <div class="relative mx-auto flex w-full max-w-md flex-col px-4 py-16 sm:py-24">
        <div class="card animate-rise p-7 shadow-soft sm:p-8">
            <span class="flex size-12 items-center justify-center rounded-2xl bg-brand-50 text-brand-600">
                <?= $this->icon('lock', 'size-6') ?>
            </span>

            <h1 class="mt-5 text-xl font-semibold tracking-tight text-slate-900">
                <?= $this->te('file.password_required') ?>
            </h1>
            <p class="mt-2 text-sm leading-relaxed text-slate-600">
                <?= $this->te('file.password_hint') ?>
            </p>

            <?php if ($passwordError !== null): ?>
                <div class="alert alert-error mt-5">
                    <?= $this->icon('x-circle') ?>
                    <div class="flex-1"><?= $this->e($passwordError) ?></div>
                </div>
            <?php endif; ?>

            <form method="post" action="<?= $this->e($this->url('/f/' . $alias)) ?>" class="mt-6 space-y-4">
                <?= $this->csrf() ?>

                <div x-data="revealable">
                    <label class="label" for="password"><?= $this->te('file.password_label') ?></label>
                    <div class="relative">
                        <input
                            x-ref="input"
                            type="password"
                            name="password"
                            id="password"
                            class="input pr-11<?= $passwordError !== null ? ' input-error' : '' ?>"
                            autocomplete="off"
                            autofocus
                            required
                        >
                        <button
                            type="button"
                            class="absolute inset-y-0 right-0 flex w-11 items-center justify-center text-slate-400 hover:text-slate-600"
                            @click="toggle()"
                            :aria-label="shown ? 'Hide' : 'Show'"
                        >
                            <span x-show="!shown"><?= $this->icon('eye', 'size-4') ?></span>
                            <span x-cloak x-show="shown"><?= $this->icon('eye-off', 'size-4') ?></span>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-full">
                    <?= $this->icon('unlock') ?>
                    <?= $this->te('file.password_submit') ?>
                </button>
            </form>
        </div>

        <p class="mt-6 text-center text-xs text-slate-500">
            <?= $this->te('home.no_listing_text') ?>
        </p>
    </div>
</section>
