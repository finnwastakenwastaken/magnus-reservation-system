<?php declare(strict_types=1); ?>
<div class="row g-4">
    <div class="col-12">
        <div class="card shadow-sm border-0"><div class="card-body">
            <div class="d-flex align-items-center gap-3">
                <?php if (!empty($user['profile_picture_path'])): ?>
                    <img src="<?= htmlspecialchars($user['profile_picture_path'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($user['first_name'], ENT_QUOTES, 'UTF-8') ?>" class="rounded-circle" style="width:64px;height:64px;object-fit:cover;">
                <?php else: ?>
                    <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold" style="width:64px;height:64px;">
                        <?= htmlspecialchars(strtoupper(substr((string) $user['first_name'], 0, 1)), ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endif; ?>
                <h1 class="h3 mb-0"><?= htmlspecialchars($translator->get('dashboard.welcome', ['name' => $user['first_name']]), ENT_QUOTES, 'UTF-8') ?></h1>
            </div>
        </div></div>
    </div>
    <div class="col-lg-6">
        <div class="card shadow-sm border-0 h-100"><div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h5 mb-0"><?= htmlspecialchars($translator->get('dashboard.upcoming'), ENT_QUOTES, 'UTF-8') ?></h2>
                <a href="/reservations/create" class="btn btn-sm btn-primary"><?= htmlspecialchars($translator->get('reservation.create'), ENT_QUOTES, 'UTF-8') ?></a>
            </div>
            <?php if ($upcomingReservations === []): ?>
                <p class="text-secondary mb-0"><?= htmlspecialchars($translator->get('common.no_data'), ENT_QUOTES, 'UTF-8') ?></p>
            <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($upcomingReservations as $reservation): ?>
                        <div class="list-group-item px-0">
                            <?= htmlspecialchars(date('d-m-Y H:i', strtotime($reservation['start_datetime'])), ENT_QUOTES, 'UTF-8') ?>
                            -
                            <?= htmlspecialchars(date('H:i', strtotime($reservation['end_datetime'])), ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div></div>
    </div>
    <div class="col-lg-6">
        <div class="card shadow-sm border-0 h-100"><div class="card-body">
            <h2 class="h5 mb-3"><?= htmlspecialchars($translator->get('dashboard.recent_messages'), ENT_QUOTES, 'UTF-8') ?></h2>
            <?php if ($inbox === []): ?>
                <p class="text-secondary mb-0"><?= htmlspecialchars($translator->get('messages.none'), ENT_QUOTES, 'UTF-8') ?></p>
            <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($inbox as $message): ?>
                        <div class="list-group-item px-0">
                            <div class="fw-semibold"><?= htmlspecialchars($message['subject'], ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="small text-secondary"><?= htmlspecialchars($message['sender_first_name'] . ' ' . substr($message['sender_last_name'], 0, 1) . '.', ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div></div>
    </div>
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h2 class="h5 mb-3"><?= htmlspecialchars($translator->get('dashboard.notifications'), ENT_QUOTES, 'UTF-8') ?></h2>
                <?php if (($notifications ?? []) === []): ?>
                    <p class="text-secondary mb-0"><?= htmlspecialchars($translator->get('common.no_data'), ENT_QUOTES, 'UTF-8') ?></p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($notifications as $notification): ?>
                            <div class="list-group-item px-0">
                                <div class="fw-semibold"><?= htmlspecialchars($notification['title'], ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="small text-secondary"><?= htmlspecialchars($notification['body'], ENT_QUOTES, 'UTF-8') ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
