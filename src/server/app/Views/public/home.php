<?php

/**
 * Landing page. Deliberately says nothing about what is stored here.
 *
 * @var App\Core\View $this
 */

$user = $this->user();
$tagline = $this->setting('site_tagline');

$features = [
    ['icon' => 'link', 'title' => $this->t('home.feature_links_title'), 'text' => $this->t('home.feature_links_text')],
    ['icon' => 'lock', 'title' => $this->t('home.feature_password_title'), 'text' => $this->t('home.feature_password_text')],
    ['icon' => 'bar-chart', 'title' => $this->t('home.feature_stats_title'), 'text' => $this->t('home.feature_stats_text')],
    ['icon' => 'server', 'title' => $this->t('home.feature_ftp_title'), 'text' => $this->t('home.feature_ftp_text')],
];

$steps = [
    ['icon' => 'cloud-upload', 'title' => $this->t('home.how_step_1_title'), 'text' => $this->t('home.how_step_1_text')],
    ['icon' => 'sliders', 'title' => $this->t('home.how_step_2_title'), 'text' => $this->t('home.how_step_2_text')],
    ['icon' => 'share', 'title' => $this->t('home.how_step_3_title'), 'text' => $this->t('home.how_step_3_text')],
];
?>
<section class="aurora relative overflow-hidden">
    <div class="grid-lines pointer-events-none absolute inset-x-0 top-0 h-[26rem]"></div>

    <div class="container-page relative py-20 sm:py-28">
        <div class="mx-auto max-w-3xl text-center">
            <span class="badge badge-brand animate-fade-in">
                <?= $this->icon('shield-check') ?>
                <?= $this->te('home.badge') ?>
            </span>

            <h1 class="mt-6 animate-rise text-4xl font-semibold tracking-tight text-slate-900 text-balance-tight sm:text-5xl lg:text-6xl">
                <?= $this->te('home.heading') ?>
            </h1>

            <p class="mx-auto mt-6 max-w-2xl animate-rise text-lg text-slate-600">
                <?= $this->te('home.subheading') ?>
            </p>

            <?php if (is_string($tagline) && $tagline !== ''): ?>
                <p class="mt-3 text-sm font-medium text-brand-600"><?= $this->e($tagline) ?></p>
            <?php endif; ?>

            <div class="mt-10 flex flex-col items-center justify-center gap-3 sm:flex-row">
                <a href="<?= $this->e($this->url($user !== null ? '/admin' : '/admin/login')) ?>" class="btn btn-primary btn-lg w-full sm:w-auto">
                    <?= $this->icon($user !== null ? 'grid' : 'log-in') ?>
                    <?= $this->te($user !== null ? 'common.dashboard' : 'home.cta_primary') ?>
                </a>
                <a href="#how" class="btn btn-secondary btn-lg w-full sm:w-auto">
                    <?= $this->icon('info') ?>
                    <?= $this->te('home.cta_secondary') ?>
                </a>
            </div>
        </div>

        <!-- Fake link preview: shows the shape of a share link without leaking anything -->
        <div class="mx-auto mt-16 max-w-2xl animate-rise">
            <div class="card glass p-2 shadow-pop">
                <div class="flex items-center gap-2 rounded-xl bg-white/80 px-3 py-2.5">
                    <span class="flex size-7 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600">
                        <?= $this->icon('lock', 'size-3.5') ?>
                    </span>
                    <code class="min-w-0 flex-1 truncate font-mono text-[13px] text-slate-500">
                        <?= $this->e(rtrim(preg_replace('#^https?://#', '', $this->absolute('/')) ?: '', '/')) ?>/<span class="font-semibold text-slate-900"><?= $this->te('common.name') ?>.pdf</span>
                    </code>
                    <span class="badge badge-neutral hidden sm:inline-flex"><?= $this->icon('eye-off') ?><?= $this->te('home.badge') ?></span>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="border-t border-slate-200/70 bg-white py-20">
    <div class="container-page">
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            <?php foreach ($features as $feature): ?>
                <div class="card card-pad flex flex-col items-center text-center transition hover:-translate-y-0.5 hover:shadow-soft">
                    <span class="flex size-14 items-center justify-center rounded-xl bg-brand-50 text-brand-600">
                        <?= $this->icon($feature['icon'], 'size-7') ?>
                    </span>
                    <h2 class="mt-4 text-base font-semibold text-slate-900"><?= $this->e($feature['title']) ?></h2>
                    <p class="mt-2 text-sm leading-relaxed text-slate-600"><?= $this->e($feature['text']) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section id="how" class="border-t border-slate-200/70 bg-slate-50 py-20">
    <div class="container-page">
        <h2 class="text-center text-2xl font-semibold tracking-tight text-slate-900 sm:text-3xl">
            <?= $this->te('home.how_title') ?>
        </h2>

        <ol class="mt-12 grid gap-6 md:grid-cols-3">
            <?php foreach ($steps as $index => $step): ?>
                <li class="card card-pad">
                    <div class="relative inline-flex">
                        <span class="flex size-11 items-center justify-center rounded-xl bg-gradient-to-br from-brand-500 to-accent-500 text-white shadow-brand">
                            <?= $this->icon($step['icon']) ?>
                        </span>
                        <span class="absolute -right-2 -bottom-2 flex size-6 items-center justify-center rounded-full bg-slate-900 text-[11px] font-semibold text-white ring-4 ring-white">
                            <?= $index + 1 ?>
                        </span>
                    </div>
                    <h3 class="mt-4 text-base font-semibold text-slate-900"><?= $this->e($step['title']) ?></h3>
                    <p class="mt-2 text-sm leading-relaxed text-slate-600"><?= $this->e($step['text']) ?></p>
                </li>
            <?php endforeach; ?>
        </ol>

        <div class="mx-auto mt-12 max-w-3xl">
            <div class="card flex flex-col gap-4 card-pad sm:flex-row sm:items-center">
                <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                    <?= $this->icon('eye-off') ?>
                </span>
                <div class="flex-1">
                    <h3 class="text-base font-semibold text-slate-900"><?= $this->te('home.no_listing_title') ?></h3>
                    <p class="mt-1 text-sm leading-relaxed text-slate-600"><?= $this->te('home.no_listing_text') ?></p>
                </div>
            </div>
        </div>
    </div>
</section>
