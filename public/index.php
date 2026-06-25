<?php

declare(strict_types=1);

$config = require dirname(__DIR__) . '/config/config.php';

if ($config['app']['debug'] ?? false) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
}

if (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') {
    $_SERVER['HTTPS'] = 'on';
}

$isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

if (!($config['app']['debug'] ?? false)) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_samesite', 'Lax');
    if ($isSecure) {
        ini_set('session.cookie_secure', '1');
    }
}

session_start();

date_default_timezone_set($config['app']['default_timezone']);

require dirname(__DIR__) . '/src/helpers.php';
require dirname(__DIR__) . '/src/Database.php';
require dirname(__DIR__) . '/src/PermissionService.php';
require dirname(__DIR__) . '/src/DepartmentService.php';
require dirname(__DIR__) . '/src/WorkScheduleService.php';
require dirname(__DIR__) . '/src/LeaveHelper.php';
require dirname(__DIR__) . '/src/MigrationRunner.php';
require dirname(__DIR__) . '/src/Csrf.php';
require dirname(__DIR__) . '/src/Auth.php';
require dirname(__DIR__) . '/src/TimezoneHelper.php';
require dirname(__DIR__) . '/src/AttendanceService.php';
require dirname(__DIR__) . '/src/TaskService.php';
require dirname(__DIR__) . '/src/ReportService.php';
require dirname(__DIR__) . '/src/UserService.php';
require dirname(__DIR__) . '/src/ScopeService.php';
require dirname(__DIR__) . '/src/CrossDepartmentService.php';
require dirname(__DIR__) . '/src/LocationService.php';
require dirname(__DIR__) . '/src/JobDescriptionService.php';
require dirname(__DIR__) . '/src/DbDiagnostics.php';

MigrationRunner::ensureLatest();

