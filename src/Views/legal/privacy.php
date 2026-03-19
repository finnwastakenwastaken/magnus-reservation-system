<?php declare(strict_types=1); ?>
<div class="card shadow-sm border-0">
    <div class="card-body p-4">
        <h1 class="h3 mb-4"><?= htmlspecialchars($translator->get('legal.privacy_title'), ENT_QUOTES, 'UTF-8') ?></h1>
        <p><?= htmlspecialchars($translator->get('legal.privacy_intro'), ENT_QUOTES, 'UTF-8') ?></p>
        <h2 class="h5 mt-4"><?= htmlspecialchars($translator->get('legal.data_collected_title'), ENT_QUOTES, 'UTF-8') ?></h2>
        <ul>
            <li><?= htmlspecialchars($translator->get('legal.data_collected_1'), ENT_QUOTES, 'UTF-8') ?></li>
            <li><?= htmlspecialchars($translator->get('legal.data_collected_2'), ENT_QUOTES, 'UTF-8') ?></li>
            <li><?= htmlspecialchars($translator->get('legal.data_collected_3'), ENT_QUOTES, 'UTF-8') ?></li>
            <li><?= htmlspecialchars($translator->get('legal.data_collected_4'), ENT_QUOTES, 'UTF-8') ?></li>
        </ul>
        <h2 class="h5 mt-4"><?= htmlspecialchars($translator->get('legal.why_title'), ENT_QUOTES, 'UTF-8') ?></h2>
        <p><?= htmlspecialchars($translator->get('legal.why_text'), ENT_QUOTES, 'UTF-8') ?></p>
        <h2 class="h5 mt-4"><?= htmlspecialchars($translator->get('legal.visibility_title'), ENT_QUOTES, 'UTF-8') ?></h2>
        <p><?= htmlspecialchars($translator->get('legal.visibility_text'), ENT_QUOTES, 'UTF-8') ?></p>
        <h2 class="h5 mt-4"><?= htmlspecialchars($translator->get('legal.oversight_title'), ENT_QUOTES, 'UTF-8') ?></h2>
        <p><?= htmlspecialchars($translator->get('legal.oversight_text'), ENT_QUOTES, 'UTF-8') ?></p>
        <h2 class="h5 mt-4"><?= htmlspecialchars($translator->get('legal.retention_title'), ENT_QUOTES, 'UTF-8') ?></h2>
        <p><?= htmlspecialchars($translator->get('legal.retention_text'), ENT_QUOTES, 'UTF-8') ?></p>
        <h2 class="h5 mt-4"><?= htmlspecialchars($translator->get('legal.rights_title'), ENT_QUOTES, 'UTF-8') ?></h2>
        <p><?= htmlspecialchars($translator->get('legal.rights_text'), ENT_QUOTES, 'UTF-8') ?></p>
        <h2 class="h5 mt-4"><?= htmlspecialchars($translator->get('legal.processors_title'), ENT_QUOTES, 'UTF-8') ?></h2>
        <p><?= htmlspecialchars($translator->get('legal.processors_text'), ENT_QUOTES, 'UTF-8') ?></p>
        <h2 class="h5 mt-4"><?= htmlspecialchars($translator->get('legal.contact_title'), ENT_QUOTES, 'UTF-8') ?></h2>
        <p><?= htmlspecialchars($translator->get('legal.contact_text', ['email' => $config['app']['admin_email']]), ENT_QUOTES, 'UTF-8') ?></p>
    </div>
</div>
