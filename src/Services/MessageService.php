<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Container;
use App\Core\ValidationException;
use PDO;

/**
 * Conversation-oriented internal messaging service.
 *
 * Direct messages are grouped by a deterministic conversation key derived from
 * the two participant IDs. This keeps the schema simple while still allowing a
 * chat-style thread UI, admin oversight, and broadcast delivery as individual
 * private conversations.
 */
final class MessageService
{
    private PDO $db;
    private AuditService $audit;
    private MailService $mail;
    private NotificationService $notifications;

    public function __construct(?AuditService $audit = null, ?MailService $mail = null)
    {
        $this->db = Container::get('db');
        $this->audit = $audit ?? new AuditService();
        $this->mail = $mail ?? new MailService();
        $this->notifications = new NotificationService();
    }

    public function send(array $sender, int $recipientId, string $subject, string $body, string $locale): string
    {
        $subject = trim($subject);
        $body = trim($body);
        $recipient = $this->findResidentRecipient($recipientId);
        $errors = $this->validateMessageInput($sender, $recipientId, $recipient, $subject, $body);

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $conversationKey = $this->conversationKey((int) $sender['id'], $recipientId);
        $messageId = $this->insertMessage((int) $sender['id'], $recipientId, $subject, $body, $conversationKey);

        $this->audit->log((int) $sender['id'], 'message.sent', 'conversation', $conversationKey, [
            'message_id' => $messageId,
            'recipient_user_id' => $recipientId,
        ]);

        $this->notifyRecipient($recipient, $sender, $subject, $locale, '/messages/thread/' . $sender['id']);

        return $conversationKey;
    }

    public function sendBroadcast(array $sender, string $subject, string $body, string $scope, array $roleIds, string $locale): int
    {
        $subject = trim($subject);
        $body = trim($body);
        if (!in_array($scope, ['all', 'roles'], true)) {
            throw new ValidationException(['target_scope' => 'validation.broadcast_recipients']);
        }
        $recipients = $this->broadcastRecipients((int) $sender['id'], $scope, $roleIds);
        if ($subject === '' || strlen($subject) > 190) {
            throw new ValidationException(['subject' => 'validation.message_subject']);
        }
        if ($body === '' || strlen($body) < 5 || strlen($body) > 5000) {
            throw new ValidationException(['body' => 'validation.message_body']);
        }
        if ($recipients === []) {
            throw new ValidationException(['target_scope' => 'validation.broadcast_recipients']);
        }

        $this->db->beginTransaction();
        try {
            foreach ($recipients as $recipient) {
                $conversationKey = $this->conversationKey((int) $sender['id'], (int) $recipient['id']);
                $this->insertMessage((int) $sender['id'], (int) $recipient['id'], $subject, $body, $conversationKey);
                $this->notifyRecipient($recipient, $sender, $subject, $locale, '/messages/thread/' . $sender['id'], true);
            }
            $this->db->commit();
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }

        $this->audit->log((int) $sender['id'], 'admin.messages_broadcast_sent', 'broadcast', date('YmdHis'), [
            'scope' => $scope,
            'role_ids' => array_values(array_map('intval', $roleIds)),
            'recipient_count' => count($recipients),
            'subject' => $subject,
        ]);

        return count($recipients);
    }

    public function conversationSummariesForUser(int $userId, int $page, int $perPage): array
    {
        $offset = ($page - 1) * $perPage;
        $countStmt = $this->db->prepare(
            'SELECT COUNT(*) FROM (
                SELECT CASE
                    WHEN sender_user_id = :count_sender_user_id THEN recipient_user_id
                    ELSE sender_user_id
                END AS other_user_id
                FROM messages
                WHERE sender_user_id = :count_scope_sender OR recipient_user_id = :count_scope_recipient
                GROUP BY other_user_id
            ) conversations'
        );
        $countStmt->execute([
            'count_sender_user_id' => $userId,
            'count_scope_sender' => $userId,
            'count_scope_recipient' => $userId,
        ]);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $this->db->prepare(
            "SELECT conv.other_user_id,
                    conv.last_message_at,
                    conv.unread_count,
                    conv.latest_message_id,
                    u.first_name,
                    u.last_name,
                    u.profile_picture_path,
                    u.deleted_at,
                    m.subject AS latest_subject,
                    m.body AS latest_body
             FROM (
                SELECT CASE
                    WHEN sender_user_id = :summary_sender_user_id THEN recipient_user_id
                    ELSE sender_user_id
                END AS other_user_id,
                MAX(created_at) AS last_message_at,
                MAX(id) AS latest_message_id,
                SUM(CASE
                    WHEN recipient_user_id = :summary_recipient_user_id AND read_at IS NULL THEN 1
                    ELSE 0
                END) AS unread_count
                FROM messages
                WHERE sender_user_id = :summary_scope_sender OR recipient_user_id = :summary_scope_recipient
                GROUP BY other_user_id
             ) conv
             LEFT JOIN users u ON u.id = conv.other_user_id
             INNER JOIN messages m ON m.id = conv.latest_message_id
             ORDER BY conv.last_message_at DESC
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute([
            'summary_sender_user_id' => $userId,
            'summary_recipient_user_id' => $userId,
            'summary_scope_sender' => $userId,
            'summary_scope_recipient' => $userId,
        ]);

        return ['items' => $stmt->fetchAll(), 'total' => $total];
    }

