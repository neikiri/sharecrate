<?php

/**
 * Centred single card layout: login, installer, error pages.
 *
 * @var App\Core\View $this
 * @var string $content
 */

$siteName = $this->siteName();
?>
<!doctype html>
<html lang="<?= $this->e($this->locale()) ?>" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <meta name="robots" content="noindex, nofollow">
    <title><?= $this->e($this->title === '' || $this->title === $siteName ? $siteName : $this->title . ' · ' . $siteName) ?></title>
    <link rel="icon" href="<?= $this->e($this->url('/assets/favicon.svg')) ?>" type="image/svg+xml">
    <?php foreach (App\Core\Url::styles() as $style): ?>
        <link rel="stylesheet" href="<?= $this->e($style) ?>">
    <?php endforeach; ?>
</head>
<body class="flex min-h-full flex-col bg-slate-50">
<div class="aurora pointer-events-none absolute inset-x-0 top-0 h-80">
    <div class="grid-lines absolute inset-0"></div>
</div>

<div class="relative flex flex-1 flex-col items-center px-4 py-10 sm:py-16">
    <a href="<?= $this->e($this->url('/')) ?>" class="mb-8 flex items-center">
        <?= $this->icon('brand-logo', 'h-[33px] w-auto') ?>
    </a>

    <div class="flex w-full flex-1 flex-col items-center justify-center">
        <?= $content ?>
    </div>

    <div class="mt-8">
        <?php $this->partial('partials/lang-switch'); ?>
    </div>
</div>

<?php $this->partial('partials/toasts'); ?>
<?php $this->partial('partials/scripts'); ?>
</body>
</html>
