<?php

declare(strict_types=1);

class PermissionService
{
    private const ALL_ROLES = [
        'employee',
        'admin_assistant',
        'program_supervisor',
        'director',
        'system_admin',
    ];

    /**
     * الصلاحيات القابلة للتفعيل لكل موظف.
     * defaults = الأدوار التي تحصل على الصلاحية تلقائياً عند الإنشاء.
     */
    public const DEFINITIONS = [
        'sign_attendance' => [
            'label' => 'تسجيل الحضور والانصراف',
            'defaults' => self::ALL_ROLES,
        ],
        'monthly_self_report' => [
            'label' => 'التقرير الشهري للموظف',
            'defaults' => self::ALL_ROLES,
        ],
        'view_reports_readonly' => [
            'label' => 'الاطلاع على تقارير الموظفين (قراءة فقط)',
            'defaults' => ['admin_assistant'],
        ],
        'monthly_employee_report' => [
            'label' => 'تقييم تقارير الموظفين والأداء',
            'defaults' => ['program_supervisor', 'director'],
        ],
        'complete_own_tasks' => [
            'label' => 'إتمام المهام المسندة إلي',
            'defaults' => self::ALL_ROLES,
        ],
        'respond_complete_tasks' => [
            'label' => 'الرد على الملاحظات وإتمام المهام',
            'defaults' => ['program_supervisor', 'director'],
        ],
        'manage_job_description' => [
            'label' => 'إدخال التوصيف الوظيفي',
            'defaults' => ['admin_assistant', 'program_supervisor', 'director'],
        ],
        'daily_notes_to_director' => [
            'label' => 'تقديم ملاحظات يومية للمدير',
            'defaults' => self::ALL_ROLES,
        ],
        'assign_main_tasks' => [
            'label' => 'إعطاء المهام لمشرفي الدوائر',
            'defaults' => ['director'],
        ],
        'manage_daily_tasks' => [
            'label' => 'إدارة المهام اليومية (إسناد وتعديل)',
            'defaults' => ['program_supervisor', 'director'],
        ],
        'print_archive_reports' => [
            'label' => 'طباعة الكشوف',
            'defaults' => ['admin_assistant'],
        ],
        'manage_documents' => [
            'label' => 'رفع المستندات',
            'defaults' => self::ALL_ROLES,
        ],
        'view_documents' => [
            'label' => 'تحميل المستندات (عام)',
            'defaults' => self::ALL_ROLES,
        ],
        'view_employee_documents' => [
            'label' => 'الاطلاع على حافظة مستندات الموظف',
            'defaults' => self::ALL_ROLES,
        ],
        'manage_employee_documents' => [
            'label' => 'رفع مستندات في حافظة الموظف',
            'defaults' => self::ALL_ROLES,
        ],
        'view_narrative_reports' => [
            'label' => 'مشاهدة التقارير السردية للموظفين',
            'defaults' => ['program_supervisor', 'director'],
        ],
        'request_work_break' => [
            'label' => 'تسجيل مغادرة أثناء العمل',
            'defaults' => ['employee'],
        ],
        'approve_work_breaks' => [
            'label' => 'اعتماد مغادرات أثناء العمل',
            'defaults' => ['program_supervisor', 'director'],
        ],
        'manage_work_schedule' => [
            'label' => 'تحديد ساعات الدوام',
            'defaults' => ['director'],
        ],
        'manage_report_deadline' => [
            'label' => 'تحديد مهلة تقديم التقرير السردي',
            'defaults' => ['director'],
        ],
        'transfer_employee' => [
            'label' => 'نقل الموظفين بين الدوائر',
            'defaults' => ['director', 'program_supervisor'],
        ],
        'borrow_employee' => [
            'label' => 'الاستعارة المؤقتة للموظفين',
            'defaults' => ['director', 'program_supervisor', 'admin_assistant'],
        ],
        'view_all_users' => [
            'label' => 'مشاهدة جميع الموظفين (العدد والحالات)',
            'defaults' => ['admin_assistant'],
        ],
        'view_department_users' => [
            'label' => 'مشاهدة موظفي الدائرة',
            'defaults' => ['program_supervisor'],
        ],
        'edit_department_users' => [
            'label' => 'تعديل موظفي الدائرة (بدون حذف)',
            'defaults' => ['program_supervisor'],
        ],
        'manage_users' => [
            'label' => 'إدارة الموظفين (إضافة وتعديل كامل)',
            'defaults' => ['director'],
        ],
        'manage_permissions' => [
            'label' => 'تعديل صلاحيات الموظفين',
            'defaults' => ['director', 'system_admin'],
        ],
        'manage_locations' => [
            'label' => 'إدارة المواقع الجغرافية',
            'defaults' => ['admin_assistant'],
        ],
        'manage_departments' => [
            'label' => 'إدارة الدوائر (إضافة وتعديل)',
            'defaults' => ['director'],
        ],
        'view_departments' => [
            'label' => 'الاطلاع على الدوائر (قراءة فقط)',
            'defaults' => ['program_supervisor'],
        ],
        'manage_system' => [
            'label' => 'إعدادات النظام (تدقيق، نسخ احتياطي)',
            'defaults' => ['director'],
        ],
    ];

