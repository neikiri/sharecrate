<?php

/**
 * Coloured tile showing the file extension.
 *
 * @var App\Core\View $this
 * @var array<string, mixed> $file
 * @var string|null $tileClass  extra sizing classes
 */

use App\Support\FileTypes;

$extension = strtolower((string) ($file['extension'] ?? ''));
$category = FileTypes::category($extension);
$classes = $tileClass ?? 'size-11 text-[11px]';
$label = $extension === '' ? '' : mb_strtoupper(mb_substr($extension, 0, 4));
?>
<span class="file-tile <?= $this->e($classes) ?> <?= $this->e(FileTypes::tileClasses($category)) ?>"
      title="<?= $this->e(FileTypes::label($category)) ?>">
    <?php if ($label === ''): ?>
        <?= $this->icon(FileTypes::icon($category), 'size-5') ?>
    <?php else: ?>
        <span class="tracking-tight"><?= $this->e($label) ?></span>
    <?php endif; ?>
</span>
