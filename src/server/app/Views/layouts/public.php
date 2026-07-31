<?php

/**
 * Public layout: landing page, file pages, legal page.
 *
 * @var App\Core\View $this
 * @var string $content
 */

$siteName = $this->siteName();
$user = $this->user();
?>
<!doctype html>
<html lang="<?= $this->e($this->locale()) ?>" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title><?= $this->e($this->title === '' || $this->title === $siteName ? $siteName : $this->title . ' · ' . $siteName) ?></title>
    <?php if ($this->description !== ''): ?>
        <meta name="description" content="<?= $this->e($this->description) ?>">
    <?php endif; ?>
    <?php if ($this->noindex): ?>
        <meta name="robots" content="noindex, nofollow, noarchive">
    <?php else: ?>
        <meta name="robots" content="noindex, nofollow">
    <?php endif; ?>
    <?php if ($this->canonical !== null): ?>
        <link rel="canonical" href="<?= $this->e($this->canonical) ?>">
    <?php endif; ?>
    <meta property="og:site_name" content="<?= $this->e($siteName) ?>">
    <meta property="og:title" content="<?= $this->e($this->title !== '' ? $this->title : $siteName) ?>">
    <?php if ($this->description !== ''): ?>
        <meta property="og:description" content="<?= $this->e($this->description) ?>">
    <?php endif; ?>
    <meta property="og:type" content="website">
    <link rel="icon" href="<?= $this->e($this->url('/assets/favicon.svg')) ?>" type="image/svg+xml">
    <?php foreach (App\Core\Url::styles() as $style): ?>
        <link rel="stylesheet" href="<?= $this->e($style) ?>">
    <?php endforeach; ?>
</head>
<body class="flex min-h-full flex-col bg-white">
<a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:top-3 focus:left-3 focus:z-50 focus:rounded-lg focus:bg-white focus:px-4 focus:py-2 focus:shadow-pop">
    <?= $this->te('common.skip_to_content') ?>
</a>

<header class="sticky top-0 z-30 border-b border-slate-200/70 bg-white/85 backdrop-blur-xl">
    <div class="container-page flex h-16 items-center justify-between gap-4">
        <a href="<?= $this->e($this->url('/')) ?>" class="group flex items-center">
            <?= $this->icon('brand-logo', 'h-[33px] w-auto') ?>
        </a>

        <div class="flex items-center gap-2 sm:gap-3">
            <?php $this->partial('partials/lang-switch'); ?>

            <?php if ($user !== null): ?>
                <a href="<?= $this->e($this->url('/admin')) ?>" class="btn btn-primary btn-sm sm:px-4 sm:py-2 sm:text-sm">
                    <?= $this->icon('grid') ?>
                    <span class="hidden sm:inline"><?= $this->te('common.dashboard') ?></span>
                </a>
            <?php else: ?>
                <a href="<?= $this->e($this->url('/admin/login')) ?>" class="btn btn-secondary btn-sm sm:px-4 sm:py-2 sm:text-sm">
                    <?= $this->icon('log-in') ?>
                    <span class="hidden sm:inline"><?= $this->te('common.sign_in') ?></span>
                </a>
            <?php endif; ?>
        </div>
    </div>
</header>

<main id="main" class="flex-1">
    <?php $flashes = $this->flashes(); ?>
    <?php if ($flashes !== []): ?>
        <div class="container-narrow pt-6">
            <?php foreach ($flashes as $message): ?>
                <div class="alert <?= $message['type'] === 'error' ? 'alert-error' : ($message['type'] === 'success' ? 'alert-success' : 'alert-info') ?> mb-4 animate-rise" data-autohide="6000" role="status">
                    <?= $this->icon($message['type'] === 'error' ? 'x-circle' : 'check-circle') ?>
                    <div class="flex-1"><?= $this->e($message['message']) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?= $content ?>
</main>

<footer class="border-t border-slate-200/70 bg-slate-50">
    <div class="container-page flex flex-col gap-4 py-8 text-sm text-slate-500 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex flex-col gap-1">
            <span class="font-medium text-slate-700"><?= $this->e($siteName) ?></span>
            <span><?= $this->te('home.footer_note') ?></span>
        </div>
        <div class="flex flex-wrap items-center gap-x-5 gap-y-2">
            <a href="<?= $this->e($this->url('/legal')) ?>" class="hover:text-slate-700"><?= $this->te('home.no_listing_title') ?></a>
            <?php $contact = $this->setting('contact_email'); ?>
            <?php if (is_string($contact) && $contact !== ''): ?>
                <a href="mailto:<?= $this->e($contact) ?>" class="hover:text-slate-700"><?= $this->e($contact) ?></a>
            <?php endif; ?>
            <a href="https://github.com/neikiri" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1.5 hover:text-slate-700">
                <?= $this->icon('github', 'size-4') ?>
                <?= $this->te('home.footer_author') ?>
            </a>
            <span class="text-slate-400"><?= date('Y') ?></span>
        </div>
    </div>
</footer>

<?php $this->partial('partials/toasts'); ?>
<?php $this->partial('partials/confirm-dialog'); ?>
<?php $this->partial('partials/scripts'); ?>
</body>
</html>