    public static function allDefinitions(): array
    {
        return self::DEFINITIONS;
    }

    /** مجموعة الصلاحيات التي يمكن للمدير/مدير النظام إسنادها لأي موظف */
    public static function assignablePool(): array
    {
        return self::DEFINITIONS;
    }

    public static function canGrantToOthers(int $grantorId, string $grantorRole): bool
    {
        if (RoleHelper::canEditPermissions($grantorRole)) {
            return true;
        }

        return self::can($grantorId, 'manage_permissions');
    }

    /** الصلاحيات مجمّعة للعرض في واجهة الاختيار */
    public static function groupedForPicker(): array
    {
        $groups = [
            'employee' => ['title' => 'صلاحيات الموظف', 'codes' => []],
            'management' => ['title' => 'صلاحيات الإدارة', 'codes' => []],
            'system' => ['title' => 'صلاحيات النظام', 'codes' => []],
        ];
        $map = [
            'sign_attendance' => 'employee',
            'monthly_self_report' => 'employee',
            'complete_own_tasks' => 'employee',
            'daily_notes_to_director' => 'employee',
            'view_reports_readonly' => 'management',
            'monthly_employee_report' => 'management',
            'respond_complete_tasks' => 'management',
            'manage_job_description' => 'management',
            'assign_main_tasks' => 'management',
            'manage_daily_tasks' => 'management',
            'print_archive_reports' => 'management',
            'manage_documents' => 'management',
            'view_documents' => 'management',
            'view_employee_documents' => 'management',
            'manage_employee_documents' => 'management',
            'view_narrative_reports' => 'management',
            'request_work_break' => 'employee',
            'approve_work_breaks' => 'management',
            'transfer_employee' => 'management',
            'borrow_employee' => 'management',
            'view_all_users' => 'management',
            'view_department_users' => 'management',
            'edit_department_users' => 'management',
            'manage_users' => 'management',
            'manage_permissions' => 'management',
            'manage_departments' => 'management',
            'view_departments' => 'management',
            'manage_work_schedule' => 'system',
            'manage_report_deadline' => 'system',
            'manage_locations' => 'system',
            'manage_system' => 'system',
        ];
        foreach (array_keys(self::DEFINITIONS) as $code) {
            $group = $map[$code] ?? 'management';
            $groups[$group]['codes'][] = $code;
        }

        return $groups;
    }

    /** خريطة الصلاحيات الافتراضية لكل دور — للواجهة */
    public static function roleDefaultsMap(): array
    {
        $map = [];
        foreach (self::ALL_ROLES as $role) {
            $map[$role] = self::defaultCodesForRole($role);
        }

        return $map;
    }

    public static function defaultCodesForRole(string $role): array
    {
        $role = RoleHelper::normalizeRole($role);
        if ($role === 'system_admin') {
            return array_keys(self::DEFINITIONS);
        }

        $codes = [];
        foreach (self::DEFINITIONS as $code => $def) {
            if (in_array($role, $def['defaults'], true)) {
                $codes[] = $code;
            }
        }

        return array_values(array_unique($codes));
    }

    public static function grantDefaults(int $userId, string $role): void
    {
        self::setForUser($userId, self::defaultCodesForRole($role));
    }

    /** يضيف الصلاحيات الافتراضية الناقصة — لا يُستدعى عند تسجيل الدخول */
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

    public static function canAccessUsersList(int $userId): bool
    {
        return self::can($userId, 'manage_users')
            || self::can($userId, 'view_all_users')
            || self::can($userId, 'view_department_users')
            || self::can($userId, 'borrow_employee');
    }

    public static function canViewAllUsers(int $userId): bool
    {
        return self::can($userId, 'manage_users') || self::can($userId, 'view_all_users');
    }

    public static function canEditUserRecord(int $userId): bool
    {
        return self::can($userId, 'manage_users') || self::can($userId, 'edit_department_users');
    }

    public static function hasFullUserManagement(int $userId): bool
    {
        return self::can($userId, 'manage_users');
    }

    public static function canViewDepartments(int $userId): bool
    {
        return self::can($userId, 'manage_departments') || self::can($userId, 'view_departments');
    }

    public static function canManageDepartments(int $userId): bool
    {
        return self::can($userId, 'manage_departments');
    }
}
