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
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT granted FROM user_permissions WHERE user_id = ? AND permission_code = ? LIMIT 1'
        );
        $stmt->execute([$userId, $code]);
        $row = $stmt->fetch();
        return $row && (int) $row['granted'] === 1;
    }

    public static function label(string $code): string
    {
        return self::DEFINITIONS[$code]['label'] ?? $code;
    }
}
