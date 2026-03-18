<?php declare(strict_types=1); ?>
<div class="card shadow-sm border-0">
    <div class="card-body p-4">
        <h1 class="h3 mb-4"><?= htmlspecialchars($translator->get('legal.cookies_title'), ENT_QUOTES, 'UTF-8') ?></h1>
        <p><?= htmlspecialchars($translator->get('legal.cookies_text_1'), ENT_QUOTES, 'UTF-8') ?></p>
        <ul>
            <li><?= htmlspecialchars($translator->get('legal.cookies_item_1'), ENT_QUOTES, 'UTF-8') ?></li>
            <li><?= htmlspecialchars($translator->get('legal.cookies_item_2'), ENT_QUOTES, 'UTF-8') ?></li>
            <li><?= htmlspecialchars($translator->get('legal.cookies_item_3'), ENT_QUOTES, 'UTF-8') ?></li>
        </ul>
        <p class="mb-0"><?= htmlspecialchars($translator->get('legal.cookies_text_2'), ENT_QUOTES, 'UTF-8') ?></p>
    </div>
</div>
