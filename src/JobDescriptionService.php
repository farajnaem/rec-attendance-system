<?php

declare(strict_types=1);

class JobDescriptionService
{
    public static function assertCanManage(int $actorId, string $actorRole): void
    {
        if (!PermissionService::can($actorId, 'manage_job_description')) {
            throw new RuntimeException('لا تملك صلاحية إدخال التوصيف الوظيفي.');
        }
    }

    public static function assertCanManageUser(int $actorId, string $actorRole, int $targetUserId): void
    {
        self::assertCanManage($actorId, $actorRole);
        $role = RoleHelper::normalizeRole($actorRole);
        if ($role === 'program_supervisor'
            && !ScopeService::canManageDepartmentUser($actorId, $actorRole, $targetUserId)) {
            throw new RuntimeException('لا يمكنك إدارة توصيف هذا الموظف.');
        }
        $user = UserService::getById($targetUserId);
        if (!$user || (int) $user['is_active'] !== 1) {
            throw new RuntimeException('المستخدم غير موجود أو غير نشط.');
        }
    }

    public static function manageableUsers(int $actorId, string $actorRole): array
    {
        $pdo = Database::getConnection();
        $role = RoleHelper::normalizeRole($actorRole);

        if ($role === 'program_supervisor') {
            $users = ScopeService::visibleUsers($actorId, $actorRole);
            if ($users === []) {
                return [];
            }
            $ids = array_map(static fn (array $u): int => (int) $u['id'], $users);
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare(
                "SELECT u.id, u.name, u.email, u.role,
                        p.job_title,
                        p.duties_body,
                        (SELECT COUNT(*) FROM job_description_duties jd
                         WHERE jd.user_id = u.id AND jd.is_active = 1) AS duty_count
                 FROM users u
                 LEFT JOIN job_description_profiles p ON p.user_id = u.id
                 WHERE u.is_active = 1 AND u.id IN ($ph)
                 ORDER BY u.name"
            );
            $stmt->execute($ids);

            return $stmt->fetchAll();
        }

        return $pdo->query(
            'SELECT u.id, u.name, u.email, u.role,
                    p.job_title,
                    p.duties_body,
                    (SELECT COUNT(*) FROM job_description_duties jd
                     WHERE jd.user_id = u.id AND jd.is_active = 1) AS duty_count
             FROM users u
             LEFT JOIN job_description_profiles p ON p.user_id = u.id
             WHERE u.is_active = 1
             ORDER BY u.name'
        )->fetchAll();
    }

    public static function getProfile(int $userId): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM job_description_profiles WHERE user_id = ?');
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function getDutiesBodyText(int $userId): string
    {
        $profile = self::getProfile($userId);
        if ($profile && trim((string) ($profile['duties_body'] ?? '')) !== '') {
            return (string) $profile['duties_body'];
        }

        $tasks = self::tasksForUser($userId);
        if ($tasks === []) {
            return '';
        }

        return implode("\n", array_map(static fn (array $t): string => trim((string) $t['title']), $tasks));
    }

    public static function hasDutiesContent(int $userId): bool
    {
        return trim(self::getDutiesBodyText($userId)) !== '';
    }

    public static function saveProfile(int $userId, string $jobTitle, string $dutiesBody, int $updatedBy): void
    {
        self::setJobTitle($userId, $jobTitle, $updatedBy);
        self::setDutiesBody($userId, $dutiesBody, $updatedBy);
    }

    public static function setJobTitle(int $userId, string $jobTitle, int $updatedBy): void
    {
        $jobTitle = trim($jobTitle);
        if ($jobTitle === '') {
            throw new InvalidArgumentException('المسمى الوظيفي مطلوب.');
        }

        $pdo = Database::getConnection();
        $existing = self::getProfile($userId);
        if ($existing) {
            $pdo->prepare(
                'UPDATE job_description_profiles SET job_title = ?, updated_by = ? WHERE user_id = ?'
            )->execute([$jobTitle, $updatedBy, $userId]);
        } else {
            $pdo->prepare(
                'INSERT INTO job_description_profiles (user_id, job_title, duties_body, updated_by) VALUES (?, ?, ?, ?)'
            )->execute([$userId, $jobTitle, '', $updatedBy]);
        }
    }

    public static function setDutiesBody(int $userId, string $dutiesBody, int $updatedBy): void
    {
        $dutiesBody = trim(str_replace(["\r\n", "\r"], "\n", $dutiesBody));
        $pdo = Database::getConnection();
        $existing = self::getProfile($userId);
        if ($existing) {
            $pdo->prepare(
                'UPDATE job_description_profiles SET duties_body = ?, updated_by = ? WHERE user_id = ?'
            )->execute([$dutiesBody, $updatedBy, $userId]);
        } else {
            throw new InvalidArgumentException('احفظ المسمى الوظيفي أولاً.');
        }

        $pdo->prepare('UPDATE job_description_duties SET is_active = 0 WHERE user_id = ?')->execute([$userId]);
        if ($dutiesBody === '') {
            return;
        }

        $lines = preg_split('/\n+/', $dutiesBody) ?: [];
        $sort = 1;
        $insert = $pdo->prepare(
            'INSERT INTO job_description_duties (user_id, title, description, sort_order, created_by, is_active)
             VALUES (?, ?, NULL, ?, ?, 1)'
        );
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $insert->execute([$userId, $line, $sort++, $updatedBy]);
        }
    }

    /** المسمى الوظيفي + الوصف/التبعية للعرض عند الموظف */
    public static function fullForUser(int $userId): array
    {
        $profile = self::getProfile($userId);
        $body = self::getDutiesBodyText($userId);

        return [
            'job_title' => $profile['job_title'] ?? null,
            'duties_body' => $body,
            'tasks' => self::tasksForUser($userId),
        ];
    }

    public static function tasksForUser(int $userId, bool $activeOnly = true): array
    {
        $pdo = Database::getConnection();
        $sql = 'SELECT id, user_id, title, sort_order FROM job_description_duties WHERE user_id = ?';
        if ($activeOnly) {
            $sql .= ' AND is_active = 1';
        }
        $sql .= ' ORDER BY sort_order ASC, id ASC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public static function getById(int $id): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM job_description_duties WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @deprecated استخدم saveProfile */
    public static function createTask(int $userId, string $title, int $createdBy): int
    {
        $body = self::getDutiesBodyText($userId);
        $body = trim($body . "\n" . trim($title));
        self::setDutiesBody($userId, $body, $createdBy);

        return 0;
    }

    /** @deprecated */
    public static function updateTask(int $id, string $title): void
    {
        $duty = self::getById($id);
        if (!$duty || (int) $duty['is_active'] !== 1) {
            throw new RuntimeException('المهمة غير موجودة.');
        }
        $pdo = Database::getConnection();
        $pdo->prepare('UPDATE job_description_duties SET title = ? WHERE id = ?')->execute([trim($title), $id]);
    }

    /** @deprecated */
    public static function deleteTask(int $id): void
    {
        $duty = self::getById($id);
        if (!$duty) {
            throw new RuntimeException('المهمة غير موجودة.');
        }
        $pdo = Database::getConnection();
        $pdo->prepare('UPDATE job_description_duties SET is_active = 0 WHERE id = ?')->execute([$id]);
    }
}
