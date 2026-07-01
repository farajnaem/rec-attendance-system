<?php

declare(strict_types=1);

/**
 * تحميل مشترك للتطبيق (HTTP و CLI و Docker init).
 */

function rec_load_core(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $loaded = true;

    $root = dirname(__DIR__);
    require_once $root . '/config/config.php';
    require_once $root . '/src/helpers.php';
    require_once $root . '/src/Database.php';
    require_once $root . '/src/PermissionService.php';
    require_once $root . '/src/DepartmentService.php';
    require_once $root . '/src/WorkScheduleService.php';
    require_once $root . '/src/LeaveHelper.php';
    require_once $root . '/src/MigrationRunner.php';
    require_once $root . '/src/Csrf.php';
    require_once $root . '/src/Auth.php';
    require_once $root . '/src/TimezoneHelper.php';
    require_once $root . '/src/AttendanceService.php';
    require_once $root . '/src/TaskService.php';
    require_once $root . '/src/ReportService.php';
    require_once $root . '/src/UserService.php';
    require_once $root . '/src/ScopeService.php';
    require_once $root . '/src/CrossDepartmentService.php';
    require_once $root . '/src/LocationService.php';
    require_once $root . '/src/JobDescriptionService.php';
    require_once $root . '/src/DbDiagnostics.php';
    require_once $root . '/src/AuditService.php';
    require_once $root . '/src/LoginRateLimiter.php';
    require_once $root . '/src/LeaveService.php';
    require_once $root . '/src/NotificationService.php';
    require_once $root . '/src/NarrativeReportService.php';
    require_once $root . '/src/DocumentService.php';
    require_once $root . '/src/ContractService.php';
    require_once $root . '/src/WorkBreakService.php';
}

function rec_run_migrations_if_enabled(): void
{
    if (envBool('RUN_MIGRATIONS_ON_REQUEST', false) || PHP_SAPI === 'cli') {
        MigrationRunner::ensureLatest();
    }
}

function rec_installed_lock_path(): string
{
    return dirname(__DIR__) . '/storage/installed.lock';
}

function rec_mark_installed(): void
{
    $dir = dirname(rec_installed_lock_path());
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    file_put_contents(rec_installed_lock_path(), date('c'));
}

function rec_is_installed(): bool
{
    if (is_file(rec_installed_lock_path())) {
        return true;
    }
    try {
        rec_load_core();
        return Auth::userCount() > 0;
    } catch (Throwable) {
        return false;
    }
}
