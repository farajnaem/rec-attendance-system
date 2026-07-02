<?php

declare(strict_types=1);

class DocumentService
{
    public const CATEGORIES = [
        'contract' => 'عقد العمل',
        'report' => 'تقارير',
        'identity' => 'هوية / وثائق شخصية',
        'certificate' => 'شهادات',
        'other' => 'أخرى',
    ];

    private const ALLOWED = [
        'application/pdf' => 'pdf',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/msword' => 'doc',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    private const MAX_BYTES = 10 * 1024 * 1024;

    public static function storageDir(): string
    {
        $dir = dirname(__DIR__) . '/storage/documents';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return $dir;
    }

    public static function canAccess(int $actorId, string $actorRole, int $ownerUserId): bool
    {
        if ($actorId === $ownerUserId) {
            return true;
        }
        if (PermissionService::can($actorId, 'manage_employee_documents')
            || PermissionService::can($actorId, 'view_employee_documents')) {
            return ScopeService::canViewUser($actorId, $actorRole, $ownerUserId);
        }
        if (PermissionService::can($actorId, 'manage_documents')
            || PermissionService::can($actorId, 'view_documents')) {
            return ScopeService::canViewUser($actorId, $actorRole, $ownerUserId);
        }

        return false;
    }

    public static function canManage(int $actorId, string $actorRole, int $ownerUserId): bool
    {
        if ($actorId === $ownerUserId) {
            return PermissionService::can($actorId, 'manage_employee_documents');
        }
        if (PermissionService::can($actorId, 'manage_employee_documents')) {
            return ScopeService::canViewUser($actorId, $actorRole, $ownerUserId);
        }

        return PermissionService::can($actorId, 'manage_documents')
            && ScopeService::canViewUser($actorId, $actorRole, $ownerUserId);
    }

    /** موظفون لديهم حافظة مستندات (للاختيار) */
    public static function employeesForHub(int $actorId, string $actorRole): array
    {
        if (RoleHelper::isEmployee($actorRole)) {
            $self = UserService::getById($actorId);
            return $self ? [$self] : [];
        }
        $users = ScopeService::visibleUsers($actorId, $actorRole);
        return array_values(array_filter($users, static fn (array $u): bool => RoleHelper::isEmployee($u['role'])));
    }

    public static function listForEmployee(int $ownerUserId): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT d.*, u.name AS uploader_name
             FROM documents d
             JOIN users u ON u.id = d.uploaded_by
             WHERE d.owner_user_id = ?
             ORDER BY d.category, d.created_at DESC'
        );
        $stmt->execute([$ownerUserId]);

        return $stmt->fetchAll();
    }

    public static function countForEmployee(int $ownerUserId): int
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM documents WHERE owner_user_id = ?');
        $stmt->execute([$ownerUserId]);

        return (int) $stmt->fetchColumn();
    }

    public static function get(int $id): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM documents WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    public static function upload(
        int $uploaderId,
        int $ownerUserId,
        string $category,
        string $title,
        array $file
    ): int {
        if (!isset(self::CATEGORIES[$category])) {
            throw new InvalidArgumentException('تصنيف المستند غير صالح.');
        }
        $title = trim($title);
        if ($title === '') {
            throw new InvalidArgumentException('عنوان المستند مطلوب.');
        }
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('فشل رفع الملف.');
        }
        if (($file['size'] ?? 0) > self::MAX_BYTES) {
            throw new RuntimeException('حجم الملف يتجاوز 10 ميغابايت.');
        }

        $mime = mime_content_type($file['tmp_name']) ?: ($file['type'] ?? '');
        if (!isset(self::ALLOWED[$mime])) {
            throw new InvalidArgumentException('نوع الملف غير مدعوم. المسموح: PDF، Excel، Word، صور.');
        }

        $ext = self::ALLOWED[$mime];
        $stored = bin2hex(random_bytes(16)) . '.' . $ext;
        $dest = self::storageDir() . DIRECTORY_SEPARATOR . $stored;
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            throw new RuntimeException('تعذّر حفظ الملف على الخادم.');
        }

        $pdo = Database::getConnection();
        $pdo->prepare(
            'INSERT INTO documents
             (title, original_filename, stored_filename, mime_type, file_size, uploaded_by, owner_user_id, category)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $title,
            (string) ($file['name'] ?? $stored),
            $stored,
            $mime,
            (int) ($file['size'] ?? 0),
            $uploaderId,
            $ownerUserId,
            $category,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function delete(int $id, int $actorId, string $actorRole): void
    {
        $doc = self::get($id);
        if (!$doc) {
            throw new RuntimeException('المستند غير موجود.');
        }
        $ownerId = (int) ($doc['owner_user_id'] ?? $doc['uploaded_by']);
        if (!self::canManage($actorId, $actorRole, $ownerId)
            && !(RoleHelper::isSystemAdmin($actorRole) && (int) $doc['uploaded_by'] === $actorId)) {
            throw new RuntimeException('لا يمكنك حذف هذا المستند.');
        }

        $path = self::storageDir() . DIRECTORY_SEPARATOR . $doc['stored_filename'];
        if (is_file($path)) {
            unlink($path);
        }

        Database::getConnection()->prepare('DELETE FROM documents WHERE id = ?')->execute([$id]);
    }

    public static function filePath(array $doc): string
    {
        return self::storageDir() . DIRECTORY_SEPARATOR . $doc['stored_filename'];
    }

    public static function categoryLabel(string $code): string
    {
        return self::CATEGORIES[$code] ?? $code;
    }
}
