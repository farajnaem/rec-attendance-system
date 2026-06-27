<?php

declare(strict_types=1);

class UserService
{
    public static function listForAdmin(): array
    {
        $pdo = Database::getConnection();
        return $pdo->query(
            'SELECT u.*, m.name AS manager_name, d.name AS department_name
             FROM users u
             LEFT JOIN users m ON m.id = u.manager_id
             LEFT JOIN user_departments ud ON ud.user_id = u.id AND ud.is_current = 1
             LEFT JOIN departments d ON d.id = ud.department_id
             ORDER BY u.role, u.name'
        )->fetchAll();
    }

    public static function listForManager(int $managerId): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT u.*, m.name AS manager_name, d.name AS department_name
             FROM users u
             LEFT JOIN users m ON m.id = u.manager_id
             LEFT JOIN user_departments ud ON ud.user_id = u.id AND ud.is_current = 1
             LEFT JOIN departments d ON d.id = ud.department_id
             WHERE u.manager_id = ? AND u.role = "employee"
             ORDER BY u.name'
        );
        $stmt->execute([$managerId]);
        return $stmt->fetchAll();
    }

    public static function getById(int $id): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function supervisors(): array
    {
        $pdo = Database::getConnection();
        $roles = RoleHelper::supervisorRoles();
        $placeholders = implode(',', array_fill(0, count($roles), '?'));
        $stmt = $pdo->prepare(
            "SELECT id, name, role FROM users WHERE role IN ($placeholders) AND is_active = 1 ORDER BY name"
        );
        $stmt->execute($roles);
        return $stmt->fetchAll();
    }

    public static function create(
        string $name,
        string $email,
        string $password,
        string $role,
        string $timezone,
        ?int $managerId,
        ?int $departmentId = null,
        ?array $permissions = null
    ): int {
        $name = trim($name);
        $email = trim(strtolower($email));
        $role = RoleHelper::normalizeRole($role);

        if ($name === '' || $email === '' || strlen($password) < passwordMinLength()) {
            throw new InvalidArgumentException('يرجى تعبئة جميع الحقول. كلمة المرور ' . passwordMinLength() . ' أحرف على الأقل.');
        }
        if (!RoleHelper::isValid($role)) {
            throw new InvalidArgumentException('الدور غير صالح.');
        }
        if (!TimezoneHelper::isValid($timezone)) {
            throw new InvalidArgumentException('المنطقة الزمنية غير صالحة.');
        }
        if ($role === 'employee' && !$managerId && !$departmentId) {
            throw new InvalidArgumentException('يجب تعيين دائرة أو مشرف للموظف.');
        }

        $pdo = Database::getConnection();
        $exists = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $exists->execute([$email]);
        if ($exists->fetch()) {
            throw new RuntimeException('البريد الإلكتروني مستخدم مسبقاً.');
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO users (name, email, password_hash, role, timezone, manager_id)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $name,
                $email,
                $hash,
                $role,
                $timezone,
                $role === 'employee' ? $managerId : null,
            ]);

            $userId = (int) $pdo->lastInsertId();

            if ($departmentId) {
                DepartmentService::assignUser($userId, $departmentId, Auth::id() ?: $userId);
            }

            if ($permissions !== null) {
                PermissionService::setForUser($userId, $permissions);
            } else {
                PermissionService::grantDefaults($userId, $role);
            }

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        return $userId;
    }

    public static function assertCanEdit(int $userId, int $actorId, string $actorRole): void
    {
        if (!ScopeService::canViewUser($actorId, $actorRole, $userId)) {
            throw new RuntimeException('لا يمكنك تعديل هذا المستخدم.');
        }
    }

    public static function update(
        int $userId,
        string $name,
        string $email,
        string $timezone,
        string $role,
        ?int $managerId,
        ?int $departmentId,
        ?string $newPassword,
        int $actorId,
        string $actorRole,
        bool $canChangeRole
    ): void {
        self::assertCanEdit($userId, $actorId, $actorRole);

        $name = trim($name);
        $email = trim(strtolower($email));
        $role = RoleHelper::normalizeRole($role);

        if ($name === '' || $email === '') {
            throw new InvalidArgumentException('الاسم والبريد مطلوبان.');
        }
        if (!TimezoneHelper::isValid($timezone)) {
            throw new InvalidArgumentException('المنطقة الزمنية غير صالحة.');
        }
        if (!$canChangeRole) {
            $existing = self::getById($userId);
            $role = RoleHelper::normalizeRole($existing['role']);
        } elseif (!RoleHelper::isValid($role)) {
            throw new InvalidArgumentException('الدور غير صالح.');
        }

        $pdo = Database::getConnection();
        $dup = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
        $dup->execute([$email, $userId]);
        if ($dup->fetch()) {
            throw new RuntimeException('البريد الإلكتروني مستخدم من مستخدم آخر.');
        }

        $managerId = $role === 'employee' ? $managerId : null;

        if ($newPassword !== null && $newPassword !== '') {
            if (strlen($newPassword) < passwordMinLength()) {
                throw new InvalidArgumentException('كلمة المرور ' . passwordMinLength() . ' أحرف على الأقل.');
            }
            $hash = password_hash($newPassword, PASSWORD_BCRYPT);
            $pdo->prepare(
                'UPDATE users SET name=?, email=?, timezone=?, role=?, manager_id=?, password_hash=? WHERE id=?'
            )->execute([$name, $email, $timezone, $role, $managerId, $hash, $userId]);
        } else {
            $pdo->prepare(
                'UPDATE users SET name=?, email=?, timezone=?, role=?, manager_id=? WHERE id=?'
            )->execute([$name, $email, $timezone, $role, $managerId, $userId]);
        }

        if ($departmentId) {
            $current = DepartmentService::currentForUser($userId);
            if (!$current || (int) $current['id'] !== $departmentId) {
                DepartmentService::assignUser($userId, $departmentId, $actorId);
            }
        }

        if ($userId === $actorId) {
            $_SESSION['user_name'] = $name;
            $_SESSION['user_email'] = $email;
            $_SESSION['user_timezone'] = $timezone;
            $_SESSION['user_role'] = $role;
        }
    }

    public static function updatePermissions(int $userId, array $permissions, int $actorId, string $actorRole): void
    {
        if (!RoleHelper::canEditPermissions($actorRole)) {
            throw new RuntimeException('لا يمكنك تعديل الصلاحيات — متاح للمدير ومدير النظام فقط.');
        }
        $user = self::getById($userId);
        if (!$user) {
            throw new RuntimeException('المستخدم غير موجود.');
        }
        PermissionService::setForUser($userId, $permissions);
    }

    public static function transferDepartment(
        int $userId,
        int $departmentId,
        int $actorId,
        string $actorRole
    ): void {
        if (!PermissionService::can($actorId, 'transfer_employee') && !RoleHelper::isOrgAdmin($actorRole)) {
            throw new RuntimeException('لا يمكنك نقل الموظف بين الدوائر.');
        }
        if (!ScopeService::canViewUser($actorId, $actorRole, $userId)) {
            throw new RuntimeException('لا يمكنك نقل هذا الموظف.');
        }
        DepartmentService::transferUser($userId, $departmentId, $actorId);
    }

    public static function changePassword(int $userId, string $current, string $new): void
    {
        $user = self::getById($userId);
        if (!$user || !password_verify($current, (string) $user['password_hash'])) {
            throw new RuntimeException('كلمة المرور الحالية غير صحيحة.');
        }
        if (strlen($new) < passwordMinLength()) {
            throw new InvalidArgumentException('كلمة المرور الجديدة يجب أن تكون ' . passwordMinLength() . ' أحرف على الأقل.');
        }

        $pdo = Database::getConnection();
        $hash = password_hash($new, PASSWORD_BCRYPT);
        $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$hash, $userId]);
        AuditService::log('password.change', 'user', $userId);
    }

    public static function toggleActive(int $userId, int $actorId, string $actorRole): void
    {
        if ($userId === $actorId) {
            throw new RuntimeException('لا يمكنك تعطيل حسابك.');
        }
        self::assertCanEdit($userId, $actorId, $actorRole);

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        if (!$user) {
            throw new RuntimeException('المستخدم غير موجود.');
        }

        $actorRoleNorm = RoleHelper::normalizeRole($actorRole);
        if (!RoleHelper::isOrgAdmin($actorRoleNorm)
            && RoleHelper::isManagement($user['role'])
        ) {
            throw new RuntimeException('لا يمكنك تعطيل مستخدم إداري.');
        }

        $newStatus = (int) $user['is_active'] === 1 ? 0 : 1;
        $pdo->prepare('UPDATE users SET is_active = ? WHERE id = ?')->execute([$newStatus, $userId]);
    }

    public static function delete(int $userId, int $actorId, string $actorRole): void
    {
        if (RoleHelper::normalizeRole($actorRole) !== 'system_admin') {
            throw new RuntimeException('حذف المستخدمين متاح لمدير النظام فقط.');
        }
        if ($userId === $actorId) {
            throw new RuntimeException('لا يمكنك حذف حسابك.');
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        if (!$user) {
            throw new RuntimeException('المستخدم غير موجود.');
        }

        if (RoleHelper::normalizeRole($user['role']) === 'system_admin') {
            $adminCount = (int) $pdo->query(
                'SELECT COUNT(*) FROM users WHERE role = "system_admin" AND is_active = 1'
            )->fetchColumn();
            if ($adminCount <= 1) {
                throw new RuntimeException('لا يمكن حذف آخر مدير نظام نشط.');
            }
        }

        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
    }
}
