<?php declare(strict_types=1);

$monthValue = $selectedMonth->format('Y-m');
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1"><?= htmlspecialchars($translator->get('reservation.public_title'), ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="text-secondary mb-0"><?= htmlspecialchars($translator->get('reservation.public_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
    </div>
    <form method="get">
        <label class="visually-hidden" for="month"><?= htmlspecialchars($translator->get('reservation.month'), ENT_QUOTES, 'UTF-8') ?></label>
        <input id="month" type="month" class="form-control" name="month" value="<?= htmlspecialchars($monthValue, ENT_QUOTES, 'UTF-8') ?>" onchange="this.form.submit()">
    </form>
</div>
<div class="card shadow-sm border-0">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
            <tr>
                <th><?= htmlspecialchars($translator->get('reservation.start'), ENT_QUOTES, 'UTF-8') ?></th>
                <th><?= htmlspecialchars($translator->get('reservation.end'), ENT_QUOTES, 'UTF-8') ?></th>
                <th><?= htmlspecialchars($translator->get('reservation.status'), ENT_QUOTES, 'UTF-8') ?></th>
            </tr>
            </thead>
            <tbody>
            <?php if (($reservations ?? []) === []): ?>
                <tr><td colspan="3" class="text-center text-secondary py-4"><?= htmlspecialchars($translator->get('reservation.none'), ENT_QUOTES, 'UTF-8') ?></td></tr>
            <?php endif; ?>
            <?php foreach (($reservations ?? []) as $reservation): ?>
                <tr>
                    <td><?= htmlspecialchars(date('d-m-Y H:i', strtotime($reservation['start_datetime'])), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars(date('d-m-Y H:i', strtotime($reservation['end_datetime'])), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><span class="badge text-bg-secondary"><?= htmlspecialchars($translator->get('reservation.unavailable'), ENT_QUOTES, 'UTF-8') ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
