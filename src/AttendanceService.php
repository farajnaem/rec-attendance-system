<?php

declare(strict_types=1);

class AttendanceService
{
    public static function todayStatus(int $userId, string $timezone): array
    {
        $utcNow = TimezoneHelper::utcNow();
        $localDate = TimezoneHelper::localWorkDate($utcNow, $timezone);

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT * FROM attendance_records WHERE user_id = ? AND local_work_date = ? ORDER BY signed_at_utc'
        );
        $stmt->execute([$userId, $localDate]);
        $records = $stmt->fetchAll();

        $checkIn = null;
        $checkOut = null;
        foreach ($records as $r) {
            if ($r['type'] === 'check_in') {
                $checkIn = $r;
            }
            if ($r['type'] === 'check_out') {
                $checkOut = $r;
            }
        }

        return [
            'local_date' => $localDate,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
        ];
    }

    public static function sign(
        int $userId,
        string $type,
        string $signatureData,
        string $timezone,
        string $ip,
        ?float $latitude = null,
        ?float $longitude = null
    ): void {
        if (!in_array($type, ['check_in', 'check_out'], true)) {
            throw new InvalidArgumentException('نوع التوقيع غير صالح.');
        }
        if (strlen($signatureData) < 100) {
            throw new InvalidArgumentException('يرجى التوقيع الإلكتروني قبل الإرسال.');
        }

        $location = LocationService::validateSignCoordinates($latitude, $longitude);

        $utcNow = TimezoneHelper::utcNow();
        $localDate = TimezoneHelper::localWorkDate($utcNow, $timezone);

        $pdo = Database::getConnection();
        $existing = $pdo->prepare(
            'SELECT id FROM attendance_records WHERE user_id = ? AND local_work_date = ? AND type = ?'
        );
        $existing->execute([$userId, $localDate, $type]);
        if ($existing->fetch()) {
            throw new RuntimeException($type === 'check_in' ? 'تم تسجيل الحضور اليوم مسبقاً.' : 'تم تسجيل الانصراف اليوم مسبقاً.');
        }

        if ($type === 'check_out') {
            $in = $pdo->prepare(
                'SELECT id FROM attendance_records WHERE user_id = ? AND local_work_date = ? AND type = ?'
            );
            $in->execute([$userId, $localDate, 'check_in']);
            if (!$in->fetch()) {
                throw new RuntimeException('يجب تسجيل الحضور قبل الانصراف.');
            }
        }

        $stmt = $pdo->prepare(
            'INSERT INTO attendance_records
             (user_id, type, signed_at_utc, local_work_date, timezone, signature_data, ip_address,
              latitude, longitude, work_location_id, work_location_name)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $userId,
            $type,
            $utcNow->format('Y-m-d H:i:s'),
            $localDate,
            $timezone,
            $signatureData,
            $ip,
            $latitude,
            $longitude,
            $location['location_id'],
            $location['location_name'],
        ]);
    }

    public static function recent(int $userId, int $days = 7): array
    {
        $pdo = Database::getConnection();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $stmt = $pdo->prepare(
                'SELECT * FROM attendance_records WHERE user_id = ?
                 AND local_work_date >= date("now", ?)
                 ORDER BY local_work_date DESC, signed_at_utc DESC'
            );
            $stmt->execute([$userId, '-' . $days . ' days']);
        } else {
            $stmt = $pdo->prepare(
                'SELECT * FROM attendance_records WHERE user_id = ?
                 AND local_work_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                 ORDER BY local_work_date DESC, signed_at_utc DESC'
            );
            $stmt->execute([$userId, $days]);
        }

        return $stmt->fetchAll();
    }

    public static function teamAttendanceForActor(int $actorId, string $actorRole, string $date): array
    {
        $staff = ScopeService::visibleStaff($actorId, $actorRole);
        if (empty($staff)) {
            return [];
        }
        $ids = array_column($staff, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $pdo = Database::getConnection();
        $params = array_merge([$date], $ids);
        $stmt = $pdo->prepare(
            "SELECT u.id, u.name, u.timezone, u.role,
                    MAX(CASE WHEN a.type = 'check_in' THEN a.signed_at_utc END) AS check_in_utc,
                    MAX(CASE WHEN a.type = 'check_out' THEN a.signed_at_utc END) AS check_out_utc,
                    MAX(CASE WHEN a.type = 'check_in' THEN a.work_location_name END) AS work_location_name
             FROM users u
             LEFT JOIN attendance_records a ON a.user_id = u.id AND a.local_work_date = ?
             WHERE u.id IN ($placeholders) AND u.is_active = 1
             GROUP BY u.id, u.name, u.timezone, u.role
             ORDER BY u.name"
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
