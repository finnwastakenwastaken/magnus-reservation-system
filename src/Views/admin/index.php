<?php declare(strict_types=1); ?>
<div class="row g-4">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h1 class="h4 mb-1"><?= htmlspecialchars($translator->get('admin.title'), ENT_QUOTES, 'UTF-8') ?></h1>
                    <p class="text-secondary mb-0"><?= htmlspecialchars($translator->get('admin.current_version', ['version' => $appVersion]), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <a href="/admin/updates" class="btn btn-outline-primary"><?= htmlspecialchars($translator->get('admin.updates'), ENT_QUOTES, 'UTF-8') ?></a>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h1 class="h4 mb-0"><?= htmlspecialchars($translator->get('admin.pending'), ENT_QUOTES, 'UTF-8') ?></h1>
                    <a href="/admin/users?status=0" class="btn btn-sm btn-outline-primary"><?= htmlspecialchars($translator->get('admin.users'), ENT_QUOTES, 'UTF-8') ?></a>
                </div>
                <?php if ($pendingUsers === []): ?>
                    <p class="text-secondary mb-0"><?= htmlspecialchars($translator->get('common.no_data'), ENT_QUOTES, 'UTF-8') ?></p>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($pendingUsers as $user): ?>
                            <li class="list-group-item px-0">
                                <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name'] . ' (' . $user['apartment_number'] . ')', ENT_QUOTES, 'UTF-8') ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h4 mb-0"><?= htmlspecialchars($translator->get('admin.reservations'), ENT_QUOTES, 'UTF-8') ?></h2>
                    <a href="/admin/reservations" class="btn btn-sm btn-outline-primary"><?= htmlspecialchars($translator->get('reservation.title'), ENT_QUOTES, 'UTF-8') ?></a>
                </div>
                <?php if ($reservations === []): ?>
                    <p class="text-secondary mb-0"><?= htmlspecialchars($translator->get('common.no_data'), ENT_QUOTES, 'UTF-8') ?></p>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($reservations as $reservation): ?>
                            <li class="list-group-item px-0">
                                <?= htmlspecialchars($reservation['first_name'] . ' ' . $reservation['last_name'] . ' - ' . date('d-m-Y H:i', strtotime($reservation['start_datetime'])), ENT_QUOTES, 'UTF-8') ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
