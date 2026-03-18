<?php declare(strict_types=1);

use App\Core\Csrf;

$old ??= [];
$errors ??= [];
$role ??= null;
$selectedPermissions = $old['permissions'] ?? ($role['permissions'] ?? []);
$isProtected = $role !== null && (int) ($role['is_super_admin'] ?? 0) === 1;
?>
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <h1 class="h3 mb-4">
                    <?= htmlspecialchars($role ? $translator->get('admin.role_edit') : $translator->get('admin.role_create'), ENT_QUOTES, 'UTF-8') ?>
                </h1>
                <form method="post">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                    <div class="mb-3">
                        <label class="form-label"><?= htmlspecialchars($translator->get('admin.role_name'), ENT_QUOTES, 'UTF-8') ?></label>
                        <input class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>" name="name" value="<?= htmlspecialchars((string) ($old['name'] ?? $role['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        <?php if (isset($errors['name'])): ?><div class="invalid-feedback"><?= htmlspecialchars($translator->get($errors['name']), ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
                    </div>
                    <div class="mb-4">
                        <label class="form-label"><?= htmlspecialchars($translator->get('admin.role_description'), ENT_QUOTES, 'UTF-8') ?></label>
                        <textarea class="form-control <?= isset($errors['description']) ? 'is-invalid' : '' ?>" name="description" rows="3"><?= htmlspecialchars((string) ($old['description'] ?? $role['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                        <?php if (isset($errors['description'])): ?><div class="invalid-feedback"><?= htmlspecialchars($translator->get($errors['description']), ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
                    </div>
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label mb-0"><?= htmlspecialchars($translator->get('admin.permissions'), ENT_QUOTES, 'UTF-8') ?></label>
                            <?php if ($isProtected): ?>
                                <span class="badge text-bg-danger"><?= htmlspecialchars($translator->get('admin.role_super_admin_locked'), ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if (isset($errors['permissions'])): ?><div class="alert alert-danger py-2"><?= htmlspecialchars($translator->get($errors['permissions']), ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
                        <div class="row g-3">
                            <?php foreach (($permissions ?? []) as $permission): ?>
                                <div class="col-md-6">
                                    <label class="border rounded p-3 d-block bg-body-tertiary h-100">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="permissions[]" value="<?= htmlspecialchars($permission['code'], ENT_QUOTES, 'UTF-8') ?>" <?= in_array($permission['code'], $selectedPermissions, true) || $isProtected ? 'checked' : '' ?> <?= $isProtected ? 'disabled' : '' ?>>
                                            <span class="form-check-label fw-semibold"><?= htmlspecialchars($translator->get($permission['label_key']), ENT_QUOTES, 'UTF-8') ?></span>
                                        </div>
                                        <div class="small text-secondary mt-2"><?= htmlspecialchars($translator->get($permission['description_key']), ENT_QUOTES, 'UTF-8') ?></div>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-primary" type="submit"><?= htmlspecialchars($translator->get('admin.save'), ENT_QUOTES, 'UTF-8') ?></button>
                        <a class="btn btn-outline-secondary" href="/admin/roles"><?= htmlspecialchars($translator->get('common.cancel'), ENT_QUOTES, 'UTF-8') ?></a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
