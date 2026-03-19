<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Container;
use App\Core\ValidationException;
use App\Core\Validator;
use App\Security\Permissions;
use PDO;
use PDOException;

/**
 * Handles user-facing privacy/account management features.
 *
 * The service centralizes privacy-sensitive operations so the application has a
 * single place for email-change verification, password changes, visibility
 * settings, export, anonymization, and retention cleanup.
 */
final class PrivacyService
{
    private PDO $db;
    private AuditService $audit;
    private MailService $mail;
    private SettingsService $settings;
    private FileSystemService $files;
    private ImageUploadService $images;

    public function __construct()
    {
        $this->db = Container::get('db');
        $this->audit = new AuditService();
        $this->mail = new MailService();
        $this->settings = new SettingsService();
        $this->files = new FileSystemService();
        $this->images = new ImageUploadService();
    }

    /**
     * Return a readable account-data overview for transparency/export screens.
     *
     * The returned structure is intentionally filtered. Residents should be
     * able to see their own stored personal data, but not internal-only
     * security fields such as password hashes, activation hashes, or pending
     * email verification hashes.
     */
    public function accountData(int $userId): array
    {
        $user = $this->findUserForTransparency($userId);
        if ($user === null) {
            throw new \RuntimeException('User not found.');
        }

        $reservationStmt = $this->db->prepare(
            'SELECT id, start_datetime, end_datetime, status, created_at
             FROM reservations
             WHERE user_id = :user_id
             ORDER BY start_datetime DESC'
        );
        $reservationStmt->execute(['user_id' => $userId]);

        $messageStmt = $this->db->prepare(
            'SELECT id, sender_user_id, recipient_user_id, subject, body, created_at, read_at
             FROM messages
             WHERE sender_user_id = :sender_user_id OR recipient_user_id = :recipient_user_id
             ORDER BY created_at DESC'
        );
        $messageStmt->execute([
            'sender_user_id' => $userId,
            'recipient_user_id' => $userId,
        ]);

        return [
            'user' => $user,
            'privacy_settings' => [
                'show_phone_to_users' => (int) $user['show_phone_to_users'] === 1,
                'show_contact_notes_to_users' => (int) $user['show_contact_notes_to_users'] === 1,
            ],
            'reservations' => $reservationStmt->fetchAll(),
            'messages' => $messageStmt->fetchAll(),
        ];
    }

    /**
     * Update the small set of resident-managed fields.
     *
     * Apartment data is intentionally not editable here because residency and
     * building assignment are admin-managed records.
     */
    public function updateProfilePrivacy(array $user, array $input): void
    {
        $phone = trim((string) ($input['phone_number'] ?? ''));
        $contactNotes = trim((string) ($input['contact_notes'] ?? ''));
        $showPhone = !empty($input['show_phone_to_users']) ? 1 : 0;
        $showContactNotes = !empty($input['show_contact_notes_to_users']) ? 1 : 0;

        $errors = [];
        if ($phone !== '' && preg_match('/^[0-9+\-\s()]{6,30}$/', $phone) !== 1) {
            $errors['phone_number'] = 'validation.phone_invalid';
        }
        if (strlen($contactNotes) > 255) {
            $errors['contact_notes'] = 'validation.contact_notes_length';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $stmt = $this->db->prepare(
            'UPDATE users
             SET phone_number = :phone_number,
                 contact_notes = :contact_notes,
                 show_phone_to_users = :show_phone_to_users,
                 show_contact_notes_to_users = :show_contact_notes_to_users,
                 updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            'phone_number' => $phone !== '' ? $phone : null,
            'contact_notes' => $contactNotes !== '' ? $contactNotes : null,
            'show_phone_to_users' => $showPhone,
            'show_contact_notes_to_users' => $showContactNotes,
            'id' => $user['id'],
        ]);

        $this->audit->log((int) $user['id'], 'user.privacy_updated', 'user', (string) $user['id'], [
            'show_phone_to_users' => $showPhone === 1,
            'show_contact_notes_to_users' => $showContactNotes === 1,
            'has_phone_number' => $phone !== '',
            'has_contact_notes' => $contactNotes !== '',
        ]);
    }

