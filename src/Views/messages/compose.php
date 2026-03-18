<?php declare(strict_types=1);

use App\Core\Csrf;

$old ??= [];
$errors ??= [];
$users ??= [];
?>
<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <h1 class="h3 mb-4"><?= htmlspecialchars($translator->get('messages.compose'), ENT_QUOTES, 'UTF-8') ?></h1>
                <form method="post">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                    <div class="mb-3">
                        <label class="form-label"><?= htmlspecialchars($translator->get('messages.recipient'), ENT_QUOTES, 'UTF-8') ?></label>
                        <select class="form-select <?= isset($errors['recipient_user_id']) ? 'is-invalid' : '' ?>" name="recipient_user_id">
                            <option value="">-</option>
                            <?php foreach ($users as $recipient): ?>
                                <option value="<?= (int) $recipient['id'] ?>" <?= (string) ($old['recipient_user_id'] ?? '') === (string) $recipient['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($recipient['first_name'] . ' ' . strtoupper(substr($recipient['last_name'], 0, 1)) . '. - ' . $recipient['apartment_number'], ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['recipient_user_id'])): ?><div class="invalid-feedback"><?= htmlspecialchars($translator->get($errors['recipient_user_id']), ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= htmlspecialchars($translator->get('messages.subject'), ENT_QUOTES, 'UTF-8') ?></label>
                        <input class="form-control <?= isset($errors['subject']) ? 'is-invalid' : '' ?>" name="subject" value="<?= htmlspecialchars((string) ($old['subject'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        <?php if (isset($errors['subject'])): ?><div class="invalid-feedback"><?= htmlspecialchars($translator->get($errors['subject']), ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= htmlspecialchars($translator->get('messages.body'), ENT_QUOTES, 'UTF-8') ?></label>
                        <textarea class="form-control <?= isset($errors['body']) ? 'is-invalid' : '' ?>" name="body" rows="7"><?= htmlspecialchars((string) ($old['body'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                        <?php if (isset($errors['body'])): ?><div class="invalid-feedback"><?= htmlspecialchars($translator->get($errors['body']), ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
                    </div>
                    <?php require BASE_PATH . '/src/Views/partials/turnstile.php'; ?>
                    <button class="btn btn-primary" type="submit"><?= htmlspecialchars($translator->get('messages.send'), ENT_QUOTES, 'UTF-8') ?></button>
                </form>
            </div>
        </div>
    </div>
</div>