    public function threadForUser(int $userId, int $otherUserId): array
    {
        $otherUser = $this->findConversationUser($otherUserId);
        if ($otherUser === null) {
            throw new \RuntimeException('Conversation participant not found.');
        }

        $conversationKey = $this->conversationKey($userId, $otherUserId);
        $messages = $this->messagesByConversationKey($conversationKey);
        $subject = $messages !== [] ? (string) $messages[0]['subject'] : '';
        $this->markThreadRead($userId, $otherUserId);

        return [
            'conversation_key' => $conversationKey,
            'subject' => $subject,
            'other_user' => $otherUser,
            'messages' => $messages,
        ];
    }

    public function replyInThread(array $sender, int $otherUserId, string $subject, string $body, string $locale): string
    {
        $otherUser = $this->findConversationUser($otherUserId);
        $errors = $this->validateMessageInput($sender, $otherUserId, $otherUser, trim($subject), trim($body));
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $conversationKey = $this->conversationKey((int) $sender['id'], $otherUserId);
        $messageId = $this->insertMessage((int) $sender['id'], $otherUserId, trim($subject), trim($body), $conversationKey);
        $this->audit->log((int) $sender['id'], 'message.replied', 'conversation', $conversationKey, [
            'message_id' => $messageId,
            'recipient_user_id' => $otherUserId,
        ]);
        $this->notifyRecipient($otherUser, $sender, trim($subject), $locale, '/messages/thread/' . $sender['id']);

        return $conversationKey;
    }

    public function conversationSummariesForAdmin(int $page, int $perPage): array
    {
        $offset = ($page - 1) * $perPage;
        $countStmt = $this->db->query(
            'SELECT COUNT(*) FROM (
                SELECT LEAST(sender_user_id, recipient_user_id) AS user_a_id,
                       GREATEST(sender_user_id, recipient_user_id) AS user_b_id
                FROM messages
                GROUP BY user_a_id, user_b_id
            ) conversations'
        );
        $total = (int) $countStmt->fetchColumn();

        $stmt = $this->db->prepare(
            "SELECT conv.user_a_id,
                    conv.user_b_id,
                    conv.last_message_at,
                    conv.latest_message_id,
                    conv.total_messages,
                    ua.first_name AS user_a_first_name,
                    ua.last_name AS user_a_last_name,
                    ub.first_name AS user_b_first_name,
                    ub.last_name AS user_b_last_name,
                    latest.subject AS latest_subject
             FROM (
                SELECT LEAST(sender_user_id, recipient_user_id) AS user_a_id,
                       GREATEST(sender_user_id, recipient_user_id) AS user_b_id,
                       MAX(created_at) AS last_message_at,
                       MAX(id) AS latest_message_id,
                       COUNT(*) AS total_messages
                FROM messages
                GROUP BY user_a_id, user_b_id
             ) conv
             LEFT JOIN users ua ON ua.id = conv.user_a_id
             LEFT JOIN users ub ON ub.id = conv.user_b_id
             INNER JOIN messages latest ON latest.id = conv.latest_message_id
             ORDER BY conv.last_message_at DESC
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute();

        return ['items' => $stmt->fetchAll(), 'total' => $total];
    }

    public function threadForAdmin(int $userAId, int $userBId): array
    {
        $userA = $this->findConversationUser($userAId, false);
        $userB = $this->findConversationUser($userBId, false);
        if ($userA === null || $userB === null) {
            throw new \RuntimeException('Conversation participants not found.');
        }

        return [
            'conversation_key' => $this->conversationKey($userAId, $userBId),
            'user_a' => $userA,
            'user_b' => $userB,
            'messages' => $this->messagesByConversationKey($this->conversationKey($userAId, $userBId)),
        ];
    }

    public function listForRedirect(int $userId, int $page, int $perPage): array
    {
        return $this->conversationSummariesForUser($userId, $page, $perPage);
    }

    private function validateMessageInput(array $sender, int $recipientId, ?array $recipient, string $subject, string $body): array
    {
        $errors = [];
        if ($recipientId <= 0) {
            $errors['recipient_user_id'] = 'validation.recipient_required';
        }
        if ($subject === '' || strlen($subject) > 190) {
            $errors['subject'] = 'validation.message_subject';
        }
        if ($body === '' || strlen($body) < 5 || strlen($body) > 5000) {
            $errors['body'] = 'validation.message_body';
        }
        if ($recipient === null || (int) ($recipient['is_active'] ?? 0) !== 1 || $recipientId === (int) $sender['id']) {
            $errors['recipient_user_id'] = 'validation.recipient_required';
        }

        return $errors;
    }

