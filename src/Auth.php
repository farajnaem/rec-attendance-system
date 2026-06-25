<?php

declare(strict_types=1);

class Auth
{
    public static function attempt(string $email, string $password): bool
    {
        $pdo = Database::getConnection();
        $email = strtolower(trim($email));
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = RoleHelper::normalizeRole($user['role']);
        $_SESSION['user_timezone'] = $user['timezone'];

        PermissionService::loadPermissionsToSession((int) $user['id'], (string) $user['role']);

        AuditService::log('login', 'user', (int) $user['id']);

        return true;
    }

    public static function refreshSessionUser(): bool
    {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([(int) $_SESSION['user_id']]);
        $user = $stmt->fetch();

        if (!$user || !(int) $user['is_active']) {
            self::logout();
            return false;
        }

        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = RoleHelper::normalizeRole((string) $user['role']);
        $_SESSION['user_timezone'] = $user['timezone'];
        PermissionService::loadPermissionsToSession((int) $user['id'], (string) $user['role']);

        return true;
    }

    public static function verifyPassword(string $password): bool
    {
        $user = self::user();
        if (!$user) {
            return false;
        }

        return password_verify($password, (string) $user['password_hash']);
    }

    public static function logout(): void
    {
        if (self::check()) {
            AuditService::log('logout', 'user', self::id());
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    public static function check(): bool
    {
        return isset($_SESSION['user_id']);
    }

    public static function id(): int
    {
        return (int) ($_SESSION['user_id'] ?? 0);
    }

    public static function role(): string
    {
        $role = $_SESSION['user_role'] ?? '';
        return RoleHelper::normalizeRole($role);
    }

    public static function timezone(): string
    {
        return $_SESSION['user_timezone'] ?? TimezoneHelper::defaultTimezone();
    }

    public static function name(): string
    {
        return $_SESSION['user_name'] ?? '';
    }

    public static function email(): string
    {
        return $_SESSION['user_email'] ?? '';
    }

    public static function requireLogin(): void
    {
        if (!self::check() || !self::refreshSessionUser()) {
            redirect('/login');
        }
    }

    public static function requireRole(array $roles): void
    {
        self::requireLogin();
        $normalized = array_map([RoleHelper::class, 'normalizeRole'], $roles);
        $current = self::role();
        if (!in_array($current, $normalized, true)) {
            flash('error', 'ليس لديك صلاحية للوصول إلى هذه الصفحة.');
            redirect(RoleHelper::dashboardPath($current));
        }
    }

    public static function requirePermission(string $code): void
    {
        self::requireLogin();
        if (!PermissionService::can(self::id(), $code)) {
            flash('error', 'ليس لديك صلاحية: ' . PermissionService::label($code));
            redirect(RoleHelper::dashboardPath(self::role()));
        }
    }

    public static function can(string $code): bool
    {
        if (!self::check()) {
            return false;
        }
        if (isset($_SESSION['permissions']) && is_array($_SESSION['permissions'])) {
            return in_array($code, $_SESSION['permissions'], true);
        }

        return PermissionService::can(self::id(), $code);
    }

    public static function user(): ?array
    {
        if (!self::check()) {
            return null;
        }
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([self::id()]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public static function userCount(): int
    {
        $pdo = Database::getConnection();
        return (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    }

    public static function needsSetup(): bool
    {
        $lock = dirname(__DIR__) . '/storage/installed.lock';
        if (is_file($lock)) {
            return false;
        }

        return self::userCount() === 0 && (bool) config('app.setup_enabled', false);
    }
}