    /**
     * Start a verified email-change flow.
     *
     * The new address is not activated until the recipient confirms the link.
     * The old address is notified so an unexpected request is visible.
     */
    public function requestEmailChange(array $user, string $newEmail, string $currentPassword, string $locale): void
    {
        $newEmail = strtolower(trim($newEmail));
        $errors = [];

        if (!password_verify($currentPassword, $user['password_hash'])) {
            $errors['current_password_for_email'] = 'validation.password_incorrect';
        }
        if (!Validator::email($newEmail)) {
            $errors['new_email'] = 'validation.email_invalid';
        }
        if ($newEmail === strtolower((string) $user['email'])) {
            $errors['new_email'] = 'validation.email_same';
        }
        if ($this->emailInUseOrPending($newEmail)) {
            $errors['new_email'] = 'validation.email_taken';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $token = bin2hex(random_bytes(32));
        try {
            $stmt = $this->db->prepare(
                'UPDATE users
                 SET pending_email = :pending_email,
                     pending_email_token_hash = :pending_email_token_hash,
                     pending_email_requested_at = NOW(),
                     pending_email_expires_at = DATE_ADD(NOW(), INTERVAL 1 DAY),
                     updated_at = NOW()
                 WHERE id = :id'
            );
            $stmt->execute([
                'pending_email' => $newEmail,
                'pending_email_token_hash' => password_hash($token, PASSWORD_DEFAULT),
                'id' => $user['id'],
            ]);
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                throw new ValidationException(['new_email' => 'validation.email_taken']);
            }

            throw $exception;
        }

        $baseUrl = rtrim(Container::get('config')['app']['url'], '/');
        $confirmUrl = $baseUrl . '/account/email-change/confirm?token=' . urlencode($token);
        $this->mail->sendEmailChangeConfirmation($user, $newEmail, $confirmUrl, $locale);
        $this->mail->sendEmailChangeNotice($user, $newEmail, $locale);
        $this->audit->log((int) $user['id'], 'user.email_change_requested', 'user', (string) $user['id'], [
            'pending_email' => $newEmail,
        ]);
    }

    /**
     * Finalize an email change from the emailed confirmation token.
     */
    public function confirmEmailChange(string $token): bool
    {
        $stmt = $this->db->query(
            'SELECT id, email, pending_email, pending_email_token_hash, pending_email_expires_at
             FROM users
             WHERE pending_email IS NOT NULL'
        );

        foreach ($stmt->fetchAll() as $user) {
            if (
                $user['pending_email_token_hash']
                && $user['pending_email_expires_at'] !== null
                && strtotime((string) $user['pending_email_expires_at']) >= time()
                && password_verify($token, (string) $user['pending_email_token_hash'])
            ) {
                $update = $this->db->prepare(
                    'UPDATE users
                     SET email = :email,
                         pending_email = NULL,
                         pending_email_token_hash = NULL,
                         pending_email_requested_at = NULL,
                         pending_email_expires_at = NULL,
                         updated_at = NOW()
                     WHERE id = :id'
                );
                $update->execute([
                    'email' => $user['pending_email'],
                    'id' => $user['id'],
                ]);
                $this->audit->log((int) $user['id'], 'user.email_changed', 'user', (string) $user['id'], [
                    'previous_email' => $user['email'],
                    'new_email' => $user['pending_email'],
                ]);

                return true;
            }
        }

        return false;
    }

    /**
     * Change the account password after confirming the current password.
     */
    public function changePassword(array $user, string $currentPassword, string $newPassword): void
    {
        $errors = [];
        if (!password_verify($currentPassword, $user['password_hash'])) {
            $errors['current_password'] = 'validation.password_incorrect';
        }
        if (strlen($newPassword) < 12) {
            $errors['new_password'] = 'validation.password_length';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $stmt = $this->db->prepare(
            'UPDATE users SET password_hash = :password_hash, updated_at = NOW() WHERE id = :id'
        );
        $stmt->execute([
            'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
            'id' => $user['id'],
        ]);

        $this->audit->log((int) $user['id'], 'user.password_changed', 'user', (string) $user['id'], [
            'password_rotated' => true,
        ]);
    }

    /**
     * Replace the user's profile picture with a validated uploaded image.
     */
    public function updateProfilePicture(array $user, ?array $file): void
    {
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            throw new ValidationException(['profile_picture' => 'account.profile_picture_required']);
        }

        try {
            $newPath = $this->images->store($file, 'avatars', 2 * 1024 * 1024);
        } catch (\RuntimeException) {
            throw new ValidationException(['profile_picture' => 'account.profile_picture_invalid']);
        }

        $current = $this->findUserById((int) $user['id']);
        $stmt = $this->db->prepare(
            'UPDATE users SET profile_picture_path = :profile_picture_path, updated_at = NOW() WHERE id = :id'
        );
        $stmt->execute([
            'profile_picture_path' => $newPath,
            'id' => $user['id'],
        ]);

        $this->images->deletePublicPath($current['profile_picture_path'] ?? null);
        $this->audit->log((int) $user['id'], 'user.profile_picture_updated', 'user', (string) $user['id'], [
            'profile_picture_updated' => true,
        ]);
    }

