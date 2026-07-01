<?php

declare(strict_types=1);

class NarrativeReportService
{
    public static function get(int $userId, int $year, int $month): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT * FROM monthly_narrative_reports WHERE user_id = ? AND year = ? AND month = ? LIMIT 1'
        );
        $stmt->execute([$userId, $year, $month]);

        return $stmt->fetch() ?: null;
    }

    public static function save(
        int $userId,
        int $year,
        int $month,
        string $workSummary,
        string $positives,
        string $negatives,
        string $developmentNotes,
        string $difficulties,
        bool $submit = false
    ): void {
        if (!self::canEdit($year, $month)) {
            throw new RuntimeException('انتهت مهلة تقديم التقرير السردي لهذا الشهر.');
        }

        $pdo = Database::getConnection();
        $existing = self::get($userId, $year, $month);
        $submittedAt = $submit ? date('Y-m-d H:i:s') : ($existing['submitted_at'] ?? null);

        if ($existing) {
            $pdo->prepare(
                'UPDATE monthly_narrative_reports
                 SET work_summary = ?, positives = ?, negatives = ?, development_notes = ?,
                     difficulties = ?, submitted_at = ?, updated_at = CURRENT_TIMESTAMP
                 WHERE id = ?'
            )->execute([
                self::clean($workSummary),
                self::clean($positives),
                self::clean($negatives),
                self::clean($developmentNotes),
                self::clean($difficulties),
                $submittedAt,
                $existing['id'],
            ]);
        } else {
            $pdo->prepare(
                'INSERT INTO monthly_narrative_reports
                 (user_id, year, month, work_summary, positives, negatives, development_notes, difficulties, submitted_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $userId,
                $year,
                $month,
                self::clean($workSummary),
                self::clean($positives),
                self::clean($negatives),
                self::clean($developmentNotes),
                self::clean($difficulties),
                $submittedAt,
            ]);
        }
    }

    public static function canView(int $actorId, string $actorRole, int $subjectUserId): bool
    {
        if ($actorId === $subjectUserId) {
            return PermissionService::can($actorId, 'monthly_self_report');
        }
        if (!PermissionService::can($actorId, 'view_narrative_reports')) {
            return false;
        }

        return ScopeService::canViewUser($actorId, $actorRole, $subjectUserId);
    }

    public static function listSubmittedForReviewer(int $actorId, string $actorRole, int $year, int $month): array
    {
        if (!PermissionService::can($actorId, 'view_narrative_reports')) {
            return [];
        }
        $staff = ScopeService::visibleStaff($actorId, $actorRole);
        if ($staff === []) {
            return [];
        }
        $ids = array_column($staff, 'id');
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $pdo = Database::getConnection();
        $params = array_merge($ids, [$year, $month]);
        $stmt = $pdo->prepare(
            "SELECT n.*, u.name AS user_name
             FROM monthly_narrative_reports n
             JOIN users u ON u.id = n.user_id
             WHERE n.user_id IN ($ph) AND n.year = ? AND n.month = ? AND n.submitted_at IS NOT NULL
             ORDER BY u.name"
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function submissionDays(): int
    {
        $schedule = WorkScheduleService::get();

        return max(1, min(31, (int) ($schedule['report_submission_days'] ?? 5)));
    }

    public static function deadline(int $year, int $month): DateTimeImmutable
    {
        $lastDay = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        $lastDay = $lastDay->modify('last day of this month');
        $days = self::submissionDays();

        return $lastDay->modify("+{$days} days");
    }

    public static function canEdit(int $year, int $month): bool
    {
        $monthStart = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        $today = new DateTimeImmutable('today');
        if ($monthStart > $today) {
            return false;
        }

        return $today <= self::deadline($year, $month);
    }

    public static function deadlineLabel(int $year, int $month): string
    {
        return self::deadline($year, $month)->format('Y-m-d');
    }

    private static function clean(string $text): ?string
    {
        $text = trim($text);

        return $text === '' ? null : $text;
    }
}
