<?php

declare(strict_types=1);

use App\Core\Auth;
use App\Core\Csrf;

$t = static fn(string $key, array $replace = []): string => $translator->get($key, $replace);
?>
<!doctype html>
<html lang="<?= htmlspecialchars($translator->locale(), ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($t('app.name'), ENT_QUOTES, 'UTF-8') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/app.css" rel="stylesheet">
</head>
<body class="bg-body-tertiary">
<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-semibold" href="/"><?= htmlspecialchars($t('app.name'), ENT_QUOTES, 'UTF-8') ?></a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navMain">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link" href="/"><?= htmlspecialchars($t('nav.home'), ENT_QUOTES, 'UTF-8') ?></a></li>
                <?php if (Auth::check()): ?>
                    <li class="nav-item"><a class="nav-link" href="/dashboard"><?= htmlspecialchars($t('nav.dashboard'), ENT_QUOTES, 'UTF-8') ?></a></li>
                    <li class="nav-item"><a class="nav-link" href="/reservations"><?= htmlspecialchars($t('nav.reservations'), ENT_QUOTES, 'UTF-8') ?></a></li>
                    <li class="nav-item"><a class="nav-link" href="/messages/inbox"><?= htmlspecialchars($t('nav.messages'), ENT_QUOTES, 'UTF-8') ?></a></li>
                    <?php if (($auth['role'] ?? '') === 'admin'): ?>
                        <li class="nav-item"><a class="nav-link" href="/admin"><?= htmlspecialchars($t('nav.admin'), ENT_QUOTES, 'UTF-8') ?></a></li>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>
            <div class="d-flex gap-2 align-items-center">
                <a class="btn btn-sm btn-outline-light" href="/lang/en">EN</a>
                <a class="btn btn-sm btn-outline-light" href="/lang/nl">NL</a>
                <?php if (Auth::check()): ?>
                    <form action="/logout" method="post" class="d-inline">
                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                        <button class="btn btn-sm btn-warning" type="submit"><?= htmlspecialchars($t('nav.logout'), ENT_QUOTES, 'UTF-8') ?></button>
                    </form>
                <?php else: ?>
                    <a class="btn btn-sm btn-light" href="/login"><?= htmlspecialchars($t('nav.login'), ENT_QUOTES, 'UTF-8') ?></a>
                    <a class="btn btn-sm btn-outline-light" href="/activate"><?= htmlspecialchars($t('nav.activate'), ENT_QUOTES, 'UTF-8') ?></a>
                    <a class="btn btn-sm btn-warning" href="/signup"><?= htmlspecialchars($t('nav.signup'), ENT_QUOTES, 'UTF-8') ?></a>
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
    <?= htmlspecialchars($t('app.name'), ENT_QUOTES, 'UTF-8') ?> v<?= htmlspecialchars($config['app']['version'], ENT_QUOTES, 'UTF-8') ?>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php if ($config['turnstile']['enabled']): ?>
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
<?php endif; ?>
</body>
</html>
