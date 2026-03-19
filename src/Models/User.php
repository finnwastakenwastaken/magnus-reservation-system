<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Container;
use PDO;

/**
 * Lightweight user lookup model.
 *
 * More complex account workflows live in UserService; this class exists mainly
 * for simple current-user hydration in the auth layer.
 */
final class User
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Container::get('db');
    }

    public function findById(int $id): ?array
    {
        // Soft-deleted/anonymized accounts must no longer hydrate active
        // sessions. Returning null here forces authorization checks to fail
        // cleanly if a session outlives the account.
        $stmt = $this->db->prepare($this->hydratedUserSql('u.id = :id AND u.deleted_at IS NULL'));
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch();

        if ($user) {
            $user['permission_codes'] = $this->permissionCodesFromRow($user);
        }

        return $user ?: null;
    }

    private function hydratedUserSql(string $whereSql): string
    {
        return
            'SELECT u.*,
                    r.slug AS role,
                    r.name AS role_name,
                    r.description AS role_description,
                    r.is_super_admin,
                    (
                        SELECT COUNT(*)
                        FROM notifications n
                        WHERE n.user_id = u.id
                          AND n.is_read = 0
                    ) AS unread_notification_count,
                    (
                        SELECT GROUP_CONCAT(p.code ORDER BY p.code SEPARATOR \',\')
                        FROM role_permissions rp
                        INNER JOIN permissions p ON p.id = rp.permission_id
                        WHERE rp.role_id = r.id
                    ) AS permission_codes_csv
             FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             WHERE ' . $whereSql . '
             LIMIT 1';
    }

    /**
     * @return list<string>
     */
    private function permissionCodesFromRow(array $row): array
    {
        $csv = trim((string) ($row['permission_codes_csv'] ?? ''));
        if ($csv === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $csv))));
    }
}
