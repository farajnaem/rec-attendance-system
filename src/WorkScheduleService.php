<?php

declare(strict_types=1);

class WorkScheduleService
{
    public static function get(): array
    {
        $pdo = Database::getConnection();
        $row = $pdo->query('SELECT * FROM work_schedule ORDER BY id DESC LIMIT 1')->fetch();
        if (!$row) {
            return [
                'work_start_time' => '08:00',
                'work_end_time' => '16:00',
                'late_grace_minutes' => 15,
                'work_days' => '0,1,2,3,4',
            ];
        }
        return $row;
    }

    public static function update(
        string $startTime,
        string $endTime,
        int $graceMinutes,
        string $workDays,
        int $updatedBy
    ): void {
        if (!preg_match('/^\d{2}:\d{2}$/', $startTime) || !preg_match('/^\d{2}:\d{2}$/', $endTime)) {
            throw new InvalidArgumentException('صيغة الوقت غير صحيحة (HH:MM).');
        }
        if ($graceMinutes < 0 || $graceMinutes > 120) {
            throw new InvalidArgumentException('مهلة التأخير يجب أن تكون بين 0 و 120 دقيقة.');
        }
        $pdo = Database::getConnection();
        $existing = $pdo->query('SELECT id FROM work_schedule ORDER BY id DESC LIMIT 1')->fetch();
        if ($existing) {
            $pdo->prepare(
                'UPDATE work_schedule SET work_start_time=?, work_end_time=?, late_grace_minutes=?,
                 work_days=?, updated_by=?, updated_at=CURRENT_TIMESTAMP WHERE id=?'
            )->execute([$startTime, $endTime, $graceMinutes, $workDays, $updatedBy, $existing['id']]);
        } else {
            $pdo->prepare(
                'INSERT INTO work_schedule (work_start_time, work_end_time, late_grace_minutes, work_days, updated_by)
                 VALUES (?, ?, ?, ?, ?)'
            )->execute([$startTime, $endTime, $graceMinutes, $workDays, $updatedBy]);
        }
    }

    /**
     * هل وقت الحضور متأخراً؟ (يُستخدم في المراحل القادمة)
     * @param string $localDateTime مثل 2026-06-24 08:45:00
     */
    public static function isLateCheckIn(string $localDateTime): bool
    {
        $schedule = self::get();
        $dt = new DateTime($localDateTime);
        $dayOfWeek = (string) $dt->format('w');
        $workDays = array_map('trim', explode(',', $schedule['work_days'] ?? '0,1,2,3,4'));
        if (!in_array($dayOfWeek, $workDays, true)) {
            return false;
        }
        $start = DateTime::createFromFormat('Y-m-d H:i', $dt->format('Y-m-d') . ' ' . $schedule['work_start_time']);
        if (!$start) {
            return false;
        }
        $grace = (int) ($schedule['late_grace_minutes'] ?? 0);
        $start->modify("+{$grace} minutes");
        return $dt > $start;
    }

    public static function workDayLabels(): array
    {
        return [
            '0' => 'الأحد',
            '1' => 'الإثنين',
            '2' => 'الثلاثاء',
            '3' => 'الأربعاء',
            '4' => 'الخميس',
            '5' => 'الجمعة',
            '6' => 'السبت',
        ];
    }

    public static function isWorkDay(DateTimeInterface $date): bool
    {
        $schedule = self::get();
        $dayOfWeek = (string) $date->format('w');
        $workDays = array_map('trim', explode(',', $schedule['work_days'] ?? '0,1,2,3,4'));

        return in_array($dayOfWeek, $workDays, true);
    }

    public static function workingDaysInMonth(int $year, int $month): int
    {
        $days = 0;
        $start = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        $end = $start->modify('last day of this month');
        for ($d = $start; $d <= $end; $d = $d->modify('+1 day')) {
            if (self::isWorkDay($d)) {
                $days++;
            }
        }

        return $days;
    }
}
