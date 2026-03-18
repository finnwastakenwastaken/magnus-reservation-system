<?php declare(strict_types=1);

use App\Core\Auth;
use App\Core\Csrf;

$monthValue = $selectedMonth->format('Y-m');
$currentUser = Auth::user();
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1"><?= htmlspecialchars($translator->get('reservation.calendar'), ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="text-secondary mb-0"><?= htmlspecialchars($selectedMonth->format('F Y'), ENT_QUOTES, 'UTF-8') ?></p>
    </div>
    <div class="d-flex gap-2">
        <form method="get">
            <label class="visually-hidden" for="month"><?= htmlspecialchars($translator->get('reservation.month'), ENT_QUOTES, 'UTF-8') ?></label>
            <input id="month" type="month" class="form-control" name="month" value="<?= htmlspecialchars($monthValue, ENT_QUOTES, 'UTF-8') ?>" onchange="this.form.submit()">
        </form>
        <a class="btn btn-primary" href="/reservations/create"><?= htmlspecialchars($translator->get('reservation.create'), ENT_QUOTES, 'UTF-8') ?></a>
    </div>
</div>
<div class="card shadow-sm border-0">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
            <tr>
                <th><?= htmlspecialchars($translator->get('reservation.start'), ENT_QUOTES, 'UTF-8') ?></th>
                <th><?= htmlspecialchars($translator->get('reservation.end'), ENT_QUOTES, 'UTF-8') ?></th>
                <th><?= htmlspecialchars($translator->get('reservation.booked_by'), ENT_QUOTES, 'UTF-8') ?></th>
                <th><?= htmlspecialchars($translator->get('reservation.status'), ENT_QUOTES, 'UTF-8') ?></th>
                <th><?= htmlspecialchars($translator->get('reservation.actions'), ENT_QUOTES, 'UTF-8') ?></th>
            </tr>
            </thead>
            <tbody>
            <?php if ($reservations === []): ?>
                <tr><td colspan="5" class="text-center text-secondary py-4"><?= htmlspecialchars($translator->get('reservation.none'), ENT_QUOTES, 'UTF-8') ?></td></tr>
            <?php endif; ?>
            <?php foreach ($reservations as $reservation): ?>
                <tr>
                    <td><?= htmlspecialchars(date('d-m-Y H:i', strtotime($reservation['start_datetime'])), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars(date('d-m-Y H:i', strtotime($reservation['end_datetime'])), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($reservation['first_name'] . ' ' . strtoupper(substr($reservation['last_name'], 0, 1)) . '.', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><span class="badge text-bg-success"><?= htmlspecialchars($reservation['status'], ENT_QUOTES, 'UTF-8') ?></span></td>
                    <td>
                        <?php if ((int) $reservation['user_id'] === (int) $currentUser['id']): ?>
                            <form method="post" action="/reservations/<?= (int) $reservation['id'] ?>/cancel">
                                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                                <button class="btn btn-sm btn-outline-danger" type="submit"><?= htmlspecialchars($translator->get('reservation.cancel'), ENT_QUOTES, 'UTF-8') ?></button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
