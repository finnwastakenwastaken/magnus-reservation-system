<?php

declare(strict_types=1);

use App\Core\Csrf;

$old ??= [];
$errors ??= [];
?>
<div class="row justify-content-center">
    <div class="col-xl-9">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4 p-lg-5">
                <div class="mb-4">
                    <h1 class="h2 mb-2"><?= htmlspecialchars($translator->get('installer.title'), ENT_QUOTES, 'UTF-8') ?></h1>
                    <p class="text-secondary mb-0"><?= htmlspecialchars($translator->get('installer.subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <?php if (isset($errors['general_key']) || isset($errors['general_text'])): ?>
                    <div class="alert alert-danger">
                        <?= htmlspecialchars(isset($errors['general_key']) ? $translator->get((string) $errors['general_key']) : (string) $errors['general_text'], ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endif; ?>
                <form method="post" novalidate>
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                    <div class="row g-4">
                        <div class="col-lg-6">
                            <h2 class="h5 mb-3"><?= htmlspecialchars($translator->get('installer.database_section'), ENT_QUOTES, 'UTF-8') ?></h2>
                            <?php foreach ([
                                'db_host' => 'installer.db_host',
                                'db_port' => 'installer.db_port',
                                'db_database' => 'installer.db_database',
                                'db_username' => 'installer.db_username',
                                'db_password' => 'installer.db_password',
                                'app_url' => 'installer.app_url',
                            ] as $field => $label): ?>
                                <div class="mb-3">
                                    <label class="form-label"><?= htmlspecialchars($translator->get($label), ENT_QUOTES, 'UTF-8') ?></label>
                                    <input class="form-control <?= isset($errors[$field]) ? 'is-invalid' : '' ?>" name="<?= htmlspecialchars($field, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars((string) ($old[$field] ?? ''), ENT_QUOTES, 'UTF-8') ?>" <?= $field === 'db_password' ? 'type="password"' : '' ?>>
                                    <?php if (isset($errors[$field])): ?><div class="invalid-feedback"><?= htmlspecialchars($translator->get($errors[$field]), ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="col-lg-6">
                            <h2 class="h5 mb-3"><?= htmlspecialchars($translator->get('installer.admin_section'), ENT_QUOTES, 'UTF-8') ?></h2>
                            <?php foreach ([
                                'admin_first_name' => 'auth.first_name',
                                'admin_last_name' => 'auth.last_name',
                                'admin_email' => 'auth.email',
                                'admin_password' => 'auth.password',
                            ] as $field => $label): ?>
                                <div class="mb-3">
                                    <label class="form-label"><?= htmlspecialchars($translator->get($label), ENT_QUOTES, 'UTF-8') ?></label>
                                    <input class="form-control <?= isset($errors[$field]) ? 'is-invalid' : '' ?>" name="<?= htmlspecialchars($field, ENT_QUOTES, 'UTF-8') ?>" value="<?= $field === 'admin_password' ? '' : htmlspecialchars((string) ($old[$field] ?? ''), ENT_QUOTES, 'UTF-8') ?>" <?= $field === 'admin_password' ? 'type="password"' : '' ?>>
                                    <?php if (isset($errors[$field])): ?><div class="invalid-feedback"><?= htmlspecialchars($translator->get($errors[$field]), ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mt-4">
                        <p class="small text-secondary mb-0"><?= htmlspecialchars($translator->get('installer.note'), ENT_QUOTES, 'UTF-8') ?></p>
                        <button class="btn btn-primary btn-lg" type="submit"><?= htmlspecialchars($translator->get('installer.submit'), ENT_QUOTES, 'UTF-8') ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