    public function removeProfilePicture(array $user): void
    {
        $current = $this->findUserById((int) $user['id']);
        if ($current === null || empty($current['profile_picture_path'])) {
            return;
        }

        $stmt = $this->db->prepare(
            'UPDATE users SET profile_picture_path = NULL, updated_at = NOW() WHERE id = :id'
        );
        $stmt->execute(['id' => $user['id']]);
        $this->images->deletePublicPath($current['profile_picture_path']);
        $this->audit->log((int) $user['id'], 'user.profile_picture_removed', 'user', (string) $user['id'], [
            'profile_picture_removed' => true,
        ]);
    }

    /**
     * Anonymize a self-deleted account while preserving linked reservations,
     * messages, and audit references needed for system integrity.
     *
     * Strategy:
     * - keep the user row so foreign keys remain valid
     * - remove direct identifiers and disable login
     * - preserve linked reservations/messages, which then display anonymized
     *   names instead of the original resident identity
     */
    public function deleteOwnAccount(array $user, string $password, bool $confirmed): void
    {
        if ((int) ($user['is_super_admin'] ?? 0) === 1 || in_array(Permissions::ADMIN_ACCESS, (array) ($user['permission_codes'] ?? []), true)) {
            throw new ValidationException(['delete_account' => 'account.delete_admin_blocked']);
        }

        $errors = [];
        if (!$confirmed) {
            $errors['delete_confirm'] = 'account.delete_confirm_required';
        }
        if (!password_verify($password, $user['password_hash'])) {
            $errors['delete_password'] = 'validation.password_incorrect';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $this->db->beginTransaction();
        try {
            $this->cancelFutureReservations((int) $user['id']);
            $anonymizedEmail = 'deleted-user-' . $user['id'] . '@example.invalid';
            $stmt = $this->db->prepare(
                'UPDATE users
                 SET first_name = :first_name,
                     last_name = :last_name,
                     email = :email,
                     apartment_number = :apartment_number,
                     profile_picture_path = NULL,
                     phone_number = NULL,
                     contact_notes = NULL,
                     show_phone_to_users = 0,
                     show_contact_notes_to_users = 0,
                     password_hash = :password_hash,
                     is_active = 0,
                     activation_code_hash = NULL,
                     activation_code_created_at = NULL,
                     pending_email = NULL,
                     pending_email_token_hash = NULL,
                     pending_email_requested_at = NULL,
                     pending_email_expires_at = NULL,
                     deleted_at = NOW(),
                     anonymized_at = NOW(),
                     updated_at = NOW()
                 WHERE id = :id'
            );
            $stmt->execute([
                'first_name' => 'Deleted',
                'last_name' => 'Resident',
                'email' => $anonymizedEmail,
                'apartment_number' => 'REMOVED',
                'password_hash' => password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT),
                'id' => $user['id'],
            ]);

            $this->images->deletePublicPath($user['profile_picture_path'] ?? null);
            $this->db->commit();
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }

        $this->audit->log((int) $user['id'], 'user.self_deleted', 'user', (string) $user['id'], [
            'mode' => 'anonymized',
        ]);
        Auth::logout();
    }

    /**
     * Return privacy-safe profile cards for other residents.
     */
    public function visibleResidents(int $viewerUserId): array
    {
        $stmt = $this->db->prepare(
            'SELECT u.id, u.first_name, u.last_name, u.profile_picture_path, u.phone_number, u.contact_notes, u.show_phone_to_users, u.show_contact_notes_to_users
             FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             WHERE u.is_active = 1
               AND r.slug = :role
               AND u.deleted_at IS NULL
               AND u.id <> :id
             ORDER BY first_name, last_name'
        );
        $stmt->execute([
            'role' => 'user',
            'id' => $viewerUserId,
        ]);

        return array_map(static function (array $row): array {
            return [
                'id' => (int) $row['id'],
                'display_name' => $row['first_name'] . ' ' . strtoupper(substr((string) $row['last_name'], 0, 1)) . '.',
                'profile_picture_path' => $row['profile_picture_path'] ?: null,
                'phone_number' => (int) $row['show_phone_to_users'] === 1 ? $row['phone_number'] : null,
                'contact_notes' => (int) $row['show_contact_notes_to_users'] === 1 ? $row['contact_notes'] : null,
            ];
        }, $stmt->fetchAll());
    }