$route = $_GET['route'] ?? '/';
$route = '/' . trim($route, '/');
if ($route === '//') {
    $route = '/';
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    match (true) {
        $route === '/' && $method === 'GET' => (function () {
            if (Auth::check()) {
                redirect(RoleHelper::dashboardPath(Auth::role()));
            }
            redirect('/login');
        })(),

        $route === '/login' && $method === 'GET' => (function () {
            if (Auth::check()) {
                redirect(RoleHelper::dashboardPath(Auth::role()));
            }
            if (Auth::needsSetup()) {
                flash('error', 'لم يُنشأ حساب مسؤول بعد. أكمل الإعداد أولاً.');
                redirect('/setup.php');
            }
            view('login', ['title' => 'تسجيل الدخول', 'setupEnabled' => (bool) config('app.setup_enabled')]);
        })(),

        $route === '/login' && $method === 'POST' => (function () {
            if (Auth::needsSetup()) {
                flash('error', 'يجب إنشاء حساب المسؤول من صفحة الإعداد أولاً.');
                redirect('/setup.php');
            }
            if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
                flash('error', 'انتهت صلاحية النموذج. أعد المحاولة.');
                redirect('/login');
            }
            $email = strtolower(trim($_POST['email'] ?? ''));
            $password = $_POST['password'] ?? '';
            if (Auth::attempt($email, $password)) {
                redirect(RoleHelper::dashboardPath(Auth::role()));
            }
            flash('error', 'البريد أو كلمة المرور غير صحيحة.');
            redirect('/login');
        })(),

        $route === '/logout' => (function () {
            Auth::logout();
            redirect('/login');
        })(),

        $route === '/health' && $method === 'GET' => (function () {
            header('Content-Type: application/json');
            $payload = ['status' => 'ok', 'db' => 'unknown'];

            try {
                Database::getConnection()->query('SELECT 1');
                $payload['db'] = 'connected';
            } catch (Throwable $e) {
                $payload['db'] = 'failed';
                if (config('app.debug')) {
                    $payload['db_error'] = $e->getMessage();
                }
            }

            echo json_encode($payload);
        })(),

        $route === '/debug/db' && $method === 'GET' => (function () {
            if (!config('app.debug')) {
                http_response_code(404);
                echo 'Not found';
                return;
            }

            header('Content-Type: application/json');
            echo json_encode(testDatabaseConnection(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        })(),

        $route === '/employee/dashboard' && $method === 'GET' => (function () {
            Auth::requireLogin();
            $tz = Auth::timezone();
            $status = AttendanceService::todayStatus(Auth::id(), $tz);
            $tasks = TaskService::forEmployee(Auth::id(), date('Y-m-d', strtotime('-7 days')), date('Y-m-d', strtotime('+7 days')));
            $recent = AttendanceService::recent(Auth::id(), 7);
            $gpsRequired = LocationService::hasActiveLocations();
            $workLocations = $gpsRequired ? LocationService::activeForClient() : [];
            $crossAssignment = CrossDepartmentService::activeForUser(Auth::id());
            $jobDescription = JobDescriptionService::fullForUser(Auth::id());
            view('employee/dashboard', array_merge(
                compact('status', 'tasks', 'recent', 'tz', 'gpsRequired', 'crossAssignment', 'workLocations', 'jobDescription'),
                ['loadSignature' => Auth::can('sign_attendance')]
            ));
        })(),

        $route === '/employee/attendance' && $method === 'POST' => (function () {
            Auth::requirePermission('sign_attendance');
            if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
                flash('error', 'انتهت صلاحية النموذج.');
                redirect('/employee/dashboard');
            }
            try {
                $lat = isset($_POST['latitude']) && $_POST['latitude'] !== ''
                    ? (float) $_POST['latitude'] : null;
                $lng = isset($_POST['longitude']) && $_POST['longitude'] !== ''
                    ? (float) $_POST['longitude'] : null;
                AttendanceService::sign(
                    Auth::id(),
                    $_POST['type'] ?? '',
                    $_POST['signature_data'] ?? '',
                    Auth::timezone(),
                    clientIp(),
                    $lat,
                    $lng
                );
                flash('success', $_POST['type'] === 'check_in' ? 'تم تسجيل الحضور بنجاح.' : 'تم تسجيل الانصراف بنجاح.');
            } catch (Throwable $e) {
                flash('error', $e->getMessage());
            }
            redirect('/employee/dashboard');
        })(),

        $route === '/employee/task/complete' && $method === 'POST' => (function () {
            Auth::requireLogin();
            if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
                flash('error', 'انتهت صلاحية النموذج.');
                redirect('/employee/dashboard');
            }
            $taskId = (int) ($_POST['task_id'] ?? 0);
            if (!TaskService::canAccess($taskId, Auth::id(), Auth::role())) {
                flash('error', 'لا يمكنك إتمام هذه المهمة.');
                redirect(Auth::role() === 'employee' ? '/employee/dashboard' : '/manager/tasks');
            }
            $task = TaskService::getById($taskId);
            $isOwner = $task && (int) $task['employee_id'] === Auth::id();
            if (!$isOwner && !Auth::can('complete_daily_tasks') && !Auth::can('manage_daily_tasks')) {
                flash('error', 'لا يمكنك إتمام المهام.');
                redirect(RoleHelper::dashboardPath(Auth::role()));
            }
            try {
                $tz = Auth::timezone();
                $localDt = str_replace('T', ' ', $_POST['completed_at'] ?? '');
                TaskService::complete($taskId, Auth::id(), $localDt, $tz, trim($_POST['notes'] ?? '') ?: null);
                flash('success', 'تم تسجيل إتمام المهمة.');
            } catch (Throwable $e) {
                flash('error', $e->getMessage());
            }
            redirect(Auth::role() === 'employee' ? '/employee/dashboard' : '/manager/tasks');
        })(),

        $route === '/employee/task/create' && $method === 'POST' => (function () {
            Auth::requireLogin();
            if (!RoleHelper::isEmployee(Auth::role())) {
                flash('error', 'هذه الصفحة للموظفين فقط.');
                redirect('/manager/tasks');
            }
            if (!Auth::can('manage_daily_tasks')) {
                flash('error', 'لا يمكنك إضافة مهام يومية.');
                redirect('/employee/dashboard');
            }
            if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
                flash('error', 'انتهت صلاحية النموذج.');
                redirect('/employee/dashboard');
            }
            try {
                TaskService::create(
                    Auth::id(),
                    Auth::id(),
                    trim($_POST['title'] ?? ''),
                    trim($_POST['description'] ?? '') ?: null,
                    $_POST['task_date'] ?? date('Y-m-d')
                );
                flash('success', 'تمت إضافة المهمة.');
            } catch (Throwable $e) {
                flash('error', $e->getMessage());
            }
            redirect('/employee/dashboard#my-tasks');
        })(),

        $route === '/employee/report' && $method === 'GET' => (function () {
            Auth::requireLogin();
            $year = (int) ($_GET['year'] ?? date('Y'));
            $month = (int) ($_GET['month'] ?? date('n'));
            $report = ReportService::fullReport(Auth::id(), $year, $month);
            view('employee/monthly_report', compact('report', 'year', 'month'));
        })(),

        $route === '/manager/dashboard' && $method === 'GET' => (function () {
            Auth::requireRole(RoleHelper::managementRoles());
            $team = ScopeService::visibleStaff(Auth::id(), Auth::role());
            $today = TimezoneHelper::localWorkDate(TimezoneHelper::utcNow(), Auth::timezone());
            $attendance = AttendanceService::teamAttendanceForActor(Auth::id(), Auth::role(), $today);
            view('manager/dashboard', compact('team', 'attendance', 'today'));
        })(),

        $route === '/manager/tasks' && $method === 'GET' => (function () {
            Auth::requireRole(RoleHelper::managementRoles());
            Auth::requirePermission('manage_daily_tasks');
            $from = $_GET['from'] ?? date('Y-m-d', strtotime('-7 days'));
            $to = $_GET['to'] ?? date('Y-m-d', strtotime('+7 days'));
            $tasks = Auth::role() === 'system_admin'
                ? Database::getConnection()->query(
                    'SELECT t.*, e.name AS employee_name, tc.completed_at_utc, te.score
                     FROM daily_tasks t JOIN users e ON e.id=t.employee_id
                     LEFT JOIN task_completions tc ON tc.task_id=t.id
                     LEFT JOIN task_evaluations te ON te.task_id=t.id
                     ORDER BY t.task_date DESC'
                )->fetchAll()
                : TaskService::forStaffIds(
                    array_column(ScopeService::visibleStaff(Auth::id(), Auth::role()), 'id'),
                    $from,
                    $to
                );
            $employees = ScopeService::visibleStaff(Auth::id(), Auth::role());
            view('manager/tasks', compact('tasks', 'employees', 'from', 'to'));
        })(),

        $route === '/manager/tasks/create' && $method === 'POST' => (function () {
            Auth::requireRole(RoleHelper::managementRoles());
            Auth::requirePermission('manage_daily_tasks');
            if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
                flash('error', 'انتهت صلاحية النموذج.');
                redirect('/manager/tasks');
            }
            try {
                $employeeId = (int) $_POST['employee_id'];
                if (Auth::role() !== 'system_admin'
                    && !ScopeService::canViewUser(Auth::id(), Auth::role(), $employeeId)) {
                    throw new RuntimeException('لا يمكنك إسناد مهمة لهذا المستخدم.');
                }
                TaskService::create(
                    $employeeId,
                    Auth::id(),
                    trim($_POST['title'] ?? ''),
                    trim($_POST['description'] ?? '') ?: null,
                    $_POST['task_date'] ?? date('Y-m-d')
                );
                flash('success', 'تمت إضافة المهمة.');
            } catch (Throwable $e) {
                flash('error', $e->getMessage());
            }
            redirect('/manager/tasks');
        })(),

        $route === '/manager/attendance' && $method === 'GET' => (function () {
            if (!Auth::can('view_reports_readonly') && !Auth::can('monthly_employee_report')) {
                flash('error', 'لا يمكنك عرض سجل الحضور.');
                redirect('/manager/dashboard');
            }
            Auth::requireRole(RoleHelper::managementRoles());
            $date = $_GET['date'] ?? TimezoneHelper::localWorkDate(TimezoneHelper::utcNow(), Auth::timezone());
            $attendance = AttendanceService::teamAttendanceForActor(Auth::id(), Auth::role(), $date);
            view('manager/attendance', compact('attendance', 'date'));
        })(),

        $route === '/manager/evaluate' && $method === 'GET' => (function () {
            Auth::requireRole(RoleHelper::managementRoles());
            $taskId = (int) ($_GET['id'] ?? 0);
            if (!TaskService::canAccess($taskId, Auth::id(), Auth::role())) {
                flash('error', 'المهمة غير موجودة.');
                redirect('/manager/tasks');
            }
            $task = TaskService::getById($taskId);
            view('manager/evaluate', compact('task'));
        })(),

        $route === '/manager/evaluate' && $method === 'POST' => (function () {
            Auth::requireRole(RoleHelper::managementRoles());
            if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
                flash('error', 'انتهت صلاحية النموذج.');
                redirect('/manager/tasks');
            }
            $taskId = (int) ($_POST['task_id'] ?? 0);
            if (!TaskService::canAccess($taskId, Auth::id(), Auth::role())) {
                flash('error', 'لا يمكنك تقييم هذه المهمة.');
                redirect('/manager/tasks');
            }
            try {
                TaskService::evaluate($taskId, Auth::id(), (int) $_POST['score'], trim($_POST['notes'] ?? '') ?: null);
                flash('success', 'تم حفظ التقييم.');
            } catch (Throwable $e) {
                flash('error', $e->getMessage());
            }
            redirect('/manager/tasks');
        })(),

        $route === '/manager/reports' && $method === 'GET' => (function () {
            if (!Auth::can('view_reports_readonly') && !Auth::can('monthly_employee_report')) {
                flash('error', 'لا يمكنك عرض التقارير الشهرية.');
                redirect('/manager/dashboard');
            }
            Auth::requireRole(RoleHelper::managementRoles());
            $year = (int) ($_GET['year'] ?? date('Y'));
            $month = (int) ($_GET['month'] ?? date('n'));
            $employees = ScopeService::visibleStaff(Auth::id(), Auth::role());
            $employeeId = (int) ($_GET['employee_id'] ?? ($employees[0]['id'] ?? 0));
            if ($employeeId && !ScopeService::canViewReport(Auth::id(), Auth::role(), $employeeId)) {
                flash('error', 'لا يمكنك عرض تقرير هذا المستخدم.');
                redirect('/manager/reports');
            }
            $report = $employeeId ? ReportService::fullReport($employeeId, $year, $month) : null;
            view('manager/reports', compact('employees', 'report', 'year', 'month', 'employeeId'));
        })(),

        $route === '/manager/users' && $method === 'GET' => (function () {
            Auth::requirePermission('manage_users');
            $isSystemAdmin = Auth::role() === 'system_admin';
            $canAssignPermissions = RoleHelper::canAssignPermissions(Auth::role());
            $users = ScopeService::visibleUsers(Auth::id(), Auth::role());
            $supervisors = UserService::supervisors();
            $departments = DepartmentService::all();
            $availableRoles = $canAssignPermissions
                ? RoleHelper::all()
                : ['employee' => RoleHelper::label('employee')];
            $canBorrowEmployee = Auth::can('borrow_employee');
            view('manager/users', compact(
                'users', 'supervisors', 'departments', 'isSystemAdmin',
                'canAssignPermissions', 'availableRoles', 'canBorrowEmployee'
            ));
        })(),

        $route === '/manager/users/create' && $method === 'POST' => (function () {
            Auth::requirePermission('manage_users');
            if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
                flash('error', 'انتهت صلاحية النموذج.');
                redirect('/manager/users');
            }
            try {
                $role = $_POST['role'] ?? 'employee';
                if (!RoleHelper::canAssignPermissions(Auth::role())) {
                    $role = 'employee';
                }
                if (!RoleHelper::isValid($role)) {
                    throw new InvalidArgumentException('الدور غير صالح.');
                }
                $managerId = $role === 'employee'
                    ? (int) ($_POST['manager_id'] ?? 0) ?: null
                    : null;
                $deptId = (int) ($_POST['department_id'] ?? 0) ?: null;
                $perms = isset($_POST['permissions']) && is_array($_POST['permissions'])
                    ? $_POST['permissions']
                    : null;
                UserService::create(
                    $_POST['name'] ?? '',
                    $_POST['email'] ?? '',
                    $_POST['password'] ?? '',
                    $role,
                    $_POST['timezone'] ?? config('app.default_timezone'),
                    $managerId,
                    $deptId,
                    $perms
                );
                flash('success', 'تمت إضافة المستخدم بنجاح.');
            } catch (Throwable $e) {
                flash('error', $e->getMessage());
            }
            redirect('/manager/users');
        })(),

        $route === '/manager/users/edit' && $method === 'GET' => (function () {
            Auth::requirePermission('manage_users');
            $userId = (int) ($_GET['id'] ?? 0);
            try {
                UserService::assertCanEdit($userId, Auth::id(), Auth::role());
            } catch (Throwable $e) {
                flash('error', $e->getMessage());
                redirect('/manager/users');
            }
            $user = UserService::getById($userId);
            if (!$user) {
                flash('error', 'المستخدم غير موجود.');
                redirect('/manager/users');
            }
            $canChangeRole = RoleHelper::canAssignPermissions(Auth::role());
            $canAssignPermissions = $canChangeRole;
            $department = DepartmentService::currentForUser($userId);
            $departments = DepartmentService::all();
            $supervisors = UserService::supervisors();
            $canBorrowEmployee = Auth::can('borrow_employee');
            $crossAssignments = CrossDepartmentService::listForUser($userId);
            $activeCross = CrossDepartmentService::activeForUser($userId);
            view('manager/user_edit', compact(
                'user', 'department', 'departments', 'supervisors',
                'canChangeRole', 'canAssignPermissions', 'canBorrowEmployee',
                'crossAssignments', 'activeCross'
            ));
        })(),

        $route === '/manager/users/update' && $method === 'POST' => (function () {
            Auth::requirePermission('manage_users');
            if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
                flash('error', 'انتهت صلاحية النموذج.');
                redirect('/manager/users');
            }
            $userId = (int) ($_POST['user_id'] ?? 0);
            try {
                $role = $_POST['role'] ?? 'employee';
                $canChangeRole = RoleHelper::canAssignPermissions(Auth::role());
                if (!$canChangeRole) {
                    $existing = UserService::getById($userId);
                    $role = $existing ? $existing['role'] : 'employee';
                }
                $managerId = RoleHelper::normalizeRole($role) === 'employee'
                    ? (int) ($_POST['manager_id'] ?? 0) ?: null
                    : null;
                $deptId = (int) ($_POST['department_id'] ?? 0) ?: null;
                $password = trim($_POST['password'] ?? '');
                UserService::update(
                    $userId,
                    $_POST['name'] ?? '',
                    $_POST['email'] ?? '',
                    $_POST['timezone'] ?? TimezoneHelper::defaultTimezone(),
                    $role,
                    $managerId,
                    $deptId,
                    $password !== '' ? $password : null,
                    Auth::id(),
                    Auth::role(),
                    $canChangeRole
                );
                flash('success', 'تم حفظ بيانات المستخدم.');
            } catch (Throwable $e) {
                flash('error', $e->getMessage());
            }
            redirect('/manager/users/edit?id=' . $userId);
        })(),

        $route === '/manager/users/toggle' && $method === 'POST' => (function () {
            Auth::requirePermission('manage_users');
            if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
                flash('error', 'انتهت صلاحية النموذج.');
                redirect('/manager/users');
            }
            try {
                UserService::toggleActive((int) ($_POST['user_id'] ?? 0), Auth::id(), Auth::role());
                flash('success', 'تم تحديث حالة المستخدم.');
            } catch (Throwable $e) {
                flash('error', $e->getMessage());
            }
            redirect('/manager/users');
        })(),

        $route === '/manager/users/delete' && $method === 'POST' => (function () {
            Auth::requireRole(['system_admin']);
            if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
                flash('error', 'انتهت صلاحية النموذج.');
                redirect('/manager/users');
            }
            try {
                UserService::delete((int) ($_POST['user_id'] ?? 0), Auth::id(), Auth::role());
                flash('success', 'تم حذف المستخدم وجميع سجلاته المرتبطة.');
            } catch (Throwable $e) {
                flash('error', $e->getMessage());
            }
            redirect('/manager/users');
        })(),

        $route === '/manager/users/permissions' && $method === 'GET' => (function () {
            Auth::requirePermission('manage_users');
            if (!RoleHelper::canAssignPermissions(Auth::role())) {
                flash('error', 'لا يمكنك تعديل الصلاحيات.');
                redirect('/manager/users');
            }
            $userId = (int) ($_GET['id'] ?? 0);
            $user = UserService::getById($userId);
            if (!$user) {
                flash('error', 'المستخدم غير موجود.');
                redirect('/manager/users');
            }
            $granted = PermissionService::codesForUser($userId);
            $department = DepartmentService::currentForUser($userId);
            $departments = DepartmentService::all();
            view('manager/user_permissions', compact('user', 'granted', 'department', 'departments'));
        })(),

        $route === '/manager/users/permissions' && $method === 'POST' => (function () {
            Auth::requirePermission('manage_users');
            if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
                flash('error', 'انتهت صلاحية النموذج.');
                redirect('/manager/users');
            }
            try {
                $userId = (int) ($_POST['user_id'] ?? 0);
                $perms = is_array($_POST['permissions'] ?? null) ? $_POST['permissions'] : [];
                UserService::updatePermissions($userId, $perms, Auth::id(), Auth::role());
                flash('success', 'تم حفظ الصلاحيات.');
            } catch (Throwable $e) {
                flash('error', $e->getMessage());
            }
            redirect('/manager/users/permissions?id=' . (int) ($_POST['user_id'] ?? 0));
        })(),

        $route === '/manager/users/transfer' && $method === 'POST' => (function () {
            Auth::requirePermission('transfer_employee');
            if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
                flash('error', 'انتهت صلاحية النموذج.');
                redirect('/manager/users');
            }
            try {
                UserService::transferDepartment(
                    (int) ($_POST['user_id'] ?? 0),
                    (int) ($_POST['department_id'] ?? 0),
                    Auth::id(),
                    Auth::role()
                );
                flash('success', 'تم نقل الموظف إلى الدائرة الجديدة.');
            } catch (Throwable $e) {
                flash('error', $e->getMessage());
            }
            redirect('/manager/users/permissions?id=' . (int) ($_POST['user_id'] ?? 0));
        })(),

        $route === '/manager/departments' && $method === 'GET' => (function () {
            Auth::requirePermission('manage_departments');
            $departments = DepartmentService::all(false);
            $supervisors = DepartmentService::supervisorsForSelect();
            view('manager/departments', compact('departments', 'supervisors'));
        })(),

        $route === '/manager/departments/create' && $method === 'POST' => (function () {
            Auth::requirePermission('manage_departments');
            if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
                flash('error', 'انتهت صلاحية النموذج.');
                redirect('/manager/departments');
            }
            try {
                DepartmentService::create(
                    $_POST['name'] ?? '',
                    $_POST['description'] ?? null,
                    (int) ($_POST['supervisor_id'] ?? 0) ?: null
                );
                flash('success', 'تمت إضافة الدائرة.');
            } catch (Throwable $e) {
                flash('error', $e->getMessage());
            }
            redirect('/manager/departments');
        })(),

        $route === '/manager/departments/update' && $method === 'POST' => (function () {
            Auth::requirePermission('manage_departments');
            if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
                flash('error', 'انتهت صلاحية النموذج.');
                redirect('/manager/departments');
            }
            try {
                DepartmentService::update(
                    (int) ($_POST['department_id'] ?? 0),
                    $_POST['name'] ?? '',
                    $_POST['description'] ?? null,
                    (int) ($_POST['supervisor_id'] ?? 0) ?: null
                );
                flash('success', 'تم تحديث الدائرة.');
            } catch (Throwable $e) {
                flash('error', $e->getMessage());
            }
            redirect('/manager/departments');
        })(),

        $route === '/manager/departments/delete' && $method === 'POST' => (function () {
            Auth::requirePermission('manage_departments');
            if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
                flash('error', 'انتهت صلاحية النموذج.');
                redirect('/manager/departments');
            }
            try {
                DepartmentService::delete((int) ($_POST['department_id'] ?? 0));
                flash('success', 'تم تعطيل الدائرة.');
            } catch (Throwable $e) {
                flash('error', $e->getMessage());
            }
            redirect('/manager/departments');
        })(),

        $route === '/manager/work-schedule' && $method === 'GET' => (function () {
            Auth::requireRole(RoleHelper::orgAdminRoles());
            $schedule = WorkScheduleService::get();
            view('manager/work_schedule', compact('schedule'));
        })(),

        $route === '/manager/work-schedule/save' && $method === 'POST' => (function () {
            Auth::requireRole(RoleHelper::orgAdminRoles());
            if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
                flash('error', 'انتهت صلاحية النموذج.');
                redirect('/manager/work-schedule');
            }
            try {
                $days = $_POST['work_days'] ?? [];
                if (!is_array($days) || empty($days)) {
                    throw new InvalidArgumentException('اختر يوم عمل واحد على الأقل.');
                }
                WorkScheduleService::update(
                    $_POST['work_start_time'] ?? '08:00',
                    $_POST['work_end_time'] ?? '16:00',
                    (int) ($_POST['late_grace_minutes'] ?? 15),
                    implode(',', $days),
                    Auth::id()
                );
                flash('success', 'تم حفظ ساعات الدوام الرسمي.');
            } catch (Throwable $e) {
                flash('error', $e->getMessage());
            }
            redirect('/manager/work-schedule');
        })(),

        $route === '/manager/locations' && $method === 'GET' => (function () {
            Auth::requirePermission('manage_locations');
            $locations = LocationService::all(false);
            view('manager/locations', compact('locations'));
        })(),

        $route === '/manager/locations/create' && $method === 'POST' => (function () {
            Auth::requirePermission('manage_locations');
            if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
                flash('error', 'انتهت صلاحية النموذج.');
                redirect('/manager/locations');
            }
            try {
                LocationService::create(
                    $_POST['name'] ?? '',
                    $_POST['address'] ?? '',
                    (float) ($_POST['latitude'] ?? 0),
                    (float) ($_POST['longitude'] ?? 0),
                    (int) ($_POST['radius_meters'] ?? 200),
                    Auth::id()
                );
                flash('success', 'تمت إضافة موقع العمل.');
            } catch (Throwable $e) {
                flash('error', $e->getMessage());
            }
            redirect('/manager/locations');
        })(),

        $route === '/manager/locations/update' && $method === 'POST' => (function () {
            Auth::requirePermission('manage_locations');
            if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
                flash('error', 'انتهت صلاحية النموذج.');
                redirect('/manager/locations');
            }
            try {
                LocationService::update(
                    (int) ($_POST['location_id'] ?? 0),
                    $_POST['name'] ?? '',
                    $_POST['address'] ?? '',
                    (float) ($_POST['latitude'] ?? 0),
                    (float) ($_POST['longitude'] ?? 0),
                    (int) ($_POST['radius_meters'] ?? 200),
                    isset($_POST['is_active'])
                );
                flash('success', 'تم تحديث الموقع.');
            } catch (Throwable $e) {
                flash('error', $e->getMessage());
            }
            redirect('/manager/locations');
        })(),

        $route === '/manager/users/cross-assign' && $method === 'POST' => (function () {
            Auth::requirePermission('borrow_employee');
            if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
                flash('error', 'انتهت صلاحية النموذج.');
                redirect('/manager/users');
            }
            $userId = (int) ($_POST['user_id'] ?? 0);
            try {
                CrossDepartmentService::assertCanAssign(Auth::id(), Auth::role());
                if (!ScopeService::canViewUser(Auth::id(), Auth::role(), $userId)) {
                    throw new RuntimeException('لا يمكنك تعيين هذا المستخدم.');
                }
                CrossDepartmentService::create(
                    $userId,
                    (int) ($_POST['target_department_id'] ?? 0),
                    $_POST['start_date'] ?? date('Y-m-d'),
                    trim($_POST['end_date'] ?? '') ?: null,
                    $_POST['notes'] ?? null,
                    Auth::id()
                );
                flash('success', 'تم تعيين الموظف مؤقتاً في الدائرة المحددة.');
            } catch (Throwable $e) {
                flash('error', $e->getMessage());
            }
            redirect('/manager/users/edit?id=' . $userId);
        })(),

        $route === '/manager/users/cross-end' && $method === 'POST' => (function () {
            Auth::requirePermission('borrow_employee');
            if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
                flash('error', 'انتهت صلاحية النموذج.');
                redirect('/manager/users');
            }
            $userId = (int) ($_POST['user_id'] ?? 0);
            try {
                CrossDepartmentService::end((int) ($_POST['assignment_id'] ?? 0), Auth::id());
                flash('success', 'تم إنهاء التعيين المؤقت.');
            } catch (Throwable $e) {
                flash('error', $e->getMessage());
            }
            redirect('/manager/users/edit?id=' . $userId);
        })(),

        $route === '/manager/job-description' && $method === 'GET' => (function () {
            Auth::requirePermission('manage_job_description');
            try {
                JobDescriptionService::assertCanManage(Auth::id(), Auth::role());
            } catch (Throwable $e) {
                flash('error', $e->getMessage());
                redirect('/manager/dashboard');
            }
            $users = JobDescriptionService::manageableUsers();
            view('manager/job_description', compact('users'));
        })(),

        $route === '/manager/job-description/edit' && $method === 'GET' => (function () {
            Auth::requirePermission('manage_job_description');
            $userId = (int) ($_GET['user_id'] ?? 0);
            try {
                JobDescriptionService::assertCanManageUser(Auth::id(), Auth::role(), $userId);
            } catch (Throwable $e) {
                flash('error', $e->getMessage());
                redirect('/manager/job-description');
            }
            $user = UserService::getById($userId);
            $profile = JobDescriptionService::getProfile($userId);
            $tasks = JobDescriptionService::tasksForUser($userId);
            view('manager/job_description_edit', compact('user', 'profile', 'tasks'));
        })(),

        $route === '/manager/job-description/title' && $method === 'POST' => (function () {
            Auth::requirePermission('manage_job_description');
            if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
                flash('error', 'انتهت صلاحية النموذج.');
                redirect('/manager/job-description');
            }
            $userId = (int) ($_POST['user_id'] ?? 0);
            try {
                JobDescriptionService::assertCanManageUser(Auth::id(), Auth::role(), $userId);
                JobDescriptionService::setJobTitle($userId, $_POST['job_title'] ?? '', Auth::id());
                flash('success', 'تم حفظ المسمى الوظيفي.');
            } catch (Throwable $e) {
                flash('error', $e->getMessage());
            }
            redirect('/manager/job-description/edit?user_id=' . $userId);
        })(),

        $route === '/manager/job-description/create' && $method === 'POST' => (function () {
            Auth::requirePermission('manage_job_description');
            if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
                flash('error', 'انتهت صلاحية النموذج.');
                redirect('/manager/job-description');
            }
            $userId = (int) ($_POST['user_id'] ?? 0);
            try {
                JobDescriptionService::assertCanManageUser(Auth::id(), Auth::role(), $userId);
                JobDescriptionService::createTask($userId, $_POST['title'] ?? '', Auth::id());
                flash('success', 'تمت إضافة المهمة الفرعية.');
            } catch (Throwable $e) {
                flash('error', $e->getMessage());
            }
            redirect('/manager/job-description/edit?user_id=' . $userId);
        })(),

        $route === '/manager/job-description/update' && $method === 'POST' => (function () {
            Auth::requirePermission('manage_job_description');
            if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
                flash('error', 'انتهت صلاحية النموذج.');
                redirect('/manager/job-description');
            }
            $userId = (int) ($_POST['user_id'] ?? 0);
            $dutyId = (int) ($_POST['duty_id'] ?? 0);
            try {
                JobDescriptionService::assertCanManage(Auth::id(), Auth::role());
                $duty = JobDescriptionService::getById($dutyId);
                if (!$duty || (int) $duty['user_id'] !== $userId) {
                    throw new RuntimeException('المهمة غير موجودة.');
                }
                JobDescriptionService::updateTask($dutyId, $_POST['title'] ?? '');
                flash('success', 'تم حفظ المهمة الفرعية.');
            } catch (Throwable $e) {
                flash('error', $e->getMessage());
            }
            redirect('/manager/job-description/edit?user_id=' . $userId);
        })(),

        $route === '/manager/job-description/delete' && $method === 'POST' => (function () {
            Auth::requirePermission('manage_job_description');
            if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
                flash('error', 'انتهت صلاحية النموذج.');
                redirect('/manager/job-description');
            }
            $userId = (int) ($_POST['user_id'] ?? 0);
            $dutyId = (int) ($_POST['duty_id'] ?? 0);
            try {
                JobDescriptionService::assertCanManage(Auth::id(), Auth::role());
                $duty = JobDescriptionService::getById($dutyId);
                if (!$duty || (int) $duty['user_id'] !== $userId) {
                    throw new RuntimeException('المهمة غير موجودة.');
                }
                JobDescriptionService::deleteTask($dutyId);
                flash('success', 'تم حذف المهمة الفرعية.');
            } catch (Throwable $e) {
                flash('error', $e->getMessage());
            }
            redirect('/manager/job-description/edit?user_id=' . $userId);
        })(),

        default => (function () use ($route) {
            http_response_code(404);
            echo '<h1>404 - الصفحة غير موجودة</h1><p><a href="' . e(url('/')) . '">العودة</a></p>';
        })(),
    };
} catch (Throwable $e) {
    if (config('app.debug')) {
        http_response_code(500);
        echo '<h1>خطأ</h1><pre>' . e($e->getMessage()) . '</pre>';
        echo '<pre>' . e($e->getTraceAsString()) . '</pre>';
        throw $e;
    }
    http_response_code(500);
    echo '<h1>خطأ في الخادم</h1>';
}
