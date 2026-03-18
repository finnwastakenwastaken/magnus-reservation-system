<?php declare(strict_types=1);

use App\Core\Csrf;
?>
<div class="card shadow-sm border-0">
    <div class="card-body">
        <h1 class="h3 mb-4"><?= htmlspecialchars($translator->get('admin.users'), ENT_QUOTES, 'UTF-8') ?></h1>
        <form method="get" class="row g-3 mb-4">
            <div class="col-md-5">
                <input class="form-control" name="search" value="<?= htmlspecialchars((string) ($search ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="<?= htmlspecialchars($translator->get('admin.search'), ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="col-md-3">
                <select class="form-select" name="status">
                    <option value="" <?= ($status ?? '') === '' ? 'selected' : '' ?>><?= htmlspecialchars($translator->get('common.all'), ENT_QUOTES, 'UTF-8') ?></option>
                    <option value="1" <?= (string) ($status ?? '') === '1' ? 'selected' : '' ?>><?= htmlspecialchars($translator->get('admin.active'), ENT_QUOTES, 'UTF-8') ?></option>
                    <option value="0" <?= (string) ($status ?? '') === '0' ? 'selected' : '' ?>><?= htmlspecialchars($translator->get('admin.inactive'), ENT_QUOTES, 'UTF-8') ?></option>
                </select>
            </div>
            <div class="col-md-2"><button class="btn btn-primary w-100" type="submit"><?= htmlspecialchars($translator->get('common.submit'), ENT_QUOTES, 'UTF-8') ?></button></div>
        </form>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th><?= htmlspecialchars($translator->get('auth.first_name'), ENT_QUOTES, 'UTF-8') ?></th>
                    <th><?= htmlspecialchars($translator->get('auth.email'), ENT_QUOTES, 'UTF-8') ?></th>
                    <th><?= htmlspecialchars($translator->get('auth.apartment'), ENT_QUOTES, 'UTF-8') ?></th>
                    <th><?= htmlspecialchars($translator->get('admin.filter_status'), ENT_QUOTES, 'UTF-8') ?></th>
                    <th><?= htmlspecialchars($translator->get('reservation.actions'), ENT_QUOTES, 'UTF-8') ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach (($items ?? []) as $user): ?>
                    <tr>
                        <td><?= (int) $user['id'] ?></td>
                        <td><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($user['apartment_number'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><span class="badge text-bg-<?= (int) $user['is_active'] === 1 ? 'success' : 'secondary' ?>"><?= htmlspecialchars((int) $user['is_active'] === 1 ? $translator->get('admin.active') : $translator->get('admin.inactive'), ENT_QUOTES, 'UTF-8') ?></span></td>
                        <td class="d-flex gap-2 flex-wrap">
                            <form method="post" action="/admin/users/<?= (int) $user['id'] ?>/reset-password">
                                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                                <button class="btn btn-sm btn-outline-warning" type="submit"><?= htmlspecialchars($translator->get('admin.password_reset'), ENT_QUOTES, 'UTF-8') ?></button>
                            </form>
                            <?php if ($user['role'] !== 'admin'): ?>
                                <form method="post" action="/admin/users/<?= (int) $user['id'] ?>/delete" onsubmit="return confirm('<?= htmlspecialchars($translator->get('admin.confirm_delete'), ENT_QUOTES, 'UTF-8') ?>');">
                                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                                    <button class="btn btn-sm btn-outline-danger" type="submit"><?= htmlspecialchars($translator->get('admin.delete'), ENT_QUOTES, 'UTF-8') ?></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php require BASE_PATH . '/src/Views/partials/pagination.php'; ?>
    </div>
</div>
