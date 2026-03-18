<?php declare(strict_types=1);

use App\Core\Csrf;

$old ??= [];
?>
<div class="row justify-content-center">
    <div class="col-lg-5">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <h1 class="h3 mb-4"><?= htmlspecialchars($translator->get('auth.login'), ENT_QUOTES, 'UTF-8') ?></h1>
                <form method="post">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                    <div class="mb-3">
                        <label class="form-label"><?= htmlspecialchars($translator->get('auth.email'), ENT_QUOTES, 'UTF-8') ?></label>
                        <input type="email" class="form-control" name="email" value="<?= htmlspecialchars((string) ($old['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= htmlspecialchars($translator->get('auth.password'), ENT_QUOTES, 'UTF-8') ?></label>
                        <input type="password" class="form-control" name="password">
                    </div>
                    <?php require BASE_PATH . '/src/Views/partials/turnstile.php'; ?>
                    <button class="btn btn-primary" type="submit"><?= htmlspecialchars($translator->get('auth.login_submit'), ENT_QUOTES, 'UTF-8') ?></button>
                </form>
            </div>
        </div>
    </div>
</div>
