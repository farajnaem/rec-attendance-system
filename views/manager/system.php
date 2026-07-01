<?php $title = 'إدارة النظام'; ?>
<h1>إدارة النظام</h1>
<p class="text-muted">إعدادات الدوام، المواقع الجغرافية، النقل، التدقيق، والنسخ الاحتياطي — حسب صلاحياتك.</p>

<div class="grid-2">
    <?php if (Auth::can('manage_work_schedule') || Auth::can('manage_report_deadline') || RoleHelper::isSystemAdmin(Auth::role())): ?>
    <a href="<?= e(url('/manager/work-schedule')) ?>" class="card link-card">
        <h3>ساعات الدوام ومهلة التقرير</h3>
        <p class="text-muted">تحديد أوقات العمل، أيام الدوام، ومهلة تقديم التقرير السردي.</p>
    </a>
    <?php endif; ?>
    <?php if (Auth::can('manage_locations')): ?>
    <a href="<?= e(url('/manager/locations')) ?>" class="card link-card">
        <h3>المناطق الجغرافية</h3>
        <p class="text-muted">مواقع العمل وإحداثيات GPS للتحقق من الحضور.</p>
    </a>
    <?php endif; ?>
    <?php if (RoleHelper::isSystemAdmin(Auth::role())): ?>
    <a href="<?= e(url('/manager/audit')) ?>" class="card link-card">
        <h3>سجل التدقيق</h3>
        <p class="text-muted">مراجعة عمليات النظام والتغييرات.</p>
    </a>
    <a href="<?= e(url('/manager/database')) ?>" class="card link-card">
        <h3>النسخ الاحتياطي</h3>
        <p class="text-muted">تصدير واستيراد بيانات النظام.</p>
    </a>
    <?php endif; ?>
</div>
