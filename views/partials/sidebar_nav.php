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
    <a class="rd-nav-link<?= $navActive('/employee/report') ?>" href="<?= e(url('/employee/report')) ?>">
        <span class="rd-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M16 13H8"/><path d="M16 17H8"/><path d="M10 9H8"/></svg></span>
        <span>تقريري الشهري</span>
    </a>
    <a class="rd-nav-link" href="<?= e(url('/employee/dashboard')) ?>#job-description">
        <span class="rd-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1"/></svg></span>
        <span>التوصيف الوظيفي</span>
    </a>

    <?php if (!RoleHelper::isEmployee(Auth::role())): ?>
    <div class="rd-nav-section">الإدارة</div>
    <a class="rd-nav-link<?= $navActive('/manager/dashboard') ?>" href="<?= e(url('/manager/dashboard')) ?>">
        <span class="rd-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M3 3v18h18"/><path d="M7 16l4-8 4 4 6-10"/></svg></span>
        <span>لوحة التحكم</span>
    </a>
    <?php if (Auth::can('manage_users')): ?>
    <a class="rd-nav-link<?= $navActive('/manager/users') ?>" href="<?= e(url('/manager/users')) ?>">
        <span class="rd-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg></span>
        <span>المستخدمون</span>
    </a>
    <?php endif; ?>
    <?php if (Auth::can('manage_departments')): ?>
    <a class="rd-nav-link<?= $navActive('/manager/departments') ?>" href="<?= e(url('/manager/departments')) ?>">
        <span class="rd-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 22V12h6v10"/></svg></span>
        <span>الدوائر</span>
    </a>
    <?php endif; ?>
    <?php if (RoleHelper::isOrgAdmin(Auth::role())): ?>
    <a class="rd-nav-link<?= $navActive('/manager/work-schedule') ?>" href="<?= e(url('/manager/work-schedule')) ?>">
        <span class="rd-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg></span>
        <span>ساعات الدوام</span>
    </a>
    <?php endif; ?>
    <?php if (Auth::can('manage_locations')): ?>
    <a class="rd-nav-link<?= $navActive('/manager/locations') ?>" href="<?= e(url('/manager/locations')) ?>">
        <span class="rd-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg></span>
        <span>مواقع العمل</span>
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
    <?php endif; ?>

    <?php if (RoleHelper::isSystemAdmin(Auth::role())): ?>
    <div class="rd-nav-section">النظام</div>
    <a class="rd-nav-link<?= $navActive('/manager/database') ?>" href="<?= e(url('/manager/database')) ?>">
        <span class="rd-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg></span>
        <span>نسخ احتياطي</span>
    </a>
    <?php endif; ?>
</nav>
