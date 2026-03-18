<?php declare(strict_types=1);

use App\Core\Csrf;

$old ??= [];
$errors ??= [];
?>
<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <h1 class="h3 mb-4"><?= htmlspecialchars($translator->get('auth.signup'), ENT_QUOTES, 'UTF-8') ?></h1>
                <form method="post" novalidate>
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label"><?= htmlspecialchars($translator->get('auth.first_name'), ENT_QUOTES, 'UTF-8') ?></label>
                            <input class="form-control <?= isset($errors['first_name']) ? 'is-invalid' : '' ?>" name="first_name" value="<?= htmlspecialchars((string) ($old['first_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                            <?php if (isset($errors['first_name'])): ?><div class="invalid-feedback"><?= htmlspecialchars($translator->get($errors['first_name']), ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?= htmlspecialchars($translator->get('auth.last_name'), ENT_QUOTES, 'UTF-8') ?></label>
                            <input class="form-control <?= isset($errors['last_name']) ? 'is-invalid' : '' ?>" name="last_name" value="<?= htmlspecialchars((string) ($old['last_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                            <?php if (isset($errors['last_name'])): ?><div class="invalid-feedback"><?= htmlspecialchars($translator->get($errors['last_name']), ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?= htmlspecialchars($translator->get('auth.email'), ENT_QUOTES, 'UTF-8') ?></label>
                            <input type="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>" name="email" value="<?= htmlspecialchars((string) ($old['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                            <?php if (isset($errors['email'])): ?><div class="invalid-feedback"><?= htmlspecialchars($translator->get($errors['email']), ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?= htmlspecialchars($translator->get('auth.apartment'), ENT_QUOTES, 'UTF-8') ?></label>
                            <input class="form-control <?= isset($errors['apartment_number']) ? 'is-invalid' : '' ?>" name="apartment_number" value="<?= htmlspecialchars((string) ($old['apartment_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                            <?php if (isset($errors['apartment_number'])): ?><div class="invalid-feedback"><?= htmlspecialchars($translator->get($errors['apartment_number']), ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
                        </div>
                        <div class="col-12">
                            <label class="form-label"><?= htmlspecialchars($translator->get('auth.password'), ENT_QUOTES, 'UTF-8') ?></label>
                            <input type="password" class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>" name="password">
                            <?php if (isset($errors['password'])): ?><div class="invalid-feedback"><?= htmlspecialchars($translator->get($errors['password']), ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
                        </div>
                        <?php require BASE_PATH . '/src/Views/partials/turnstile.php'; ?>
                        <div class="col-12">
                            <button class="btn btn-primary" type="submit"><?= htmlspecialchars($translator->get('auth.signup_submit'), ENT_QUOTES, 'UTF-8') ?></button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
