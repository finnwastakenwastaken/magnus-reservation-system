<?php

declare(strict_types=1);
?>
<!doctype html>
<html lang="<?= htmlspecialchars($translator->locale(), ENT_QUOTES, 'UTF-8') ?>" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($translator->get('installer.title'), ENT_QUOTES, 'UTF-8') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/app.css" rel="stylesheet">
</head>
<body class="app-shell">
<main class="py-5">
    <div class="container">
        <div class="d-flex justify-content-end gap-2 mb-3">
            <a class="btn btn-sm btn-outline-secondary" href="?lang=en">EN</a>
            <a class="btn btn-sm btn-outline-secondary" href="?lang=nl">NL</a>
        </div>
        <?= $content ?>
    </div>
</main>
</body>
</html>
