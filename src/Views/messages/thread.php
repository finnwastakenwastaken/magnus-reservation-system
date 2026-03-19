<?php declare(strict_types=1);

use App\Core\Auth;
use App\Core\Csrf;

$errors ??= [];
$old ??= [];
$threadMessages = $thread['messages'] ?? [];
$otherUser = $thread['other_user'] ?? [];
$currentUser = Auth::user();
?>
<div class="row g-4">
    <div class="col-lg-4 col-xl-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3 gap-3">
                    <div>
                        <h1 class="h3 mb-1"><?= htmlspecialchars($translator->get('messages.title'), ENT_QUOTES, 'UTF-8') ?></h1>
                        <p class="text-secondary mb-0"><?= htmlspecialchars($translator->get('messages.thread_list_notice'), ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                    <a class="btn btn-primary btn-sm" href="/messages/compose"><?= htmlspecialchars($translator->get('messages.compose'), ENT_QUOTES, 'UTF-8') ?></a>
                </div>
                <div class="conversation-list">
                    <?php foreach (($items ?? []) as $summary): ?>
                        <?php
                        $hasResidentName = !empty($summary['first_name']);
                        $displayName = $hasResidentName
                            ? $summary['first_name'] . ' ' . strtoupper(substr((string) ($summary['last_name'] ?? ''), 0, 1)) . '.'
                            : $translator->get('messages.deleted_user');
                        ?>
                        <a class="conversation-list-item <?= (int) $summary['other_user_id'] === (int) ($activeThreadUserId ?? 0) ? 'active' : '' ?>" href="/messages/thread/<?= (int) $summary['other_user_id'] ?>">
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
                <?php require BASE_PATH . '/src/Views/partials/pagination.php'; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-8 col-xl-8">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body d-flex flex-column gap-3">
                <div class="conversation-header">
                    <div>
                        <?php
                        $otherUserDisplayName = !empty($otherUser['first_name'])
                            ? $otherUser['first_name'] . ' ' . strtoupper(substr((string) ($otherUser['last_name'] ?? ''), 0, 1)) . '.'
                            : $translator->get('messages.deleted_user');
                        ?>
                        <h2 class="h4 mb-1"><?= htmlspecialchars($otherUserDisplayName, ENT_QUOTES, 'UTF-8') ?></h2>
                        <div class="text-secondary small"><?= htmlspecialchars((string) ($thread['subject'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                </div>
                <div class="conversation-thread flex-grow-1">
                    <?php foreach ($threadMessages as $message): ?>
                        <?php $isOwn = (int) $message['sender_user_id'] === (int) $currentUser['id']; ?>
                        <div class="conversation-bubble <?= $isOwn ? 'conversation-bubble-outgoing' : 'conversation-bubble-incoming' ?>">
                            <div class="small text-secondary mb-1">
                                <?= htmlspecialchars($isOwn ? $translator->get('reservation.you') : (($message['sender_first_name'] ?? $translator->get('messages.deleted_user')) . ' ' . strtoupper(substr((string) ($message['sender_last_name'] ?? ''), 0, 1)) . '.'), ENT_QUOTES, 'UTF-8') ?>
                                &middot;
                                <?= htmlspecialchars(date('d-m-Y H:i', strtotime($message['created_at'])), ENT_QUOTES, 'UTF-8') ?>
                            </div>
                            <?php if ((string) $message['subject'] !== ''): ?>
                                <div class="fw-semibold mb-1"><?= htmlspecialchars($message['subject'], ENT_QUOTES, 'UTF-8') ?></div>
                            <?php endif; ?>
                            <div><?= nl2br(htmlspecialchars($message['body'], ENT_QUOTES, 'UTF-8')) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <form method="post" class="conversation-reply-box">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="subject" value="<?= htmlspecialchars((string) ($thread['subject'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    <div class="mb-3">
                        <label class="form-label"><?= htmlspecialchars($translator->get('messages.reply_label'), ENT_QUOTES, 'UTF-8') ?></label>
                        <textarea class="form-control <?= isset($errors['body']) ? 'is-invalid' : '' ?>" name="body" rows="4"><?= htmlspecialchars((string) ($old['body'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                        <?php if (isset($errors['body'])): ?><div class="invalid-feedback"><?= htmlspecialchars($translator->get($errors['body']), ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
                    </div>
                    <p class="small text-secondary"><?= htmlspecialchars($translator->get('messages.notification_notice'), ENT_QUOTES, 'UTF-8') ?></p>
                    <?php require BASE_PATH . '/src/Views/partials/turnstile.php'; ?>
                    <button class="btn btn-primary" type="submit"><?= htmlspecialchars($translator->get('messages.reply_send'), ENT_QUOTES, 'UTF-8') ?></button>
                </form>
            </div>
        </div>
    </div>
</div>
