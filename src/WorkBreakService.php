<?php

declare(strict_types=1);

class WorkBreakService
{
    public static function create(
        int $employeeId,
        string $workDate,
        string $exitTime,
        string $returnTime,
        int $authorizedById,
        ?string $notes = null
    ): int {
        if (!PermissionService::can($employeeId, 'request_work_break')
            && !PermissionService::can($employeeId, 'sign_attendance')) {
            throw new RuntimeException('لا يمكنك تسجيل مغادرة أثناء العمل.');
        }
        $authorized = UserService::getById($authorizedById);
        if (!$authorized || (int) $authorized['is_active'] !== 1) {
            throw new InvalidArgumentException('معطي الإذن غير صالح.');
        }
        if (!self::validTime($exitTime) || !self::validTime($returnTime)) {
            throw new InvalidArgumentException('صيغة الوقت غير صحيحة (HH:MM).');
        }
        if ($returnTime <= $exitTime) {
            throw new InvalidArgumentException('وقت العودة يجب أن يكون بعد وقت الخروج.');
        }

        $pdo = Database::getConnection();
        $pdo->prepare(
            'INSERT INTO work_day_breaks
             (user_id, work_date, exit_time, return_time, authorized_by, notes, status)
             VALUES (?, ?, ?, ?, ?, ?, "pending")'
        )->execute([
            $employeeId,
            $workDate,
            $exitTime,
            $returnTime,
            $authorizedById,
            self::clean($notes),
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function listForUser(int $userId, int $limit = 20): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT b.*, a.name AS authorized_by_name, r.name AS reviewer_name
             FROM work_day_breaks b
             JOIN users a ON a.id = b.authorized_by
             LEFT JOIN users r ON r.id = b.reviewed_by
             WHERE b.user_id = ?
             ORDER BY b.work_date DESC, b.id DESC
             LIMIT ?'
        );
        $stmt->execute([$userId, $limit]);

        return $stmt->fetchAll();
    }

    public static function pendingForReviewer(int $actorId, string $actorRole): array
    {
        if (!PermissionService::can($actorId, 'approve_work_breaks')) {
            return [];
        }
        $staffIds = array_column(ScopeService::visibleStaff($actorId, $actorRole), 'id');
        if ($staffIds === []) {
            return [];
        }
        $ph = implode(',', array_fill(0, count($staffIds), '?'));
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            "SELECT b.*, u.name AS employee_name, a.name AS authorized_by_name
             FROM work_day_breaks b
             JOIN users u ON u.id = b.user_id
             JOIN users a ON a.id = b.authorized_by
             WHERE b.status = 'pending' AND b.user_id IN ($ph)
             ORDER BY b.work_date DESC, b.id DESC"
        );
        $stmt->execute($staffIds);

        return $stmt->fetchAll();
    }

    public static function review(int $breakId, int $reviewerId, string $actorRole, bool $approve): void
    {
        if (!PermissionService::can($reviewerId, 'approve_work_breaks')) {
            throw new RuntimeException('لا يمكنك اعتماد مغادرات أثناء العمل.');
        }
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM work_day_breaks WHERE id = ? LIMIT 1');
        $stmt->execute([$breakId]);
        $row = $stmt->fetch();
        if (!$row || ($row['status'] ?? '') !== 'pending') {
            throw new RuntimeException('الطلب غير موجود أو تمت معالجته.');
        }
        if (!ScopeService::canViewUser($reviewerId, $actorRole, (int) $row['user_id'])) {
            throw new RuntimeException('لا يمكنك معالجة هذا الطلب.');
        }

        $status = $approve ? 'approved' : 'rejected';
        $pdo->prepare(
            'UPDATE work_day_breaks SET status = ?, reviewed_by = ?, reviewed_at = CURRENT_TIMESTAMP WHERE id = ?'
        )->execute([$status, $reviewerId, $breakId]);
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'approved' => 'موافق عليه',
            'rejected' => 'مرفوض',
            default => 'قيد الانتظار',
        };
    }

    private static function validTime(string $time): bool
    {
        return (bool) preg_match('/^\d{2}:\d{2}$/', $time);
    }

    private static function clean(?string $text): ?string
    {
        $text = trim((string) $text);

        return $text === '' ? null : $text;
    }
}
