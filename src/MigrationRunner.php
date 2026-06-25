<?php

declare(strict_types=1);

class MigrationRunner
{
    private const PHASE = 'phase_1_org_permissions';
    private const PHASE_2 = 'phase_2_gps_borrow';
    private const PHASE_3 = 'phase_3_job_description';
    private const PHASE_4 = 'phase_4_job_title';

    public static function ensureLatest(): void
    {
        $pdo = Database::getConnection();
        self::ensureMigrationsTable($pdo);
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
        $map = [
            'admin' => 'system_admin',
            'manager' => 'program_supervisor',
            'dept_manager' => 'director',
        ];
        foreach ($map as $old => $new) {
            $pdo->prepare('UPDATE users SET role = ? WHERE role = ?')->execute([$new, $old]);
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
}
