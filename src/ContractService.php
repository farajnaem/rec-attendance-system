<?php

declare(strict_types=1);

class ContractService
{
    public static function freezeGraceDays(): int
    {
        $schedule = WorkScheduleService::get();

        return max(0, min(365, (int) ($schedule['contract_freeze_grace_days'] ?? 30)));
    }

    public static function freezeDeadline(?string $contractEndDate): ?DateTimeImmutable
    {
        if ($contractEndDate === null || trim($contractEndDate) === '') {
            return null;
        }
        try {
            $end = new DateTimeImmutable($contractEndDate);
        } catch (Throwable) {
            return null;
        }
        $grace = self::freezeGraceDays();

        return $end->modify("+{$grace} days");
    }

    public static function isPastFreezeDeadline(array $user): bool
    {
        $deadline = self::freezeDeadline($user['contract_end_date'] ?? null);
        if (!$deadline) {
            return false;
        }

        return new DateTimeImmutable('today') > $deadline;
    }

    public static function enforceForUser(int $userId): void
    {
        $user = UserService::getById($userId);
        if (!$user || (int) $user['is_active'] !== 1) {
            return;
        }
        if (!self::isPastFreezeDeadline($user)) {
            return;
        }
        if (RoleHelper::normalizeRole((string) $user['role']) === 'system_admin') {
            return;
        }

        $pdo = Database::getConnection();
        $pdo->prepare('UPDATE users SET is_active = 0 WHERE id = ?')->execute([$userId]);
        AuditService::log('contract.freeze', 'user', $userId, [
            'contract_end' => $user['contract_end_date'],
            'grace_days' => self::freezeGraceDays(),
        ]);
    }

    public static function enforceAllExpired(): int
    {
        $pdo = Database::getConnection();
        $rows = $pdo->query(
            'SELECT id FROM users WHERE is_active = 1 AND contract_end_date IS NOT NULL AND contract_end_date != ""'
        )->fetchAll();
        $count = 0;
        foreach ($rows as $row) {
            $uid = (int) $row['id'];
            $before = UserService::getById($uid);
            self::enforceForUser($uid);
            $after = UserService::getById($uid);
            if ($before && $after && (int) $before['is_active'] === 1 && (int) $after['is_active'] === 0) {
                $count++;
            }
        }

        return $count;
    }
}
