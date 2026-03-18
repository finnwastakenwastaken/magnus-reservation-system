<?php declare(strict_types=1); ?>
<div class="card shadow-sm border-0">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="h3 mb-0"><?= htmlspecialchars($translator->get('messages.inbox'), ENT_QUOTES, 'UTF-8') ?></h1>
            <a class="btn btn-primary" href="/messages/compose"><?= htmlspecialchars($translator->get('messages.compose'), ENT_QUOTES, 'UTF-8') ?></a>
        </div>
        <?php if (($items ?? []) === []): ?>
            <p class="text-secondary mb-0"><?= htmlspecialchars($translator->get('messages.none'), ENT_QUOTES, 'UTF-8') ?></p>
        <?php else: ?>
            <div class="list-group list-group-flush">
                <?php foreach ($items as $message): ?>
                    <div class="list-group-item px-0">
                        <div class="d-flex justify-content-between gap-3 flex-wrap">
                            <div>
                                <div class="fw-semibold"><?= htmlspecialchars($message['subject'], ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="small text-secondary"><?= htmlspecialchars($message['sender_first_name'] . ' ' . strtoupper(substr($message['sender_last_name'], 0, 1)) . '.', ENT_QUOTES, 'UTF-8') ?></div>
                            </div>
                            <div class="small text-secondary"><?= htmlspecialchars(date('d-m-Y H:i', strtotime($message['created_at'])), ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                        <p class="mt-2 mb-0"><?= nl2br(htmlspecialchars($message['body'], ENT_QUOTES, 'UTF-8')) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php require BASE_PATH . '/src/Views/partials/pagination.php'; ?>
    </div>
</div>
