<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Container;
use App\Core\ValidationException;
use App\Core\Validator;
use PDO;
use PDOException;

/**
 * User account lifecycle service.
 *
 * Owns registration, activation, authentication, admin account management, and
 * resident lookup methods used across messaging and admin screens.
 */
final class UserService
{
    private PDO $db;
    private AuditService $audit;
    private MailService $mail;

    public function __construct(?AuditService $audit = null, ?MailService $mail = null)
    {
        $this->db = Container::get('db');
        $this->audit = $audit ?? new AuditService();
        $this->mail = $mail ?? new MailService();
    }

    /**
     * Create a new inactive resident and generate a mailbox activation code.
     *
     * Returns the plaintext activation code for operational use at signup time.
     * The database only stores a secure password hash of the code.
     */
    public function createUser(array $input, string $locale): string
    {
        $errors = [];
        $firstName = trim((string) ($input['first_name'] ?? ''));
        $lastName = trim((string) ($input['last_name'] ?? ''));
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $apartment = trim((string) ($input['apartment_number'] ?? ''));
        $password = (string) ($input['password'] ?? '');

        if ($firstName === '') {
            $errors['first_name'] = 'validation.first_name_required';
        }
        if ($lastName === '') {
            $errors['last_name'] = 'validation.last_name_required';
        }
        if (!Validator::email($email)) {
            $errors['email'] = 'validation.email_invalid';
        }
        if (!Validator::apartment($apartment)) {
            $errors['apartment_number'] = 'validation.apartment_invalid';
        }
        if (strlen($password) < 12) {
            $errors['password'] = 'validation.password_length';
        }
        if ($this->emailInUseOrPending($email)) {
            $errors['email'] = 'validation.email_taken';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $activationCode = strtoupper(substr(bin2hex(random_bytes(6)), 0, 10));
        $stmt = $this->db->prepare(
            'INSERT INTO users (
                first_name, last_name, email, apartment_number, password_hash, role, is_active,
                activation_code_hash, activation_code_created_at, created_at, updated_at
            ) VALUES (
                :first_name, :last_name, :email, :apartment_number, :password_hash, :role, 0,
                :activation_code_hash, NOW(), NOW(), NOW()
            )'
        );
        try {
            $stmt->execute([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'apartment_number' => $apartment,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'role' => 'user',
                'activation_code_hash' => password_hash($activationCode, PASSWORD_DEFAULT),
            ]);
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                throw new ValidationException(['email' => 'validation.email_taken']);
            }
            throw $exception;
        }

        $userId = (int) $this->db->lastInsertId();
        $user = $this->findById($userId);
        $this->audit->log($userId, 'user.signup', 'user', (string) $userId, ['email' => $email]);
        $this->mail->notifyAdminNewSignup($user, $locale);

