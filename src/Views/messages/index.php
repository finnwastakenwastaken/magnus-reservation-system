<?php declare(strict_types=1); ?>
<div class="row g-4">
    <div class="col-lg-5 col-xl-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3 gap-3">
                    <div>
                        <h1 class="h3 mb-1"><?= htmlspecialchars($translator->get('messages.title'), ENT_QUOTES, 'UTF-8') ?></h1>
                        <p class="text-secondary mb-0"><?= htmlspecialchars($translator->get('messages.thread_list_notice'), ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                    <a class="btn btn-primary" href="/messages/compose"><?= htmlspecialchars($translator->get('messages.compose'), ENT_QUOTES, 'UTF-8') ?></a>
                </div>
                <?php if (($items ?? []) === []): ?>
                    <p class="text-secondary mb-0"><?= htmlspecialchars($translator->get('messages.none'), ENT_QUOTES, 'UTF-8') ?></p>
                <?php else: ?>
                    <div class="conversation-list">
                        <?php foreach ($items as $summary): ?>
                            <?php
                            $hasResidentName = !empty($summary['first_name']);
                            $displayName = $hasResidentName
                                ? $summary['first_name'] . ' ' . strtoupper(substr((string) ($summary['last_name'] ?? ''), 0, 1)) . '.'
                                : $translator->get('messages.deleted_user');
                            ?>
                            <a class="conversation-list-item" href="/messages/thread/<?= (int) $summary['other_user_id'] ?>">
                                <div class="d-flex justify-content-between gap-3">
                                    <div class="fw-semibold"><?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?></div>
                                    <div class="small text-secondary"><?= htmlspecialchars(date('d-m H:i', strtotime($summary['last_message_at'])), ENT_QUOTES, 'UTF-8') ?></div>
                                </div>
                                <div class="small text-secondary mt-1"><?= htmlspecialchars((string) ($summary['latest_subject'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="small text-secondary text-truncate"><?= htmlspecialchars((string) ($summary['latest_body'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                                <?php if ((int) ($summary['unread_count'] ?? 0) > 0): ?>
                                    <span class="badge text-bg-primary mt-2"><?= (int) $summary['unread_count'] ?></span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php require BASE_PATH . '/src/Views/partials/pagination.php'; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-7 col-xl-8">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body d-flex align-items-center justify-content-center text-center">
                <div>
                    <h2 class="h4 mb-2"><?= htmlspecialchars($translator->get('messages.open_conversation'), ENT_QUOTES, 'UTF-8') ?></h2>
                    <p class="text-secondary mb-0"><?= htmlspecialchars($translator->get('messages.open_conversation_notice'), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
            </div>
        </div>
    </div>
</div>
