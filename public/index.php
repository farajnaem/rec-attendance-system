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

require dirname(__DIR__) . '/src/bootstrap.php';
rec_load_core();
require dirname(__DIR__) . '/routes/extras.php';

if (envBool('RUN_MIGRATIONS_ON_REQUEST', false)) {
    MigrationRunner::ensureLatest();
}

$route = $_GET['route'] ?? '/';
$route = '/' . trim($route, '/');
if ($route === '//') {
    $route = '/';
}

$method = $_SERVER['REQUEST_METHOD'];

if (dispatchExtraRoutes($route, $method)) {
    return;
}

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
            try {
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
                if (LoginRateLimiter::tooManyAttempts($email)) {
                    flash('error', LoginRateLimiter::lockMessage());
                    redirect('/login');
                }
                if (Auth::attempt($email, $password)) {
                    LoginRateLimiter::clear($email);
                    redirect(RoleHelper::dashboardPath(Auth::role()));
                }
                LoginRateLimiter::hit($email);
                AuditService::log('login.failed', 'user', null, ['email' => $email], null);
                flash('error', 'البريد أو كلمة المرور غير صحيحة.');
                redirect('/login');
            } catch (PDOException $e) {
                error_log('Login database error: ' . $e->getMessage());
                $message = config('app.debug')
                    ? 'خطأ قاعدة البيانات: ' . $e->getMessage()
                    : 'تعذّر الاتصال بقاعدة البيانات. تحقق من إعدادات MySQL في Coolify أو افتح /health';
                flash('error', $message);
                redirect('/login');
            }
        })(),

        $route === '/health' && $method === 'GET' => (function () {
            header('Content-Type: application/json');
            $payload = [
                'status' => 'ok',
                'db' => 'unknown',
                'db_source' => dbConnectionSource(),
            ];

            try {
                $pdo = Database::getConnection();
                $pdo->query('SELECT 1');
                $payload['db'] = 'connected';
                $payload['users'] = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
            } catch (Throwable $e) {
                $payload['status'] = 'degraded';
                $payload['db'] = 'failed';
                if (config('app.debug')) {
                    $payload['db_error'] = $e->getMessage();
                }
            }

            echo json_encode($payload, JSON_UNESCAPED_UNICODE);
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
            $taskReplies = TaskService::repliesForTasks(array_column($tasks, 'id'));
            $recent = AttendanceService::recent(Auth::id(), 7);
            $gpsRequired = LocationService::hasActiveLocations();
            $workLocations = $gpsRequired ? LocationService::activeForClient() : [];
            $crossAssignment = CrossDepartmentService::activeForUser(Auth::id());
            $jobDescription = JobDescriptionService::fullForUser(Auth::id());
            $leaves = LeaveService::listForUser(Auth::id());
            $workBreaks = WorkBreakService::listForUser(Auth::id());
            $breakAuthorizers = UserService::supervisors();
            $isLateToday = false;
            if (!empty($status['check_in'])) {
                $localCheckIn = TimezoneHelper::toLocal($status['check_in']['signed_at_utc'], $tz)->format('Y-m-d H:i:s');
                $isLateToday = WorkScheduleService::isLateCheckIn($localCheckIn);
            }
            view('employee/dashboard', array_merge(
                compact('status', 'tasks', 'taskReplies', 'recent', 'tz', 'gpsRequired', 'crossAssignment', 'workLocations', 'jobDescription', 'leaves', 'workBreaks', 'breakAuthorizers', 'isLateToday'),
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
            if ($isOwner && !Auth::can('complete_own_tasks')) {
                flash('error', 'لا يمكنك إتمام المهام.');
                redirect(RoleHelper::dashboardPath(Auth::role()));
            }
            if (!$isOwner && !Auth::can('respond_complete_tasks') && !Auth::can('manage_daily_tasks')) {
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
            Auth::requirePermission('monthly_self_report');
            $year = (int) ($_GET['year'] ?? date('Y'));
            $month = (int) ($_GET['month'] ?? date('n'));
            $report = ReportService::fullReport(Auth::id(), $year, $month);
            $narrative = NarrativeReportService::get(Auth::id(), $year, $month);
            $canEditNarrative = NarrativeReportService::canEdit($year, $month);
            $narrativeDeadline = NarrativeReportService::deadlineLabel($year, $month);
            $submissionDays = NarrativeReportService::submissionDays();
            view('employee/monthly_report', compact(
                'report', 'year', 'month', 'narrative', 'canEditNarrative',
                'narrativeDeadline', 'submissionDays'
            ));
        })(),

        $route === '/employee/report/narrative/save' && $method === 'POST' => (function () {
            Auth::requireLogin();
            Auth::requirePermission('monthly_self_report');
            if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
                flash('error', 'انتهت صلاحية النموذج.');
                redirect('/employee/report');
            }
            $year = (int) ($_POST['year'] ?? date('Y'));
            $month = (int) ($_POST['month'] ?? date('n'));
            try {
                NarrativeReportService::save(
                    Auth::id(),
                    $year,
                    $month,
                    $_POST['work_summary'] ?? '',
                    $_POST['positives'] ?? '',
                    $_POST['negatives'] ?? '',
                    $_POST['development_notes'] ?? '',
                    $_POST['difficulties'] ?? '',
                    isset($_POST['submit_final'])
                );
                flash('success', isset($_POST['submit_final']) ? 'تم تقديم التقرير السردي.' : 'تم حفظ المسودة.');
            } catch (Throwable $e) {
                flash('error', $e->getMessage());
            }
            redirect('/employee/report?year=' . $year . '&month=' . $month);
        })(),

        $route === '/employee/task/reply' && $method === 'POST' => (function () {
            Auth::requireLogin();
            if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
                flash('error', 'انتهت صلاحية النموذج.');
                redirect('/employee/dashboard#my-tasks');
            }
            $taskId = (int) ($_POST['task_id'] ?? 0);
            try {
                if (!TaskService::canAccess($taskId, Auth::id(), Auth::role())) {
                    throw new RuntimeException('المهمة غير موجودة.');
                }
                TaskService::addReply($taskId, Auth::id(), $_POST['message'] ?? '');
                flash('success', 'تم إرسال الرد إلى معطي المهمة.');
            } catch (Throwable $e) {
                flash('error', $e->getMessage());
            }
            redirect('/employee/dashboard#my-tasks');
        })(),

        $route === '/manager/dashboard' && $method === 'GET' => (function () {
            Auth::requireRole(RoleHelper::managementRoles());
            $team = ScopeService::visibleStaff(Auth::id(), Auth::role());
            $today = TimezoneHelper::localWorkDate(TimezoneHelper::utcNow(), Auth::timezone());
            $attendance = AttendanceService::teamAttendanceForActor(Auth::id(), Auth::role(), $today);
            $stats = ReportService::dashboardStats(Auth::id(), Auth::role(), $today);
            view('manager/dashboard', compact('team', 'attendance', 'today', 'stats'));
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
            $taskReplies = TaskService::repliesForTasks(array_column($tasks, 'id'));
            view('manager/tasks', compact('tasks', 'employees', 'from', 'to', 'taskReplies'));
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
                    throw new RuntimeException('لا يمكنك إسناد مهمة لهذا الموظف.');
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
            $from = $_GET['from'] ?? date('Y-m-d', strtotime('-30 days'));
            $to = $_GET['to'] ?? date('Y-m-d');
            $records = AttendanceService::teamRecordsForActor(Auth::id(), Auth::role(), $from, $to);
            $canManual = Auth::can('view_reports_readonly') || Auth::can('manage_users');
            $staff = $canManual ? ScopeService::visibleStaff(Auth::id(), Auth::role()) : [];
            view('manager/attendance', compact('records', 'from', 'to', 'canManual', 'staff'));
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
                flash('error', 'لا يمكنك عرض تقرير هذا الموظف.');
                redirect('/manager/reports');
            }
            $report = $employeeId ? ReportService::fullReport($employeeId, $year, $month) : null;
            view('manager/reports', compact('employees', 'report', 'year', 'month', 'employeeId'));
        })(),

        $route === '/manager/users' && $method === 'GET' => (function () {
            Auth::requireAnyPermission(['manage_users', 'view_department_users', 'view_all_users', 'borrow_employee']);
            $isSystemAdmin = Auth::role() === 'system_admin';
            $canManageAllUsers = PermissionService::hasFullUserManagement(Auth::id());
            $canEditDeptUsers = Auth::can('edit_department_users');
            $canAssignPermissions = RoleHelper::canEditPermissions(Auth::role());
            $canEditPermissions = $canAssignPermissions;
            $users = ScopeService::visibleUsers(Auth::id(), Auth::role());
            $page = max(1, (int) ($_GET['page'] ?? 1));
            $pagination = paginate($users, $page, 20);
            $supervisors = UserService::supervisors();
            $departments = DepartmentService::all();
            $availableRoles = $canAssignPermissions
                ? RoleHelper::all()
                : ['employee' => RoleHelper::label('employee')];
            $canBorrowEmployee = Auth::can('borrow_employee');
            $userOverview = null;
            if (Auth::can('view_all_users') || $canManageAllUsers || $canEditDeptUsers) {
                $userOverview = [
                    'total' => count($users),
                    'active' => count(array_filter($users, static fn (array $u): bool => (int) $u['is_active'] === 1)),
                    'inactive' => count(array_filter($users, static fn (array $u): bool => (int) $u['is_active'] !== 1)),
                ];
            }
            $borrowable = [];
            $activeBorrowings = [];
            $borrowTargetDepartments = [];
            if ($canBorrowEmployee) {
                try {
                    $borrowable = CrossDepartmentService::borrowableEmployees(Auth::id(), Auth::role());
                    $activeBorrowings = CrossDepartmentService::activeBorrowingsForActor(Auth::id(), Auth::role());
                    $role = RoleHelper::normalizeRole(Auth::role());
                    if (in_array($role, ['system_admin', 'director', 'admin_assistant'], true)) {
                        $borrowTargetDepartments = DepartmentService::all();
                    } else {
                        $deptIds = ScopeService::supervisedDepartmentIds(Auth::id());
                        $borrowTargetDepartments = array_values(array_filter(
                            DepartmentService::all(),
                            static fn ($d) => in_array((int) $d['id'], $deptIds, true)
                        ));
                    }
                } catch (Throwable) {
                    $borrowable = [];
                    $activeBorrowings = [];
                    $borrowTargetDepartments = [];
                }
            }
            view('manager/users', compact(
                'users', 'pagination', 'supervisors', 'departments', 'isSystemAdmin',
                'canAssignPermissions', 'canEditPermissions', 'availableRoles', 'canBorrowEmployee',
                'canManageAllUsers', 'canEditDeptUsers', 'userOverview',
                'borrowable', 'activeBorrowings', 'borrowTargetDepartments'
            ) + ['roleDefaultsMap' => PermissionService::roleDefaultsMap()]);
        })(),

        $route === '/manager/users/create' && $method === 'POST' => (function () {
            Auth::requirePermission('manage_users');
            if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
                flash('error', 'انتهت صلاحية النموذج.');
                redirect('/manager/users');
            }
            try {
                $role = $_POST['role'] ?? 'employee';
                if (!RoleHelper::canEditPermissions(Auth::role())) {
                    $role = 'employee';
                }
                if (!RoleHelper::isValid($role)) {
                    throw new InvalidArgumentException('الدور غير صالح.');
                }
                $managerId = $role === 'employee'
                    ? (int) ($_POST['manager_id'] ?? 0) ?: null
                    : null;
                $deptId = (int) ($_POST['department_id'] ?? 0) ?: null;
                $canAssign = RoleHelper::canEditPermissions(Auth::role());
                $role = RoleHelper::normalizeRole($role);
                $perms = $canAssign
                    ? (isset($_POST['permissions']) && is_array($_POST['permissions']) && $_POST['permissions'] !== []
                        ? $_POST['permissions']
                        : PermissionService::defaultCodesForRole($role))
                    : null;
                UserService::create(
                    $_POST['name'] ?? '',
                    $_POST['email'] ?? '',
                    $_POST['password'] ?? '',
                    $role,
                    $_POST['timezone'] ?? config('app.default_timezone'),
                    $managerId,
                    $deptId,
                    $perms,
                    $_POST['contract_end_date'] ?? null
                );
                flash('success', 'تمت إضافة الموظف بنجاح.');
            } catch (Throwable $e) {
                flash('error', $e->getMessage());
            }
            redirect('/manager/users');
        })(),

        $route === '/manager/users/edit' && $method === 'GET' => (function () {
            Auth::requireAnyPermission(['manage_users', 'edit_department_users']);
            $userId = (int) ($_GET['id'] ?? 0);
            try {
                UserService::assertCanEdit($userId, Auth::id(), Auth::role());
            } catch (Throwable $e) {
                flash('error', $e->getMessage());
                redirect('/manager/users');
            }
            $user = UserService::getById($userId);
            if (!$user) {
                flash('error', 'الموظف غير موجود.');
                redirect('/manager/users');
            }
            $canManageAllUsers = PermissionService::hasFullUserManagement(Auth::id());
            $canChangeRole = RoleHelper::canEditPermissions(Auth::role());
            $canAssignPermissions = RoleHelper::canEditPermissions(Auth::role());
            $canEditPermissions = $canAssignPermissions;
            $department = DepartmentService::currentForUser($userId);
            $departments = DepartmentService::all();
            $supervisors = UserService::supervisors();
            $canBorrowEmployee = Auth::can('borrow_employee');
            $canChangeDepartment = $canManageAllUsers;
            $crossAssignments = CrossDepartmentService::listForUser($userId);
            $activeCross = CrossDepartmentService::activeForUser($userId);
            view('manager/user_edit', compact(
                'user', 'department', 'departments', 'supervisors',
                'canChangeRole', 'canAssignPermissions', 'canEditPermissions', 'canBorrowEmployee',
                'canManageAllUsers', 'canChangeDepartment',
                'crossAssignments', 'activeCross'
            ));
        })(),

        $route === '/manager/users/update' && $method === 'POST' => (function () {
            Auth::requireAnyPermission(['manage_users', 'edit_department_users']);
            if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
                flash('error', 'انتهت صلاحية النموذج.');
                redirect('/manager/users');
            }
            $userId = (int) ($_POST['user_id'] ?? 0);
            try {
                $role = $_POST['role'] ?? 'employee';
                $canChangeRole = RoleHelper::canEditPermissions(Auth::role());
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
                    $canChangeRole,
                    $_POST['contract_end_date'] ?? null,
                    PermissionService::hasFullUserManagement(Auth::id())
                );
                flash('success', 'تم حفظ بيانات الموظف.');
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
                flash('success', 'تم تحديث حالة الموظف.');
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
                flash('success', 'تم حذف الموظف وجميع سجلاته المرتبطة.');
            } catch (Throwable $e) {
                flash('error', $e->getMessage());
            }
            redirect('/manager/users');
        })(),

        $route === '/manager/permissions' && $method === 'GET' => (function () {
            if (!PermissionService::canGrantToOthers(Auth::id(), Auth::role())) {
                flash('error', 'إسناد الصلاحيات متاح للمدير ومدير النظام فقط.');
                redirect('/manager/dashboard');
            }
            $employees = [];
            foreach (ScopeService::visibleUsers(Auth::id(), Auth::role()) as $emp) {
                $emp['permission_count'] = count(
                    PermissionService::effectiveCodesForUser((int) $emp['id'], (string) $emp['role'])
                );
                $employees[] = $emp;
            }
            view('manager/permissions_hub', compact('employees'));
        })(),

        $route === '/manager/users/permissions' && $method === 'GET' => (function () {
            if (!PermissionService::canGrantToOthers(Auth::id(), Auth::role())) {
                flash('error', 'إسناد الصلاحيات متاح للمدير ومدير النظام فقط.');
                redirect('/manager/users');
            }
            $userId = (int) ($_GET['id'] ?? 0);
            $user = UserService::getById($userId);
            if (!$user) {
                flash('error', 'الموظف غير موجود.');
                redirect('/manager/users');
            }
            $granted = PermissionService::effectiveCodesForUser($userId, (string) $user['role']);
            $department = DepartmentService::currentForUser($userId);
            $departments = DepartmentService::all();
            view('manager/user_permissions', compact('user', 'granted', 'department', 'departments'));
        })(),

        $route === '/manager/users/permissions' && $method === 'POST' => (function () {
            if (!PermissionService::canGrantToOthers(Auth::id(), Auth::role())) {
                flash('error', 'إسناد الصلاحيات متاح للمدير ومدير النظام فقط.');
                redirect('/manager/users');
            }
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
            Auth::requireAnyPermission(['manage_departments', 'view_departments']);
            $canManageDepartments = PermissionService::canManageDepartments(Auth::id());
            $departments = DepartmentService::all(false);
            $supervisors = DepartmentService::supervisorsForSelect();
            view('manager/departments', compact('departments', 'supervisors', 'canManageDepartments'));
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
            if (!Auth::can('manage_work_schedule') && !Auth::can('manage_report_deadline') && !RoleHelper::isSystemAdmin(Auth::role())) {
                flash('error', 'لا يمكنك تعديل ساعات الدوام.');
                redirect('/manager/system');
            }
            $schedule = WorkScheduleService::get();
            view('manager/work_schedule', compact('schedule'));
        })(),

        $route === '/manager/work-schedule/save' && $method === 'POST' => (function () {
            if (!Auth::can('manage_work_schedule') && !Auth::can('manage_report_deadline') && !RoleHelper::isSystemAdmin(Auth::role())) {
                flash('error', 'لا يمكنك تعديل ساعات الدوام.');
                redirect('/manager/system');
            }
            if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
                flash('error', 'انتهت صلاحية النموذج.');
                redirect('/manager/work-schedule');
            }
            try {
                $days = $_POST['work_days'] ?? [];
                if (!is_array($days) || empty($days)) {
                    throw new InvalidArgumentException('اختر يوم عمل واحد على الأقل.');
                }
                $reportDays = Auth::can('manage_report_deadline') || RoleHelper::isSystemAdmin(Auth::role())
                    ? (int) ($_POST['report_submission_days'] ?? 5)
                    : null;
                $freezeDays = Auth::can('manage_work_schedule') || RoleHelper::isSystemAdmin(Auth::role())
                    ? (int) ($_POST['contract_freeze_grace_days'] ?? 30)
                    : null;
                WorkScheduleService::update(
                    $_POST['work_start_time'] ?? '08:00',
                    $_POST['work_end_time'] ?? '16:00',
                    (int) ($_POST['late_grace_minutes'] ?? 15),
                    implode(',', $days),
                    Auth::id(),
                    $reportDays,
                    $freezeDays
                );
                flash('success', 'تم حفظ إعدادات الدوام والتقرير السردي.');
            } catch (Throwable $e) {
                flash('error', $e->getMessage());
            }
            redirect('/manager/work-schedule');
        })(),

        $route === '/manager/system' && $method === 'GET' => (function () {
            $canAccess = Auth::can('manage_work_schedule')
                || Auth::can('manage_report_deadline')
                || Auth::can('manage_locations')
                || Auth::can('transfer_employee')
                || Auth::can('manage_system')
                || RoleHelper::isSystemAdmin(Auth::role());
            if (!$canAccess) {
                flash('error', 'لا يمكنك الوصول لإدارة النظام.');
                redirect('/manager/dashboard');
            }
            view('manager/system');
        })(),

        $route === '/manager/borrow-employee' && $method === 'GET' => (function () {
            Auth::requirePermission('borrow_employee');
            redirect('/manager/users#borrow-employees');
        })(),

        $route === '/manager/borrow-employee/create' && $method === 'POST' => (function () {
            Auth::requirePermission('borrow_employee');
            if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
                flash('error', 'انتهت صلاحية النموذج.');
                redirect('/manager/users#borrow-employees');
            }
            try {
                $targetDept = (int) ($_POST['target_department_id'] ?? 0);
                CrossDepartmentService::assertCanAssign(Auth::id(), Auth::role(), $targetDept);
                $userId = (int) ($_POST['user_id'] ?? 0);
                CrossDepartmentService::create(
                    $userId,
                    $targetDept,
                    $_POST['start_date'] ?? date('Y-m-d'),
                    trim($_POST['end_date'] ?? '') ?: null,
                    $_POST['notes'] ?? null,
                    Auth::id()
                );
                flash('success', 'تمت استعارة الموظف بنجاح.');
            } catch (Throwable $e) {
                flash('error', $e->getMessage());
            }
            redirect('/manager/users#borrow-employees');
        })(),

        $route === '/manager/borrow-employee/end' && $method === 'POST' => (function () {
            Auth::requirePermission('borrow_employee');
            if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
                flash('error', 'انتهت صلاحية النموذج.');
                redirect('/manager/users#borrow-employees');
            }
            try {
                CrossDepartmentService::end((int) ($_POST['assignment_id'] ?? 0), Auth::id(), Auth::role());
                flash('success', 'تم إنهاء الاستعارة.');
            } catch (Throwable $e) {
                flash('error', $e->getMessage());
            }
            redirect('/manager/users#borrow-employees');
        })(),

        $route === '/documents' && $method === 'GET' => (function () {
            Auth::requireLogin();
            $employees = DocumentService::employeesForHub(Auth::id(), Auth::role());
            if (count($employees) === 1 && RoleHelper::isEmployee(Auth::role())) {
                redirect('/documents/employee?id=' . (int) $employees[0]['id']);
            }
            if (!Auth::can('view_employee_documents') && !Auth::can('manage_employee_documents')
                && !Auth::can('view_documents') && !Auth::can('manage_documents')) {
                flash('error', 'لا يمكنك عرض المستندات.');
                redirect(RoleHelper::dashboardPath(Auth::role()));
            }
            view('documents/index', compact('employees'));
        })(),

        $route === '/documents/employee' && $method === 'GET' => (function () {
            Auth::requireLogin();
            $ownerId = (int) ($_GET['id'] ?? Auth::id());
            if (!DocumentService::canAccess(Auth::id(), Auth::role(), $ownerId)) {
                flash('error', 'لا يمكنك عرض حافظة هذا الموظف.');
                redirect('/documents');
            }
            $owner = UserService::getById($ownerId);
            if (!$owner) {
                flash('error', 'الموظف غير موجود.');
                redirect('/documents');
            }
            $documents = DocumentService::listForEmployee($ownerId);
            $canManageDocs = DocumentService::canManage(Auth::id(), Auth::role(), $ownerId);
            $categories = DocumentService::CATEGORIES;
            view('documents/employee', compact('owner', 'documents', 'canManageDocs', 'categories'));
        })(),

        $route === '/documents/upload' && $method === 'POST' => (function () {
            Auth::requireLogin();
            if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
                flash('error', 'انتهت صلاحية النموذج.');
                redirect('/documents');
            }
            $ownerId = (int) ($_POST['owner_user_id'] ?? Auth::id());
            try {
                if (!DocumentService::canManage(Auth::id(), Auth::role(), $ownerId)) {
                    throw new RuntimeException('لا يمكنك رفع مستندات لهذا الموظف.');
                }
                DocumentService::upload(
                    Auth::id(),
                    $ownerId,
                    $_POST['category'] ?? 'other',
                    $_POST['title'] ?? '',
                    $_FILES['document'] ?? []
                );
                flash('success', 'تم رفع المستند.');
            } catch (Throwable $e) {
                flash('error', $e->getMessage());
            }
            redirect('/documents/employee?id=' . $ownerId);
        })(),

        $route === '/documents/download' && $method === 'GET' => (function () {
            Auth::requireLogin();
            $doc = DocumentService::get((int) ($_GET['id'] ?? 0));
            if (!$doc) {
                http_response_code(404);
                echo 'غير موجود';
                return;
            }
            $ownerId = (int) ($doc['owner_user_id'] ?? $doc['uploaded_by']);
            if (!DocumentService::canAccess(Auth::id(), Auth::role(), $ownerId)) {
                http_response_code(403);
                echo 'غير مصرح';
                return;
            }
            $path = DocumentService::filePath($doc);
            if (!is_file($path)) {
                http_response_code(404);
                echo 'الملف غير موجود';
                return;
            }
            header('Content-Type: ' . $doc['mime_type']);
            header('Content-Disposition: attachment; filename="' . rawurlencode($doc['original_filename']) . '"');
            header('Content-Length: ' . (string) filesize($path));
            readfile($path);
            exit;
        })(),

        $route === '/documents/delete' && $method === 'POST' => (function () {
            Auth::requireLogin();
            if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
                flash('error', 'انتهت صلاحية النموذج.');
                redirect('/documents');
            }
            $ownerId = (int) ($_POST['owner_user_id'] ?? 0);
            try {
                DocumentService::delete((int) ($_POST['document_id'] ?? 0), Auth::id(), Auth::role());
                flash('success', 'تم حذف المستند.');
            } catch (Throwable $e) {
                flash('error', $e->getMessage());
            }
            redirect($ownerId ? '/documents/employee?id=' . $ownerId : '/documents');
        })(),

        $route === '/employee/work-break/request' && $method === 'POST' => (function () {
            Auth::requireLogin();
            if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
                flash('error', 'انتهت صلاحية النموذج.');
                redirect('/employee/dashboard');
            }
            try {
                WorkBreakService::create(
                    Auth::id(),
                    $_POST['work_date'] ?? date('Y-m-d'),
                    $_POST['exit_time'] ?? '',
                    $_POST['return_time'] ?? '',
                    (int) ($_POST['authorized_by'] ?? 0),
                    $_POST['notes'] ?? null
                );
                flash('success', 'تم تسجيل طلب المغادرة — بانتظار اعتماد المشرف.');
            } catch (Throwable $e) {
                flash('error', $e->getMessage());
            }
            redirect('/employee/dashboard#work-breaks');
        })(),

        $route === '/manager/narrative-reports' && $method === 'GET' => (function () {
            Auth::requirePermission('view_narrative_reports');
            $year = (int) ($_GET['year'] ?? date('Y'));
            $month = (int) ($_GET['month'] ?? date('n'));
            $reports = NarrativeReportService::listSubmittedForReviewer(Auth::id(), Auth::role(), $year, $month);
            view('manager/narrative_reports', compact('reports', 'year', 'month'));
        })(),

        $route === '/manager/database' && $method === 'GET' => (function () {
            Auth::requireRole(['system_admin']);
            require dirname(__DIR__) . '/database/DataSync.php';
            $driverLabel = DataSync::driverLabel();
            try {
                $preview = DataSync::exportCurrent();
                $stats = $preview['stats'] ?? [];
            } catch (Throwable $e) {
                flash('error', 'تعذّر قراءة قاعدة البيانات: ' . $e->getMessage());
                $stats = [];
            }
            $maxUploadMb = 25;
            view('manager/database', compact('driverLabel', 'stats', 'maxUploadMb'));
        })(),

        $route === '/manager/database/import' && $method === 'POST' => (function () {
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

            $maxBytes = 25 * 1024 * 1024;
            $file = $_FILES['import_file'] ?? null;
            if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                flash('error', 'يرجى اختيار ملف JSON صالح.');
                redirect('/manager/database');
            }
            if (($file['size'] ?? 0) > $maxBytes) {
                flash('error', 'حجم الملف أكبر من 25 ميجابايت.');
                redirect('/manager/database');
            }

            $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
            if ($ext !== 'json') {
                flash('error', 'يجب أن يكون الملف بصيغة .json');
                redirect('/manager/database');
            }

            $mode = ($_POST['import_mode'] ?? 'replace') === 'merge' ? 'merge' : 'replace';
            if ($mode === 'replace') {
                if (trim($_POST['confirm_text'] ?? '') !== 'استبدال') {
                    flash('error', 'اكتب «استبدال» للتأكيد على الاستبدال الكامل.');
                    redirect('/manager/database');
                }
            }
            if (empty($_POST['confirm_ack'])) {
                flash('error', 'يجب تأكيد فهمك لخطورة العملية.');
                redirect('/manager/database');
            }

            try {
                $payload = DataSync::loadExport((string) $file['tmp_name']);
                $summary = DataSync::importCurrent($payload, $mode === 'replace');
                AuditService::log('database.import', null, null, ['mode' => $mode]);
                $total = array_sum($summary['imported']);
                flash('success', 'تم الاستيراد بنجاح — ' . $total . ' سجل في ' . count($summary['imported']) . ' جدول.');
            } catch (Throwable $e) {
                flash('error', 'فشل الاستيراد: ' . $e->getMessage());
            }
            redirect('/manager/database');
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
                CrossDepartmentService::assertCanAssign(Auth::id(), Auth::role(), (int) ($_POST['target_department_id'] ?? 0));
                if (!ScopeService::canViewUser(Auth::id(), Auth::role(), $userId)) {
                    throw new RuntimeException('لا يمكنك تعيين هذا الموظف.');
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
                CrossDepartmentService::end((int) ($_POST['assignment_id'] ?? 0), Auth::id(), Auth::role());
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
            $users = JobDescriptionService::manageableUsers(Auth::id(), Auth::role());
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
            $dutiesBody = JobDescriptionService::getDutiesBodyText($userId);
            view('manager/job_description_edit', compact('user', 'profile', 'dutiesBody'));
        })(),

        $route === '/manager/job-description/save' && $method === 'POST' => (function () {
            Auth::requirePermission('manage_job_description');
            if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
                flash('error', 'انتهت صلاحية النموذج.');
                redirect('/manager/job-description');
            }
            $userId = (int) ($_POST['user_id'] ?? 0);
            try {
                JobDescriptionService::assertCanManageUser(Auth::id(), Auth::role(), $userId);
                JobDescriptionService::saveProfile(
                    $userId,
                    $_POST['job_title'] ?? '',
                    $_POST['duties_body'] ?? '',
                    Auth::id()
                );
                flash('success', 'تم حفظ التوصيف الوظيفي.');
            } catch (Throwable $e) {
                flash('error', $e->getMessage());
            }
            redirect('/manager/job-description/edit?user_id=' . $userId);
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
                JobDescriptionService::assertCanManageUser(Auth::id(), Auth::role(), $userId);
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
                JobDescriptionService::assertCanManageUser(Auth::id(), Auth::role(), $userId);
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
