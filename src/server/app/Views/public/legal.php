<?php

/**
 * Short privacy note. No cookie banner needed - only functional cookies.
 *
 * @var App\Core\View $this
 */

$contact = $this->setting('contact_email');
$ipMode = $this->setting('privacy_ip_mode', 'full');
$retention = (int) ($this->setting('log_retention_days', '365') ?? '365');
?>
<section class="container-narrow py-16">
    <h1 class="text-3xl font-semibold tracking-tight text-slate-900">
        <?= $this->te('home.no_listing_title') ?>
    </h1>
    <p class="mt-4 text-slate-600"><?= $this->te('home.no_listing_text') ?></p>

    <div class="mt-10 space-y-4">
        <div class="card card-pad">
            <h2 class="card-title"><?= $this->te('settings.section_privacy') ?></h2>
            <ul class="mt-3 space-y-2 text-sm text-slate-600">
                <li class="flex gap-2">
                    <?= $this->icon('check', 'size-4 mt-0.5 shrink-0 text-emerald-500') ?>
                    <span>
                        <?= $this->te('downloads.subtitle') ?>
                        <?php if ($ipMode === 'none'): ?>
                            — <?= $this->te('settings.privacy_none') ?>.
                        <?php elseif ($ipMode === 'anonymised'): ?>
                            — <?= $this->te('settings.privacy_anonymised') ?>.
                        <?php else: ?>
                            — <?= $this->te('settings.privacy_full') ?>.
                        <?php endif; ?>
                    </span>
                </li>
                <li class="flex gap-2">
                    <?= $this->icon('check', 'size-4 mt-0.5 shrink-0 text-emerald-500') ?>
                    <span>
                        <?= $this->te('settings.log_retention') ?>:
                        <?= $retention === 0 ? $this->te('common.unlimited') : $this->e($this->number($retention)) ?>
                    </span>
                </li>
                <li class="flex gap-2">
                    <?= $this->icon('check', 'size-4 mt-0.5 shrink-0 text-emerald-500') ?>
                    <span><?= $this->te('home.footer_note') ?></span>
                </li>
            </ul>
        </div>

        <?php if (is_string($contact) && $contact !== ''): ?>
            <div class="card flex items-center gap-4 card-pad">
                <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600">
                    <?= $this->icon('mail') ?>
                </span>
                <div>
                    <div class="text-sm font-medium text-slate-900"><?= $this->te('settings.contact_email') ?></div>
                    <a href="mailto:<?= $this->e($contact) ?>" class="link text-sm"><?= $this->e($contact) ?></a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <a href="<?= $this->e($this->url('/')) ?>" class="btn btn-secondary mt-10">
        <?= $this->icon('arrow-left') ?>
        <?= $this->te('errors.go_home') ?>
    </a>
</section>
