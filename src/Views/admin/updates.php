<?php declare(strict_types=1);

$statusLog = $update['status_log'] ?? null;
$commands = $update['commands'] ?? [];
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
                    <span class="badge text-bg-warning">
                        <?= htmlspecialchars($translator->get('admin.update_strategy', ['strategy' => $update['strategy']]), ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </div>

                <div class="alert alert-warning mb-4">
                    <?= htmlspecialchars($translator->get('admin.updates_disabled'), ENT_QUOTES, 'UTF-8') ?>
                </div>

                <h2 class="h5 mb-3"><?= htmlspecialchars($translator->get('admin.release_notes'), ENT_QUOTES, 'UTF-8') ?></h2>
                <div class="rounded border bg-body-tertiary p-3 mb-4">
                    <p class="text-secondary mb-3"><?= htmlspecialchars($translator->get('admin.docker_update_notice'), ENT_QUOTES, 'UTF-8') ?></p>
                    <pre class="small mb-0" style="white-space: pre-wrap;"><?= htmlspecialchars(implode(PHP_EOL, $commands), ENT_QUOTES, 'UTF-8') ?></pre>
                </div>

                <div class="small text-secondary">
                    <?= htmlspecialchars($translator->get('admin.docker_update_volume_notice'), ENT_QUOTES, 'UTF-8') ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <h2 class="h5 mb-3"><?= htmlspecialchars($translator->get('admin.release_notes'), ENT_QUOTES, 'UTF-8') ?></h2>
                <pre class="small bg-body-tertiary border rounded p-3 mb-0" style="white-space: pre-wrap;"><?= htmlspecialchars((string) ($update['changelog'] ?? ''), ENT_QUOTES, 'UTF-8') ?></pre>
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
