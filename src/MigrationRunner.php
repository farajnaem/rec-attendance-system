<?php

declare(strict_types=1);

class MigrationRunner
{
    private const PHASE = 'phase_1_org_permissions';
    private const PHASE_2 = 'phase_2_gps_borrow';
    private const PHASE_3 = 'phase_3_job_description';
    private const PHASE_4 = 'phase_4_job_title';
    private const PHASE_5 = 'phase_5_sync_permissions';
    private const PHASE_6 = 'phase_6_audit_security';
    private const PHASE_7 = 'phase_7_leaves_workflow';
    private const PHASE_8 = 'phase_8_narrative_docs_replies';
    private const PHASE_9 = 'phase_9_permissions_matrix';
    private const PHASE_10 = 'phase_10_manage_permissions';
    private const PHASE_11 = 'phase_11_supervisor_dept_users';
    private const PHASE_12 = 'phase_12_admin_assistant_view_all';
    private const PHASE_13 = 'phase_13_borrow_users_tab';
    private const PHASE_14 = 'phase_14_supervisor_view_departments';
    private const PHASE_15 = 'phase_15_contracts_docs_breaks';

    public static function ensureLatest(): void
    {
        $pdo = Database::getConnection();
        self::ensureMigrationsTable($pdo);
        self::ensureUsersRoleColumn($pdo);
        if (!self::isApplied($pdo, self::PHASE)) {
            self::runPhase1($pdo);
            self::markApplied($pdo, self::PHASE);
        }
        if (!self::isApplied($pdo, self::PHASE_2)) {
            self::runPhase2($pdo);
            self::markApplied($pdo, self::PHASE_2);
        }
        if (!self::isApplied($pdo, self::PHASE_3)) {
            self::runPhase3($pdo);
            self::markApplied($pdo, self::PHASE_3);
        }
        if (!self::isApplied($pdo, self::PHASE_4)) {
            self::runPhase4($pdo);
            self::markApplied($pdo, self::PHASE_4);
        }
        if (!self::isApplied($pdo, self::PHASE_5)) {
            self::runPhase5($pdo);
            self::markApplied($pdo, self::PHASE_5);
        }
        if (!self::isApplied($pdo, self::PHASE_6)) {
            self::runPhase6($pdo);
            self::markApplied($pdo, self::PHASE_6);
        }
        if (!self::isApplied($pdo, self::PHASE_7)) {
            self::runPhase7($pdo);
            self::markApplied($pdo, self::PHASE_7);
        }
        if (!self::isApplied($pdo, self::PHASE_8)) {
            self::runPhase8($pdo);
            self::markApplied($pdo, self::PHASE_8);
        }
        if (!self::isApplied($pdo, self::PHASE_9)) {
            self::runPhase9($pdo);
            self::markApplied($pdo, self::PHASE_9);
        }
        if (!self::isApplied($pdo, self::PHASE_10)) {
            self::runPhase10($pdo);
            self::markApplied($pdo, self::PHASE_10);
        }
        if (!self::isApplied($pdo, self::PHASE_11)) {
            self::runPhase11($pdo);
            self::markApplied($pdo, self::PHASE_11);
        }
        if (!self::isApplied($pdo, self::PHASE_12)) {
            self::runPhase12($pdo);
            self::markApplied($pdo, self::PHASE_12);
        }
        if (!self::isApplied($pdo, self::PHASE_13)) {
            self::runPhase13($pdo);
            self::markApplied($pdo, self::PHASE_13);
        }
        if (!self::isApplied($pdo, self::PHASE_14)) {
            self::runPhase14($pdo);
            self::markApplied($pdo, self::PHASE_14);
        }
        if (!self::isApplied($pdo, self::PHASE_15)) {
            self::runPhase15($pdo);
            self::markApplied($pdo, self::PHASE_15);
        }
    }

