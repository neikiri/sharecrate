<?php

/**
 * Site settings.
 *
 * @var App\Core\View $this
 * @var string[] $timezones
 * @var array<string, string|bool> $system
 * @var array{upload_max: int, post_max: int, effective: int, execution_time: int} $limits
 */

$this->title = $this->t('settings.title');

$aliasStyle = (string) ($this->setting('alias_style', 'slug') ?? 'slug');
$privacyMode = (string) ($this->setting('privacy_ip_mode', 'full') ?? 'full');
$timezone = (string) ($this->setting('timezone', 'Europe/Prague') ?? 'Europe/Prague');
?>
<div class="mx-auto max-w-4xl space-y-6">
    <div>
        <h2 class="text-2xl font-semibold tracking-tight text-slate-900"><?= $this->te('settings.title') ?></h2>
        <p class="mt-1 text-sm text-slate-500"><?= $this->te('settings.subtitle') ?></p>
    </div>

    <form method="post" action="<?= $this->e($this->url('/admin/settings')) ?>" class="space-y-6">
        <?= $this->csrf() ?>

        <!-- General -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><?= $this->te('settings.section_general') ?></h3>
            </div>
            <div class="grid gap-5 p-5 sm:grid-cols-2 sm:p-6">
                <div>
                    <label class="label" for="site_name"><?= $this->te('settings.site_name') ?></label>
                    <input type="text" name="site_name" id="site_name" class="input" maxlength="100"
                           value="<?= $this->e((string) $this->setting('site_name')) ?>">
                </div>
                <div>
                    <label class="label" for="site_tagline"><?= $this->te('settings.site_tagline') ?></label>
                    <input type="text" name="site_tagline" id="site_tagline" class="input" maxlength="190"
                           value="<?= $this->e((string) ($this->setting('site_tagline') ?? '')) ?>">
                </div>
                <div>
                    <label class="label" for="contact_email"><?= $this->te('settings.contact_email') ?></label>
                    <input type="email" name="contact_email" id="contact_email" class="input" maxlength="190"
                           value="<?= $this->e((string) ($this->setting('contact_email') ?? '')) ?>">
                    <p class="help"><?= $this->te('settings.contact_email_hint') ?></p>
                </div>
                <div>
                    <label class="label" for="timezone"><?= $this->te('settings.timezone') ?></label>
                    <select name="timezone" id="timezone" class="select">
                        <?php foreach ($timezones as $zone): ?>
                            <option value="<?= $this->e($zone) ?>" <?= $zone === $timezone ? 'selected' : '' ?>><?= $this->e($zone) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="help"><?= $this->te('settings.timezone_hint') ?></p>
                </div>
            </div>
        </div>

        <!-- Links -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><?= $this->te('settings.section_links') ?></h3>
            </div>
            <div class="space-y-5 p-5 sm:p-6">
                <fieldset>
                    <legend class="label"><?= $this->te('settings.alias_style') ?></legend>
                    <div class="space-y-2">
                        <?php foreach (['slug', 'slug_random', 'random'] as $style): ?>
                            <label class="field-group">
                                <input type="radio" name="alias_style" value="<?= $style ?>" class="checkbox mt-0.5 rounded-full"
                                    <?= $aliasStyle === $style ? 'checked' : '' ?>>
                                <span class="text-sm text-slate-700"><?= $this->te('settings.alias_style_' . $style) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label class="label" for="alias_random_len"><?= $this->te('settings.alias_random_len') ?></label>
                        <input type="number" name="alias_random_len" id="alias_random_len" min="3" max="16" class="input"
                               value="<?= $this->e((string) $this->setting('alias_random_len', '6')) ?>">
                    </div>
                    <div>
                        <label class="label" for="default_expiry_days"><?= $this->te('settings.default_expiry_days') ?></label>
                        <input type="number" name="default_expiry_days" id="default_expiry_days" min="0" max="3650" class="input"
                               value="<?= $this->e((string) $this->setting('default_expiry_days', '0')) ?>">
                        <p class="help"><?= $this->te('settings.default_expiry_hint') ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Privacy -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><?= $this->te('settings.section_privacy') ?></h3>
            </div>
            <div class="space-y-5 p-5 sm:p-6">
                <fieldset>
                    <legend class="label"><?= $this->te('settings.privacy_ip_mode') ?></legend>
                    <div class="space-y-2">
                        <?php foreach (['full' => 'privacy_full', 'anonymised' => 'privacy_anonymised', 'none' => 'privacy_none'] as $mode => $key): ?>
                            <label class="field-group">
                                <input type="radio" name="privacy_ip_mode" value="<?= $mode ?>" class="checkbox mt-0.5 rounded-full"
                                    <?= $privacyMode === $mode ? 'checked' : '' ?>>
                                <span class="text-sm text-slate-700"><?= $this->te('settings.' . $key) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <p class="help"><?= $this->te('settings.privacy_hint') ?></p>
                </fieldset>

                <div class="sm:max-w-xs">
                    <label class="label" for="log_retention_days"><?= $this->te('settings.log_retention') ?></label>
                    <input type="number" name="log_retention_days" id="log_retention_days" min="0" max="3650" class="input"
                           value="<?= $this->e((string) $this->setting('log_retention_days', '365')) ?>">
                    <p class="help"><?= $this->te('settings.log_retention_hint') ?></p>
                </div>
            </div>
        </div>

        <!-- Uploads -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><?= $this->te('settings.section_uploads') ?></h3>
            </div>
            <div class="space-y-5 p-5 sm:p-6">
                <div class="sm:max-w-xs">
                    <label class="label" for="max_upload_mb"><?= $this->te('settings.max_upload_mb') ?></label>
                    <input type="number" name="max_upload_mb" id="max_upload_mb" min="0" class="input"
                           value="<?= $this->e((string) $this->setting('max_upload_mb', '0')) ?>">
                    <p class="help"><?= $this->te('settings.max_upload_hint', ['php_limit' => $this->bytes($limits['upload_max'])]) ?></p>
                </div>

                <label class="field-group">
                    <input type="checkbox" name="allow_uploader_delete" value="1" class="checkbox mt-0.5"
                        <?= $this->setting('allow_uploader_delete') === '1' ? 'checked' : '' ?>>
                    <span class="text-sm text-slate-700"><?= $this->te('settings.allow_uploader_delete') ?></span>
                </label>

                <label class="field-group">
                    <input type="checkbox" name="show_file_owner" value="1" class="checkbox mt-0.5"
                        <?= $this->setting('show_file_owner') === '1' ? 'checked' : '' ?>>
                    <span class="text-sm text-slate-700"><?= $this->te('settings.show_file_owner') ?></span>
                </label>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="btn btn-primary">
                <?= $this->icon('check') ?>
                <?= $this->te('common.save_changes') ?>
            </button>
        </div>
    </form>

    <!-- System -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><?= $this->te('settings.section_system') ?></h3>
            <form method="post" action="<?= $this->e($this->url('/admin/settings')) ?>">
                <?= $this->csrf() ?>
                <button type="submit" name="action" value="maintenance" class="btn btn-secondary btn-sm">
                    <?= $this->icon('refresh') ?>
                    <?= $this->te('settings.run_maintenance') ?>
                </button>
            </form>
        </div>

        <dl class="grid gap-px bg-slate-100 sm:grid-cols-2">
            <?php
            $rows = [
                ['label' => $this->t('settings.php_version'), 'value' => (string) $system['php'], 'ok' => null],
                ['label' => $this->t('settings.db_version'), 'value' => (string) $system['database'], 'ok' => null],
                ['label' => $this->t('settings.storage_path'), 'value' => (string) $system['storage_path'], 'ok' => (bool) $system['storage_writable']],
                ['label' => $this->t('settings.gd_available'), 'value' => $system['gd'] ? $this->t('common.yes') : $this->t('common.no'), 'ok' => (bool) $system['gd']],
                ['label' => $this->t('settings.geo_provider'), 'value' => (string) $system['geo'], 'ok' => null],
                ['label' => 'APP_URL', 'value' => (string) $system['app_url'], 'ok' => null],
            ];
            ?>
            <?php foreach ($rows as $row): ?>
                <div class="flex items-start justify-between gap-4 bg-white px-5 py-4">
                    <dt class="text-sm text-slate-500"><?= $this->e($row['label']) ?></dt>
                    <dd class="flex min-w-0 items-center gap-2 text-right">
                        <?php if ($row['ok'] !== null): ?>
                            <span class="<?= $row['ok'] ? 'text-emerald-500' : 'text-rose-500' ?>">
                                <?= $this->icon($row['ok'] ? 'check-circle' : 'x-circle', 'size-4') ?>
                            </span>
                        <?php endif; ?>
                        <span class="truncate font-mono text-xs text-slate-700"><?= $this->e($row['value']) ?></span>
                    </dd>
                </div>
            <?php endforeach; ?>
        </dl>
    </div>
</div>
