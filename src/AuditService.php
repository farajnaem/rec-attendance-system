<?php

declare(strict_types=1);

class AuditService
{
    public static function log(
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $details = null,
        ?int $userId = null
    ): void {
        if (!self::tableExists()) {
            return;
        }

        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare(
                'INSERT INTO audit_log (user_id, action, entity_type, entity_id, details, ip_address)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $userId ?? (Auth::check() ? Auth::id() : null),
                $action,
                $entityType,
                $entityId,
                $details !== null ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
                clientIp(),
            ]);
        } catch (Throwable) {
            // لا نُعطّل تسجيل الدخول أو العمليات الأساسية إذا فشل التدقيق
        }
    }

    public static function recent(int $limit = 100): array
    {
        if (!self::tableExists()) {
            return [];
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT a.*, u.name AS user_name
             FROM audit_log a
             LEFT JOIN users u ON u.id = a.user_id
             ORDER BY a.id DESC
             LIMIT ?'
        );
        $stmt->bindValue(1, max(1, min($limit, 500)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public static function actionLabel(string $action): string
    {
        return match ($action) {
            'login' => 'تسجيل دخول',
            'logout' => 'تسجيل خروج',
            'login.failed' => 'محاولة دخول فاشلة',
            'user.create' => 'إنشاء مستخدم',
            'user.update' => 'تحديث مستخدم',
            'user.delete' => 'حذف مستخدم',
            'permissions.update' => 'تحديث صلاحيات',
            'database.export' => 'تصدير قاعدة البيانات',
            'database.import' => 'استيراد قاعدة البيانات',
            'attendance.manual' => 'تصحيح حضور يدوي',
            'leave.create' => 'طلب إجازة',
            'leave.approve' => 'موافقة إجازة',
            'leave.reject' => 'رفض إجازة',
            'password.change' => 'تغيير كلمة المرور',
            default => $action,
        };
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
                $stmt = $pdo->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='audit_log' LIMIT 1");
            } else {
                $stmt = $pdo->query(
                    "SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'audit_log' LIMIT 1"
                );
            }
            $exists = (bool) $stmt->fetchColumn();
        } catch (Throwable) {
            $exists = false;
        }

        return $exists;
    }
}
