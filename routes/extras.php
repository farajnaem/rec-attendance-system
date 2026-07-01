<?php

declare(strict_types=1);

function dispatchExtraRoutes(string $route, string $method): bool
{
    if ($route === '/logout') {
        if ($method === 'POST') {
            if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
                flash('error', 'انتهت صلاحية النموذج.');
                redirect('/login');
            }
            Auth::logout();
            redirect('/login');
        }
        flash('error', 'استخدم زر تسجيل الخروج من القائمة.');
        redirect('/login');
    }

    if ($route === '/account/settings') {
        Auth::requireLogin();
        if ($method === 'GET') {
            view('account/settings', ['title' => 'إعدادات الحساب']);
            return true;
        }
        if ($method === 'POST') {
            if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
                flash('error', 'انتهت صلاحية النموذج.');
                redirect('/account/settings');
            }
            try {
                $newPassword = $_POST['new_password'] ?? '';
                $confirmPassword = $_POST['confirm_password'] ?? '';
                if ($newPassword !== $confirmPassword) {
                    throw new InvalidArgumentException('كلمتا المرور الجديدتان غير متطابقتين.');
                }
                UserService::changePassword(
                    Auth::id(),
                    $_POST['current_password'] ?? '',
                    $newPassword
                );
                flash('success', 'تم تغيير كلمة المرور بنجاح.');
            } catch (Throwable $e) {
                flash('error', $e->getMessage());
            }
            redirect('/account/settings');
        }
    }

    if ($route === '/manager/leaves' && $method === 'GET') {
        Auth::requireRole(RoleHelper::managementRoles());
        $status = $_GET['status'] ?? null;
        if ($status === '') {
            $status = null;
        }
        $leaves = LeaveService::listForManager(Auth::id(), Auth::role(), $status);
        $pendingBreaks = WorkBreakService::pendingForReviewer(Auth::id(), Auth::role());
        view('manager/leaves', ['title' => 'إدارة الإجازات', 'leaves' => $leaves, 'status' => $status, 'pendingBreaks' => $pendingBreaks]);
        return true;
    }

    if ($route === '/manager/work-break/approve' && $method === 'POST') {
        Auth::requirePermission('approve_work_breaks');
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            flash('error', 'انتهت صلاحية النموذج.');
            redirect('/manager/leaves');
        }
        try {
            WorkBreakService::review((int) ($_POST['break_id'] ?? 0), Auth::id(), Auth::role(), true);
            flash('success', 'تم اعتماد المغادرة.');
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
        }
        redirect('/manager/leaves');
    }

    if ($route === '/manager/work-break/reject' && $method === 'POST') {
        Auth::requirePermission('approve_work_breaks');
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            flash('error', 'انتهت صلاحية النموذج.');
            redirect('/manager/leaves');
        }
        try {
            WorkBreakService::review((int) ($_POST['break_id'] ?? 0), Auth::id(), Auth::role(), false);
            flash('success', 'تم رفض طلب المغادرة.');
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
        }
        redirect('/manager/leaves');
    }

    if ($route === '/manager/leaves/approve' && $method === 'POST') {
        Auth::requireRole(RoleHelper::managementRoles());
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            flash('error', 'انتهت صلاحية النموذج.');
            redirect('/manager/leaves');
        }
        try {
            LeaveService::approve((int) ($_POST['leave_id'] ?? 0), Auth::id(), Auth::role());
            flash('success', 'تمت الموافقة على الإجازة.');
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
        }
        redirect('/manager/leaves');
    }

    if ($route === '/manager/leaves/reject' && $method === 'POST') {
        Auth::requireRole(RoleHelper::managementRoles());
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            flash('error', 'انتهت صلاحية النموذج.');
            redirect('/manager/leaves');
        }
        try {
            LeaveService::reject((int) ($_POST['leave_id'] ?? 0), Auth::id(), Auth::role());
            flash('success', 'تم رفض طلب الإجازة.');
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
        }
        redirect('/manager/leaves');
    }

    if ($route === '/employee/leaves/request' && $method === 'POST') {
        Auth::requireLogin();
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            flash('error', 'انتهت صلاحية النموذج.');
            redirect(RoleHelper::dashboardPath(Auth::role()));
        }
        try {
            LeaveService::request(
                Auth::id(),
                $_POST['leave_type'] ?? '',
                $_POST['start_date'] ?? '',
                $_POST['end_date'] ?? '',
                trim($_POST['notes'] ?? '') ?: null
            );
            flash('success', 'تم إرسال طلب الإجازة.');
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
        }
        redirect(RoleHelper::dashboardPath(Auth::role()));
    }

    if ($route === '/manager/audit' && $method === 'GET') {
        Auth::requireRole(['system_admin']);
        $entries = AuditService::recent(200);
        view('manager/audit', ['title' => 'سجل التدقيق', 'entries' => $entries]);
        return true;
    }

    if ($route === '/manager/attendance/manual' && $method === 'POST') {
        if (!Auth::can('view_reports_readonly') && !Auth::can('manage_users')) {
            flash('error', 'لا يمكنك تصحيح سجلات الحضور.');
            redirect('/manager/dashboard');
        }
        Auth::requireRole(RoleHelper::managementRoles());
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            flash('error', 'انتهت صلاحية النموذج.');
            redirect('/manager/attendance');
        }
        $userId = (int) ($_POST['user_id'] ?? 0);
        try {
            if (!ScopeService::canViewUser(Auth::id(), Auth::role(), $userId)) {
                throw new RuntimeException('لا يمكنك تعديل حضور هذا الموظف.');
            }
            AttendanceService::manualRecord(
                $userId,
                $_POST['type'] ?? '',
                $_POST['local_date'] ?? '',
                trim($_POST['reason'] ?? ''),
                Auth::id()
            );
            AuditService::log('attendance.manual', 'attendance', $userId, [
                'type' => $_POST['type'] ?? '',
                'date' => $_POST['local_date'] ?? '',
            ]);
            flash('success', 'تم تسجيل التصحيح اليدوي.');
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
        }
        redirect('/manager/attendance');
    }

    if ($route === '/manager/attendance/detail' && $method === 'GET') {
        if (!Auth::can('view_reports_readonly') && !Auth::can('monthly_employee_report')) {
            flash('error', 'لا يمكنك عرض تفاصيل الحضور.');
            redirect('/manager/dashboard');
        }
        Auth::requireRole(RoleHelper::managementRoles());
        $record = AttendanceService::getRecord((int) ($_GET['id'] ?? 0));
        if (!$record || !ScopeService::canViewUser(Auth::id(), Auth::role(), (int) $record['user_id'])) {
            flash('error', 'السجل غير موجود أو لا يمكنك عرضه.');
            redirect('/manager/attendance');
        }
        view('manager/attendance_detail', compact('record'));
        return true;
    }

    if ($route === '/manager/reports/export' && $method === 'GET') {
        if (!Auth::can('view_reports_readonly') && !Auth::can('monthly_employee_report')) {
            flash('error', 'لا يمكنك تصدير التقارير.');
            redirect('/manager/dashboard');
        }
        Auth::requireRole(RoleHelper::managementRoles());
        $employeeId = (int) ($_GET['employee_id'] ?? 0);
        $year = (int) ($_GET['year'] ?? date('Y'));
        $month = (int) ($_GET['month'] ?? date('n'));
        if (!$employeeId || !ScopeService::canViewReport(Auth::id(), Auth::role(), $employeeId)) {
            flash('error', 'لا يمكنك تصدير تقرير هذا الموظف.');
            redirect('/manager/reports');
        }
        $report = ReportService::fullReport($employeeId, $year, $month);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="report-' . $employeeId . '-' . $year . '-' . $month . '.csv"');
        header('Cache-Control: no-store');
        $out = fopen('php://output', 'w');
        fprintf($out, "\xEF\xBB\xBF");
        fputcsv($out, ['التاريخ', 'يوم عمل', 'إجازة', 'حضور', 'انصراف', 'مكتمل', 'متأخر']);
        foreach ($report['attendance']['daily'] as $day) {
            fputcsv($out, [
                $day['date'],
                $day['is_workday'] ? 'نعم' : 'لا',
                $day['on_leave'] ? 'نعم' : 'لا',
                $day['check_in'] ? 'نعم' : 'لا',
                $day['check_out'] ? 'نعم' : 'لا',
                $day['complete'] ? 'نعم' : 'لا',
                $day['late'] ? 'نعم' : 'لا',
            ]);
        }
        fclose($out);
        exit;
    }

    if ($route === '/manager/attendance/export' && $method === 'GET') {
        if (!Auth::can('view_reports_readonly') && !Auth::can('monthly_employee_report')) {
            flash('error', 'لا يمكنك تصدير سجل الحضور.');
            redirect('/manager/dashboard');
        }
        Auth::requireRole(RoleHelper::managementRoles());
        $from = $_GET['from'] ?? date('Y-m-d', strtotime('-30 days'));
        $to = $_GET['to'] ?? date('Y-m-d');
        $records = AttendanceService::teamRecordsForActor(Auth::id(), Auth::role(), $from, $to);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="attendance-' . $from . '-' . $to . '.csv"');
        header('Cache-Control: no-store');
        $out = fopen('php://output', 'w');
        fprintf($out, "\xEF\xBB\xBF");
        fputcsv($out, ['الموظف', 'النوع', 'التاريخ', 'التوقيت المحلي', 'المنطقة الزمنية', 'موقع العمل', 'GPS']);
        foreach ($records as $r) {
            $tz = $r['timezone'] ?? $r['user_timezone'] ?? TimezoneHelper::defaultTimezone();
            $local = TimezoneHelper::toLocal($r['signed_at_utc'], $tz)->format('Y-m-d H:i:s');
            $gps = ($r['latitude'] !== null && $r['longitude'] !== null)
                ? $r['latitude'] . ',' . $r['longitude']
                : '';
            fputcsv($out, [
                $r['user_name'],
                $r['type'] === 'check_in' ? 'حضور' : 'انصراف',
                $r['local_work_date'],
                $local,
                $tz,
                $r['work_location_name'] ?? '',
                $gps,
            ]);
        }
        fclose($out);
        exit;
    }

    if ($route === '/manager/database/export' && $method === 'POST') {
        Auth::requireRole(['system_admin']);
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            flash('error', 'انتهت صلاحية النموذج.');
            redirect('/manager/database');
        }
        if (!verifyAdminPassword($_POST['admin_password'] ?? null)) {
            flash('error', 'كلمة مرور المسؤول غير صحيحة.');
            redirect('/manager/database');
        }
        require dirname(__DIR__) . '/database/DataSync.php';
        try {
            $payload = DataSync::exportCurrent();
            AuditService::log('database.export');
            $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            if ($json === false) {
                throw new RuntimeException('تعذّر تحويل البيانات إلى JSON.');
            }
            $filename = 'rec-export-' . date('Y-m-d-His') . '.json';
            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: no-store');
            echo $json;
            exit;
        } catch (Throwable $e) {
            flash('error', 'فشل التصدير: ' . $e->getMessage());
            redirect('/manager/database');
        }
    }

    return false;
}
