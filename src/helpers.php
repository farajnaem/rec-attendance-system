<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/RoleHelper.php';

function config(string $key, mixed $default = null): mixed
{
    if (!function_exists('app_config')) {
        require_once dirname(__DIR__) . '/config/config.php';
    }
    $cfg = app_config();
    $parts = explode('.', $key);
    $value = $cfg;
    foreach ($parts as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

function base_path(): string
{
    return rtrim((string) config('app.base_path', ''), '/');
}

function url(string $path = ''): string
{
    $base = base_path();
    $path = '/' . ltrim($path, '/');
    return $base . ($path === '/' ? '' : $path);
}

/** مسار ثابت لملفات CSS/JS — يعمل من أي صفحة بما فيها setup.php */
function asset(string $path): string
{
    $path = '/' . ltrim($path, '/');
    $file = dirname(__DIR__) . '/public' . $path;
    $version = is_file($file) ? (string) filemtime($file) : '';
    $url = base_path() . $path;
    return $version !== '' ? $url . '?v=' . $version : $url;
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }
    $val = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $val;
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function view(string $name, array $data = []): void
{
    $data['name'] = $name;
    extract($data);
    require dirname(__DIR__) . '/views/layout.php';
}

function partial(string $name, array $data = []): void
{
    extract($data);
    require dirname(__DIR__) . '/views/' . $name . '.php';
}

function statusLabel(string $status): string
{
    return match ($status) {
        'pending' => 'قيد الانتظار',
        'completed' => 'مكتملة',
        'evaluated' => 'مُقيَّمة',
        default => $status,
    };
}

function roleLabel(string $role): string
{
    return RoleHelper::label($role);
}

function clientIp(): string
{
    return $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function currentRoute(): string
{
    $route = $_GET['route'] ?? currentPath();
    $route = '/' . trim((string) $route, '/');

    return $route === '//' ? '/' : $route;
}

function userInitials(string $name): string
{
    $parts = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    if ($parts === []) {
        return '?';
    }
    if (count($parts) === 1) {
        return mb_strtoupper(mb_substr($parts[0], 0, 2));
    }

    return mb_strtoupper(mb_substr($parts[0], 0, 1) . mb_substr($parts[1], 0, 1));
}

function userHandle(string $email): string
{
    $email = trim(strtolower($email));
    $local = explode('@', $email)[0] ?? $email;

    return '@' . $local;
}

function currentPath(): string
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $path = rtrim($path, '/') ?: '/';
    $base = base_path();
    if ($base !== '' && str_starts_with($path, $base)) {
        $path = substr($path, strlen($base)) ?: '/';
    }
    return $path;
}

function navIsActive(string $path, bool $exact = false): bool
{
    $current = currentPath();
    $path = rtrim($path, '/') ?: '/';
    if ($exact) {
        return $current === $path;
    }
    return $current === $path || str_starts_with($current, $path . '/');
}
