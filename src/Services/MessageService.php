<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Container;
use App\Core\ValidationException;
use PDO;

/**
 * Handles resident-to-resident messaging and notification email dispatch.
 */
final class MessageService
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
     * Persist an internal message and notify the recipient by email.
     *
     * Privacy note: the UI uses user IDs and display names only. Real email
     * addresses stay inside the server-side service and mail transport layer.
     */
    public function send(array $sender, int $recipientId, string $subject, string $body, string $locale): void
    {
        $errors = [];
        $subject = trim($subject);
        $body = trim($body);

        if ($recipientId <= 0) {
            $errors['recipient_user_id'] = 'validation.recipient_required';
        }
        if ($subject === '' || strlen($subject) > 190) {
            $errors['subject'] = 'validation.message_subject';
        }
        if ($body === '' || strlen($body) < 5 || strlen($body) > 5000) {
            $errors['body'] = 'validation.message_body';
        }

        $recipient = $this->findUser($recipientId);
        if (!$recipient || (int) $recipient['is_active'] !== 1 || $recipientId === (int) $sender['id']) {
            $errors['recipient_user_id'] = 'validation.recipient_required';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $stmt = $this->db->prepare(
            'INSERT INTO messages (sender_user_id, recipient_user_id, subject, body, created_at)
             VALUES (:sender_user_id, :recipient_user_id, :subject, :body, NOW())'
        );
        $stmt->execute([
            'sender_user_id' => $sender['id'],
            'recipient_user_id' => $recipientId,
            'subject' => $subject,
            'body' => $body,
        ]);

        $this->audit->log((int) $sender['id'], 'message.sent', 'message', (string) $this->db->lastInsertId());
        $this->mail->notifyUserNewMessage($recipient, $sender, $subject, $locale);
    }

    public function inbox(int $userId, int $page, int $perPage): array
    {
        return $this->listFor('recipient_user_id', $userId, $page, $perPage);
    }

    public function sent(int $userId, int $page, int $perPage): array
    {
        return $this->listFor('sender_user_id', $userId, $page, $perPage);
    }

    private function listFor(string $column, int $userId, int $page, int $perPage): array
    {
        $offset = ($page - 1) * $perPage;
        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM messages WHERE {$column} = :user_id");
        $countStmt->execute(['user_id' => $userId]);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $this->db->prepare(
            "SELECT m.*, s.first_name AS sender_first_name, s.last_name AS sender_last_name,
                    r.first_name AS recipient_first_name, r.last_name AS recipient_last_name
             FROM messages m
             INNER JOIN users s ON s.id = m.sender_user_id
             INNER JOIN users r ON r.id = m.recipient_user_id
             WHERE m.{$column} = :user_id
             ORDER BY m.created_at DESC
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute(['user_id' => $userId]);

        return ['items' => $stmt->fetchAll(), 'total' => $total];
    }

    private function findUser(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }
}
