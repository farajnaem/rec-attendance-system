<?php

declare(strict_types=1);

class RoleHelper
{
    /** الأدوار الجديدة */
    public const ROLES = [
        'employee' => 'موظف',
        'admin_assistant' => 'مساعد إداري',
        'program_supervisor' => 'مشرف برنامج',
        'director' => 'مدير',
        'system_admin' => 'مدير نظام',
    ];

    /** ترحيل الأدوار القديمة */
    private const LEGACY_MAP = [
        'admin' => 'system_admin',
        'manager' => 'program_supervisor',
        'dept_manager' => 'director',
    ];

    public static function normalizeRole(string $role): string
    {
        return self::LEGACY_MAP[$role] ?? $role;
    }

    public static function label(string $role): string
    {
        $role = self::normalizeRole($role);
        return self::ROLES[$role] ?? $role;
    }

    public static function all(): array
    {
        return self::ROLES;
    }

    public static function isValid(string $role): bool
    {
        return isset(self::ROLES[self::normalizeRole($role)]);
    }

    public static function isEmployee(string $role): bool
    {
        return self::normalizeRole($role) === 'employee';
    }

    public static function isManagement(string $role): bool
    {
        return !self::isEmployee($role);
    }

    /** مدير النظام فقط — عمليات حساسة (نسخ احتياطي، استيراد...) */
    public static function isSystemAdmin(string $role): bool
    {
        return self::normalizeRole($role) === 'system_admin';
    }

    /** مدير النظام أو المدير — إدارة الدوائر والصلاحيات */
    public static function isOrgAdmin(string $role): bool
    {
        return in_array(self::normalizeRole($role), ['system_admin', 'director'], true);
    }

    /** المدير ومدير النظام — إسناد الصلاحيات من المجموعة لأي موظف */
    public static function canEditPermissions(string $role): bool
    {
        $role = self::normalizeRole($role);
        return in_array($role, ['system_admin', 'director'], true);
    }

    /** @deprecated استخدم canEditPermissions */
    public static function canAssignPermissions(string $role): bool
    {
        return self::canEditPermissions($role);
    }

    public static function canManageUsers(string $role): bool
    {
        $role = self::normalizeRole($role);
        return in_array($role, ['system_admin', 'director'], true);
    }

    public static function dashboardPath(string $role): string
    {
        return self::isEmployee($role) ? '/employee/dashboard' : '/manager/dashboard';
    }

    /** أدوار يمكن تعيينها كمشرف دائرة */
    public static function supervisorRoles(): array
    {
        return ['system_admin', 'director', 'program_supervisor'];
    }

    /** جميع الأدوار الإدارية (للتحقق من المسارات) */
    public static function managementRoles(): array
    {
        return [
            'admin_assistant', 'program_supervisor', 'director', 'system_admin',
            // legacy
            'admin', 'manager', 'dept_manager',
        ];
    }

    public static function orgAdminRoles(): array
    {
        return ['system_admin', 'director', 'admin'];
    }
}
