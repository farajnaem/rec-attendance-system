<?php

declare(strict_types=1);

class ScopeService
{
    /** قائمة المستخدمين المرئيين للمدير/المشرف في صفحة المستخدمين */
    public static function visibleUsers(int $actorId, string $actorRole): array
    {
        $role = RoleHelper::normalizeRole($actorRole);
        if (in_array($role, ['system_admin', 'director'], true)) {
            return UserService::listForAdmin();
        }
        if ($role === 'program_supervisor') {
            return self::usersForSupervisor($actorId);
        }
        return UserService::listForManager($actorId);
    }

    /** موظفون/مستخدمون نشطون لقوائم المهام والتقارير والحضور */
    public static function visibleStaff(int $actorId, string $actorRole): array
    {
        $role = RoleHelper::normalizeRole($actorRole);
        if (in_array($role, ['system_admin', 'director'], true)) {
            $pdo = Database::getConnection();
            return $pdo->query(
                'SELECT u.id, u.name, u.email, u.timezone, u.role
                 FROM users u WHERE u.is_active = 1 ORDER BY u.name'
            )->fetchAll();
        }
        if ($role === 'program_supervisor') {
            return self::staffForSupervisor($actorId);
        }
        return TaskService::teamEmployees($actorId);
    }

    public static function canViewUser(int $actorId, string $actorRole, int $targetUserId): bool
    {
        if ($actorId === $targetUserId) {
            return true;
        }
        $role = RoleHelper::normalizeRole($actorRole);
        if (in_array($role, ['system_admin', 'director'], true)) {
            return UserService::getById($targetUserId) !== null;
        }
        foreach (self::visibleUsers($actorId, $actorRole) as $u) {
            if ((int) $u['id'] === $targetUserId) {
                return true;
            }
        }
        return false;
    }

    public static function canViewReport(int $actorId, string $actorRole, int $subjectUserId): bool
    {
        if ($actorId === $subjectUserId) {
            return true;
        }
        if (!PermissionService::can($actorId, 'monthly_employee_report')
            && !PermissionService::can($actorId, 'view_reports_readonly')) {
            return false;
        }
        return self::canViewUser($actorId, $actorRole, $subjectUserId);
    }

    /** أقسام يشرف عليها المستخدم */
    public static function supervisedDepartmentIds(int $userId): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT department_id FROM department_supervisors
             WHERE user_id = ? AND is_active = 1'
        );
        $stmt->execute([$userId]);
        return array_map('intval', array_column($stmt->fetchAll(), 'department_id'));
    }

    private static function usersForSupervisor(int $supervisorId): array
    {
        $pdo = Database::getConnection();
        $deptIds = self::supervisedDepartmentIds($supervisorId);
        $ids = self::collectUserIds($supervisorId, $deptIds);
        if (empty($ids)) {
            return UserService::listForManager($supervisorId);
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare(
            "SELECT u.*, m.name AS manager_name, d.name AS department_name
             FROM users u
             LEFT JOIN users m ON m.id = u.manager_id
             LEFT JOIN user_departments ud ON ud.user_id = u.id AND ud.is_current = 1
             LEFT JOIN departments d ON d.id = ud.department_id
             WHERE u.id IN ($placeholders)
             ORDER BY u.role, u.name"
        );
        $stmt->execute(array_values($ids));
        return $stmt->fetchAll();
    }

    private static function staffForSupervisor(int $supervisorId): array
    {
        $pdo = Database::getConnection();
        $deptIds = self::supervisedDepartmentIds($supervisorId);
        $ids = self::collectUserIds($supervisorId, $deptIds);
        if (empty($ids)) {
            return TaskService::teamEmployees($supervisorId);
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare(
            "SELECT u.id, u.name, u.email, u.timezone, u.role
             FROM users u WHERE u.id IN ($placeholders) AND u.is_active = 1
             ORDER BY u.name"
        );
        $stmt->execute(array_values($ids));
        return $stmt->fetchAll();
    }

    private static function collectUserIds(int $supervisorId, array $deptIds): array
    {
        $pdo = Database::getConnection();
        $ids = [];

        if (!empty($deptIds)) {
            $ph = implode(',', array_fill(0, count($deptIds), '?'));
            $stmt = $pdo->prepare(
                "SELECT DISTINCT u.id FROM users u
                 JOIN user_departments ud ON ud.user_id = u.id AND ud.is_current = 1
                 WHERE ud.department_id IN ($ph)"
            );
            $stmt->execute($deptIds);
            foreach ($stmt->fetchAll() as $row) {
                $ids[(int) $row['id']] = true;
            }
        }

        $direct = $pdo->prepare('SELECT id FROM users WHERE manager_id = ? AND is_active = 1');
        $direct->execute([$supervisorId]);
        foreach ($direct->fetchAll() as $row) {
            $ids[(int) $row['id']] = true;
        }

        foreach (CrossDepartmentService::borrowedUserIdsForSupervisor($supervisorId) as $uid) {
            $ids[$uid] = true;
        }

        return array_keys($ids);
    }
}
