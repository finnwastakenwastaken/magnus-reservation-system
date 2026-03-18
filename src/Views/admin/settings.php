<?php declare(strict_types=1);

use App\Core\Csrf;
?>
<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <h1 class="h3 mb-4"><?= htmlspecialchars($translator->get('admin.settings'), ENT_QUOTES, 'UTF-8') ?></h1>
                <form method="post">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                    <div class="mb-3">
                        <label class="form-label"><?= htmlspecialchars($translator->get('admin.booking_start_hour'), ENT_QUOTES, 'UTF-8') ?></label>
                        <input type="number" min="0" max="23" class="form-control" name="booking_start_hour" value="<?= htmlspecialchars((string) $settings['start_hour'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= htmlspecialchars($translator->get('admin.booking_end_hour'), ENT_QUOTES, 'UTF-8') ?></label>
                        <input type="number" min="1" max="24" class="form-control" name="booking_end_hour" value="<?= htmlspecialchars((string) $settings['end_hour'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= htmlspecialchars($translator->get('admin.max_week_hours'), ENT_QUOTES, 'UTF-8') ?></label>
                        <input type="number" min="1" max="168" class="form-control" name="max_hours_per_week" value="<?= htmlspecialchars((string) $settings['max_week_hours'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= htmlspecialchars($translator->get('admin.max_month_hours'), ENT_QUOTES, 'UTF-8') ?></label>
                        <input type="number" min="1" max="744" class="form-control" name="max_hours_per_month" value="<?= htmlspecialchars((string) $settings['max_month_hours'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <button class="btn btn-primary" type="submit"><?= htmlspecialchars($translator->get('admin.save'), ENT_QUOTES, 'UTF-8') ?></button>
                </form>
            </div>
        </div>
    </div>
</div>