        return $activationCode;
    }

    /**
     * Activate an account when the submitted code matches the stored hash.
     */
    public function activate(string $email, string $code): bool
    {
        $user = $this->findByEmail($email);
        if (!$user || (int) $user['is_active'] === 1 || !$user['activation_code_hash']) {
            return false;
        }

        if (!password_verify($code, $user['activation_code_hash'])) {
            return false;
        }

        $stmt = $this->db->prepare(
            'UPDATE users
             SET is_active = 1, activation_code_hash = NULL, activated_at = NOW(), updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute(['id' => $user['id']]);
        $this->audit->log((int) $user['id'], 'user.activated', 'user', (string) $user['id']);

        return true;
    }

    /**
     * Authenticate an already activated user.
     *
     * Passwords are verified with password_verify() against the stored hash.
     */
    public function authenticate(string $email, string $password): ?array
    {
        $user = $this->findByEmail($email);
        if (!$user || (int) $user['is_active'] !== 1 || $user['deleted_at'] !== null) {
            return null;
        }

        if (!password_verify($password, $user['password_hash'])) {
            return null;
        }

        $this->db->prepare('UPDATE users SET last_login_at = NOW(), updated_at = NOW() WHERE id = :id')
            ->execute(['id' => $user['id']]);

        return $this->findById((int) $user['id']);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => strtolower($email)]);
        return $stmt->fetch() ?: null;
    }

    public function activeRecipients(int $excludeUserId): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, first_name, last_name
             FROM users
             WHERE is_active = 1
               AND deleted_at IS NULL
               AND id <> :id
             ORDER BY first_name, last_name'
        );
        $stmt->execute(['id' => $excludeUserId]);

        return $stmt->fetchAll();
    }

    public function paginatedUsers(int $page, int $perPage, ?string $search = null, ?int $isActive = null): array
    {
        $offset = ($page - 1) * $perPage;
        $where = [];
        $params = [];

        if ($search) {
            $where[] = '(first_name LIKE :search OR last_name LIKE :search OR email LIKE :search OR apartment_number LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }
        if ($isActive !== null) {
            $where[] = 'is_active = :is_active';
            $params['is_active'] = $isActive;
        }
        $where[] = 'deleted_at IS NULL';

        $sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM users {$sqlWhere}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $listStmt = $this->db->prepare("SELECT * FROM users {$sqlWhere} ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}");
        $listStmt->execute($params);

        return ['items' => $listStmt->fetchAll(), 'total' => $total];
    }

    public function deleteUser(int $id, int $actorUserId): void
    {
        $user = $this->findById($id);
        if ($user === null || $user['role'] === 'admin') {
            return;
        }

        $this->db->beginTransaction();
        try {
            $cancelStmt = $this->db->prepare(
                'UPDATE reservations
                 SET status = :status,
                     cancelled_at = NOW(),
                     cancelled_by_user_id = :actor_user_id,
                     updated_at = NOW()
                 WHERE user_id = :user_id
                   AND status = :current_status
                   AND start_datetime >= NOW()'
            );
            $cancelStmt->execute([
                'status' => 'cancelled',
                'actor_user_id' => $actorUserId,
                'user_id' => $id,
                'current_status' => 'active',
            ]);

            $stmt = $this->db->prepare(
                'UPDATE users
                 SET first_name = :first_name,
                     last_name = :last_name,
                     email = :email,
                     apartment_number = :apartment_number,
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
                'email' => 'deleted-user-' . $id . '@example.invalid',
                'apartment_number' => 'REMOVED',
                'password_hash' => password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT),
                'id' => $id,
            ]);

            $this->db->commit();
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }

        $this->audit->log($actorUserId, 'admin.user_deleted', 'user', (string) $id, ['mode' => 'anonymized']);
    }

    public function adminResetPassword(int $id, int $actorUserId): string
    {
        $tempPassword = bin2hex(random_bytes(6));
        $stmt = $this->db->prepare('UPDATE users SET password_hash = :password_hash, updated_at = NOW() WHERE id = :id');
        $stmt->execute([
            'id' => $id,
            'password_hash' => password_hash($tempPassword, PASSWORD_DEFAULT),
        ]);
        $this->audit->log($actorUserId, 'admin.password_reset', 'user', (string) $id);

        return $tempPassword;
    }

    /**
     * Update the apartment assignment from the admin side only.
     *
     * Apartment assignment is treated as building-managed residency data and is
     * therefore intentionally excluded from the resident self-service account UI.
     */
    public function adminUpdateApartment(int $id, string $apartmentNumber, int $actorUserId): void
    {
        if (!Validator::apartment($apartmentNumber)) {
            throw new ValidationException(['apartment_number' => 'validation.apartment_invalid']);
        }

        $stmt = $this->db->prepare('UPDATE users SET apartment_number = :apartment_number, updated_at = NOW() WHERE id = :id');
        $stmt->execute([
            'apartment_number' => trim($apartmentNumber),
            'id' => $id,
        ]);

        $this->audit->log($actorUserId, 'admin.apartment_updated', 'user', (string) $id, [
            'apartment_number' => trim($apartmentNumber),
        ]);
    }

    private function emailInUseOrPending(string $email): bool
    {
        $stmt = $this->db->prepare(
            'SELECT id
             FROM users
             WHERE email = :email OR pending_email = :email
             LIMIT 1'
        );
        $stmt->execute(['email' => strtolower($email)]);

        return (bool) $stmt->fetchColumn();
    }
}