    /**
     * Cleanup routine for retention-friendly data categories.
     *
     * The app does not silently delete confirmed reservations/messages, but it
     * does purge stale security/support data that no longer serves a purpose.
     */
    public function runRetentionCleanup(): void
    {
        $cacheFile = BASE_PATH . '/storage/cache/retention-last-run.txt';
        $lastRun = is_file($cacheFile) ? (int) trim((string) file_get_contents($cacheFile)) : 0;
        if ($lastRun > (time() - 86400)) {
            return;
        }

        $retentionUnactivatedDays = (int) $this->settings->get('retention_unactivated_days', 60);
        $retentionPasswordResetDays = (int) $this->settings->get('retention_password_reset_days', 30);
        $retentionRateLimitDays = (int) $this->settings->get('retention_rate_limit_days', 30);
        $retentionUpdateBackupDays = (int) $this->settings->get('retention_update_backup_days', 30);
        $unactivatedBefore = date('Y-m-d H:i:s', time() - ($retentionUnactivatedDays * 86400));
        $passwordResetBefore = date('Y-m-d H:i:s', time() - ($retentionPasswordResetDays * 86400));
        $rateLimitBefore = date('Y-m-d H:i:s', time() - ($retentionRateLimitDays * 86400));

        $this->db->prepare(
            'DELETE FROM users
             WHERE is_active = 0
               AND deleted_at IS NULL
               AND activation_code_created_at < :cutoff'
        )->execute(['cutoff' => $unactivatedBefore]);

        $this->db->prepare(
            'DELETE FROM password_resets
             WHERE expires_at < :cutoff
                OR used_at < :used_cutoff'
        )->execute([
            'cutoff' => $passwordResetBefore,
            'used_cutoff' => $passwordResetBefore,
        ]);

        $this->db->prepare(
            'DELETE FROM rate_limits
             WHERE updated_at < :cutoff'
        )->execute(['cutoff' => $rateLimitBefore]);

        $this->expirePendingEmailChanges();
        $this->cleanupDirectory(BASE_PATH . '/storage/backups', $retentionUpdateBackupDays);
        $this->cleanupDirectory(BASE_PATH . '/storage/updates', $retentionUpdateBackupDays);

        file_put_contents($cacheFile, (string) time());
    }

    private function cleanupDirectory(string $path, int $retentionDays): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..' || $item === '.gitkeep') {
                continue;
            }
            $fullPath = $path . '/' . $item;
            if (filemtime($fullPath) !== false && filemtime($fullPath) < (time() - ($retentionDays * 86400))) {
                if (is_dir($fullPath)) {
                    $this->files->deleteRecursive($fullPath);
                    @rmdir($fullPath);
                } else {
                    @unlink($fullPath);
                }
            }
        }
    }

    private function expirePendingEmailChanges(): void
    {
        $stmt = $this->db->prepare(
            'UPDATE users
             SET pending_email = NULL,
                 pending_email_token_hash = NULL,
                 pending_email_requested_at = NULL,
                 pending_email_expires_at = NULL,
                 updated_at = NOW()
             WHERE pending_email_expires_at IS NOT NULL
               AND pending_email_expires_at < NOW()'
        );
        $stmt->execute();
    }

    private function cancelFutureReservations(int $userId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE reservations
             SET status = :status,
                 cancelled_at = NOW(),
                 cancelled_by_user_id = NULL,
                 updated_at = NOW()
             WHERE user_id = :user_id
               AND status = :current_status
               AND start_datetime >= NOW()'
        );
        $stmt->execute([
            'status' => 'cancelled',
            'current_status' => 'active',
            'user_id' => $userId,
        ]);
    }

    /**
     * Fetch the subset of account data that is appropriate for resident
     * transparency/export screens.
     */
    private function findUserForTransparency(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT u.id, u.first_name, u.last_name, u.email, u.apartment_number, u.profile_picture_path,
                    r.slug AS role, r.name AS role_name, u.is_active,
                    u.phone_number, u.contact_notes, u.show_phone_to_users,
                    u.show_contact_notes_to_users, u.pending_email, u.activated_at,
                    u.created_at, u.updated_at, u.last_login_at, u.deleted_at, u.anonymized_at
             FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             WHERE u.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }

    private function findUserById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }

    /**
     * Reserve email addresses across both live and pending account state.
     */
    private function emailInUseOrPending(string $email): bool
    {
        $stmt = $this->db->prepare(
            'SELECT id
             FROM users
             WHERE email = :email OR pending_email = :pending_email
             LIMIT 1'
        );
        $normalized = strtolower($email);
        $stmt->execute([
            'email' => $normalized,
            'pending_email' => $normalized,
        ]);

        return (bool) $stmt->fetchColumn();
    }
}
