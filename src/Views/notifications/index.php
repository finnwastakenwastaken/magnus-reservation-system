<?php declare(strict_types=1);

use App\Core\Csrf;
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1"><?= htmlspecialchars($translator->get('notifications.title'), ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="text-secondary mb-0"><?= htmlspecialchars($translator->get('notifications.subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
    </div>
    <form method="post" action="/notifications/mark-all-read">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
        <button class="btn btn-outline-primary" type="submit"><?= htmlspecialchars($translator->get('notifications.mark_all_read'), ENT_QUOTES, 'UTF-8') ?></button>
    </form>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <?php if (($items ?? []) === []): ?>
            <p class="text-secondary mb-0"><?= htmlspecialchars($translator->get('notifications.none'), ENT_QUOTES, 'UTF-8') ?></p>
        <?php else: ?>
            <div class="list-group list-group-flush">
                <?php foreach ($items as $notification): ?>
                    <div class="list-group-item px-0 bg-transparent">
                        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <span class="badge <?= (int) $notification['is_read'] === 1 ? 'text-bg-secondary' : 'text-bg-primary' ?>">
                                        <?= htmlspecialchars($translator->get((int) $notification['is_read'] === 1 ? 'notifications.read' : 'notifications.unread'), ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                    <span class="fw-semibold"><?= htmlspecialchars($notification['title'], ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                                <div class="text-secondary small mb-2"><?= htmlspecialchars(date('d-m-Y H:i', strtotime($notification['created_at'])), ENT_QUOTES, 'UTF-8') ?></div>
                                <p class="mb-2"><?= nl2br(htmlspecialchars($notification['body'], ENT_QUOTES, 'UTF-8')) ?></p>
                                <?php if (!empty($notification['link_url'])): ?>
                                    <a class="btn btn-sm btn-outline-light" href="<?= htmlspecialchars($notification['link_url'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($translator->get('notifications.open_link'), ENT_QUOTES, 'UTF-8') ?></a>
                                <?php endif; ?>
                            </div>
                            <?php if ((int) $notification['is_read'] === 0): ?>
                                <form method="post" action="/notifications/<?= (int) $notification['id'] ?>/read">
                                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                                    <button class="btn btn-sm btn-outline-primary" type="submit"><?= htmlspecialchars($translator->get('notifications.mark_read'), ENT_QUOTES, 'UTF-8') ?></button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php require BASE_PATH . '/src/Views/partials/pagination.php'; ?>
    </div>
</div>
