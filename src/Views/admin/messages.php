<?php declare(strict_types=1);

use App\Core\Csrf;
?>
<div class="card shadow-sm border-0">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h1 class="h3 mb-1"><?= htmlspecialchars($translator->get('admin.messages_oversight'), ENT_QUOTES, 'UTF-8') ?></h1>
                <p class="text-secondary mb-0"><?= htmlspecialchars($translator->get('admin.messages_oversight_notice'), ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <?php if (\App\Core\Auth::hasPermission(\App\Security\Permissions::MESSAGES_BROADCAST)): ?>
                <a class="btn btn-primary" href="/admin/messages/broadcast"><?= htmlspecialchars($translator->get('admin.broadcast_messages'), ENT_QUOTES, 'UTF-8') ?></a>
            <?php endif; ?>
        </div>
        <div class="alert alert-warning">
            <?= htmlspecialchars($translator->get('admin.messages_reason_notice'), ENT_QUOTES, 'UTF-8') ?>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                <tr>
                    <th><?= htmlspecialchars($translator->get('admin.conversation'), ENT_QUOTES, 'UTF-8') ?></th>
                    <th><?= htmlspecialchars($translator->get('messages.subject'), ENT_QUOTES, 'UTF-8') ?></th>
                    <th><?= htmlspecialchars($translator->get('admin.message_count'), ENT_QUOTES, 'UTF-8') ?></th>
                    <th><?= htmlspecialchars($translator->get('common.created_at'), ENT_QUOTES, 'UTF-8') ?></th>
                    <th><?= htmlspecialchars($translator->get('reservation.actions'), ENT_QUOTES, 'UTF-8') ?></th>
                </tr>
                </thead>
                <tbody>
                <?php if (($items ?? []) === []): ?>
                    <tr><td colspan="5" class="text-center text-secondary py-4"><?= htmlspecialchars($translator->get('common.no_data'), ENT_QUOTES, 'UTF-8') ?></td></tr>
                <?php endif; ?>
                <?php foreach (($items ?? []) as $conversation): ?>
                    <tr>
                        <td>
                            <div class="fw-semibold">
                                <?= htmlspecialchars(trim(($conversation['user_a_first_name'] ?? $translator->get('common.no_data')) . ' ' . ($conversation['user_a_last_name'] ?? '')), ENT_QUOTES, 'UTF-8') ?>
                            </div>
                            <div class="small text-secondary">
                                <?= htmlspecialchars(trim(($conversation['user_b_first_name'] ?? $translator->get('common.no_data')) . ' ' . ($conversation['user_b_last_name'] ?? '')), ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        </td>
                        <td><?= htmlspecialchars((string) ($conversation['latest_subject'] ?: '-'), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= (int) $conversation['total_messages'] ?></td>
                        <td><?= htmlspecialchars(date('d-m-Y H:i', strtotime($conversation['last_message_at'])), ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <a class="btn btn-sm btn-outline-primary" href="/admin/messages/thread/<?= (int) $conversation['user_a_id'] ?>/<?= (int) $conversation['user_b_id'] ?>">
                                <?= htmlspecialchars($translator->get('admin.request_message_access'), ENT_QUOTES, 'UTF-8') ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php require BASE_PATH . '/src/Views/partials/pagination.php'; ?>
    </div>
</div>
