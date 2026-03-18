<?php declare(strict_types=1); ?>
<div class="card shadow-sm border-0">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-1"><?= htmlspecialchars($translator->get('admin.messages_oversight'), ENT_QUOTES, 'UTF-8') ?></h1>
                <p class="text-secondary mb-0"><?= htmlspecialchars($translator->get('admin.messages_oversight_notice'), ENT_QUOTES, 'UTF-8') ?></p>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                <tr>
                    <th><?= htmlspecialchars($translator->get('messages.subject'), ENT_QUOTES, 'UTF-8') ?></th>
                    <th><?= htmlspecialchars($translator->get('admin.from'), ENT_QUOTES, 'UTF-8') ?></th>
                    <th><?= htmlspecialchars($translator->get('admin.to'), ENT_QUOTES, 'UTF-8') ?></th>
                    <th><?= htmlspecialchars($translator->get('messages.body'), ENT_QUOTES, 'UTF-8') ?></th>
                    <th><?= htmlspecialchars($translator->get('common.created_at'), ENT_QUOTES, 'UTF-8') ?></th>
                </tr>
                </thead>
                <tbody>
                <?php if (($items ?? []) === []): ?>
                    <tr><td colspan="5" class="text-center text-secondary py-4"><?= htmlspecialchars($translator->get('common.no_data'), ENT_QUOTES, 'UTF-8') ?></td></tr>
                <?php endif; ?>
                <?php foreach (($items ?? []) as $message): ?>
                    <tr>
                        <td><?= htmlspecialchars($message['subject'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($message['sender_first_name'] . ' ' . $message['sender_last_name'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($message['recipient_first_name'] . ' ' . $message['recipient_last_name'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td style="min-width: 280px;"><?= nl2br(htmlspecialchars($message['body'], ENT_QUOTES, 'UTF-8')) ?></td>
                        <td><?= htmlspecialchars(date('d-m-Y H:i', strtotime($message['created_at'])), ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php require BASE_PATH . '/src/Views/partials/pagination.php'; ?>
    </div>
</div>
