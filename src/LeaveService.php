<?php

declare(strict_types=1);

class LeaveService
{
    public static function listForManager(int $actorId, string $actorRole, ?string $status = null): array
    {
        if (!self::tableExists()) {
            return [];
        }

        $pdo = Database::getConnection();
        $staff = ScopeService::visibleStaff($actorId, $actorRole);
        $ids = array_map(fn ($u) => (int) $u['id'], $staff);
        if ($ids === []) {
            return [];
        }

        $ph = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT l.*, u.name AS user_name
                FROM approved_leaves l
                JOIN users u ON u.id = l.user_id
                WHERE l.user_id IN ($ph)";
        $params = $ids;
        if ($status !== null && in_array($status, ['pending', 'approved', 'rejected'], true)) {
            $sql .= ' AND l.status = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY l.start_date DESC, l.id DESC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function listForUser(int $userId): array
    {
        if (!self::tableExists()) {
            return [];
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT * FROM approved_leaves WHERE user_id = ? ORDER BY start_date DESC, id DESC'
        );
        $stmt->execute([$userId]);

        return $stmt->fetchAll();
    }

    public static function request(
        int $userId,
        string $leaveType,
        string $startDate,
        string $endDate,
        ?string $notes = null
    ): int {
        if (!LeaveHelper::isValid($leaveType)) {
            throw new InvalidArgumentException('نوع الإجازة غير صالح.');
        }
        if ($startDate > $endDate) {
            throw new InvalidArgumentException('تاريخ البداية يجب أن يكون قبل النهاية.');
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'INSERT INTO approved_leaves (user_id, leave_type, start_date, end_date, status, notes)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $leaveType, $startDate, $endDate, 'pending', $notes]);
        $id = (int) $pdo->lastInsertId();
        AuditService::log('leave.create', 'leave', $id, compact('leaveType', 'startDate', 'endDate'), $userId);

        return $id;
    }

    public static function approve(int $leaveId, int $actorId, string $actorRole): void
    {
        $leave = self::getById($leaveId);
        if (!$leave) {
            throw new RuntimeException('طلب الإجازة غير موجود.');
        }
        if (!ScopeService::canViewUser($actorId, $actorRole, (int) $leave['user_id'])) {
            throw new RuntimeException('لا يمكنك إدارة إجازة هذا الموظف.');
        }

        $pdo = Database::getConnection();
        $pdo->prepare(
            'UPDATE approved_leaves SET status = ?, approved_by = ? WHERE id = ?'
        )->execute(['approved', $actorId, $leaveId]);
        AuditService::log('leave.approve', 'leave', $leaveId, null, $actorId);
        NotificationService::notifyUser(
            (int) $leave['user_id'],
            'تمت الموافقة على إجازتك — ' . config('app.name'),
            'تمت الموافقة على طلب إجازتك من ' . $leave['start_date'] . ' إلى ' . $leave['end_date'] . '.'
        );
    }

    public static function reject(int $leaveId, int $actorId, string $actorRole): void
    {
        $leave = self::getById($leaveId);
        if (!$leave) {
            throw new RuntimeException('طلب الإجازة غير موجود.');
        }
        if (!ScopeService::canViewUser($actorId, $actorRole, (int) $leave['user_id'])) {
            throw new RuntimeException('لا يمكنك إدارة إجازة هذا الموظف.');
        }

        $pdo = Database::getConnection();
        $pdo->prepare(
            'UPDATE approved_leaves SET status = ?, approved_by = ? WHERE id = ?'
        )->execute(['rejected', $actorId, $leaveId]);
        AuditService::log('leave.reject', 'leave', $leaveId, null, $actorId);
        NotificationService::notifyUser(
            (int) $leave['user_id'],
            'تم رفض طلب إجازتك — ' . config('app.name'),
            'تم رفض طلب إجازتك من ' . $leave['start_date'] . ' إلى ' . $leave['end_date'] . '.'
        );
    }

    public static function isOnLeave(int $userId, string $date): bool
    {
        if (!self::tableExists()) {
            return false;
        }

        $pdo = Database::getConnection();
        if (self::hasStatusColumn()) {
            $stmt = $pdo->prepare(
                'SELECT 1 FROM approved_leaves
                 WHERE user_id = ? AND status = ? AND start_date <= ? AND end_date >= ? LIMIT 1'
            );
            $stmt->execute([$userId, 'approved', $date, $date]);
        } else {
            $stmt = $pdo->prepare(
                'SELECT 1 FROM approved_leaves
                 WHERE user_id = ? AND start_date <= ? AND end_date >= ? LIMIT 1'
            );
            $stmt->execute([$userId, $date, $date]);
        }

        return (bool) $stmt->fetchColumn();
    }

    public static function getById(int $id): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM approved_leaves WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ?: null;
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
                $stmt = $pdo->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='approved_leaves' LIMIT 1");
            } else {
                $stmt = $pdo->query(
                    "SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'approved_leaves' LIMIT 1"
                );
            }
            $exists = (bool) $stmt->fetchColumn();
        } catch (Throwable) {
            $exists = false;
        }

        return $exists;
    }

    private static function hasStatusColumn(): bool
    {
        static $has = null;
        if ($has !== null) {
            return $has;
        }
        if (!self::tableExists()) {
            $has = false;
            return false;
        }

        try {
            $pdo = Database::getConnection();
            if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
                $cols = $pdo->query('PRAGMA table_info(approved_leaves)')->fetchAll();
                foreach ($cols as $col) {
                    if (($col['name'] ?? '') === 'status') {
                        $has = true;
                        return true;
                    }
                }
                $has = false;
                return false;
            }

            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
            );
            $stmt->execute(['approved_leaves', 'status']);
            $has = (int) $stmt->fetchColumn() > 0;
        } catch (Throwable) {
            $has = false;
        }

        return $has;
    }
}
