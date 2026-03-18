<?php declare(strict_types=1);

use App\Core\Csrf;

$old ??= [];
$errors ??= [];
?>
<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <h1 class="h3 mb-2"><?= htmlspecialchars($translator->get('reservation.edit'), ENT_QUOTES, 'UTF-8') ?></h1>
                <p class="text-secondary"><?= htmlspecialchars($reservation['first_name'] . ' ' . $reservation['last_name'], ENT_QUOTES, 'UTF-8') ?></p>
                <form method="post">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                    <div class="mb-3">
                        <label class="form-label"><?= htmlspecialchars($translator->get('reservation.start'), ENT_QUOTES, 'UTF-8') ?></label>
                        <input type="datetime-local" class="form-control <?= isset($errors['start_datetime']) ? 'is-invalid' : '' ?>" name="start_datetime" value="<?= htmlspecialchars((string) ($old['start_datetime'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        <?php if (isset($errors['start_datetime'])): ?><div class="invalid-feedback"><?= htmlspecialchars($translator->get($errors['start_datetime']), ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= htmlspecialchars($translator->get('reservation.end'), ENT_QUOTES, 'UTF-8') ?></label>
                        <input type="datetime-local" class="form-control <?= isset($errors['end_datetime']) ? 'is-invalid' : '' ?>" name="end_datetime" value="<?= htmlspecialchars((string) ($old['end_datetime'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        <?php if (isset($errors['end_datetime'])): ?><div class="invalid-feedback"><?= htmlspecialchars($translator->get($errors['end_datetime']), ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
                    </div>
                    <?php if (isset($errors['general'])): ?><div class="alert alert-danger"><?= htmlspecialchars($translator->get($errors['general']), ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
                    <button class="btn btn-primary" type="submit"><?= htmlspecialchars($translator->get('reservation.save_changes'), ENT_QUOTES, 'UTF-8') ?></button>
                </form>
            </div>
        </div>
    </div>
</div>
