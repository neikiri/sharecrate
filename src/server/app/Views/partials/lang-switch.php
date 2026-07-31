<?php

/**
 * CZ / EN switch. Keeps the visitor on the same page.
 *
 * @var App\Core\View $this
 */

use App\Core\I18n;
use App\Core\Request;

$request = Request::current();
$back = $request->withQuery(['lang' => null]);
$current = $this->locale();
$names = I18n::localeNames();
?>
<div class="flex items-center gap-2">
    <a
        href="https://github.com/neikiri/sharecrate"
        target="_blank"
        rel="noopener noreferrer"
        class="btn btn-secondary btn-icon"
        title="<?= $this->te('common.view_on_github') ?>"
        aria-label="<?= $this->te('common.view_on_github') ?>"
    >
        <?= $this->icon('github', 'size-4') ?>
    </a>

    <div class="pill-nav" role="group" aria-label="<?= $this->te('common.language') ?>">
        <?php foreach (I18n::AVAILABLE as $locale): ?>
            <?php $isActive = $locale === $current; ?>
            <a
                href="<?= $this->e($this->url('/lang/' . $locale, ['to' => $back])) ?>"
                class="<?= $isActive ? 'is-active' : '' ?>"
                <?= $isActive ? 'aria-current="true"' : '' ?>
                title="<?= $this->te('common.switch_to', ['language' => $names[$locale] ?? strtoupper($locale)]) ?>"
                rel="nofollow"
            ><?= $this->e(strtoupper($locale)) ?></a>
        <?php endforeach; ?>
    </div>
</div>
