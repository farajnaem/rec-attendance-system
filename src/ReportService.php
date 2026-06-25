<?php

declare(strict_types=1);

class ReportService
{
    public static function workingDaysInMonth(int $year, int $month): int
    {
        return WorkScheduleService::workingDaysInMonth($year, $month);
    }

    public static function monthRange(int $year, int $month): array
    {
        $start = sprintf('%04d-%02d-01', $year, $month);
        $end = (new DateTimeImmutable($start))->modify('last day of this month')->format('Y-m-d');
        return [$start, $end];
    }

    public static function attendanceReport(int $userId, int $year, int $month): array
    {
        [$start, $end] = self::monthRange($year, $month);
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare(
            'SELECT local_work_date, type, signed_at_utc, timezone FROM attendance_records
             WHERE user_id = ? AND local_work_date BETWEEN ? AND ?'
        );
        $stmt->execute([$userId, $start, $end]);
        $rows = $stmt->fetchAll();

        $byDate = [];
        $lateByDate = [];
        foreach ($rows as $row) {
            $byDate[$row['local_work_date']][$row['type']] = $row;
            if ($row['type'] === 'check_in') {
                $localDt = TimezoneHelper::toLocal($row['signed_at_utc'], $row['timezone'])->format('Y-m-d H:i:s');
                $lateByDate[$row['local_work_date']] = WorkScheduleService::isLateCheckIn($localDt);
            }
        }

        $daily = [];
        $fullDays = 0;
        $cursor = new DateTimeImmutable($start);
        $last = new DateTimeImmutable($end);
        while ($cursor <= $last) {
            $date = $cursor->format('Y-m-d');
            $isWorkday = WorkScheduleService::isWorkDay($cursor);
            $onLeave = LeaveService::isOnLeave($userId, $date);
            $hasIn = isset($byDate[$date]['check_in']);
            $hasOut = isset($byDate[$date]['check_out']);
            $complete = $hasIn && $hasOut;

            if ($isWorkday && !$onLeave && $complete) {
                $fullDays++;
            }

            $daily[] = [
                'date' => $date,
                'is_workday' => $isWorkday,
                'on_leave' => $onLeave,
                'check_in' => $hasIn,
                'check_out' => $hasOut,
                'complete' => $complete,
                'late' => $hasIn && ($lateByDate[$date] ?? false),
            ];
            $cursor = $cursor->modify('+1 day');
        }

        $expected = self::workingDaysInMonth($year, $month);
        $attendanceScore = $expected > 0 ? round(($fullDays / $expected) * 100, 1) : 0;

        return [
            'daily' => $daily,
            'full_days' => $fullDays,
            'expected_workdays' => $expected,
            'attendance_score' => $attendanceScore,
        ];
    }

    public static function performanceReport(int $userId, int $year, int $month): array
    {
        [$start, $end] = self::monthRange($year, $month);
        $tasks = TaskService::forEmployee($userId, $start, $end);

        $scores = [];
        foreach ($tasks as $t) {
            if ($t['score'] !== null) {
                $scores[] = (int) $t['score'];
            }
        }

        $avg = count($scores) > 0 ? array_sum($scores) / count($scores) : null;
        $performanceScore = $avg !== null ? round(($avg / 10) * 100, 1) : null;

        return [
            'tasks' => $tasks,
            'evaluated_count' => count($scores),
            'average_score' => $avg !== null ? round($avg, 1) : null,
            'performance_score' => $performanceScore,
        ];
    }

    public static function fullReport(int $userId, int $year, int $month): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT id, name, email, timezone, role FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        if (!$user) {
            throw new RuntimeException('المستخدم غير موجود.');
        }

        return [
            'user' => $user,
            'year' => $year,
            'month' => $month,
            'attendance' => self::attendanceReport($userId, $year, $month),
            'performance' => self::performanceReport($userId, $year, $month),
        ];
    }

    public static function dashboardStats(int $actorId, string $actorRole, string $date): array
    {
        $staff = ScopeService::visibleStaff($actorId, $actorRole);
        $total = count($staff);
        $present = 0;
        $complete = 0;
        $late = 0;
        $pdo = Database::getConnection();

        foreach ($staff as $member) {
            $uid = (int) $member['id'];
            if (LeaveService::isOnLeave($uid, $date)) {
                continue;
            }
            $stmt = $pdo->prepare(
                'SELECT type, signed_at_utc, timezone FROM attendance_records
                 WHERE user_id = ? AND local_work_date = ?'
            );
            $stmt->execute([$uid, $date]);
            $records = $stmt->fetchAll();
            $hasIn = false;
            foreach ($records as $r) {
                if ($r['type'] === 'check_in') {
                    $hasIn = true;
                    $localDt = TimezoneHelper::toLocal($r['signed_at_utc'], $r['timezone'])->format('Y-m-d H:i:s');
                    if (WorkScheduleService::isLateCheckIn($localDt)) {
                        $late++;
                    }
                }
            }
            if ($hasIn) {
                $present++;
            }
            $hasOut = false;
            foreach ($records as $r) {
                if ($r['type'] === 'check_out') {
                    $hasOut = true;
                }
            }
            if ($hasIn && $hasOut) {
                $complete++;
            }
        }

        $pendingTasks = (int) $pdo->query(
            "SELECT COUNT(*) FROM daily_tasks WHERE status = 'pending'"
        )->fetchColumn();

        return [
            'total_employees' => $total,
            'present_today' => $present,
            'absent_today' => max(0, $total - $present),
            'complete_today' => $complete,
            'late_today' => $late,
            'pending_tasks' => $pendingTasks,
        ];
    }
}
