<?php

declare(strict_types=1);

use App\Core\Csrf;

$statusLog = $update['status_log'] ?? null;
$latest = $update['latest'] ?? null;
?>
<div class="row g-4">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
                    <div>
                        <h1 class="h3 mb-1"><?= htmlspecialchars($translator->get('admin.updates'), ENT_QUOTES, 'UTF-8') ?></h1>
                        <p class="text-secondary mb-0"><?= htmlspecialchars($translator->get('admin.current_version', ['version' => $update['current_version']]), ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                    <span class="badge text-bg-<?= $update['supported'] ? 'success' : 'warning' ?>">
                        <?= htmlspecialchars($translator->get('admin.update_strategy', ['strategy' => $update['strategy']]), ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </div>

                <?php if (!$update['supported'] && $update['warning']): ?>
                    <div class="alert alert-warning"><?= htmlspecialchars($update['warning'], ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>

                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="border rounded p-3 h-100">
                            <div class="small text-secondary"><?= htmlspecialchars($translator->get('admin.current_version_label'), ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="fw-semibold"><?= htmlspecialchars((string) $update['current_version'], ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-3 h-100">
                            <div class="small text-secondary"><?= htmlspecialchars($translator->get('admin.latest_version_label'), ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="fw-semibold"><?= htmlspecialchars((string) ($latest['version'] ?? $translator->get('common.no_data')), ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-3 h-100">
                            <div class="small text-secondary"><?= htmlspecialchars($translator->get('admin.maintenance_status'), ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="fw-semibold"><?= htmlspecialchars($update['maintenance_enabled'] ? 'ON' : 'OFF', ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-2">
                    <form method="post" action="/admin/updates/check">
                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                        <button class="btn btn-outline-primary" type="submit"><?= htmlspecialchars($translator->get('admin.check_updates'), ENT_QUOTES, 'UTF-8') ?></button>
                    </form>
                    <form method="post" action="/admin/updates/install" onsubmit="return confirm('<?= htmlspecialchars($translator->get('admin.confirm_install_update'), ENT_QUOTES, 'UTF-8') ?>');">
                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                        <button class="btn btn-primary" type="submit" <?= !$update['supported'] ? 'disabled' : '' ?>><?= htmlspecialchars($translator->get('admin.install_update'), ENT_QUOTES, 'UTF-8') ?></button>
                    </form>
                    <form method="post" action="/admin/updates/rollback" onsubmit="return confirm('<?= htmlspecialchars($translator->get('admin.confirm_rollback'), ENT_QUOTES, 'UTF-8') ?>');">
                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                        <button class="btn btn-outline-warning" type="submit"><?= htmlspecialchars($translator->get('admin.rollback_update'), ENT_QUOTES, 'UTF-8') ?></button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <h2 class="h5 mb-3"><?= htmlspecialchars($translator->get('admin.release_notes'), ENT_QUOTES, 'UTF-8') ?></h2>
                <pre class="small bg-body-tertiary border rounded p-3 mb-0" style="white-space: pre-wrap;"><?= htmlspecialchars((string) ($latest['release_notes'] ?? $update['changelog'] ?? ''), ENT_QUOTES, 'UTF-8') ?></pre>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <h2 class="h5 mb-3"><?= htmlspecialchars($translator->get('admin.last_update_status'), ENT_QUOTES, 'UTF-8') ?></h2>
                <pre class="small bg-body-tertiary border rounded p-3 mb-0" style="white-space: pre-wrap;"><?= htmlspecialchars($statusLog ? json_encode($statusLog, JSON_PRETTY_PRINT) : $translator->get('common.no_data'), ENT_QUOTES, 'UTF-8') ?></pre>
            </div>
        </div>
    </div>
</div>
