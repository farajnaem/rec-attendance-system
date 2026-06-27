<?php $title = 'لوحة المشرف'; ?>
<div class="page-header">
    <h1>لوحة المشرف</h1>
    <p class="page-header__subtitle">
        <?= e(RoleHelper::label(Auth::role())) ?> — تاريخ اليوم: <?= e($today) ?>
    </p>
</div>
<div class="stats">
    <div class="stat-box">
        <div class="value"><?= (int) ($stats['total_employees'] ?? count($team)) ?></div>
        <div class="label">عدد الموظفين في نطاقك</div>
    </div>
    <div class="stat-box">
        <div class="value"><?= (int) ($stats['present_today'] ?? 0) ?></div>
        <div class="label">حاضرون اليوم</div>
    </div>
    <div class="stat-box">
        <div class="value"><?= (int) ($stats['absent_today'] ?? 0) ?></div>
        <div class="label">غائبون اليوم</div>
    </div>
    <div class="stat-box">
        <div class="value"><?= (int) ($stats['complete_today'] ?? 0) ?></div>
        <div class="label">حضور كامل اليوم</div>
    </div>
    <div class="stat-box">
        <div class="value"><?= (int) ($stats['late_today'] ?? 0) ?></div>
        <div class="label">متأخرون اليوم</div>
    </div>
    <div class="stat-box">
        <div class="value"><?= (int) ($stats['pending_tasks'] ?? 0) ?></div>
        <div class="label">مهام معلّقة</div>
    </div>
</div>

<?php if (Auth::can('view_reports_readonly') || Auth::can('monthly_employee_report')): ?>
<div class="card">
    <h2>حضور الفريق اليوم</h2>
    <?php if (Auth::role() === 'system_admin' && empty($attendance)): ?>
        <p class="text-muted">لا يوجد موظفون مسجلون بعد، أو استخدم صفحة الحضور لعرض الجميع.</p>
    <?php else: ?>
    <table>
        <thead><tr><th>الموظف</th><th>حضور</th><th>انصراف</th></tr></thead>
        <tbody>
        <?php if (empty($attendance)): ?>
            <tr><td colspan="3">لا يوجد موظفون في نطاقك</td></tr>
        <?php else: foreach ($attendance as $a): ?>
        <tr>
            <td><?= e($a['name']) ?></td>
            <td><?= $a['check_in_utc'] ? e(TimezoneHelper::formatArabic($a['check_in_utc'], $a['timezone'])) : '—' ?></td>
            <td><?= $a['check_out_utc'] ? e(TimezoneHelper::formatArabic($a['check_out_utc'], $a['timezone'])) : '—' ?></td>
        </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="grid-2">
    <?php if (Auth::can('manage_users')): ?>
    <a href="<?= e(url('/manager/users')) ?>" class="card link-card">
        <h3>إدارة الموظفين</h3>
        <p class="text-muted">إضافة موظفين وتعديل الصلاحيات</p>
    </a>
    <?php endif; ?>
    <?php if (Auth::can('manage_departments')): ?>
    <a href="<?= e(url('/manager/departments')) ?>" class="card link-card">
        <h3>الدوائر</h3>
        <p class="text-muted">إدارة الدوائر ومشرفيها</p>
    </a>
    <?php endif; ?>
    <?php if (RoleHelper::isOrgAdmin(Auth::role()) || Auth::can('manage_work_schedule') || Auth::can('manage_locations') || Auth::can('borrow_employee') || RoleHelper::isSystemAdmin(Auth::role())): ?>
    <a href="<?= e(url('/manager/system')) ?>" class="card link-card">
        <h3>إعدادات النظام</h3>
        <p class="text-muted">ساعات الدوام، المواقع، الاستعارة، التدقيق، والنسخ الاحتياطي</p>
    </a>
    <?php endif; ?>
    <?php if (Auth::can('manage_job_description')): ?>
    <a href="<?= e(url('/manager/job-description')) ?>" class="card link-card">
        <h3>التوصيف الوظيفي</h3>
        <p class="text-muted">المسمى والمهام الفرعية</p>
    </a>
    <?php endif; ?>
    <?php if (Auth::can('manage_daily_tasks')): ?>
    <a href="<?= e(url('/manager/tasks')) ?>" class="card link-card">
        <h3>إدارة المهام</h3>
        <p class="text-muted">إضافة مهام يومية ومتابعة إتمامها</p>
    </a>
    <?php endif; ?>
    <?php if (!RoleHelper::isEmployee(Auth::role())): ?>
    <a href="<?= e(url('/manager/leaves')) ?>" class="card link-card">
        <h3>الإجازات</h3>
        <p class="text-muted">مراجعة طلبات الإجازة</p>
    </a>
    <?php endif; ?>
    <?php if (Auth::can('view_reports_readonly') || Auth::can('monthly_employee_report')): ?>
    <a href="<?= e(url('/manager/reports')) ?>" class="card link-card">
        <h3>التقارير الشهرية</h3>
        <p class="text-muted">درجة الحضور والأداء لكل موظف</p>
    </a>
    <a href="<?= e(url('/manager/attendance')) ?>" class="card link-card">
        <h3>حضور الفريق</h3>
        <p class="text-muted">سجل الحضور والانصراف</p>
    </a>
    <?php endif; ?>
    <a href="<?= e(url('/employee/dashboard')) ?>" class="card link-card">
        <h3>لوحتي الشخصية</h3>
        <p class="text-muted">تسجيل الحضور ومهامي اليومية</p>
    </a>
    <?php if (RoleHelper::isSystemAdmin(Auth::role())): ?>
    <a href="<?= e(url('/manager/audit')) ?>" class="card link-card">
        <h3>سجل التدقيق</h3>
        <p class="text-muted">مراجعة عمليات النظام</p>
    </a>
    <a href="<?= e(url('/manager/database')) ?>" class="card link-card">
        <h3>نسخ احتياطي واستيراد</h3>
        <p class="text-muted">تصدير واستيراد قاعدة البيانات (JSON)</p>
    </a>
    <?php endif; ?>
</div>
