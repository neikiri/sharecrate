<?php

/**
 * Dashboard layout with sidebar.
 *
 * @var App\Core\View $this
 * @var string $content
 */

use App\Models\User;

$siteName = $this->siteName();
$user = $this->user() ?? [];
$isAdmin = ($user['role'] ?? '') === 'admin';

$sections = [
    [
        'label' => $this->t('nav.content'),
        'items' => [
            ['url' => '/admin', 'label' => $this->t('nav.overview'), 'icon' => 'grid', 'exact' => true],
            ['url' => '/admin/files', 'label' => $this->t('nav.files'), 'icon' => 'files', 'exact' => false],
            ['url' => '/admin/upload', 'label' => $this->t('nav.upload'), 'icon' => 'cloud-upload', 'exact' => false],
            ['url' => '/admin/import', 'label' => $this->t('nav.import'), 'icon' => 'folder-down', 'exact' => false],
            ['url' => '/admin/downloads', 'label' => $this->t('nav.downloads'), 'icon' => 'bar-chart', 'exact' => false],
        ],
    ],
    [
        'label' => $this->t('nav.management'),
        'items' => array_values(array_filter([
            $isAdmin ? ['url' => '/admin/users', 'label' => $this->t('nav.users'), 'icon' => 'users', 'exact' => false] : null,
            $isAdmin ? ['url' => '/admin/settings', 'label' => $this->t('nav.settings'), 'icon' => 'sliders', 'exact' => false] : null,
            ['url' => '/admin/profile', 'label' => $this->t('nav.profile'), 'icon' => 'user', 'exact' => false],
        ])),
    ],
];
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
<body class="min-h-full bg-slate-50" x-data="{ nav: false }">

<!-- Mobile overlay -->
<div x-cloak x-show="nav" x-transition.opacity class="fixed inset-0 z-40 bg-slate-900/40 backdrop-blur-sm lg:hidden" @click="nav = false"></div>

<aside
    x-cloak
    :class="nav ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
    class="fixed inset-y-0 left-0 z-50 flex w-72 flex-col border-r border-slate-200 bg-slate-100/80 transition-transform duration-200 lg:z-30"
>
    <div class="flex h-16 items-center justify-between px-5">
        <a href="<?= $this->e($this->url('/admin')) ?>" class="flex items-center">
            <?= $this->icon('brand-logo', 'h-[33px] w-auto') ?>
        </a>
        <button type="button" class="btn btn-ghost btn-icon lg:hidden" @click="nav = false" aria-label="<?= $this->te('common.close') ?>">
            <?= $this->icon('x') ?>
        </button>
    </div>

    <nav class="flex-1 overflow-y-auto px-3 pb-4">
        <?php foreach ($sections as $section): ?>
            <?php if ($section['items'] === []) {
                continue;
            } ?>
            <div class="nav-section"><?= $this->e($section['label']) ?></div>
            <?php foreach ($section['items'] as $item): ?>
                <a href="<?= $this->e($this->url($item['url'])) ?>"
                   class="nav-link<?= $this->activeClass($item['url'], 'nav-link-active', $item['exact']) ?>">
                    <?= $this->icon($item['icon']) ?>
                    <span><?= $this->e($item['label']) ?></span>
                </a>
            <?php endforeach; ?>
        <?php endforeach; ?>

        <div class="nav-section"><?= $this->te('nav.account') ?></div>
        <a href="<?= $this->e($this->url('/')) ?>" class="nav-link">
            <?= $this->icon('external-link') ?>
            <span><?= $this->te('nav.open_site') ?></span>
        </a>
    </nav>

    <div class="border-t border-slate-200 p-3">
        <div class="flex items-center gap-3 rounded-xl bg-white p-3 shadow-card">
            <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-brand-50 text-sm font-semibold text-brand-700">
                <?= $this->e(User::initials($user)) ?>
            </span>
            <div class="min-w-0 flex-1">
                <div class="truncate text-sm font-medium text-slate-900"><?= $this->e(User::name($user)) ?></div>
                <div class="truncate text-xs text-slate-500">
                    <?= $this->te($isAdmin ? 'users.role_admin' : 'users.role_uploader') ?>
                </div>
            </div>
            <form method="post" action="<?= $this->e($this->url('/admin/logout')) ?>">
                <?= $this->csrf() ?>
                <button type="submit" class="btn btn-ghost btn-icon" title="<?= $this->te('common.logout') ?>">
                    <?= $this->icon('log-out') ?>
                </button>
            </form>
        </div>
    </div>
</aside>

<div class="lg:pl-72">
    <header class="sticky top-0 z-20 border-b border-slate-200/70 bg-slate-50/85 backdrop-blur-xl">
        <div class="flex h-16 items-center gap-3 px-4 sm:px-6 lg:px-8">
            <button type="button" class="btn btn-secondary btn-icon lg:hidden" @click="nav = true" aria-label="<?= $this->te('common.open') ?>">
                <?= $this->icon('menu') ?>
            </button>

            <div class="min-w-0 flex-1">
                <h1 class="truncate text-base font-semibold text-slate-900"><?= $this->e($this->title) ?></h1>
            </div>

            <?php $this->partial('partials/lang-switch'); ?>
        </div>
    </header>

    <main class="px-4 py-6 sm:px-6 sm:py-8 lg:px-8">
        <div class="mx-auto w-full max-w-7xl">
            <?php $this->partial('partials/flash'); ?>
            <?= $content ?>
        </div>
    </main>

    <footer class="px-4 pb-8 text-center text-xs text-slate-400 sm:px-6 lg:px-8">
        <?= $this->e($siteName) ?> · <?= $this->te('common.powered_by') ?>
    </footer>
</div>

<?php $this->partial('partials/toasts'); ?>
<?php $this->partial('partials/confirm-dialog'); ?>
<?php $this->partial('partials/scripts'); ?>
</body>
</html>
