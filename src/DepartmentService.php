<?php

declare(strict_types=1);

class DepartmentService
{
    public static function all(bool $activeOnly = true): array
    {
        $pdo = Database::getConnection();
        $sql = 'SELECT d.*,
                (SELECT u.name FROM department_supervisors ds
                 JOIN users u ON u.id = ds.user_id
                 WHERE ds.department_id = d.id AND ds.is_active = 1
                 ORDER BY ds.id DESC LIMIT 1) AS supervisor_name,
                (SELECT ds.user_id FROM department_supervisors ds
                 WHERE ds.department_id = d.id AND ds.is_active = 1
                 ORDER BY ds.id DESC LIMIT 1) AS supervisor_id,
                (SELECT COUNT(*) FROM user_departments ud
                 WHERE ud.department_id = d.id AND ud.is_current = 1) AS member_count
                FROM departments d';
        if ($activeOnly) {
            $sql .= ' WHERE d.is_active = 1';
        }
        $sql .= ' ORDER BY d.name';
        return $pdo->query($sql)->fetchAll();
    }

    public static function get(int $id): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM departments WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(string $name, ?string $description, ?int $supervisorId): int
    {
        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException('اسم الدائرة مطلوب.');
        }
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'INSERT INTO departments (name, description, is_active) VALUES (?, ?, 1)'
        );
        $stmt->execute([$name, $description ? trim($description) : null]);
        $id = (int) $pdo->lastInsertId();
        if ($supervisorId) {
            self::setSupervisor($id, $supervisorId);
        }
        return $id;
    }

    public static function update(int $id, string $name, ?string $description, ?int $supervisorId): void
    {
        $dept = self::get($id);
        if (!$dept) {
            throw new RuntimeException('الدائرة غير موجودة.');
        }
        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException('اسم الدائرة مطلوب.');
        }
        $pdo = Database::getConnection();
        $pdo->prepare('UPDATE departments SET name = ?, description = ? WHERE id = ?')
            ->execute([$name, $description ? trim($description) : null, $id]);
        if ($supervisorId) {
            self::setSupervisor($id, $supervisorId);
        }
    }

    public static function delete(int $id): void
    {
        $pdo = Database::getConnection();
        $count = $pdo->prepare(
            'SELECT COUNT(*) FROM user_departments WHERE department_id = ? AND is_current = 1'
        );
        $count->execute([$id]);
        if ((int) $count->fetchColumn() > 0) {
            throw new RuntimeException('لا يمكن حذف دائرة فيها موظفون. انقلهم أولاً.');
        }
        $pdo->prepare('UPDATE departments SET is_active = 0 WHERE id = ?')->execute([$id]);
    }

    public static function setSupervisor(int $departmentId, int $userId): void
    {
        $pdo = Database::getConnection();
        $user = $pdo->prepare('SELECT id, role FROM users WHERE id = ? AND is_active = 1');
        $user->execute([$userId]);
        $u = $user->fetch();
        if (!$u) {
            throw new RuntimeException('المشرف غير موجود.');
        }
        $role = RoleHelper::normalizeRole($u['role']);
        if (!in_array($role, ['program_supervisor', 'director', 'system_admin'], true)) {
            throw new InvalidArgumentException('يجب أن يكون المشرف: مشرف برنامج أو مدير أو مدير نظام.');
        }
        $pdo->prepare(
            'UPDATE department_supervisors SET is_active = 0 WHERE department_id = ?'
        )->execute([$departmentId]);
        $pdo->prepare(
            'INSERT INTO department_supervisors (department_id, user_id, is_active) VALUES (?, ?, 1)'
        )->execute([$departmentId, $userId]);
    }

    public static function currentForUser(int $userId): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT d.*, ud.assigned_at
             FROM user_departments ud
             JOIN departments d ON d.id = ud.department_id
             WHERE ud.user_id = ? AND ud.is_current = 1
             LIMIT 1'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function assignUser(int $userId, int $departmentId, int $assignedBy, ?string $notes = null): void
    {
        $dept = self::get($departmentId);
        if (!$dept || !(int) $dept['is_active']) {
            throw new RuntimeException('الدائرة غير موجودة أو معطّلة.');
        }
        $pdo = Database::getConnection();
        $pdo->prepare(
            'UPDATE user_departments SET is_current = 0 WHERE user_id = ? AND is_current = 1'
        )->execute([$userId]);
        $pdo->prepare(
            'INSERT INTO user_departments (user_id, department_id, assigned_by, is_current, notes)
             VALUES (?, ?, ?, 1, ?)'
        )->execute([$userId, $departmentId, $assignedBy, $notes]);
    }

    public static function transferUser(int $userId, int $toDepartmentId, int $assignedBy, ?string $notes = null): void
    {
        self::assignUser($userId, $toDepartmentId, $assignedBy, $notes ?: 'نقل بين الدوائر');
    }

    public static function supervisorsForSelect(): array
    {
        $pdo = Database::getConnection();
        $roles = ['program_supervisor', 'director', 'system_admin', 'manager', 'dept_manager', 'admin'];
        $placeholders = implode(',', array_fill(0, count($roles), '?'));
        $stmt = $pdo->prepare(
            "SELECT id, name, role FROM users WHERE role IN ($placeholders) AND is_active = 1 ORDER BY name"
        );
        $stmt->execute($roles);
        return $stmt->fetchAll();
    }

    public static function members(int $departmentId): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT u.id, u.name, u.email, u.role, ud.assigned_at
             FROM user_departments ud
             JOIN users u ON u.id = ud.user_id
             WHERE ud.department_id = ? AND ud.is_current = 1
             ORDER BY u.name'
        );
        $stmt->execute([$departmentId]);
        return $stmt->fetchAll();
    }
}
