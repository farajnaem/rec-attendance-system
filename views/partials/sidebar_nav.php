<?php
$route = currentRoute();
$navActive = static function (string ...$paths) use ($route): string {
    foreach ($paths as $path) {
        if ($route === $path || str_starts_with($route, $path . '/')) {
            return ' is-active';
        }
    }
    return '';
};
$canSystem = Auth::can('manage_work_schedule')
    || Auth::can('manage_report_deadline')
    || Auth::can('manage_locations')
    || Auth::can('transfer_employee')
    || Auth::can('manage_system')
    || RoleHelper::isSystemAdmin(Auth::role());
?>
<nav class="rd-sidebar-nav" aria-label="القائمة الرئيسية">
    <div class="rd-nav-section">الموظف</div>
    <a class="rd-nav-link<?= $navActive('/employee/dashboard') ?>" href="<?= e(url('/employee/dashboard')) ?>">
        <span class="rd-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/></svg></span>
        <span>لوحتي</span>
    </a>
    <?php if (Auth::can('sign_attendance')): ?>
    <a class="rd-nav-link" href="<?= e(url('/employee/dashboard')) ?>#attendance">
        <span class="rd-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg></span>
        <span>تسجيل الحضور</span>
    </a>
    <?php endif; ?>
    <?php if (Auth::can('monthly_self_report')): ?>
    <a class="rd-nav-link<?= $navActive('/employee/report') ?>" href="<?= e(url('/employee/report')) ?>">
        <span class="rd-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M16 13H8"/><path d="M16 17H8"/><path d="M10 9H8"/></svg></span>
        <span>تقريري الشهري</span>
    </a>
    <?php endif; ?>
    <a class="rd-nav-link" href="<?= e(url('/employee/dashboard')) ?>#leaves">
        <span class="rd-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg></span>
        <span>الإجازات</span>
    </a>
    <a class="rd-nav-link" href="<?= e(url('/employee/dashboard')) ?>#job-description">
        <span class="rd-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1"/></svg></span>
        <span>التوصيف الوظيفي</span>
    </a>
    <?php if (RoleHelper::isEmployee(Auth::role())): ?>
    <a class="rd-nav-link<?= $navActive('/documents') ?>" href="<?= e(url('/documents')) ?>">
        <span class="rd-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg></span>
        <span>مستنداتي</span>
    </a>
    <?php endif; ?>

    <?php if (!RoleHelper::isEmployee(Auth::role())): ?>
    <?php if (Auth::can('view_employee_documents') || Auth::can('manage_employee_documents') || Auth::can('view_documents') || Auth::can('manage_documents')): ?>
    <a class="rd-nav-link<?= $navActive('/documents') ?>" href="<?= e(url('/documents')) ?>">
        <span class="rd-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg></span>
        <span>حافظات المستندات</span>
    </a>
    <?php endif; ?>
    <?php if (Auth::can('view_narrative_reports')): ?>
    <a class="rd-nav-link<?= $navActive('/manager/narrative-reports') ?>" href="<?= e(url('/manager/narrative-reports')) ?>">
        <span class="rd-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M16 13H8"/><path d="M16 17H8"/></svg></span>
        <span>التقارير السردية</span>
    </a>
    <?php endif; ?>
    <div class="rd-nav-section">الإدارة</div>
    <a class="rd-nav-link<?= $navActive('/manager/dashboard') ?>" href="<?= e(url('/manager/dashboard')) ?>">
        <span class="rd-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M3 3v18h18"/><path d="M7 16l4-8 4 4 6-10"/></svg></span>
        <span>لوحة التحكم</span>
    </a>
    <?php if (PermissionService::canAccessUsersList(Auth::id())): ?>
    <a class="rd-nav-link<?= $navActive('/manager/users', '/manager/borrow-employee') ?>" href="<?= e(url('/manager/users')) ?>">
        <span class="rd-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg></span>
        <span>الموظفون</span>
    </a>
    <?php endif; ?>
    <?php if (RoleHelper::canEditPermissions(Auth::role())): ?>
    <a class="rd-nav-link<?= $navActive('/manager/permissions', '/manager/users/permissions') ?>" href="<?= e(url('/manager/permissions')) ?>">
        <span class="rd-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></span>
        <span>مجموعة الصلاحيات</span>
    </a>
    <?php endif; ?>
    <?php if (PermissionService::canViewDepartments(Auth::id())): ?>
    <a class="rd-nav-link<?= $navActive('/manager/departments') ?>" href="<?= e(url('/manager/departments')) ?>">
        <span class="rd-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 22V12h6v10"/></svg></span>
        <span><?= PermissionService::canManageDepartments(Auth::id()) ? 'الدوائر' : 'الاطلاع على الدوائر' ?></span>
    </a>
    <?php endif; ?>
    <?php if (Auth::can('manage_job_description')): ?>
    <a class="rd-nav-link<?= $navActive('/manager/job-description') ?>" href="<?= e(url('/manager/job-description')) ?>">
        <span class="rd-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg></span>
        <span>إدارة التوصيف</span>
    </a>
    <?php endif; ?>
    <?php if (Auth::can('manage_daily_tasks')): ?>
    <a class="rd-nav-link<?= $navActive('/manager/tasks') ?>" href="<?= e(url('/manager/tasks')) ?>">
        <span class="rd-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></span>
        <span>المهام</span>
    </a>
    <?php endif; ?>
    <?php if (Auth::can('view_reports_readonly') || Auth::can('monthly_employee_report')): ?>
    <a class="rd-nav-link<?= $navActive('/manager/reports') ?>" href="<?= e(url('/manager/reports')) ?>">
        <span class="rd-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M18 20V10M12 20V4M6 20v-6"/></svg></span>
        <span>التقارير</span>
    </a>
    <a class="rd-nav-link<?= $navActive('/manager/attendance') ?>" href="<?= e(url('/manager/attendance')) ?>">
        <span class="rd-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 11l-3-3m0 0l-3 3m3-3v12"/></svg></span>
        <span>حضور الفريق</span>
    </a>
    <?php endif; ?>
    <a class="rd-nav-link<?= $navActive('/manager/leaves') ?>" href="<?= e(url('/manager/leaves')) ?>">
        <span class="rd-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/><path d="M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01"/></svg></span>
        <span>الإجازات</span>
    </a>
    <?php endif; ?>

    <?php if ($canSystem): ?>
    <div class="rd-nav-section">إدارة النظام</div>
    <a class="rd-nav-link<?= $navActive('/manager/system', '/manager/work-schedule', '/manager/locations', '/manager/audit', '/manager/database') ?>" href="<?= e(url('/manager/system')) ?>">
        <span class="rd-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg></span>
        <span>إعدادات النظام</span>
    </a>
    <?php endif; ?>
</nav>
