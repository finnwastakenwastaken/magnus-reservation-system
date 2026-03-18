<?php

declare(strict_types=1);

$t = static fn(string $key, array $replace = []): string => $translator->get($key, $replace);
?>
<div class="p-5 mb-4 bg-white rounded-4 shadow-sm border hero-surface">
    <div class="container-fluid py-4">
        <h1 class="display-5 fw-bold"><?= htmlspecialchars($t('home.title'), ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="col-md-8 fs-5 text-secondary"><?= htmlspecialchars($t('home.subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-primary btn-lg" href="/signup"><?= htmlspecialchars($t('home.cta.signup'), ENT_QUOTES, 'UTF-8') ?></a>
            <a class="btn btn-outline-secondary btn-lg" href="/login"><?= htmlspecialchars($t('home.cta.login'), ENT_QUOTES, 'UTF-8') ?></a>
        </div>
    </div>
</div>
<div class="row g-4">
    <div class="col-md-4">
        <div class="card shadow-sm border-0 h-100"><div class="card-body"><h2 class="h5">1.</h2><p class="mb-0"><?= htmlspecialchars($t('home.feature_activation'), ENT_QUOTES, 'UTF-8') ?></p></div></div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm border-0 h-100"><div class="card-body"><h2 class="h5">2.</h2><p class="mb-0"><?= htmlspecialchars($t('home.feature_reservations'), ENT_QUOTES, 'UTF-8') ?></p></div></div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm border-0 h-100"><div class="card-body"><h2 class="h5">3.</h2><p class="mb-0"><?= htmlspecialchars($t('home.feature_messages'), ENT_QUOTES, 'UTF-8') ?></p></div></div>
    </div>
</div>
<div class="card shadow-sm border-0 mt-4">
    <div class="card-body">
        <h2 class="h5"><?= htmlspecialchars($translator->get('home.privacy_title'), ENT_QUOTES, 'UTF-8') ?></h2>
        <p class="mb-0"><?= htmlspecialchars($translator->get('home.privacy_text'), ENT_QUOTES, 'UTF-8') ?> <a href="/privacy-policy"><?= htmlspecialchars($translator->get('legal.privacy_nav'), ENT_QUOTES, 'UTF-8') ?></a>.</p>
    </div>
</div>
