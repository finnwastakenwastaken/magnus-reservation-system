<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Container;
use PDO;

/**
 * Stores user-visible in-app notifications.
 *
 * Notifications complement flash messages for actions triggered by staff on a
 * user's behalf, such as reservation changes.
 */
final class NotificationService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Container::get('db');
    }

    public function create(int $userId, string $type, string $title, string $body, ?string $linkUrl = null): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO notifications (user_id, type, title, body, link_url, created_at)
             VALUES (:user_id, :type, :title, :body, :link_url, NOW())'
        );
        $stmt->execute([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'link_url' => $linkUrl,
        ]);
    }

    public function recentForUser(int $userId, int $limit = 5): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM notifications
             WHERE user_id = :user_id
             ORDER BY created_at DESC
             LIMIT ' . max(1, $limit)
        );
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll();
    }

    public function paginatedForUser(int $userId, int $page, int $perPage): array
    {
        $offset = ($page - 1) * $perPage;
        $countStmt = $this->db->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = :user_id');
        $countStmt->execute(['user_id' => $userId]);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $this->db->prepare(
            "SELECT *
             FROM notifications
             WHERE user_id = :user_id
             ORDER BY is_read ASC, created_at DESC
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute(['user_id' => $userId]);

        return ['items' => $stmt->fetchAll(), 'total' => $total];
    }

    public function markRead(int $notificationId, int $userId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE notifications
             SET is_read = 1
             WHERE id = :id
               AND user_id = :user_id'
        );
        $stmt->execute([
            'id' => $notificationId,
            'user_id' => $userId,
        ]);
    }

    public function markAllRead(int $userId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE notifications
             SET is_read = 1
             WHERE user_id = :user_id
               AND is_read = 0'
        );
        $stmt->execute(['user_id' => $userId]);
    }
}
