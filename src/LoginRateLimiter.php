<?php

declare(strict_types=1);

class LoginRateLimiter
{
    private const MAX_ATTEMPTS = 5;
    private const LOCK_MINUTES = 15;

    public static function key(string $email): string
    {
        return strtolower(trim($email)) . '|' . clientIp();
    }

    public static function tooManyAttempts(string $email): bool
    {
        if (!self::tableExists()) {
            return self::sessionTooMany($email);
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT attempts, locked_until FROM login_attempts WHERE attempt_key = ? LIMIT 1'
        );
        $stmt->execute([self::key($email)]);
        $row = $stmt->fetch();
        if (!$row) {
            return false;
        }

        if (!empty($row['locked_until']) && strtotime((string) $row['locked_until']) > time()) {
            return true;
        }

        return (int) $row['attempts'] >= self::MAX_ATTEMPTS;
    }

    public static function hit(string $email): void
    {
        if (!self::tableExists()) {
            self::sessionHit($email);
            return;
        }

        $key = self::key($email);
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT attempts FROM login_attempts WHERE attempt_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        $attempts = $row ? (int) $row['attempts'] + 1 : 1;
        $lockedUntil = $attempts >= self::MAX_ATTEMPTS
            ? date('Y-m-d H:i:s', time() + self::LOCK_MINUTES * 60)
            : null;

        if ($row) {
            $pdo->prepare(
                'UPDATE login_attempts SET attempts = ?, locked_until = ?, updated_at = CURRENT_TIMESTAMP WHERE attempt_key = ?'
            )->execute([$attempts, $lockedUntil, $key]);
        } else {
            $pdo->prepare(
                'INSERT INTO login_attempts (attempt_key, attempts, locked_until) VALUES (?, ?, ?)'
            )->execute([$key, $attempts, $lockedUntil]);
        }
    }

    public static function clear(string $email): void
    {
        if (self::tableExists()) {
            $pdo = Database::getConnection();
            $pdo->prepare('DELETE FROM login_attempts WHERE attempt_key = ?')->execute([self::key($email)]);
        }
        unset($_SESSION['login_attempts'][self::key($email)]);
    }

    public static function lockMessage(): string
    {
        return 'محاولات كثيرة. انتظر ' . self::LOCK_MINUTES . ' دقيقة ثم أعد المحاولة.';
    }

    private static function tableExists(): bool
    {
        static $exists = null;
        if ($exists !== null) {
            return $exists;
        }

        try {
            $pdo = Database::getConnection();
            $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($driver === 'sqlite') {
                $stmt = $pdo->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='login_attempts' LIMIT 1");
            } else {
                $stmt = $pdo->query(
                    "SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'login_attempts' LIMIT 1"
                );
            }
            $exists = (bool) $stmt->fetchColumn();
        } catch (Throwable) {
            $exists = false;
        }

        return $exists;
    }

    private static function sessionTooMany(string $email): bool
    {
        $key = self::key($email);
        $data = $_SESSION['login_attempts'][$key] ?? null;
        if (!$data) {
            return false;
        }
        if (!empty($data['locked_until']) && $data['locked_until'] > time()) {
            return true;
        }
        return (int) ($data['attempts'] ?? 0) >= self::MAX_ATTEMPTS;
    }

    private static function sessionHit(string $email): void
    {
        $key = self::key($email);
        $attempts = (int) ($_SESSION['login_attempts'][$key]['attempts'] ?? 0) + 1;
        $_SESSION['login_attempts'][$key] = [
            'attempts' => $attempts,
            'locked_until' => $attempts >= self::MAX_ATTEMPTS ? time() + self::LOCK_MINUTES * 60 : null,
        ];
    }
}
