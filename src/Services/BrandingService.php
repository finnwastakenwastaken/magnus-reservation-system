<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\ValidationException;

/**
 * Manages site-wide branding assets such as the uploaded logo.
 */
final class BrandingService
{
    private SettingsService $settings;
    private ImageUploadService $images;
    private AuditService $audit;

    public function __construct()
    {
        $this->settings = new SettingsService();
        $this->images = new ImageUploadService();
        $this->audit = new AuditService();
    }

    public function currentLogoPath(): ?string
    {
        return $this->settings->siteLogoPath();
    }

    public function updateLogo(?array $file, int $actorUserId): void
    {
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            throw new ValidationException(['site_logo' => 'admin.logo_required']);
        }

        try {
            $newPath = $this->images->store($file, 'logos', 2 * 1024 * 1024);
        } catch (\RuntimeException) {
            throw new ValidationException(['site_logo' => 'admin.logo_invalid']);
        }

        $oldPath = $this->currentLogoPath();
        $this->settings->updateMany(['site_logo_path' => $newPath]);
        $this->images->deletePublicPath($oldPath);
        $this->audit->log($actorUserId, 'admin.site_logo_updated', 'settings', 'site_logo');
    }

    public function resetLogo(int $actorUserId): void
    {
        $oldPath = $this->currentLogoPath();
        $this->settings->updateMany(['site_logo_path' => '']);
        $this->images->deletePublicPath($oldPath);
        $this->audit->log($actorUserId, 'admin.site_logo_reset', 'settings', 'site_logo');
    }
}
