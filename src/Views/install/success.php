<?php

declare(strict_types=1);
?>
<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="card shadow-sm border-0">
            <div class="card-body p-5 text-center">
                <h1 class="h2 mb-3"><?= htmlspecialchars($translator->get('installer.success_title'), ENT_QUOTES, 'UTF-8') ?></h1>
                <p class="text-secondary mb-4"><?= htmlspecialchars($translator->get('installer.success_text'), ENT_QUOTES, 'UTF-8') ?></p>
                <a class="btn btn-primary btn-lg" href="<?= htmlspecialchars($appUrl ?: '/', ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($translator->get('installer.go_to_app'), ENT_QUOTES, 'UTF-8') ?></a>
            </div>
        </div>
    </div>
</div>
