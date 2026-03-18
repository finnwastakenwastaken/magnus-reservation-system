<?php declare(strict_types=1); ?>
<div class="card shadow-sm border-0">
    <div class="card-body p-4">
        <h1 class="h3 mb-4"><?= htmlspecialchars($translator->get('legal.house_rules_title'), ENT_QUOTES, 'UTF-8') ?></h1>
        <ul class="mb-0">
            <li><?= htmlspecialchars($translator->get('legal.house_rules_1'), ENT_QUOTES, 'UTF-8') ?></li>
            <li><?= htmlspecialchars($translator->get('legal.house_rules_2'), ENT_QUOTES, 'UTF-8') ?></li>
            <li><?= htmlspecialchars($translator->get('legal.house_rules_3'), ENT_QUOTES, 'UTF-8') ?></li>
            <li><?= htmlspecialchars($translator->get('legal.house_rules_4'), ENT_QUOTES, 'UTF-8') ?></li>
        </ul>
    </div>
</div>
