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
}
