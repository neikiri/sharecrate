<?php

/**
 * JS bundle plus the strings the bundle needs.
 * The i18n block is JSON data, never executed, so it stays CSP friendly.
 *
 * @var App\Core\View $this
 */

use App\Core\I18n;
use App\Core\Url;

$script = Url::script();
?>
<script type="application/json" id="js-i18n"><?= json_encode(
    I18n::jsStrings(),
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
) ?></script>
<?php if ($script !== null): ?>
    <script src="<?= $this->e($script) ?>" defer></script>
<?php endif; ?>
