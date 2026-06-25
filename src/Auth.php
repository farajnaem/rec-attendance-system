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

        return true;
    }

    public static function logout(): void
    {
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

    public static function requireLogin(): void
    {
        if (!self::check()) {
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
        return self::check() && PermissionService::can(self::id(), $code);
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
        return self::userCount() === 0 && (bool) config('app.setup_enabled', false);
    }
}
