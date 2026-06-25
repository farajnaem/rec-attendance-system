<?php

declare(strict_types=1);

class PermissionService
{
    /**
     * الصلاحيات القابلة للتفعيل لكل مستخدم.
     * defaults = الأدوار التي تحصل على الصلاحية تلقائياً عند الإنشاء.
     */
    public const DEFINITIONS = [
        'sign_attendance' => [
            'label' => 'تسجيل الحضور والانصراف',
            'defaults' => ['employee', 'admin_assistant', 'program_supervisor', 'director', 'system_admin'],
        ],
        'manage_daily_tasks' => [
            'label' => 'إدارة المهام اليومية (إضافة/تعديل/حذف)',
            'defaults' => ['employee', 'program_supervisor'],
        ],
        'complete_daily_tasks' => [
            'label' => 'إتمام المهام اليومية المسندة',
            'defaults' => ['employee', 'admin_assistant', 'program_supervisor', 'director', 'system_admin'],
        ],
        'manage_job_description' => [
            'label' => 'إدخال التوصيف الوظيفي (مهام العقد)',
            'defaults' => ['admin_assistant', 'director'],
        ],
        'monthly_employee_report' => [
            'label' => 'تقرير الموظف الشهري وتقييم الأداء',
            'defaults' => ['program_supervisor', 'director'],
        ],
        'view_reports_readonly' => [
            'label' => 'الاطلاع فقط على التقارير والحضور والغياب',
            'defaults' => ['admin_assistant', 'director'],
        ],
        'daily_notes_to_director' => [
            'label' => 'تقديم ملاحظات يومية للمدير العام',
            'defaults' => ['admin_assistant'],
        ],
        'print_archive_reports' => [
            'label' => 'طباعة الكشوف وأرشفتها',
            'defaults' => ['admin_assistant'],
        ],
        'transfer_employee' => [
            'label' => 'نقل موظف من دائرة إلى أخرى',
            'defaults' => ['director'],
        ],
        'manage_departments' => [
            'label' => 'إدارة الدوائر ومشرفيها',
            'defaults' => ['system_admin', 'director'],
        ],
        'manage_work_schedule' => [
            'label' => 'تحديد ساعات الدوام الرسمي',
            'defaults' => ['system_admin', 'director'],
        ],
        'manage_locations' => [
            'label' => 'إدارة المواقع الجغرافية للمؤسسة',
            'defaults' => ['system_admin'],
        ],
        'assign_main_tasks' => [
            'label' => 'إعطاء المهام الرئيسية لمشرفي الدوائر',
            'defaults' => ['director'],
        ],
        'manage_users' => [
            'label' => 'إدارة المستخدمين والصلاحيات',
            'defaults' => ['system_admin', 'director', 'program_supervisor'],
        ],
        'borrow_employee' => [
            'label' => 'استخدام موظف من دائرة أخرى مؤقتاً',
            'defaults' => ['system_admin', 'director', 'program_supervisor'],
        ],
    ];

    public static function allDefinitions(): array
    {
        return self::DEFINITIONS;
    }

    public static function defaultCodesForRole(string $role): array
    {
        $role = RoleHelper::normalizeRole($role);
        $codes = [];
        foreach (self::DEFINITIONS as $code => $def) {
            if (in_array($role, $def['defaults'], true)) {
                $codes[] = $code;
            }
        }
        if ($role === 'system_admin') {
            $codes = array_keys(self::DEFINITIONS);
        }
        return array_values(array_unique($codes));
    }

    public static function grantDefaults(int $userId, string $role): void
    {
        self::setForUser($userId, self::defaultCodesForRole($role));
    }

    /** يضيف الصلاحيات الافتراضية الناقصة عند إنشاء المستخدم فقط — لا يُستدعى عند تسجيل الدخول */
    public static function syncMissingDefaults(int $userId, string $role): void
    {
        $role = RoleHelper::normalizeRole($role);
        $defaults = self::defaultCodesForRole($role);
        $existing = self::codesForUser($userId);
        $missing = array_diff($defaults, $existing);
        if ($missing === []) {
            return;
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'INSERT INTO user_permissions (user_id, permission_code, granted) VALUES (?, ?, 1)'
        );
        foreach ($missing as $code) {
            $check = $pdo->prepare(
                'SELECT granted FROM user_permissions WHERE user_id = ? AND permission_code = ? LIMIT 1'
            );
            $check->execute([$userId, $code]);
            if (!$check->fetch()) {
                $stmt->execute([$userId, $code]);
            }
        }
    }

    public static function roleHasDefault(string $role, string $code): bool
    {
        if (!isset(self::DEFINITIONS[$code])) {
            return false;
        }
        $role = RoleHelper::normalizeRole($role);
        if ($role === 'system_admin') {
            return true;
        }
        return in_array($role, self::DEFINITIONS[$code]['defaults'], true);
    }

    public static function loadPermissionsToSession(int $userId, string $role): void
    {
        $_SESSION['permissions'] = self::effectiveCodesForUser($userId, $role);
    }

    public static function effectiveCodesForUser(int $userId, string $role): array
    {
        $role = RoleHelper::normalizeRole($role);
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT permission_code, granted FROM user_permissions WHERE user_id = ?'
        );
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll();

        if ($rows !== []) {
            $codes = [];
            foreach ($rows as $row) {
                if ((int) $row['granted'] === 1) {
                    $codes[] = (string) $row['permission_code'];
                }
            }

            return array_values(array_unique($codes));
        }

        return self::defaultCodesForRole($role);
    }

    public static function setForUser(int $userId, array $codes): void
    {
        $pdo = Database::getConnection();
        $pdo->prepare('DELETE FROM user_permissions WHERE user_id = ?')->execute([$userId]);
        $valid = array_keys(self::DEFINITIONS);
        $stmt = $pdo->prepare(
            'INSERT INTO user_permissions (user_id, permission_code, granted) VALUES (?, ?, 1)'
        );
        foreach ($codes as $code) {
            if (in_array($code, $valid, true)) {
                $stmt->execute([$userId, $code]);
            }
        }

        $userStmt = $pdo->prepare('SELECT role FROM users WHERE id = ? LIMIT 1');
        $userStmt->execute([$userId]);
        $user = $userStmt->fetch();
        if ($user) {
            self::loadPermissionsToSession($userId, (string) $user['role']);
        }
    }

    public static function codesForUser(int $userId): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT permission_code FROM user_permissions WHERE user_id = ? AND granted = 1'
        );
        $stmt->execute([$userId]);
        return array_column($stmt->fetchAll(), 'permission_code');
    }

    public static function can(int $userId, string $code): bool
    {
        if (!isset(self::DEFINITIONS[$code])) {
            return false;
        }

        if (isset($_SESSION['user_id']) && (int) $_SESSION['user_id'] === $userId && isset($_SESSION['permissions'])) {
            return in_array($code, $_SESSION['permissions'], true);
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT granted FROM user_permissions WHERE user_id = ? AND permission_code = ? LIMIT 1'
        );
        $stmt->execute([$userId, $code]);
        $row = $stmt->fetch();
        if ($row) {
            return (int) $row['granted'] === 1;
        }

        $userStmt = $pdo->prepare('SELECT role FROM users WHERE id = ? LIMIT 1');
        $userStmt->execute([$userId]);
        $user = $userStmt->fetch();
        if (!$user) {
            return false;
        }

        return self::roleHasDefault((string) $user['role'], $code);
    }

    public static function label(string $code): string
    {
        return self::DEFINITIONS[$code]['label'] ?? $code;
    }
}
