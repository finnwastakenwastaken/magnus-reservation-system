<?php declare(strict_types=1);

use App\Core\Csrf;
?>
<div class="card shadow-sm border-0">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h1 class="h3 mb-1"><?= htmlspecialchars($translator->get('admin.roles'), ENT_QUOTES, 'UTF-8') ?></h1>
                <p class="text-secondary mb-0"><?= htmlspecialchars($translator->get('admin.roles_notice'), ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <a class="btn btn-primary" href="/admin/roles/create"><?= htmlspecialchars($translator->get('admin.role_create'), ENT_QUOTES, 'UTF-8') ?></a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                <tr>
                    <th><?= htmlspecialchars($translator->get('admin.role'), ENT_QUOTES, 'UTF-8') ?></th>
                    <th><?= htmlspecialchars($translator->get('admin.role_description'), ENT_QUOTES, 'UTF-8') ?></th>
                    <th><?= htmlspecialchars($translator->get('admin.filter_status'), ENT_QUOTES, 'UTF-8') ?></th>
                    <th><?= htmlspecialchars($translator->get('admin.users'), ENT_QUOTES, 'UTF-8') ?></th>
                    <th><?= htmlspecialchars($translator->get('reservation.actions'), ENT_QUOTES, 'UTF-8') ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach (($roles ?? []) as $role): ?>
                    <?php $canEditProtectedRole = (int) ($role['is_super_admin'] ?? 0) !== 1 || !empty($currentUserIsSuperAdmin); ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?= htmlspecialchars($role['name'], ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="small text-secondary"><?= htmlspecialchars($role['slug'], ENT_QUOTES, 'UTF-8') ?></div>
                        </td>
                        <td><?= htmlspecialchars((string) ($role['description'] ?: '-'), ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <?php if ((int) $role['is_super_admin'] === 1): ?>
                                <span class="badge text-bg-danger"><?= htmlspecialchars($translator->get('admin.role_super_admin'), ENT_QUOTES, 'UTF-8') ?></span>
                            <?php elseif ((int) $role['is_system'] === 1): ?>
                                <span class="badge text-bg-secondary"><?= htmlspecialchars($translator->get('admin.role_system'), ENT_QUOTES, 'UTF-8') ?></span>
                            <?php else: ?>
                                <span class="badge text-bg-success"><?= htmlspecialchars($translator->get('admin.role_custom'), ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?= (int) $role['assigned_users'] ?></td>
                        <td>
                            <div class="d-flex gap-2">
                                <?php if ($canEditProtectedRole): ?>
                                    <a class="btn btn-sm btn-outline-primary" href="/admin/roles/<?= (int) $role['id'] ?>/edit"><?= htmlspecialchars($translator->get('reservation.edit'), ENT_QUOTES, 'UTF-8') ?></a>
                                <?php else: ?>
                                    <span class="text-secondary small"><?= htmlspecialchars($translator->get('admin.read_only'), ENT_QUOTES, 'UTF-8') ?></span>
                                <?php endif; ?>
                                <?php if ((int) $role['is_system'] !== 1 && (int) $role['is_super_admin'] !== 1): ?>
                                    <form method="post" action="/admin/roles/<?= (int) $role['id'] ?>/delete" onsubmit="return confirm('<?= htmlspecialchars($translator->get('admin.confirm_delete_role'), ENT_QUOTES, 'UTF-8') ?>');">
                                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                                        <button class="btn btn-sm btn-outline-danger" type="submit"><?= htmlspecialchars($translator->get('admin.delete'), ENT_QUOTES, 'UTF-8') ?></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
