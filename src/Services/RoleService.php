<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Container;
use App\Core\ValidationException;
use App\Security\Permissions;
use PDO;

/**
 * Manage the role/permission catalog used by staff authorization.
 *
 * The application intentionally uses a single primary role per user. This is a
 * simpler fit for the current MVP while still allowing administrators to create
 * custom roles with granular permission sets.
 */
final class RoleService
{
    private PDO $db;
    private AuditService $audit;

    public function __construct()
    {
        $this->db = Container::get('db');
        $this->audit = new AuditService();
    }

    public function allWithCounts(): array
    {
        $stmt = $this->db->query(
            'SELECT r.*,
                    COUNT(u.id) AS assigned_users
             FROM roles r
             LEFT JOIN users u ON u.role_id = r.id AND u.deleted_at IS NULL
             GROUP BY r.id
             ORDER BY r.is_super_admin DESC, r.is_system DESC, r.name ASC'
        );

        return $stmt->fetchAll();
    }

    public function assignableRoles(): array
    {
        $stmt = $this->db->query(
            'SELECT * FROM roles ORDER BY is_super_admin DESC, is_system DESC, name ASC'
        );

        return $stmt->fetchAll();
    }

    public function permissionCatalog(): array
    {
        return array_map(
            static fn(string $code): array => [
                'code' => $code,
                'label_key' => 'permission.' . str_replace('.', '_', $code) . '.label',
                'description_key' => 'permission.' . str_replace('.', '_', $code) . '.description',
            ],
            Permissions::all()
        );
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM roles WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $role = $stmt->fetch();
        if (!$role) {
            return null;
        }

        $permissionStmt = $this->db->prepare(
            'SELECT p.code
             FROM permissions p
             INNER JOIN role_permissions rp ON rp.permission_id = p.id
             WHERE rp.role_id = :role_id
             ORDER BY p.code ASC'
        );
        $permissionStmt->execute(['role_id' => $id]);
        $role['permissions'] = array_map(
            static fn(array $row): string => (string) $row['code'],
            $permissionStmt->fetchAll()
        );

        return $role;
    }

    public function create(array $input, int $actorUserId): int
    {
        [$name, $description, $permissionCodes] = $this->validateRoleInput($input);

        $slug = $this->uniqueSlug($name);
        $stmt = $this->db->prepare(
            'INSERT INTO roles (slug, name, description, is_system, is_super_admin, created_at, updated_at)
             VALUES (:slug, :name, :description, 0, 0, NOW(), NOW())'
        );
        $stmt->execute([
            'slug' => $slug,
            'name' => $name,
            'description' => $description,
        ]);

        $roleId = (int) $this->db->lastInsertId();
        $this->syncPermissions($roleId, $permissionCodes);
        $this->audit->log($actorUserId, 'admin.role_created', 'role', (string) $roleId, [
            'slug' => $slug,
            'permissions' => $permissionCodes,
        ]);

        return $roleId;
    }

    public function update(int $roleId, array $input, int $actorUserId): void
    {
        $role = $this->find($roleId);
        if ($role === null) {
            throw new \RuntimeException('Role not found.');
        }
        $this->assertMayManageRole($actorUserId, $role);

        [$name, $description, $permissionCodes] = $this->validateRoleInput($input);
        $stmt = $this->db->prepare(
            'UPDATE roles
             SET name = :name,
                 description = :description,
                 updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            'name' => $name,
            'description' => $description,
            'id' => $roleId,
        ]);

        if ((int) $role['is_super_admin'] !== 1) {
            $this->syncPermissions($roleId, $permissionCodes);
        }

        $this->audit->log($actorUserId, 'admin.role_updated', 'role', (string) $roleId, [
            'permissions' => (int) $role['is_super_admin'] === 1 ? 'super_admin_bypass' : $permissionCodes,
        ]);
    }

    public function delete(int $roleId, int $actorUserId): void
    {
        $role = $this->find($roleId);
        if ($role === null) {
            return;
        }
        $this->assertMayManageRole($actorUserId, $role);

        if ((int) $role['is_system'] === 1 || (int) $role['is_super_admin'] === 1) {
            throw new ValidationException(['name' => 'validation.role_protected']);
        }

        $usageStmt = $this->db->prepare(
            'SELECT COUNT(*) FROM users WHERE role_id = :role_id AND deleted_at IS NULL'
        );
        $usageStmt->execute(['role_id' => $roleId]);
        if ((int) $usageStmt->fetchColumn() > 0) {
            throw new ValidationException(['name' => 'validation.role_in_use']);
        }

        $this->db->prepare('DELETE FROM role_permissions WHERE role_id = :role_id')->execute(['role_id' => $roleId]);
        $this->db->prepare('DELETE FROM roles WHERE id = :id')->execute(['id' => $roleId]);
        $this->audit->log($actorUserId, 'admin.role_deleted', 'role', (string) $roleId);
    }

    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM roles WHERE slug = :slug LIMIT 1');
        $stmt->execute(['slug' => $slug]);

        return $stmt->fetch() ?: null;
    }

    private function validateRoleInput(array $input): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        $description = trim((string) ($input['description'] ?? ''));
        $permissionCodes = array_values(array_unique(array_filter(
            array_map('strval', $input['permissions'] ?? []),
            static fn(string $code): bool => $code !== ''
        )));

        $errors = [];
        if ($name === '' || strlen($name) > 150) {
            $errors['name'] = 'validation.role_name';
        }
        if (strlen($description) > 255) {
            $errors['description'] = 'validation.role_description';
        }

        $validCodes = Permissions::all();
        foreach ($permissionCodes as $code) {
            if (!in_array($code, $validCodes, true)) {
                $errors['permissions'] = 'validation.role_permissions';
                break;
            }
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return [$name, $description, $permissionCodes];
    }

    private function syncPermissions(int $roleId, array $permissionCodes): void
    {
        $this->db->prepare('DELETE FROM role_permissions WHERE role_id = :role_id')->execute(['role_id' => $roleId]);

        if ($permissionCodes === []) {
            return;
        }

        $lookup = $this->db->prepare('SELECT id FROM permissions WHERE code = :code LIMIT 1');
        $insert = $this->db->prepare(
            'INSERT INTO role_permissions (role_id, permission_id) VALUES (:role_id, :permission_id)'
        );

        foreach ($permissionCodes as $code) {
            $lookup->execute(['code' => $code]);
            $permissionId = $lookup->fetchColumn();
            if ($permissionId === false) {
                continue;
            }

            $insert->execute([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
            ]);
        }
    }

    private function uniqueSlug(string $name): string
    {
        $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $name) ?? '', '-'));
        $slug = $slug !== '' ? $slug : 'role';
        $candidate = $slug;
        $suffix = 2;

        while ($this->findBySlug($candidate) !== null) {
            $candidate = $slug . '-' . $suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function assertMayManageRole(int $actorUserId, array $role): void
    {
        if ((int) ($role['is_super_admin'] ?? 0) !== 1) {
            return;
        }

        $actorStmt = $this->db->prepare(
            'SELECT r.is_super_admin
             FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             WHERE u.id = :id
             LIMIT 1'
        );
        $actorStmt->execute(['id' => $actorUserId]);
        if ((int) $actorStmt->fetchColumn() !== 1) {
            throw new ValidationException(['name' => 'validation.super_admin_protected']);
        }
    }
}
