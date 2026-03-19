<?php declare(strict_types=1);

use App\Core\Csrf;

$errorKey ??= null;
$accessApproved ??= false;
$accessReason ??= '';
?>
<div class="row g-4">
    <div class="col-xl-4">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h1 class="h4 mb-3"><?= htmlspecialchars($translator->get('admin.private_thread_access'), ENT_QUOTES, 'UTF-8') ?></h1>
                <p class="text-secondary mb-3"><?= htmlspecialchars($translator->get('admin.messages_oversight_notice'), ENT_QUOTES, 'UTF-8') ?></p>
                <div class="mb-3">
                    <div class="fw-semibold"><?= htmlspecialchars(trim(($thread['user_a']['first_name'] ?? '') . ' ' . ($thread['user_a']['last_name'] ?? '')), ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="small text-secondary"><?= htmlspecialchars(trim(($thread['user_b']['first_name'] ?? '') . ' ' . ($thread['user_b']['last_name'] ?? '')), ENT_QUOTES, 'UTF-8') ?></div>
                </div>
                <div class="alert alert-warning small"><?= htmlspecialchars($translator->get('admin.messages_reason_notice'), ENT_QUOTES, 'UTF-8') ?></div>
                <form method="post">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                    <div class="mb-3">
                        <label class="form-label"><?= htmlspecialchars($translator->get('admin.access_reason'), ENT_QUOTES, 'UTF-8') ?></label>
                        <textarea class="form-control <?= $errorKey ? 'is-invalid' : '' ?>" name="access_reason" rows="5"><?= htmlspecialchars($accessReason, ENT_QUOTES, 'UTF-8') ?></textarea>
                        <?php if ($errorKey): ?><div class="invalid-feedback"><?= htmlspecialchars($translator->get($errorKey), ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
                    </div>
                    <button class="btn btn-primary" type="submit"><?= htmlspecialchars($translator->get('admin.view_thread_with_reason'), ENT_QUOTES, 'UTF-8') ?></button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-xl-8">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center gap-3 mb-3">
                    <h2 class="h4 mb-0"><?= htmlspecialchars($translator->get('admin.conversation'), ENT_QUOTES, 'UTF-8') ?></h2>
                    <a class="btn btn-outline-light btn-sm" href="/admin/messages"><?= htmlspecialchars($translator->get('common.cancel'), ENT_QUOTES, 'UTF-8') ?></a>
                </div>
                <?php if (!$accessApproved): ?>
                    <div class="text-secondary"><?= htmlspecialchars($translator->get('admin.thread_hidden_until_reason'), ENT_QUOTES, 'UTF-8') ?></div>
                <?php else: ?>
                    <div class="conversation-thread">
                        <?php foreach (($thread['messages'] ?? []) as $message): ?>
                            <div class="conversation-bubble <?= (int) $message['sender_user_id'] === (int) $thread['user_a']['id'] ? 'conversation-bubble-outgoing' : 'conversation-bubble-incoming' ?>">
                                <div class="small text-secondary mb-1">
                                    <?= htmlspecialchars(trim(($message['sender_first_name'] ?? '') . ' ' . ($message['sender_last_name'] ?? '')), ENT_QUOTES, 'UTF-8') ?>
                                    &middot;
                                    <?= htmlspecialchars(date('d-m-Y H:i', strtotime($message['created_at'])), ENT_QUOTES, 'UTF-8') ?>
                                </div>
                                <div class="fw-semibold mb-1"><?= htmlspecialchars($message['subject'], ENT_QUOTES, 'UTF-8') ?></div>
                                <div><?= nl2br(htmlspecialchars($message['body'], ENT_QUOTES, 'UTF-8')) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
