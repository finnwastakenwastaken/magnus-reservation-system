<?php declare(strict_types=1); ?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1"><?= htmlspecialchars($translator->get('residents.title'), ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="text-secondary mb-0"><?= htmlspecialchars($translator->get('residents.subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
    </div>
    <a class="btn btn-outline-primary" href="/messages/compose"><?= htmlspecialchars($translator->get('messages.compose'), ENT_QUOTES, 'UTF-8') ?></a>
</div>
<div class="row g-4">
    <?php foreach ($residents as $resident): ?>
        <div class="col-md-6 col-xl-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <h2 class="h5"><?= htmlspecialchars($resident['display_name'], ENT_QUOTES, 'UTF-8') ?></h2>
                    <?php if ($resident['phone_number']): ?>
                        <p class="mb-2"><strong><?= htmlspecialchars($translator->get('account.phone_number'), ENT_QUOTES, 'UTF-8') ?>:</strong> <?= htmlspecialchars($resident['phone_number'], ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>
                    <?php if ($resident['contact_notes']): ?>
                        <p class="mb-3"><strong><?= htmlspecialchars($translator->get('account.contact_notes'), ENT_QUOTES, 'UTF-8') ?>:</strong> <?= htmlspecialchars($resident['contact_notes'], ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>
                    <?php if (!$resident['phone_number'] && !$resident['contact_notes']): ?>
                        <p class="text-secondary mb-3"><?= htmlspecialchars($translator->get('residents.no_extra_details'), ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>
                    <a class="btn btn-sm btn-primary" href="/messages/compose?recipient_user_id=<?= (int) $resident['id'] ?>"><?= htmlspecialchars($translator->get('residents.contact'), ENT_QUOTES, 'UTF-8') ?></a>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
