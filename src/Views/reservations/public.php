<?php declare(strict_types=1);

$payloadId = 'public-availability-calendar-payload';
$calendarPayload = [
    'feedUrl' => '/availability/feed',
    'locale' => $translator->locale(),
    'rules' => $calendarRules ?? [],
    'messages' => [
        'today' => $translator->get('reservation.today'),
        'day' => $translator->get('reservation.view_day'),
        'week' => $translator->get('reservation.view_week'),
        'list' => $translator->get('reservation.view_list'),
        'loading' => $translator->get('reservation.loading'),
        'noEvents' => $translator->get('reservation.no_events'),
        'genericError' => $translator->get('errors.500'),
    ],
];
?>
<div class="calendar-page">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h1 class="h2 mb-1"><?= htmlspecialchars($translator->get('reservation.public_title'), ENT_QUOTES, 'UTF-8') ?></h1>
            <p class="text-secondary mb-1"><?= htmlspecialchars($translator->get('reservation.public_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
            <p class="small text-secondary mb-0"><?= htmlspecialchars($translator->get('reservation.public_privacy_notice'), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <div class="app-stat-card">
            <div class="small text-uppercase text-secondary"><?= htmlspecialchars($translator->get('reservation.status'), ENT_QUOTES, 'UTF-8') ?></div>
            <div class="fw-semibold text-danger"><?= htmlspecialchars($translator->get('reservation.unavailable'), ENT_QUOTES, 'UTF-8') ?></div>
        </div>
    </div>

    <div class="card border-0 shadow-sm calendar-shell">
        <div class="card-body p-3 p-lg-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                <div>
                    <h2 class="h5 mb-1"><?= htmlspecialchars($translator->get('reservation.calendar_grid'), ENT_QUOTES, 'UTF-8') ?></h2>
                    <p class="text-secondary mb-0"><?= htmlspecialchars($translator->get('reservation.public_grid_notice'), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <div class="calendar-legend d-flex flex-wrap gap-3 small">
                    <span><i class="legend-swatch legend-reserved"></i><?= htmlspecialchars($translator->get('reservation.legend_reserved'), ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            </div>
            <div id="public-availability-calendar" class="reservation-calendar" data-calendar-payload="<?= htmlspecialchars($payloadId, ENT_QUOTES, 'UTF-8') ?>" data-calendar-public="1"></div>
            <script type="application/json" id="<?= htmlspecialchars($payloadId, ENT_QUOTES, 'UTF-8') ?>"><?= json_encode($calendarPayload, JSON_THROW_ON_ERROR) ?></script>
        </div>
    </div>
</div>
