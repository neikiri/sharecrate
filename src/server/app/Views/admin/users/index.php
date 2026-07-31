<?php

/**
 * User management.
 *
 * @var App\Core\View $this
 * @var array<int, array<string, mixed>> $users
 */

use App\Models\User;

$this->title = $this->t('users.title');
$currentId = (int) ($this->user()['id'] ?? 0);
?>
<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h2 class="text-2xl font-semibold tracking-tight text-slate-900"><?= $this->te('users.title') ?></h2>
            <p class="mt-1 text-sm text-slate-500"><?= $this->te('users.subtitle') ?></p>
        </div>
        <a href="<?= $this->e($this->url('/admin/users/new')) ?>" class="btn btn-primary">
            <?= $this->icon('plus') ?>
            <?= $this->te('users.add') ?>
        </a>
    </div>

    <div class="card overflow-hidden">
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th><?= $this->te('users.username') ?></th>
                    <th class="hidden md:table-cell"><?= $this->te('users.role') ?></th>
                    <th class="hidden sm:table-cell"><?= $this->te('users.files') ?></th>
                    <th class="hidden lg:table-cell"><?= $this->te('users.last_login') ?></th>
                    <th><?= $this->te('common.status') ?></th>
                    <th class="w-px text-right"><?= $this->te('common.actions') ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $user): ?>
                    <?php
                    $id = (int) $user['id'];
                    $isSelf = $id === $currentId;
                    $isAdmin = $user['role'] === 'admin';
                    $quota = $user['quota_bytes'] === null ? null : (int) $user['quota_bytes'];
                    ?>
                    <tr>
                        <td>
                            <div class="flex min-w-0 items-center gap-3">
                                <span class="flex size-9 shrink-0 items-center justify-center rounded-full <?= $isAdmin ? 'bg-brand-50 text-brand-700' : 'bg-slate-100 text-slate-600' ?> text-xs font-semibold">
                                    <?= $this->e(User::initials($user)) ?>
                                </span>
                                <div class="min-w-0">
                                    <div class="flex items-center gap-1.5">
                                        <a href="<?= $this->e($this->url('/admin/users/' . $id)) ?>" class="truncate text-sm font-medium text-slate-900 hover:text-brand-700">
                                            <?= $this->e(User::name($user)) ?>
                                        </a>
                                        <?php if ($isSelf): ?>
                                            <span class="badge badge-neutral text-[10px]"><?= $this->te('users.you') ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="truncate text-xs text-slate-500"><?= $this->e((string) $user['email']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="hidden md:table-cell">
                            <span class="badge <?= $isAdmin ? 'badge-brand' : 'badge-neutral' ?>">
                                <?= $this->icon($isAdmin ? 'shield-check' : 'upload') ?>
                                <?= $this->te($isAdmin ? 'users.role_admin' : 'users.role_uploader') ?>
                            </span>
                        </td>
                        <td class="hidden sm:table-cell">
                            <div class="text-sm text-slate-900 tabular-nums"><?= $this->e($this->number((int) $user['file_count'])) ?></div>
                            <div class="text-[11px] text-slate-400">
                                <?= $this->e($this->bytes((int) $user['total_bytes'])) ?>
                                <?php if ($quota !== null && $quota > 0): ?>
                                    / <?= $this->e($this->bytes($quota)) ?>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="hidden text-sm text-slate-600 lg:table-cell">
                            <?php if ($user['last_login_at'] === null): ?>
                                <span class="text-slate-400"><?= $this->te('users.never_logged_in') ?></span>
                            <?php else: ?>
                                <span title="<?= $this->e($this->date((string) $user['last_login_at'])) ?>">
                                    <?= $this->e($this->ago((string) $user['last_login_at'])) ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?= (int) $user['is_active'] === 1 ? 'badge-success' : 'badge-neutral' ?>">
                                <?= $this->icon((int) $user['is_active'] === 1 ? 'check-circle' : 'ban') ?>
                                <?= $this->te((int) $user['is_active'] === 1 ? 'common.active' : 'common.disabled') ?>
                            </span>
                        </td>
                        <td>
                            <div class="flex items-center justify-end gap-1">
                                <a href="<?= $this->e($this->url('/admin/files', ['owner' => $id])) ?>" class="btn btn-ghost btn-icon" title="<?= $this->te('nav.files') ?>">
                                    <?= $this->icon('files', 'size-4') ?>
                                </a>
                                <a href="<?= $this->e($this->url('/admin/users/' . $id)) ?>" class="btn btn-ghost btn-icon" title="<?= $this->te('common.edit') ?>">
                                    <?= $this->icon('pencil', 'size-4') ?>
                                </a>
                                <?php if (!$isSelf): ?>
                                    <form
                                        method="post"
                                        action="<?= $this->e($this->url('/admin/users/' . $id . '/delete')) ?>"
                                        data-confirm="<?= $this->te('users.delete_confirm', ['name' => User::name($user)]) ?>"
                                        data-confirm-detail="<?= $this->te('users.delete_confirm_detail') ?>"
                                        data-confirm-label="<?= $this->te('common.delete') ?>"
                                    >
                                        <?= $this->csrf() ?>
                                        <button type="submit" class="btn btn-ghost btn-icon text-slate-400 hover:bg-rose-50 hover:text-rose-600" title="<?= $this->te('common.delete') ?>">
                                            <?= $this->icon('trash', 'size-4') ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
