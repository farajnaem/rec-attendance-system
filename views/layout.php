<!DOCTYPE html>

<html lang="ar" dir="rtl">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= e($title ?? config('app.name')) ?></title>

    <link rel="stylesheet" href="<?= e(url('/assets/css/app.css')) ?>">

</head>

<body>

<?php if (Auth::check() && ($page ?? '') !== 'login'): ?>

<nav class="navbar no-print">

    <div><strong><?= e(config('app.name')) ?></strong></div>

    <div>

        <a href="<?= e(url('/employee/dashboard')) ?>">لوحتي</a>

        <?php if (Auth::can('sign_attendance')): ?>

        <a href="<?= e(url('/employee/dashboard')) ?>#attendance">تسجيل الحضور</a>

        <?php endif; ?>

        <a href="<?= e(url('/employee/report')) ?>">تقريري الشهري</a>

        <a href="<?= e(url('/employee/dashboard')) ?>#job-description">التوصيف الوظيفي</a>

        <?php if (!RoleHelper::isEmployee(Auth::role())): ?>

            <a href="<?= e(url('/manager/dashboard')) ?>">لوحة التحكم</a>

            <?php if (Auth::can('manage_users')): ?>

            <a href="<?= e(url('/manager/users')) ?>">المستخدمون</a>

            <?php endif; ?>

            <?php if (Auth::can('manage_departments')): ?>

            <a href="<?= e(url('/manager/departments')) ?>">الدوائر</a>

            <?php endif; ?>

            <?php if (RoleHelper::isOrgAdmin(Auth::role())): ?>

            <a href="<?= e(url('/manager/work-schedule')) ?>">ساعات الدوام</a>

            <?php endif; ?>

            <?php if (Auth::can('manage_locations')): ?>

            <a href="<?= e(url('/manager/locations')) ?>">مواقع العمل</a>

            <?php endif; ?>

            <?php if (Auth::can('manage_job_description')): ?>

            <a href="<?= e(url('/manager/job-description')) ?>">التوصيف الوظيفي</a>

            <?php endif; ?>

            <?php if (Auth::can('manage_daily_tasks')): ?>

            <a href="<?= e(url('/manager/tasks')) ?>">المهام</a>

            <?php endif; ?>

            <?php if (Auth::can('view_reports_readonly') || Auth::can('monthly_employee_report')): ?>

            <a href="<?= e(url('/manager/reports')) ?>">التقارير</a>

            <a href="<?= e(url('/manager/attendance')) ?>">حضور الفريق</a>

            <?php endif; ?>

        <?php endif; ?>

        <a href="<?= e(url('/logout')) ?>">خروج (<?= e(Auth::name()) ?>)</a>

    </div>

</nav>

<?php endif; ?>



<div class="<?= ($page ?? '') === 'login' ? '' : 'container' ?>">

    <?php if ($success = flash('success')): ?>

        <div class="alert alert-success"><?= e($success) ?></div>

    <?php endif; ?>

    <?php if ($error = flash('error')): ?>

        <div class="alert alert-error"><?= e($error) ?></div>

    <?php endif; ?>



    <?php require __DIR__ . '/' . ($name ?? 'login') . '.php'; ?>

</div>



<?php if (!empty($loadSignature)): ?>

<script src="<?= e(url('/assets/js/signature.js')) ?>"></script>

<?php endif; ?>

</body>

</html>

