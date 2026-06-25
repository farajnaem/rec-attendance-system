<?php

declare(strict_types=1);

class CrossDepartmentService
{
    public static function create(
        int $userId,
        int $targetDepartmentId,
        string $startDate,
        ?string $endDate,
        ?string $notes,
        int $assignedBy
    ): int {
        $home = DepartmentService::currentForUser($userId);
        if (!$home) {
            throw new RuntimeException('الموظف غير مرتبط بدائرة أساسية.');
        }
        if ((int) $home['id'] === $targetDepartmentId) {
            throw new InvalidArgumentException('الموظف موجود أصلاً في هذه الدائرة.');
        }
        $dept = DepartmentService::get($targetDepartmentId);
        if (!$dept || !(int) $dept['is_active']) {
            throw new RuntimeException('الدائرة المستهدفة غير موجودة.');
        }
        if ($endDate && $endDate < $startDate) {
            throw new InvalidArgumentException('تاريخ النهاية يجب أن يكون بعد البداية.');
        }

        $pdo = Database::getConnection();
        $overlap = $pdo->prepare(
            'SELECT id FROM cross_department_assignments
             WHERE user_id = ? AND is_active = 1
             AND start_date <= COALESCE(?, "9999-12-31")
             AND COALESCE(end_date, "9999-12-31") >= ?'
        );
        $overlap->execute([$userId, $endDate, $startDate]);
        if ($overlap->fetch()) {
            throw new RuntimeException('يوجد تعيين مؤقت نشط يتداخل مع هذه الفترة.');
        }

        $pdo->prepare(
            'INSERT INTO cross_department_assignments
             (user_id, home_department_id, target_department_id, start_date, end_date, notes, assigned_by, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1)'
        )->execute([
            $userId,
            (int) $home['id'],
            $targetDepartmentId,
            $startDate,
            $endDate,
            $notes ? trim($notes) : null,
            $assignedBy,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function end(int $assignmentId, int $actorId, string $actorRole): void
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM cross_department_assignments WHERE id = ?');
        $stmt->execute([$assignmentId]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new RuntimeException('التعيين غير موجود.');
        }
        if (!ScopeService::canViewUser($actorId, $actorRole, (int) $row['user_id'])) {
            throw new RuntimeException('لا يمكنك إنهاء هذا التعيين.');
        }
        $pdo->prepare(
            'UPDATE cross_department_assignments SET is_active = 0, ended_by = ?, ended_at = CURRENT_TIMESTAMP WHERE id = ?'
        )->execute([$actorId, $assignmentId]);
    }

    public static function activeForUser(int $userId, ?string $date = null): ?array
    {
        $date = $date ?? date('Y-m-d');
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT c.*, d.name AS target_department_name, h.name AS home_department_name
             FROM cross_department_assignments c
             JOIN departments d ON d.id = c.target_department_id
             JOIN departments h ON h.id = c.home_department_id
             WHERE c.user_id = ? AND c.is_active = 1
             AND c.start_date <= ? AND (c.end_date IS NULL OR c.end_date >= ?)
             ORDER BY c.id DESC LIMIT 1'
        );
        $stmt->execute([$userId, $date, $date]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function listForUser(int $userId): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT c.*, d.name AS target_department_name, h.name AS home_department_name,
                    u.name AS assigned_by_name
             FROM cross_department_assignments c
             JOIN departments d ON d.id = c.target_department_id
             JOIN departments h ON h.id = c.home_department_id
             LEFT JOIN users u ON u.id = c.assigned_by
             WHERE c.user_id = ?
             ORDER BY c.id DESC'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public static function borrowedUserIdsForSupervisor(int $supervisorId): array
    {
        $deptIds = ScopeService::supervisedDepartmentIds($supervisorId);
        if (empty($deptIds)) {
            return [];
        }
        $today = date('Y-m-d');
        $pdo = Database::getConnection();
        $ph = implode(',', array_fill(0, count($deptIds), '?'));
        $params = array_merge($deptIds, [$today, $today]);
        $stmt = $pdo->prepare(
            "SELECT DISTINCT c.user_id FROM cross_department_assignments c
             WHERE c.is_active = 1 AND c.target_department_id IN ($ph)
             AND c.start_date <= ? AND (c.end_date IS NULL OR c.end_date >= ?)"
        );
        $stmt->execute($params);
        return array_map('intval', array_column($stmt->fetchAll(), 'user_id'));
    }

    public static function assertCanAssign(int $actorId, string $actorRole): void
    {
        $role = RoleHelper::normalizeRole($actorRole);
        if (in_array($role, ['system_admin', 'director'], true)) {
            return;
        }
        if ($role === 'program_supervisor' && PermissionService::can($actorId, 'borrow_employee')) {
            return;
        }
        throw new RuntimeException('لا يمكنك تعيين موظف من دائرة أخرى.');
    }
}
