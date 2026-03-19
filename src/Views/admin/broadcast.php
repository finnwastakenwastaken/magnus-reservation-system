<?php declare(strict_types=1);

use App\Core\Csrf;

$old ??= [];
$errors ??= [];
?>
<div class="row justify-content-center">
    <div class="col-xl-8">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center gap-3 mb-4">
                    <div>
                        <h1 class="h3 mb-1"><?= htmlspecialchars($translator->get('admin.broadcast_messages'), ENT_QUOTES, 'UTF-8') ?></h1>
                        <p class="text-secondary mb-0"><?= htmlspecialchars($translator->get('admin.broadcast_notice'), ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                    <a class="btn btn-outline-light" href="/admin/messages"><?= htmlspecialchars($translator->get('admin.messages_oversight'), ENT_QUOTES, 'UTF-8') ?></a>
                </div>
                <form method="post">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                    <div class="mb-3">
                        <label class="form-label"><?= htmlspecialchars($translator->get('admin.broadcast_scope'), ENT_QUOTES, 'UTF-8') ?></label>
                        <select class="form-select <?= isset($errors['target_scope']) ? 'is-invalid' : '' ?>" name="target_scope">
                            <option value="all" <?= ($old['target_scope'] ?? 'all') === 'all' ? 'selected' : '' ?>><?= htmlspecialchars($translator->get('admin.broadcast_scope_all'), ENT_QUOTES, 'UTF-8') ?></option>
                            <option value="roles" <?= ($old['target_scope'] ?? '') === 'roles' ? 'selected' : '' ?>><?= htmlspecialchars($translator->get('admin.broadcast_scope_roles'), ENT_QUOTES, 'UTF-8') ?></option>
                        </select>
                        <?php if (isset($errors['target_scope'])): ?><div class="invalid-feedback"><?= htmlspecialchars($translator->get($errors['target_scope']), ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= htmlspecialchars($translator->get('admin.broadcast_roles'), ENT_QUOTES, 'UTF-8') ?></label>
                        <div class="row g-2">
                            <?php foreach (($roles ?? []) as $role): ?>
                                <div class="col-md-4">
                                    <label class="border rounded p-3 d-block h-100">
                                        <input class="form-check-input me-2" type="checkbox" name="role_ids[]" value="<?= (int) $role['id'] ?>" <?= in_array((string) $role['id'], array_map('strval', (array) ($old['role_ids'] ?? [])), true) ? 'checked' : '' ?>>
                                        <span class="fw-semibold"><?= htmlspecialchars($role['name'], ENT_QUOTES, 'UTF-8') ?></span>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= htmlspecialchars($translator->get('messages.subject'), ENT_QUOTES, 'UTF-8') ?></label>
                        <input class="form-control <?= isset($errors['subject']) ? 'is-invalid' : '' ?>" name="subject" value="<?= htmlspecialchars((string) ($old['subject'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        <?php if (isset($errors['subject'])): ?><div class="invalid-feedback"><?= htmlspecialchars($translator->get($errors['subject']), ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= htmlspecialchars($translator->get('messages.body'), ENT_QUOTES, 'UTF-8') ?></label>
                        <textarea class="form-control <?= isset($errors['body']) ? 'is-invalid' : '' ?>" name="body" rows="8"><?= htmlspecialchars((string) ($old['body'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                        <?php if (isset($errors['body'])): ?><div class="invalid-feedback"><?= htmlspecialchars($translator->get($errors['body']), ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
                    </div>
                    <button class="btn btn-primary" type="submit"><?= htmlspecialchars($translator->get('admin.broadcast_send'), ENT_QUOTES, 'UTF-8') ?></button>
                </form>
            </div>
        </div>
    </div>
</div>
