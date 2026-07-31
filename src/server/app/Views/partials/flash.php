<?php

/**
 * Server rendered flash messages.
 *
 * @var App\Core\View $this
 */

$messages = $this->flashes();

if ($messages === []) {
    return;
}

$map = [
    'success' => ['class' => 'alert-success', 'icon' => 'check-circle'],
    'error' => ['class' => 'alert-error', 'icon' => 'x-circle'],
    'warning' => ['class' => 'alert-warning', 'icon' => 'alert-triangle'],
    'info' => ['class' => 'alert-info', 'icon' => 'info'],
];
?>
<div class="mb-6 space-y-3">
    <?php foreach ($messages as $message): ?>
        <?php $style = $map[$message['type']] ?? $map['info']; ?>
        <div class="alert <?= $style['class'] ?> animate-rise" data-autohide="7000" role="status">
            <?= $this->icon($style['icon']) ?>
            <div class="flex-1"><?= $this->e($message['message']) ?></div>
        </div>
    <?php endforeach; ?>
</div>
