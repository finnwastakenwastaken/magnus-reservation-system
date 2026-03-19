<?php declare(strict_types=1);

use App\Core\Csrf;

$errors ??= [];
$old ??= [];
?>
<div class="row justify-content-center">
    <div class="col-xl-9">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <h1 class="h3 mb-4"><?= htmlspecialchars($translator->get('admin.integrations'), ENT_QUOTES, 'UTF-8') ?></h1>
                <form method="post" novalidate>
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                    <div class="row g-4">
                        <div class="col-lg-6">
                            <h2 class="h5 mb-3"><?= htmlspecialchars($translator->get('admin.mailjet_settings'), ENT_QUOTES, 'UTF-8') ?></h2>
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="mailjet_enabled" name="mailjet_enabled" value="1" <?= !empty($old) ? (!empty($old['mailjet_enabled']) ? 'checked' : '') : (!empty($mailjet['enabled']) ? 'checked' : '') ?>>
                                <label class="form-check-label" for="mailjet_enabled"><?= htmlspecialchars($translator->get('admin.mailjet_enabled'), ENT_QUOTES, 'UTF-8') ?></label>
                            </div>
                            <div class="mb-3">
                                <label class="form-label"><?= htmlspecialchars($translator->get('admin.mailjet_api_key'), ENT_QUOTES, 'UTF-8') ?></label>
                                <input type="password" class="form-control" name="mailjet_api_key" autocomplete="new-password" placeholder="<?= htmlspecialchars(($masked['mailjet_api_key'] ?? '') !== '' ? $masked['mailjet_api_key'] : $translator->get('admin.secret_not_set'), ENT_QUOTES, 'UTF-8') ?>">
                                <?php if (isset($errors['mailjet_api_key'])): ?><div class="invalid-feedback d-block"><?= htmlspecialchars($translator->get($errors['mailjet_api_key']), ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
                            </div>
                            <div class="mb-3">
                                <label class="form-label"><?= htmlspecialchars($translator->get('admin.mailjet_api_secret'), ENT_QUOTES, 'UTF-8') ?></label>
                                <input type="password" class="form-control" name="mailjet_api_secret" autocomplete="new-password" placeholder="<?= htmlspecialchars(($masked['mailjet_api_secret'] ?? '') !== '' ? $masked['mailjet_api_secret'] : $translator->get('admin.secret_not_set'), ENT_QUOTES, 'UTF-8') ?>">
                                <?php if (isset($errors['mailjet_api_secret'])): ?><div class="invalid-feedback d-block"><?= htmlspecialchars($translator->get($errors['mailjet_api_secret']), ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
                            </div>
                            <div class="mb-3">
                                <label class="form-label"><?= htmlspecialchars($translator->get('admin.mail_from_email'), ENT_QUOTES, 'UTF-8') ?></label>
                                <input type="email" class="form-control <?= isset($errors['mail_from_email']) ? 'is-invalid' : '' ?>" name="mail_from_email" value="<?= htmlspecialchars((string) ($old['mail_from_email'] ?? $mailjet['from_email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                <?php if (isset($errors['mail_from_email'])): ?><div class="invalid-feedback"><?= htmlspecialchars($translator->get($errors['mail_from_email']), ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
                            </div>
                            <div class="mb-0">
                                <label class="form-label"><?= htmlspecialchars($translator->get('admin.mail_from_name'), ENT_QUOTES, 'UTF-8') ?></label>
                                <input class="form-control <?= isset($errors['mail_from_name']) ? 'is-invalid' : '' ?>" name="mail_from_name" value="<?= htmlspecialchars((string) ($old['mail_from_name'] ?? $mailjet['from_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                <?php if (isset($errors['mail_from_name'])): ?><div class="invalid-feedback"><?= htmlspecialchars($translator->get($errors['mail_from_name']), ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <h2 class="h5 mb-3"><?= htmlspecialchars($translator->get('admin.turnstile_settings'), ENT_QUOTES, 'UTF-8') ?></h2>
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="turnstile_enabled" name="turnstile_enabled" value="1" <?= !empty($old) ? (!empty($old['turnstile_enabled']) ? 'checked' : '') : (!empty($turnstile['enabled']) ? 'checked' : '') ?>>
                                <label class="form-check-label" for="turnstile_enabled"><?= htmlspecialchars($translator->get('admin.turnstile_enabled'), ENT_QUOTES, 'UTF-8') ?></label>
                            </div>
                            <div class="mb-3">
                                <label class="form-label"><?= htmlspecialchars($translator->get('admin.turnstile_site_key'), ENT_QUOTES, 'UTF-8') ?></label>
                                <input class="form-control <?= isset($errors['turnstile_site_key']) ? 'is-invalid' : '' ?>" name="turnstile_site_key" value="<?= htmlspecialchars((string) ($old['turnstile_site_key'] ?? $turnstile['site_key'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                <?php if (isset($errors['turnstile_site_key'])): ?><div class="invalid-feedback"><?= htmlspecialchars($translator->get($errors['turnstile_site_key']), ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
                            </div>
                            <div class="mb-3">
                                <label class="form-label"><?= htmlspecialchars($translator->get('admin.turnstile_secret_key'), ENT_QUOTES, 'UTF-8') ?></label>
                                <input type="password" class="form-control" name="turnstile_secret_key" autocomplete="new-password" placeholder="<?= htmlspecialchars(($masked['turnstile_secret_key'] ?? '') !== '' ? $masked['turnstile_secret_key'] : $translator->get('admin.secret_not_set'), ENT_QUOTES, 'UTF-8') ?>">
                                <?php if (isset($errors['turnstile_secret_key'])): ?><div class="invalid-feedback d-block"><?= htmlspecialchars($translator->get($errors['turnstile_secret_key']), ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
                            </div>
                            <div class="small text-secondary">
                                <?= htmlspecialchars($translator->get('admin.integrations_secret_notice'), ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        </div>
                    </div>
                    <div class="mt-4">
                        <button class="btn btn-primary" type="submit"><?= htmlspecialchars($translator->get('admin.save'), ENT_QUOTES, 'UTF-8') ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
