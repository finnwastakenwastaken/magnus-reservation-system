<?php

declare(strict_types=1);

if ($config['turnstile']['enabled']):
?>
    <div class="col-12">
        <div class="cf-turnstile" data-sitekey="<?= htmlspecialchars($config['turnstile']['site_key'], ENT_QUOTES, 'UTF-8') ?>"></div>
    </div>
<?php endif; ?>
