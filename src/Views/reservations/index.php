<?php declare(strict_types=1);

use App\Core\Csrf;

$rules = $calendarRules ?? [];
$upcomingReservations ??= [];
$payloadId = 'reservation-calendar-payload';
$calendarPayload = [
    'feedUrl' => '/reservations/feed',
    'createUrl' => '/reservations/quick-create',
    'cancelUrlTemplate' => '/reservations/__ID__/cancel-quick',
    'csrfToken' => Csrf::token(),
    'locale' => $translator->locale(),
    'rules' => $rules,
    'messages' => [
        'selectPrompt' => $translator->get('reservation.drag_hint'),
        'modalTitle' => $translator->get('reservation.quick_create_title'),
        'save' => $translator->get('reservation.quick_create_submit'),
        'cancel' => $translator->get('common.cancel'),
        'createSuccess' => $translator->get('reservation.created'),
        'cancelConfirm' => $translator->get('reservation.cancel_confirm'),
        'cancelSuccess' => $translator->get('reservation.cancelled'),
        'genericError' => $translator->get('errors.500'),
    ],
];
?>
<div class="calendar-page">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h1 class="h2 mb-1"><?= htmlspecialchars($translator->get('reservation.calendar'), ENT_QUOTES, 'UTF-8') ?></h1>
            <p class="text-secondary mb-1"><?= htmlspecialchars($translator->get('reservation.calendar_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
            <p class="small text-secondary mb-0"><?= htmlspecialchars($translator->get('reservation.privacy_notice'), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a class="btn btn-outline-light" href="/reservations/create"><?= htmlspecialchars($translator->get('reservation.create_fallback'), ENT_QUOTES, 'UTF-8') ?></a>
            <a class="btn btn-primary" href="/messages/compose"><?= htmlspecialchars($translator->get('messages.compose'), ENT_QUOTES, 'UTF-8') ?></a>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="app-stat-card h-100">
                <div class="small text-uppercase text-secondary"><?= htmlspecialchars($translator->get('admin.booking_start_hour'), ENT_QUOTES, 'UTF-8') ?></div>
                <div class="display-6 fw-semibold"><?= sprintf('%02d:00', (int) ($rules['start_hour'] ?? 9)) ?></div>
                <div class="text-secondary"><?= htmlspecialchars($translator->get('reservation.availability_window'), ENT_QUOTES, 'UTF-8') ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="app-stat-card h-100">
                <div class="small text-uppercase text-secondary"><?= htmlspecialchars($translator->get('admin.max_week_hours'), ENT_QUOTES, 'UTF-8') ?></div>
                <div class="display-6 fw-semibold"><?= (int) ($rules['max_week_hours'] ?? 6) ?>h</div>
                <div class="text-secondary"><?= htmlspecialchars($translator->get('reservation.weekly_limit_notice'), ENT_QUOTES, 'UTF-8') ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="app-stat-card h-100">
                <div class="small text-uppercase text-secondary"><?= htmlspecialchars($translator->get('admin.max_month_hours'), ENT_QUOTES, 'UTF-8') ?></div>
                <div class="display-6 fw-semibold"><?= (int) ($rules['max_month_hours'] ?? 12) ?>h</div>
                <div class="text-secondary"><?= htmlspecialchars($translator->get('reservation.monthly_limit_notice'), ENT_QUOTES, 'UTF-8') ?></div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-9">
            <div class="card border-0 shadow-sm calendar-shell">
                <div class="card-body p-3 p-lg-4">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                        <div>
                            <h2 class="h5 mb-1"><?= htmlspecialchars($translator->get('reservation.calendar_grid'), ENT_QUOTES, 'UTF-8') ?></h2>
                            <p class="text-secondary mb-0"><?= htmlspecialchars($translator->get('reservation.drag_hint'), ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                        <div class="calendar-legend d-flex flex-wrap gap-3 small">
                            <span><i class="legend-swatch legend-own"></i><?= htmlspecialchars($translator->get('reservation.legend_yours'), ENT_QUOTES, 'UTF-8') ?></span>
                            <span><i class="legend-swatch legend-reserved"></i><?= htmlspecialchars($translator->get('reservation.legend_reserved'), ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    </div>
                    <div id="reservation-calendar" class="reservation-calendar" data-calendar-payload="<?= htmlspecialchars($payloadId, ENT_QUOTES, 'UTF-8') ?>"></div>
                    <script type="application/json" id="<?= htmlspecialchars($payloadId, ENT_QUOTES, 'UTF-8') ?>"><?= json_encode($calendarPayload, JSON_THROW_ON_ERROR) ?></script>
                </div>
            </div>
        </div>

        <div class="col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h5 mb-3"><?= htmlspecialchars($translator->get('reservation.upcoming_title'), ENT_QUOTES, 'UTF-8') ?></h2>
                    <?php if ($upcomingReservations === []): ?>
                        <p class="text-secondary mb-0"><?= htmlspecialchars($translator->get('reservation.none_upcoming'), ENT_QUOTES, 'UTF-8') ?></p>
                    <?php else: ?>
                        <div class="d-grid gap-3">
                            <?php foreach ($upcomingReservations as $reservation): ?>
                                <div class="upcoming-reservation-card">
                                    <div class="fw-semibold"><?= htmlspecialchars(date('D d M', strtotime($reservation['start_datetime'])), ENT_QUOTES, 'UTF-8') ?></div>
                                    <div class="text-secondary small"><?= htmlspecialchars(date('H:i', strtotime($reservation['start_datetime'])) . ' - ' . date('H:i', strtotime($reservation['end_datetime'])), ENT_QUOTES, 'UTF-8') ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="reservationQuickCreateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content bg-body border-secondary">
            <div class="modal-header border-secondary">
                <h2 class="modal-title h5 mb-0"><?= htmlspecialchars($translator->get('reservation.quick_create_title'), ENT_QUOTES, 'UTF-8') ?></h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger d-none" data-calendar-error></div>
                <p class="text-secondary small mb-3"><?= htmlspecialchars($translator->get('reservation.quick_create_help'), ENT_QUOTES, 'UTF-8') ?></p>
                <form id="reservationQuickCreateForm">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                    <div class="mb-3">
                        <label class="form-label"><?= htmlspecialchars($translator->get('reservation.start'), ENT_QUOTES, 'UTF-8') ?></label>
                        <input type="datetime-local" class="form-control" name="start_datetime" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= htmlspecialchars($translator->get('reservation.end'), ENT_QUOTES, 'UTF-8') ?></label>
                        <input type="datetime-local" class="form-control" name="end_datetime" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal"><?= htmlspecialchars($translator->get('common.cancel'), ENT_QUOTES, 'UTF-8') ?></button>
                <button type="button" class="btn btn-primary" data-calendar-submit><?= htmlspecialchars($translator->get('reservation.quick_create_submit'), ENT_QUOTES, 'UTF-8') ?></button>
            </div>
        </div>
    </div>
</div>
