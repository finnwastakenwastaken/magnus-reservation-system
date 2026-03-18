<?php declare(strict_types=1);

use App\Core\Csrf;
?>
<div class="card shadow-sm border-0">
    <div class="card-body">
        <h1 class="h3 mb-4"><?= htmlspecialchars($translator->get('admin.reservations'), ENT_QUOTES, 'UTF-8') ?></h1>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th><?= htmlspecialchars($translator->get('reservation.booked_by'), ENT_QUOTES, 'UTF-8') ?></th>
                    <th><?= htmlspecialchars($translator->get('auth.email'), ENT_QUOTES, 'UTF-8') ?></th>
                    <th><?= htmlspecialchars($translator->get('reservation.start'), ENT_QUOTES, 'UTF-8') ?></th>
                    <th><?= htmlspecialchars($translator->get('reservation.end'), ENT_QUOTES, 'UTF-8') ?></th>
                    <th><?= htmlspecialchars($translator->get('reservation.status'), ENT_QUOTES, 'UTF-8') ?></th>
                    <th><?= htmlspecialchars($translator->get('reservation.actions'), ENT_QUOTES, 'UTF-8') ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach (($items ?? []) as $reservation): ?>
                    <tr>
                        <td><?= (int) $reservation['id'] ?></td>
                        <td><?= htmlspecialchars($reservation['first_name'] . ' ' . $reservation['last_name'] . ' (' . $reservation['apartment_number'] . ')', ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($reservation['email'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars(date('d-m-Y H:i', strtotime($reservation['start_datetime'])), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars(date('d-m-Y H:i', strtotime($reservation['end_datetime'])), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($reservation['status'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <div class="d-flex gap-2">
                                <?php if (!empty($canManageReservations) && $reservation['status'] === 'active'): ?>
                                    <a class="btn btn-sm btn-outline-primary" href="/admin/reservations/<?= (int) $reservation['id'] ?>/edit"><?= htmlspecialchars($translator->get('reservation.edit'), ENT_QUOTES, 'UTF-8') ?></a>
                                    <form method="post" action="/admin/reservations/<?= (int) $reservation['id'] ?>/cancel">
                                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                                        <button class="btn btn-sm btn-outline-danger" type="submit"><?= htmlspecialchars($translator->get('reservation.cancel'), ENT_QUOTES, 'UTF-8') ?></button>
                                    </form>
                                <?php elseif ($reservation['status'] === 'active'): ?>
                                    <span class="text-secondary small"><?= htmlspecialchars($translator->get('admin.read_only'), ENT_QUOTES, 'UTF-8') ?></span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php require BASE_PATH . '/src/Views/partials/pagination.php'; ?>
    </div>
</div>
