<?php

declare(strict_types=1);

use App\Core\Csrf;

$errors ??= [];
$old ??= [];
$accountData ??= [];
$storedUser = $accountData['user'] ?? $user;
$isStaffAccount = (int) ($user['is_super_admin'] ?? 0) === 1 || in_array(\App\Security\Permissions::ADMIN_ACCESS, (array) ($user['permission_codes'] ?? []), true);
?>
<div class="row g-4">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        <h1 class="h3 mb-1"><?= htmlspecialchars($translator->get('account.title'), ENT_QUOTES, 'UTF-8') ?></h1>
                        <p class="text-secondary mb-0"><?= htmlspecialchars($translator->get('account.subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                    <a class="btn btn-outline-primary" href="/account/export"><?= htmlspecialchars($translator->get('account.export'), ENT_QUOTES, 'UTF-8') ?></a>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <h2 class="h5 mb-3"><?= htmlspecialchars($translator->get('account.data_overview'), ENT_QUOTES, 'UTF-8') ?></h2>
                <div class="mb-4 d-flex align-items-center gap-3">
                    <?php if (!empty($storedUser['profile_picture_path'])): ?>
                        <img src="<?= htmlspecialchars($storedUser['profile_picture_path'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars((string) $storedUser['first_name'], ENT_QUOTES, 'UTF-8') ?>" class="rounded-circle" style="width:72px;height:72px;object-fit:cover;">
                    <?php else: ?>
                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold" style="width:72px;height:72px;">
                            <?= htmlspecialchars(strtoupper(substr((string) $storedUser['first_name'], 0, 1)), ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    <?php endif; ?>
                    <div>
                        <div class="fw-semibold"><?= htmlspecialchars($translator->get('account.profile_picture'), ENT_QUOTES, 'UTF-8') ?></div>
                        <div class="small text-secondary"><?= htmlspecialchars($translator->get('account.profile_picture_notice'), ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                </div>
                <form method="post" action="/account/profile-picture" enctype="multipart/form-data" class="mb-3">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                    <div class="input-group">
                        <input type="file" class="form-control <?= isset($errors['profile_picture']) ? 'is-invalid' : '' ?>" name="profile_picture" accept=".png,.jpg,.jpeg,.webp">
                        <button class="btn btn-outline-primary" type="submit"><?= htmlspecialchars($translator->get('account.upload_picture'), ENT_QUOTES, 'UTF-8') ?></button>
                    </div>
                    <?php if (isset($errors['profile_picture'])): ?><div class="invalid-feedback d-block"><?= htmlspecialchars($translator->get($errors['profile_picture']), ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
                </form>
                <?php if (!empty($storedUser['profile_picture_path'])): ?>
                    <form method="post" action="/account/profile-picture/remove" class="mb-4">
                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                        <button class="btn btn-sm btn-outline-danger" type="submit"><?= htmlspecialchars($translator->get('account.remove_picture'), ENT_QUOTES, 'UTF-8') ?></button>
                    </form>
                <?php endif; ?>
                <dl class="row mb-0">
                    <dt class="col-sm-5"><?= htmlspecialchars($translator->get('auth.first_name'), ENT_QUOTES, 'UTF-8') ?></dt>
                    <dd class="col-sm-7"><?= htmlspecialchars((string) $storedUser['first_name'], ENT_QUOTES, 'UTF-8') ?></dd>
                    <dt class="col-sm-5"><?= htmlspecialchars($translator->get('auth.last_name'), ENT_QUOTES, 'UTF-8') ?></dt>
                    <dd class="col-sm-7"><?= htmlspecialchars((string) $storedUser['last_name'], ENT_QUOTES, 'UTF-8') ?></dd>
                    <dt class="col-sm-5"><?= htmlspecialchars($translator->get('auth.email'), ENT_QUOTES, 'UTF-8') ?></dt>
                    <dd class="col-sm-7"><?= htmlspecialchars((string) $storedUser['email'], ENT_QUOTES, 'UTF-8') ?></dd>
                    <dt class="col-sm-5"><?= htmlspecialchars($translator->get('auth.apartment'), ENT_QUOTES, 'UTF-8') ?></dt>
                    <dd class="col-sm-7">
                        <?= htmlspecialchars((string) $storedUser['apartment_number'], ENT_QUOTES, 'UTF-8') ?>
                        <div class="small text-secondary"><?= htmlspecialchars($translator->get('account.apartment_readonly'), ENT_QUOTES, 'UTF-8') ?></div>
                    </dd>
                    <dt class="col-sm-5"><?= htmlspecialchars($translator->get('account.account_status'), ENT_QUOTES, 'UTF-8') ?></dt>
                    <dd class="col-sm-7"><?= htmlspecialchars((int) $storedUser['is_active'] === 1 ? $translator->get('admin.active') : $translator->get('admin.inactive'), ENT_QUOTES, 'UTF-8') ?></dd>
                    <dt class="col-sm-5"><?= htmlspecialchars($translator->get('account.pending_email'), ENT_QUOTES, 'UTF-8') ?></dt>
                    <dd class="col-sm-7"><?= htmlspecialchars((string) ($storedUser['pending_email'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <h2 class="h5 mb-3"><?= htmlspecialchars($translator->get('account.data_summary'), ENT_QUOTES, 'UTF-8') ?></h2>
                <p class="mb-2"><?= htmlspecialchars($translator->get('account.reservation_count', ['count' => count($accountData['reservations'] ?? [])]), ENT_QUOTES, 'UTF-8') ?></p>
                <p class="mb-2"><?= htmlspecialchars($translator->get('account.message_count', ['count' => count($accountData['messages'] ?? [])]), ENT_QUOTES, 'UTF-8') ?></p>
                <p class="text-secondary mb-0"><?= htmlspecialchars($translator->get('account.data_explainer'), ENT_QUOTES, 'UTF-8') ?></p>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h2 class="h5 mb-3"><?= htmlspecialchars($translator->get('account.profile_privacy'), ENT_QUOTES, 'UTF-8') ?></h2>
                <form method="post" action="/account/profile">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                    <div class="mb-3">
                        <label class="form-label"><?= htmlspecialchars($translator->get('account.phone_number'), ENT_QUOTES, 'UTF-8') ?></label>
                        <input class="form-control <?= isset($errors['phone_number']) ? 'is-invalid' : '' ?>" name="phone_number" value="<?= htmlspecialchars((string) ($old['phone_number'] ?? $storedUser['phone_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        <?php if (isset($errors['phone_number'])): ?><div class="invalid-feedback"><?= htmlspecialchars($translator->get($errors['phone_number']), ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="show_phone_to_users" value="1" id="show_phone_to_users" <?= !empty($old) ? (!empty($old['show_phone_to_users']) ? 'checked' : '') : ((int) $storedUser['show_phone_to_users'] === 1 ? 'checked' : '') ?>>
                        <label class="form-check-label" for="show_phone_to_users"><?= htmlspecialchars($translator->get('account.show_phone'), ENT_QUOTES, 'UTF-8') ?></label>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= htmlspecialchars($translator->get('account.contact_notes'), ENT_QUOTES, 'UTF-8') ?></label>
                        <textarea class="form-control <?= isset($errors['contact_notes']) ? 'is-invalid' : '' ?>" name="contact_notes" rows="3"><?= htmlspecialchars((string) ($old['contact_notes'] ?? $storedUser['contact_notes'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                        <?php if (isset($errors['contact_notes'])): ?><div class="invalid-feedback"><?= htmlspecialchars($translator->get($errors['contact_notes']), ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="show_contact_notes_to_users" value="1" id="show_contact_notes_to_users" <?= !empty($old) ? (!empty($old['show_contact_notes_to_users']) ? 'checked' : '') : ((int) $storedUser['show_contact_notes_to_users'] === 1 ? 'checked' : '') ?>>
                        <label class="form-check-label" for="show_contact_notes_to_users"><?= htmlspecialchars($translator->get('account.show_contact_notes'), ENT_QUOTES, 'UTF-8') ?></label>
                    </div>
                    <p class="small text-secondary"><?= htmlspecialchars($translator->get('account.privacy_default_notice'), ENT_QUOTES, 'UTF-8') ?></p>
                    <button class="btn btn-primary" type="submit"><?= htmlspecialchars($translator->get('account.save_profile'), ENT_QUOTES, 'UTF-8') ?></button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h2 class="h5 mb-3"><?= htmlspecialchars($translator->get('account.email_change'), ENT_QUOTES, 'UTF-8') ?></h2>
                <form method="post" action="/account/email-change">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                    <div class="mb-3">
                        <label class="form-label"><?= htmlspecialchars($translator->get('account.new_email'), ENT_QUOTES, 'UTF-8') ?></label>
                        <input type="email" class="form-control <?= isset($errors['new_email']) ? 'is-invalid' : '' ?>" name="new_email" value="<?= htmlspecialchars((string) ($old['new_email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        <?php if (isset($errors['new_email'])): ?><div class="invalid-feedback"><?= htmlspecialchars($translator->get($errors['new_email']), ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= htmlspecialchars($translator->get('account.current_password_confirm'), ENT_QUOTES, 'UTF-8') ?></label>
                        <input type="password" class="form-control <?= isset($errors['current_password_for_email']) ? 'is-invalid' : '' ?>" name="current_password_for_email">
                        <?php if (isset($errors['current_password_for_email'])): ?><div class="invalid-feedback"><?= htmlspecialchars($translator->get($errors['current_password_for_email']), ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
                    </div>
                    <p class="small text-secondary"><?= htmlspecialchars($translator->get('account.email_change_notice'), ENT_QUOTES, 'UTF-8') ?></p>
                    <button class="btn btn-primary" type="submit"><?= htmlspecialchars($translator->get('account.request_email_change'), ENT_QUOTES, 'UTF-8') ?></button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h2 class="h5 mb-3"><?= htmlspecialchars($translator->get('account.password_change'), ENT_QUOTES, 'UTF-8') ?></h2>
                <form method="post" action="/account/password">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                    <div class="mb-3">
                        <label class="form-label"><?= htmlspecialchars($translator->get('account.current_password'), ENT_QUOTES, 'UTF-8') ?></label>
                        <input type="password" class="form-control <?= isset($errors['current_password']) ? 'is-invalid' : '' ?>" name="current_password">
                        <?php if (isset($errors['current_password'])): ?><div class="invalid-feedback"><?= htmlspecialchars($translator->get($errors['current_password']), ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= htmlspecialchars($translator->get('account.new_password'), ENT_QUOTES, 'UTF-8') ?></label>
                        <input type="password" class="form-control <?= isset($errors['new_password']) ? 'is-invalid' : '' ?>" name="new_password">
                        <?php if (isset($errors['new_password'])): ?><div class="invalid-feedback"><?= htmlspecialchars($translator->get($errors['new_password']), ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
                    </div>
                    <button class="btn btn-primary" type="submit"><?= htmlspecialchars($translator->get('account.change_password'), ENT_QUOTES, 'UTF-8') ?></button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card shadow-sm border-danger">
            <div class="card-body">
                <h2 class="h5 mb-3 text-danger"><?= htmlspecialchars($translator->get('account.delete_account'), ENT_QUOTES, 'UTF-8') ?></h2>
                <?php if ($isStaffAccount): ?>
                    <div class="alert alert-warning mb-0"><?= htmlspecialchars($translator->get('account.delete_admin_notice'), ENT_QUOTES, 'UTF-8') ?></div>
                <?php else: ?>
                    <p class="text-secondary"><?= htmlspecialchars($translator->get('account.delete_explainer'), ENT_QUOTES, 'UTF-8') ?></p>
                    <form method="post" action="/account/delete">
                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                        <div class="mb-3">
                            <label class="form-label"><?= htmlspecialchars($translator->get('account.current_password_confirm'), ENT_QUOTES, 'UTF-8') ?></label>
                            <input type="password" class="form-control <?= isset($errors['delete_password']) ? 'is-invalid' : '' ?>" name="delete_password">
                            <?php if (isset($errors['delete_password'])): ?><div class="invalid-feedback"><?= htmlspecialchars($translator->get($errors['delete_password']), ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input <?= isset($errors['delete_confirm']) ? 'is-invalid' : '' ?>" type="checkbox" value="1" id="delete_confirm" name="delete_confirm" <?= !empty($old['delete_confirm']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="delete_confirm"><?= htmlspecialchars($translator->get('account.delete_confirm_label'), ENT_QUOTES, 'UTF-8') ?></label>
                            <?php if (isset($errors['delete_confirm'])): ?><div class="invalid-feedback"><?= htmlspecialchars($translator->get($errors['delete_confirm']), ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
                        </div>
                        <?php if (isset($errors['delete_account'])): ?><div class="alert alert-warning"><?= htmlspecialchars($translator->get($errors['delete_account']), ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
                        <button class="btn btn-danger" type="submit"><?= htmlspecialchars($translator->get('account.delete_account'), ENT_QUOTES, 'UTF-8') ?></button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
