<?php

declare(strict_types=1);

use App\Core\Csrf;

$t = static fn(string $key, array $replace = []): string => $translator->get($key, $replace);
$isAuthenticated = $auth !== null;
$siteLogoPath = $siteSettings['site_logo_path'] ?? '';
$notificationCount = $isAuthenticated ? (int) (($auth['unread_notification_count'] ?? 0)) : 0;
$turnstile = new \App\Services\TurnstileService();
?>
<!doctype html>
<html lang="<?= htmlspecialchars($translator->locale(), ENT_QUOTES, 'UTF-8') ?>" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
    <title><?= htmlspecialchars($t('app.name'), ENT_QUOTES, 'UTF-8') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/app.css" rel="stylesheet">
</head>
<body class="app-shell">
<nav class="navbar navbar-expand-lg app-navbar shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-semibold d-flex align-items-center gap-2" href="<?= $isAuthenticated ? '/reservations' : '/' ?>">
            <?php if ($siteLogoPath !== ''): ?>
                <img src="<?= htmlspecialchars($siteLogoPath, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($t('app.name'), ENT_QUOTES, 'UTF-8') ?>" style="height:40px; width:auto;">
            <?php else: ?>
                <span class="rounded px-2 py-1 fw-bold app-brand-mark">M</span>
            <?php endif; ?>
            <span><?= htmlspecialchars($t('app.name'), ENT_QUOTES, 'UTF-8') ?></span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navMain">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link" href="<?= $isAuthenticated ? '/reservations' : '/' ?>"><?= htmlspecialchars($t('nav.home'), ENT_QUOTES, 'UTF-8') ?></a></li>
                <li class="nav-item"><a class="nav-link" href="/availability"><?= htmlspecialchars($t('nav.availability'), ENT_QUOTES, 'UTF-8') ?></a></li>
                <?php if ($isAuthenticated): ?>
                    <li class="nav-item"><a class="nav-link" href="/account"><?= htmlspecialchars($t('nav.account'), ENT_QUOTES, 'UTF-8') ?></a></li>
                    <li class="nav-item"><a class="nav-link" href="/notifications"><?= htmlspecialchars($t('nav.notifications'), ENT_QUOTES, 'UTF-8') ?><?php if ($notificationCount > 0): ?> <span class="badge rounded-pill text-bg-danger"><?= $notificationCount ?></span><?php endif; ?></a></li>
                    <li class="nav-item"><a class="nav-link" href="/residents"><?= htmlspecialchars($t('nav.residents'), ENT_QUOTES, 'UTF-8') ?></a></li>
                    <li class="nav-item"><a class="nav-link" href="/reservations"><?= htmlspecialchars($t('nav.reservations'), ENT_QUOTES, 'UTF-8') ?></a></li>
                    <li class="nav-item"><a class="nav-link" href="/messages"><?= htmlspecialchars($t('nav.messages'), ENT_QUOTES, 'UTF-8') ?></a></li>
                    <?php if (\App\Core\Auth::hasPermission(\App\Security\Permissions::ADMIN_ACCESS)): ?>
                        <li class="nav-item"><a class="nav-link" href="/admin"><?= htmlspecialchars($t('nav.admin'), ENT_QUOTES, 'UTF-8') ?></a></li>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>
            <div class="d-flex gap-2 align-items-center">
                <a class="btn btn-sm btn-outline-light" href="/lang/en">EN</a>
                <a class="btn btn-sm btn-outline-light" href="/lang/nl">NL</a>
                <?php if ($isAuthenticated): ?>
                    <form action="/logout" method="post" class="d-inline">
                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                        <button class="btn btn-sm btn-outline-warning" type="submit"><?= htmlspecialchars($t('nav.logout'), ENT_QUOTES, 'UTF-8') ?></button>
                    </form>
                <?php else: ?>
                    <a class="btn btn-sm btn-outline-light" href="/login"><?= htmlspecialchars($t('nav.login'), ENT_QUOTES, 'UTF-8') ?></a>
                    <a class="btn btn-sm btn-outline-light" href="/activate"><?= htmlspecialchars($t('nav.activate'), ENT_QUOTES, 'UTF-8') ?></a>
                    <a class="btn btn-sm btn-primary" href="/signup"><?= htmlspecialchars($t('nav.signup'), ENT_QUOTES, 'UTF-8') ?></a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<main class="py-4">
    <div class="container">
        <?php foreach ($flash as $item): ?>
            <div class="alert alert-<?= htmlspecialchars($item['type'], ENT_QUOTES, 'UTF-8') ?> alert-dismissible fade show">
                <?= htmlspecialchars($item['message'], ENT_QUOTES, 'UTF-8') ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endforeach; ?>

        <?= $content ?>
    </div>
</main>

<footer class="py-3 text-center text-secondary small">
    <div class="mb-2">
        <a class="link-secondary me-2" href="/privacy-policy"><?= htmlspecialchars($t('legal.privacy_nav'), ENT_QUOTES, 'UTF-8') ?></a>
        <a class="link-secondary me-2" href="/cookie-notice"><?= htmlspecialchars($t('legal.cookies_nav'), ENT_QUOTES, 'UTF-8') ?></a>
        <a class="link-secondary" href="/house-rules"><?= htmlspecialchars($t('legal.house_rules_nav'), ENT_QUOTES, 'UTF-8') ?></a>
    </div>
    <?= htmlspecialchars($t('app.name'), ENT_QUOTES, 'UTF-8') ?> v<?= htmlspecialchars($config['app']['version'], ENT_QUOTES, 'UTF-8') ?>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/app.js"></script>
<?php if ($turnstile->enabled()): ?>
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
<?php endif; ?>
</body>
</html>
