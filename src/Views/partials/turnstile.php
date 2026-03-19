<?php

declare(strict_types=1);

$turnstile = new \App\Services\TurnstileService();
if ($turnstile->enabled()):
?>
    <div class="col-12">
        <div class="cf-turnstile" data-sitekey="<?= htmlspecialchars($turnstile->siteKey(), ENT_QUOTES, 'UTF-8') ?>"></div>
    </div>
<?php endif; ?>
