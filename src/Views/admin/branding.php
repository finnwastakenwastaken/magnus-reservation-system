<?php declare(strict_types=1);

use App\Core\Csrf;

$errors ??= [];
?>
<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <h1 class="h3 mb-4"><?= htmlspecialchars($translator->get('admin.branding'), ENT_QUOTES, 'UTF-8') ?></h1>
                <div class="mb-4">
                    <div class="small text-secondary mb-2"><?= htmlspecialchars($translator->get('admin.current_logo'), ENT_QUOTES, 'UTF-8') ?></div>
                    <?php if (!empty($logoPath)): ?>
                        <img src="<?= htmlspecialchars($logoPath, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($translator->get('app.name'), ENT_QUOTES, 'UTF-8') ?>" class="img-fluid border rounded p-3 bg-white" style="max-height:120px;">
                    <?php else: ?>
                        <div class="border rounded p-4 bg-white text-secondary"><?= htmlspecialchars($translator->get('admin.default_logo_notice'), ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endif; ?>
                </div>
                <form method="post" action="/admin/branding/logo" enctype="multipart/form-data" class="mb-3">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                    <div class="mb-3">
                        <label class="form-label"><?= htmlspecialchars($translator->get('admin.upload_logo'), ENT_QUOTES, 'UTF-8') ?></label>
                        <input type="file" class="form-control <?= isset($errors['site_logo']) ? 'is-invalid' : '' ?>" name="site_logo" accept=".png,.jpg,.jpeg,.webp">
                        <?php if (isset($errors['site_logo'])): ?><div class="invalid-feedback"><?= htmlspecialchars($translator->get($errors['site_logo']), ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
                    </div>
                    <button class="btn btn-primary" type="submit"><?= htmlspecialchars($translator->get('admin.logo_upload_submit'), ENT_QUOTES, 'UTF-8') ?></button>
                </form>
                <form method="post" action="/admin/branding/logo/reset">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                    <button class="btn btn-outline-danger" type="submit"><?= htmlspecialchars($translator->get('admin.logo_reset'), ENT_QUOTES, 'UTF-8') ?></button>
                </form>
            </div>
        </div>
    </div>
</div>