    private static function ensureMigrationsTable(PDO $pdo): void
    {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                version TEXT NOT NULL UNIQUE,
                applied_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )');
        } else {
            $pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                version VARCHAR(64) NOT NULL UNIQUE,
                applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        }
    }

    private static function isApplied(PDO $pdo, string $version): bool
    {
        $stmt = $pdo->prepare('SELECT id FROM schema_migrations WHERE version = ? LIMIT 1');
        $stmt->execute([$version]);
        return (bool) $stmt->fetch();
    }

    private static function markApplied(PDO $pdo, string $version): void
    {
        $stmt = $pdo->prepare('INSERT INTO schema_migrations (version) VALUES (?)');
        $stmt->execute([$version]);
    }

    private static function runPhase1(PDO $pdo): void
    {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $isSqlite = $driver === 'sqlite';

        if ($isSqlite) {
            self::runPhase1Sqlite($pdo);
        } else {
            self::runPhase1Mysql($pdo);
        }

        self::migrateLegacyRoles($pdo);
        self::seedWorkSchedule($pdo);
        self::seedUserPermissions($pdo);
    }

    private static function runPhase1Sqlite(PDO $pdo): void
    {
        $pdo->exec('
            CREATE TABLE IF NOT EXISTS departments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                description TEXT NULL,
                is_active INTEGER NOT NULL DEFAULT 1,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS department_supervisors (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                department_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                is_active INTEGER NOT NULL DEFAULT 1,
                assigned_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            );

            CREATE TABLE IF NOT EXISTS user_departments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                department_id INTEGER NOT NULL,
                assigned_by INTEGER NULL,
                is_current INTEGER NOT NULL DEFAULT 1,
                assigned_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                notes TEXT NULL,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
                FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL
            );

            CREATE TABLE IF NOT EXISTS user_permissions (
                user_id INTEGER NOT NULL,
                permission_code TEXT NOT NULL,
                granted INTEGER NOT NULL DEFAULT 1,
                PRIMARY KEY (user_id, permission_code),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            );

            CREATE TABLE IF NOT EXISTS work_schedule (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                work_start_time TEXT NOT NULL DEFAULT "08:00",
                work_end_time TEXT NOT NULL DEFAULT "16:00",
                late_grace_minutes INTEGER NOT NULL DEFAULT 15,
                work_days TEXT NOT NULL DEFAULT "0,1,2,3,4",
                updated_by INTEGER NULL,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
            );

            CREATE TABLE IF NOT EXISTS work_locations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                latitude REAL NOT NULL,
                longitude REAL NOT NULL,
                radius_meters INTEGER NOT NULL DEFAULT 100,
                is_active INTEGER NOT NULL DEFAULT 1,
                created_by INTEGER NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
            );

            CREATE TABLE IF NOT EXISTS approved_leaves (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                leave_type TEXT NOT NULL CHECK(leave_type IN ("sick","emergency","regular")),
                start_date TEXT NOT NULL,
                end_date TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT "approved" CHECK(status IN ("pending","approved","rejected")),
                approved_by INTEGER NULL,
                notes TEXT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
            );
        ');
    }

    private static function runPhase1Mysql(PDO $pdo): void
    {
        $pdo->exec('
            CREATE TABLE IF NOT EXISTS departments (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(150) NOT NULL,
                description TEXT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS department_supervisors (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                department_id INT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NOT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_ds_dept FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
                CONSTRAINT fk_ds_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS user_departments (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                department_id INT UNSIGNED NOT NULL,
                assigned_by INT UNSIGNED NULL,
                is_current TINYINT(1) NOT NULL DEFAULT 1,
                assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                notes TEXT NULL,
                CONSTRAINT fk_ud_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                CONSTRAINT fk_ud_dept FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
                CONSTRAINT fk_ud_by FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS user_permissions (
                user_id INT UNSIGNED NOT NULL,
                permission_code VARCHAR(64) NOT NULL,
                granted TINYINT(1) NOT NULL DEFAULT 1,
                PRIMARY KEY (user_id, permission_code),
                CONSTRAINT fk_perm_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS work_schedule (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                work_start_time VARCHAR(5) NOT NULL DEFAULT "08:00",
                work_end_time VARCHAR(5) NOT NULL DEFAULT "16:00",
                late_grace_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 15,
                work_days VARCHAR(32) NOT NULL DEFAULT "0,1,2,3,4",
                updated_by INT UNSIGNED NULL,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT fk_ws_user FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS work_locations (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(150) NOT NULL,
                latitude DECIMAL(10,7) NOT NULL,
                longitude DECIMAL(10,7) NOT NULL,
                radius_meters INT UNSIGNED NOT NULL DEFAULT 100,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_by INT UNSIGNED NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_wl_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS approved_leaves (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                leave_type ENUM("sick","emergency","regular") NOT NULL,
                start_date DATE NOT NULL,
                end_date DATE NOT NULL,
                status ENUM("pending","approved","rejected") NOT NULL DEFAULT "approved",
                approved_by INT UNSIGNED NULL,
                notes TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_leave_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                CONSTRAINT fk_leave_approver FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ');
    }

    private static function migrateLegacyRoles(PDO $pdo): void
    {
        self::ensureUsersRoleColumn($pdo);
        $map = [
            'admin' => 'system_admin',
            'manager' => 'program_supervisor',
            'dept_manager' => 'director',
        ];
        foreach ($map as $old => $new) {
            $pdo->prepare('UPDATE users SET role = ? WHERE role = ?')->execute([$new, $old]);
        }
    }

    /** يوسّع عمود role ليدعم الأدوار الجديدة (system_admin, program_supervisor, ...) */
    private static function ensureUsersRoleColumn(PDO $pdo): void
    {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            return;
        }
        try {
            $pdo->query('SELECT 1 FROM users LIMIT 1');
        } catch (Throwable) {
            return;
        }
        try {
            $pdo->exec(
                "ALTER TABLE users MODIFY COLUMN role VARCHAR(32) NOT NULL DEFAULT 'employee'"
            );
        } catch (Throwable) {
            // قد يكون العمود معدّلاً مسبقاً
        }
    }

    private static function seedWorkSchedule(PDO $pdo): void
    {
        $count = (int) $pdo->query('SELECT COUNT(*) FROM work_schedule')->fetchColumn();
        if ($count > 0) {
            return;
        }
        $pdo->exec(
            'INSERT INTO work_schedule (work_start_time, work_end_time, late_grace_minutes, work_days)
             VALUES ("08:00", "16:00", 15, "0,1,2,3,4")'
        );
    }

    private static function seedUserPermissions(PDO $pdo): void
    {
        $users = $pdo->query('SELECT id, role FROM users')->fetchAll();
        foreach ($users as $user) {
            $uid = (int) $user['id'];
            $existing = $pdo->prepare('SELECT COUNT(*) FROM user_permissions WHERE user_id = ?');
            $existing->execute([$uid]);
            if ((int) $existing->fetchColumn() > 0) {
                continue;
            }
            PermissionService::grantDefaults($uid, $user['role']);
        }
    }

    private static function runPhase2(PDO $pdo): void
    {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $isSqlite = $driver === 'sqlite';

        if ($isSqlite) {
            $pdo->exec('
                CREATE TABLE IF NOT EXISTS cross_department_assignments (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NOT NULL,
                    home_department_id INTEGER NOT NULL,
                    target_department_id INTEGER NOT NULL,
                    start_date TEXT NOT NULL,
                    end_date TEXT NULL,
                    notes TEXT NULL,
                    assigned_by INTEGER NULL,
                    ended_by INTEGER NULL,
                    ended_at TEXT NULL,
                    is_active INTEGER NOT NULL DEFAULT 1,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    FOREIGN KEY (home_department_id) REFERENCES departments(id) ON DELETE CASCADE,
                    FOREIGN KEY (target_department_id) REFERENCES departments(id) ON DELETE CASCADE
                );
            ');
            self::addColumnIfMissing($pdo, 'work_locations', 'address', 'TEXT NULL');
            self::addColumnIfMissing($pdo, 'attendance_records', 'latitude', 'REAL NULL');
            self::addColumnIfMissing($pdo, 'attendance_records', 'longitude', 'REAL NULL');
            self::addColumnIfMissing($pdo, 'attendance_records', 'work_location_id', 'INTEGER NULL');
            self::addColumnIfMissing($pdo, 'attendance_records', 'work_location_name', 'TEXT NULL');
        } else {
            $pdo->exec('
                CREATE TABLE IF NOT EXISTS cross_department_assignments (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    user_id INT UNSIGNED NOT NULL,
                    home_department_id INT UNSIGNED NOT NULL,
                    target_department_id INT UNSIGNED NOT NULL,
                    start_date DATE NOT NULL,
                    end_date DATE NULL,
                    notes TEXT NULL,
                    assigned_by INT UNSIGNED NULL,
                    ended_by INT UNSIGNED NULL,
                    ended_at DATETIME NULL,
                    is_active TINYINT(1) NOT NULL DEFAULT 1,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    CONSTRAINT fk_cda_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    CONSTRAINT fk_cda_home FOREIGN KEY (home_department_id) REFERENCES departments(id) ON DELETE CASCADE,
                    CONSTRAINT fk_cda_target FOREIGN KEY (target_department_id) REFERENCES departments(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ');
            self::addColumnIfMissing($pdo, 'work_locations', 'address', 'VARCHAR(255) NULL');
            self::addColumnIfMissing($pdo, 'attendance_records', 'latitude', 'DECIMAL(10,7) NULL');
            self::addColumnIfMissing($pdo, 'attendance_records', 'longitude', 'DECIMAL(10,7) NULL');
            self::addColumnIfMissing($pdo, 'attendance_records', 'work_location_id', 'INT UNSIGNED NULL');
            self::addColumnIfMissing($pdo, 'attendance_records', 'work_location_name', 'VARCHAR(150) NULL');
        }

        self::grantPhase2Permissions($pdo);
    }

    private static function addColumnIfMissing(PDO $pdo, string $table, string $column, string $definition): void
    {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $cols = $pdo->query("PRAGMA table_info($table)")->fetchAll();
            foreach ($cols as $col) {
                if (($col['name'] ?? '') === $column) {
                    return;
                }
            }
            $pdo->exec("ALTER TABLE $table ADD COLUMN $column $definition");
            return;
        }
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $stmt->execute([$table, $column]);
        if ((int) $stmt->fetchColumn() === 0) {
            $pdo->exec("ALTER TABLE $table ADD COLUMN $column $definition");
        }
    }

    private static function grantPhase2Permissions(PDO $pdo): void
    {
        $users = $pdo->query('SELECT id, role FROM users')->fetchAll();
        foreach ($users as $user) {
            $uid = (int) $user['id'];
            foreach (PermissionService::defaultCodesForRole($user['role']) as $code) {
                $exists = $pdo->prepare(
                    'SELECT granted FROM user_permissions WHERE user_id = ? AND permission_code = ?'
                );
                $exists->execute([$uid, $code]);
                if (!$exists->fetch()) {
                    $pdo->prepare(
                        'INSERT INTO user_permissions (user_id, permission_code, granted) VALUES (?, ?, 1)'
                    )->execute([$uid, $code]);
                }
            }
        }
    }

    private static function runPhase3(PDO $pdo): void
    {
        $isSqlite = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';

        if ($isSqlite) {
            $pdo->exec('
                CREATE TABLE IF NOT EXISTS job_description_duties (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NOT NULL,
                    title TEXT NOT NULL,
                    description TEXT NULL,
                    sort_order INTEGER NOT NULL DEFAULT 0,
                    created_by INTEGER NULL,
                    is_active INTEGER NOT NULL DEFAULT 1,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
                );
            ');
        } else {
            $pdo->exec('
                CREATE TABLE IF NOT EXISTS job_description_duties (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    user_id INT UNSIGNED NOT NULL,
                    title VARCHAR(255) NOT NULL,
                    description TEXT NULL,
                    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
                    created_by INT UNSIGNED NULL,
                    is_active TINYINT(1) NOT NULL DEFAULT 1,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    CONSTRAINT fk_jd_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    CONSTRAINT fk_jd_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
                    KEY idx_jd_user (user_id, is_active)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ');
        }

        self::syncJobDescriptionPermissions($pdo);
    }

    private static function syncJobDescriptionPermissions(PDO $pdo): void
    {
        $remove = $pdo->query(
            'SELECT id FROM users WHERE role IN ("program_supervisor", "manager")'
        )->fetchAll();
        foreach ($remove as $row) {
            $pdo->prepare('DELETE FROM user_permissions WHERE user_id = ? AND permission_code = ?')
                ->execute([(int) $row['id'], 'manage_job_description']);
        }

        $grantRoles = ['director', 'admin_assistant', 'dept_manager'];
        foreach ($grantRoles as $role) {
            $users = $pdo->prepare('SELECT id FROM users WHERE role = ?');
            $users->execute([$role]);
            foreach ($users->fetchAll() as $u) {
                $uid = (int) $u['id'];
                $exists = $pdo->prepare(
                    'SELECT granted FROM user_permissions WHERE user_id = ? AND permission_code = ?'
                );
                $exists->execute([$uid, 'manage_job_description']);
                if (!$exists->fetch()) {
                    $pdo->prepare(
                        'INSERT INTO user_permissions (user_id, permission_code, granted) VALUES (?, ?, 1)'
                    )->execute([$uid, 'manage_job_description']);
                }
            }
        }
    }

    private static function runPhase4(PDO $pdo): void
    {
        $isSqlite = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';

        if ($isSqlite) {
            $pdo->exec('
                CREATE TABLE IF NOT EXISTS job_description_profiles (
                    user_id INTEGER NOT NULL UNIQUE,
                    job_title TEXT NOT NULL,
                    updated_by INTEGER NULL,
                    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
                );
            ');
        } else {
            $pdo->exec('
                CREATE TABLE IF NOT EXISTS job_description_profiles (
                    user_id INT UNSIGNED NOT NULL PRIMARY KEY,
                    job_title VARCHAR(150) NOT NULL,
                    updated_by INT UNSIGNED NULL,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    CONSTRAINT fk_jdp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    CONSTRAINT fk_jdp_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ');
        }
    }

    private static function runPhase5(PDO $pdo): void
    {
        $users = $pdo->query('SELECT id, role FROM users')->fetchAll();
        foreach ($users as $user) {
            PermissionService::syncMissingDefaults((int) $user['id'], (string) $user['role']);
        }
    }

    private static function runPhase6(PDO $pdo): void
    {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $pdo->exec('CREATE TABLE IF NOT EXISTS audit_log (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NULL,
                action TEXT NOT NULL,
                entity_type TEXT NULL,
                entity_id INTEGER NULL,
                details TEXT NULL,
                ip_address TEXT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )');
            $pdo->exec('CREATE TABLE IF NOT EXISTS login_attempts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                attempt_key TEXT NOT NULL UNIQUE,
                attempts INTEGER NOT NULL DEFAULT 0,
                locked_until TEXT NULL,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )');
        } else {
            $pdo->exec('CREATE TABLE IF NOT EXISTS audit_log (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NULL,
                action VARCHAR(64) NOT NULL,
                entity_type VARCHAR(64) NULL,
                entity_id INT UNSIGNED NULL,
                details JSON NULL,
                ip_address VARCHAR(45) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_audit_created (created_at),
                CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
            $pdo->exec('CREATE TABLE IF NOT EXISTS login_attempts (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                attempt_key VARCHAR(190) NOT NULL UNIQUE,
                attempts INT UNSIGNED NOT NULL DEFAULT 0,
                locked_until DATETIME NULL,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        }
    }

    /**
     * خوادم قديمة: phase_1 مُطبَّق قبل إضافة approved_leaves أو عمود status.
     */
    private static function runPhase7(PDO $pdo): void
    {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $pdo->exec('CREATE TABLE IF NOT EXISTS approved_leaves (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                leave_type TEXT NOT NULL CHECK(leave_type IN ("sick","emergency","regular")),
                start_date TEXT NOT NULL,
                end_date TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT "approved" CHECK(status IN ("pending","approved","rejected")),
                approved_by INTEGER NULL,
                notes TEXT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
            )');
            self::addColumnIfMissing($pdo, 'approved_leaves', 'status', 'TEXT NOT NULL DEFAULT "approved"');
            self::addColumnIfMissing($pdo, 'approved_leaves', 'approved_by', 'INTEGER NULL');
            self::addColumnIfMissing($pdo, 'approved_leaves', 'notes', 'TEXT NULL');
            self::addColumnIfMissing($pdo, 'approved_leaves', 'created_at', 'TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP');
            return;
        }

        $pdo->exec('CREATE TABLE IF NOT EXISTS approved_leaves (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            leave_type ENUM("sick","emergency","regular") NOT NULL,
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            status ENUM("pending","approved","rejected") NOT NULL DEFAULT "approved",
            approved_by INT UNSIGNED NULL,
            notes TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_leave_user_v7 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_leave_approver_v7 FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

        self::addColumnIfMissing(
            $pdo,
            'approved_leaves',
            'status',
            "ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved'"
        );
        self::addColumnIfMissing($pdo, 'approved_leaves', 'approved_by', 'INT UNSIGNED NULL');
        self::addColumnIfMissing($pdo, 'approved_leaves', 'notes', 'TEXT NULL');
        self::addColumnIfMissing($pdo, 'approved_leaves', 'created_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
    }

    private static function runPhase8(PDO $pdo): void
    {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $isSqlite = $driver === 'sqlite';

        if ($isSqlite) {
            $pdo->exec('CREATE TABLE IF NOT EXISTS monthly_narrative_reports (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                year INTEGER NOT NULL,
                month INTEGER NOT NULL,
                work_summary TEXT NULL,
                positives TEXT NULL,
                negatives TEXT NULL,
                development_notes TEXT NULL,
                submitted_at TEXT NULL,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(user_id, year, month),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )');
            $pdo->exec('CREATE TABLE IF NOT EXISTS task_replies (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                task_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                message TEXT NOT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (task_id) REFERENCES daily_tasks(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )');
            $pdo->exec('CREATE TABLE IF NOT EXISTS documents (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                original_filename TEXT NOT NULL,
                stored_filename TEXT NOT NULL,
                mime_type TEXT NOT NULL,
                file_size INTEGER NOT NULL DEFAULT 0,
                uploaded_by INTEGER NOT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
            )');
            self::addColumnIfMissing($pdo, 'work_schedule', 'report_submission_days', 'INTEGER NOT NULL DEFAULT 5');
        } else {
            $pdo->exec('CREATE TABLE IF NOT EXISTS monthly_narrative_reports (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                year SMALLINT UNSIGNED NOT NULL,
                month TINYINT UNSIGNED NOT NULL,
                work_summary TEXT NULL,
                positives TEXT NULL,
                negatives TEXT NULL,
                development_notes TEXT NULL,
                submitted_at DATETIME NULL,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_narrative_user_month (user_id, year, month),
                CONSTRAINT fk_narrative_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
            $pdo->exec('CREATE TABLE IF NOT EXISTS task_replies (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                task_id INT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NOT NULL,
                message TEXT NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_reply_task FOREIGN KEY (task_id) REFERENCES daily_tasks(id) ON DELETE CASCADE,
                CONSTRAINT fk_reply_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
            $pdo->exec('CREATE TABLE IF NOT EXISTS documents (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                original_filename VARCHAR(255) NOT NULL,
                stored_filename VARCHAR(255) NOT NULL,
                mime_type VARCHAR(120) NOT NULL,
                file_size INT UNSIGNED NOT NULL DEFAULT 0,
                uploaded_by INT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_doc_uploader FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
            self::addColumnIfMissing($pdo, 'work_schedule', 'report_submission_days', 'INT UNSIGNED NOT NULL DEFAULT 5');
        }

        self::grantPhase2Permissions($pdo);
    }

    /** مزامنة الصلاحيات الافتراضية الجديدة مع الموظفين الحاليين */
    private static function runPhase9(PDO $pdo): void
    {
        $users = $pdo->query('SELECT id, role FROM users')->fetchAll();
        foreach ($users as $user) {
            PermissionService::syncMissingDefaults((int) $user['id'], (string) $user['role']);
        }
    }

    private static function runPhase10(PDO $pdo): void
    {
        $users = $pdo->query('SELECT id, role FROM users')->fetchAll();
        foreach ($users as $user) {
            PermissionService::syncMissingDefaults((int) $user['id'], (string) $user['role']);
        }
    }

    /** صلاحيات محدودة للمشرف على موظفي دائرته فقط */
    private static function runPhase11(PDO $pdo): void
    {
        $supervisors = $pdo->query(
            'SELECT id FROM users WHERE role IN ("program_supervisor", "manager")'
        )->fetchAll();
        foreach ($supervisors as $row) {
            $pdo->prepare('DELETE FROM user_permissions WHERE user_id = ? AND permission_code = ?')
                ->execute([(int) $row['id'], 'manage_users']);
        }

        $users = $pdo->query('SELECT id, role FROM users')->fetchAll();
        foreach ($users as $user) {
            PermissionService::syncMissingDefaults((int) $user['id'], (string) $user['role']);
        }
    }

    /** مشاهدة جميع الموظفين + تحميل المستندات للمساعد الإداري */
    private static function runPhase12(PDO $pdo): void
    {
        $assistants = $pdo->query(
            'SELECT id FROM users WHERE role = "admin_assistant"'
        )->fetchAll();
        foreach ($assistants as $row) {
            $uid = (int) $row['id'];
            foreach (['view_all_users', 'view_documents', 'manage_documents'] as $code) {
                $exists = $pdo->prepare(
                    'SELECT granted FROM user_permissions WHERE user_id = ? AND permission_code = ?'
                );
                $exists->execute([$uid, $code]);
                if (!$exists->fetch()) {
                    $pdo->prepare(
                        'INSERT INTO user_permissions (user_id, permission_code, granted) VALUES (?, ?, 1)'
                    )->execute([$uid, $code]);
                }
            }
        }

        $users = $pdo->query('SELECT id, role FROM users')->fetchAll();
        foreach ($users as $user) {
            PermissionService::syncMissingDefaults((int) $user['id'], (string) $user['role']);
        }
    }

    /** الاستعارة في تبويب الموظفين + صلاحيات المساعد الإداري */
    private static function runPhase13(PDO $pdo): void
    {
        $roles = ['director', 'program_supervisor', 'admin_assistant', 'manager'];
        foreach ($roles as $role) {
            $users = $pdo->prepare('SELECT id FROM users WHERE role = ?');
            $users->execute([$role]);
            foreach ($users->fetchAll() as $row) {
                $uid = (int) $row['id'];
                foreach (['borrow_employee', 'view_all_users', 'view_documents'] as $code) {
                    if ($role === 'director' && $code === 'view_all_users') {
                        continue;
                    }
                    if (in_array($role, ['program_supervisor', 'manager'], true) && $code === 'view_all_users') {
                        continue;
                    }
                    $exists = $pdo->prepare(
                        'SELECT granted FROM user_permissions WHERE user_id = ? AND permission_code = ?'
                    );
                    $exists->execute([$uid, $code]);
                    if (!$exists->fetch()) {
                        $pdo->prepare(
                            'INSERT INTO user_permissions (user_id, permission_code, granted) VALUES (?, ?, 1)'
                        )->execute([$uid, $code]);
                    }
                }
            }
        }

        $users = $pdo->query('SELECT id, role FROM users')->fetchAll();
        foreach ($users as $user) {
            PermissionService::syncMissingDefaults((int) $user['id'], (string) $user['role']);
        }
    }

    /** مشرف الدائرة: اطلاع فقط على الدوائر بدون إدارة */
    private static function runPhase14(PDO $pdo): void
    {
        $supervisors = $pdo->query(
            'SELECT id FROM users WHERE role IN ("program_supervisor", "manager")'
        )->fetchAll();
        foreach ($supervisors as $row) {
            $uid = (int) $row['id'];
            $pdo->prepare('DELETE FROM user_permissions WHERE user_id = ? AND permission_code = ?')
                ->execute([$uid, 'manage_departments']);
        }

        $users = $pdo->query('SELECT id, role FROM users')->fetchAll();
        foreach ($users as $user) {
            PermissionService::syncMissingDefaults((int) $user['id'], (string) $user['role']);
        }
    }

    private static function runPhase15(PDO $pdo): void
    {
        $isSqlite = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';

        self::addColumnIfMissing($pdo, 'users', 'contract_end_date', $isSqlite ? 'TEXT NULL' : 'DATE NULL');
        self::addColumnIfMissing(
            $pdo,
            'work_schedule',
            'contract_freeze_grace_days',
            $isSqlite ? 'INTEGER NOT NULL DEFAULT 30' : 'INT UNSIGNED NOT NULL DEFAULT 30'
        );
        self::addColumnIfMissing($pdo, 'monthly_narrative_reports', 'difficulties', 'TEXT NULL');
        self::addColumnIfMissing(
            $pdo,
            'documents',
            'owner_user_id',
            $isSqlite ? 'INTEGER NULL' : 'INT UNSIGNED NULL'
        );
        self::addColumnIfMissing(
            $pdo,
            'documents',
            'category',
            $isSqlite ? 'TEXT NOT NULL DEFAULT "other"' : "VARCHAR(32) NOT NULL DEFAULT 'other'"
        );

        if ($isSqlite) {
            $pdo->exec('CREATE TABLE IF NOT EXISTS work_day_breaks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                work_date TEXT NOT NULL,
                exit_time TEXT NOT NULL,
                return_time TEXT NOT NULL,
                authorized_by INTEGER NOT NULL,
                notes TEXT NULL,
                status TEXT NOT NULL DEFAULT "pending",
                reviewed_by INTEGER NULL,
                reviewed_at TEXT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (authorized_by) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
            )');
        } else {
            $pdo->exec('CREATE TABLE IF NOT EXISTS work_day_breaks (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                work_date DATE NOT NULL,
                exit_time TIME NOT NULL,
                return_time TIME NOT NULL,
                authorized_by INT UNSIGNED NOT NULL,
                notes TEXT NULL,
                status ENUM("pending","approved","rejected") NOT NULL DEFAULT "pending",
                reviewed_by INT UNSIGNED NULL,
                reviewed_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_wdb_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                CONSTRAINT fk_wdb_auth FOREIGN KEY (authorized_by) REFERENCES users(id) ON DELETE CASCADE,
                CONSTRAINT fk_wdb_rev FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        }

        $pdo->exec('UPDATE documents SET owner_user_id = uploaded_by WHERE owner_user_id IS NULL');

        $users = $pdo->query('SELECT id, role FROM users')->fetchAll();
        foreach ($users as $user) {
            PermissionService::syncMissingDefaults((int) $user['id'], (string) $user['role']);
        }

        ContractService::enforceAllExpired();
    }
}