    private function insertMessage(int $senderId, int $recipientId, string $subject, string $body, string $conversationKey): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO messages (conversation_key, sender_user_id, recipient_user_id, subject, body, created_at)
             VALUES (:conversation_key, :sender_user_id, :recipient_user_id, :subject, :body, NOW())'
        );
        $stmt->execute([
            'conversation_key' => $conversationKey,
            'sender_user_id' => $senderId,
            'recipient_user_id' => $recipientId,
            'subject' => $subject,
            'body' => $body,
        ]);

        return (int) $this->db->lastInsertId();
    }

    private function notifyRecipient(array $recipient, array $sender, string $subject, string $locale, string $linkUrl, bool $isBroadcast = false): void
    {
        $title = $isBroadcast
            ? ($locale === 'nl' ? 'Nieuw beheerbericht' : 'New admin message')
            : ($locale === 'nl' ? 'Nieuw bericht ontvangen' : 'New message received');
        $body = $locale === 'nl'
            ? "Nieuw bericht van {$sender['first_name']} {$sender['last_name']}.\nOnderwerp: {$subject}"
            : "New message from {$sender['first_name']} {$sender['last_name']}.\nSubject: {$subject}";

        $this->notifications->create((int) $recipient['id'], $isBroadcast ? 'broadcast_message' : 'message_received', $title, $body, $linkUrl);

        if ($isBroadcast) {
            $this->mail->notifyBroadcastMessage($recipient, $sender, $subject, $locale);
        } else {
            $this->mail->notifyUserNewMessage($recipient, $sender, $subject, $locale);
        }
    }

    private function messagesByConversationKey(string $conversationKey): array
    {
        $stmt = $this->db->prepare(
            'SELECT m.*,
                    s.first_name AS sender_first_name,
                    s.last_name AS sender_last_name,
                    s.profile_picture_path AS sender_profile_picture_path,
                    r.first_name AS recipient_first_name,
                    r.last_name AS recipient_last_name
             FROM messages m
             LEFT JOIN users s ON s.id = m.sender_user_id
             LEFT JOIN users r ON r.id = m.recipient_user_id
             WHERE m.conversation_key = :conversation_key
             ORDER BY m.created_at ASC, m.id ASC'
        );
        $stmt->execute(['conversation_key' => $conversationKey]);

        return $stmt->fetchAll();
    }

    private function markThreadRead(int $userId, int $otherUserId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE messages
             SET read_at = NOW()
             WHERE conversation_key = :conversation_key
               AND recipient_user_id = :recipient_user_id
               AND read_at IS NULL'
        );
        $stmt->execute([
            'conversation_key' => $this->conversationKey($userId, $otherUserId),
            'recipient_user_id' => $userId,
        ]);
    }

    private function findResidentRecipient(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT u.*, r.slug AS role
             FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             WHERE u.id = :id
               AND r.slug = :role
               AND u.deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute([
            'id' => $id,
            'role' => 'user',
        ]);

        return $stmt->fetch() ?: null;
    }

    private function findConversationUser(int $id, bool $mustBeActive = true): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT u.*, r.slug AS role
             FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             WHERE u.id = :id
               AND u.deleted_at IS NULL
               AND (:must_be_active = 0 OR u.is_active = 1)
             LIMIT 1'
        );
        $stmt->execute([
            'id' => $id,
            'must_be_active' => $mustBeActive ? 1 : 0,
        ]);

        return $stmt->fetch() ?: null;
    }

    private function broadcastRecipients(int $senderUserId, string $scope, array $roleIds): array
    {
        $params = ['sender_user_id' => $senderUserId];
        $where = ['u.deleted_at IS NULL', 'u.is_active = 1', 'u.id <> :sender_user_id'];

        if ($scope === 'roles') {
            $roleIds = array_values(array_filter(array_map('intval', $roleIds), static fn(int $id): bool => $id > 0));
            if ($roleIds === []) {
                return [];
            }

            $placeholders = [];
            foreach ($roleIds as $index => $roleId) {
                $placeholder = 'role_id_' . $index;
                $placeholders[] = ':' . $placeholder;
                $params[$placeholder] = $roleId;
            }
            $where[] = 'u.role_id IN (' . implode(', ', $placeholders) . ')';
        }

        $stmt = $this->db->prepare(
            'SELECT u.id, u.first_name, u.last_name, u.email
             FROM users u
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY u.id ASC'
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function conversationKey(int $userAId, int $userBId): string
    {
        $first = min($userAId, $userBId);
        $second = max($userAId, $userBId);

        return 'direct:' . $first . ':' . $second;
    }
}
