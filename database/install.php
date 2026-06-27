<?php

declare(strict_types=1);

/**
 * تثبيت محلي (SQLite) — للتطوير فقط.
 * الإنتاج يستخدم Docker + MySQL.
 */

require dirname(__DIR__) . '/src/bootstrap.php';
rec_load_core();

if ((getenv('DB_DRIVER') ?: 'mysql') !== 'sqlite') {
    echo "install.php للتطوير المحلي بـ SQLite فقط.\n";
    echo "للإنتاج استخدم Docker: docker compose up -d\n";
    exit(1);
}

$pdo = Database::getConnection();

$tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'")->fetch();
if ($tables) {
    MigrationRunner::ensureLatest();
    echo "قاعدة البيانات جاهزة.\n";
    exit(0);
}

$pdo->exec('
CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT "employee",
    timezone TEXT NOT NULL DEFAULT "Asia/Riyadh",
    manager_id INTEGER NULL,
    is_active INTEGER NOT NULL DEFAULT 1,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE attendance_records (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    type TEXT NOT NULL CHECK(type IN ("check_in","check_out")),
    signed_at_utc TEXT NOT NULL,
    local_work_date TEXT NOT NULL,
    timezone TEXT NOT NULL,
    signature_data TEXT NOT NULL,
    ip_address TEXT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE(user_id, local_work_date, type)
);

CREATE TABLE daily_tasks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    employee_id INTEGER NOT NULL,
    assigned_by INTEGER NOT NULL,
    title TEXT NOT NULL,
    description TEXT NULL,
    task_date TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT "pending" CHECK(status IN ("pending","completed","evaluated")),
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE task_completions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    task_id INTEGER NOT NULL UNIQUE,
    completed_by INTEGER NOT NULL,
    completed_at_utc TEXT NOT NULL,
    completed_at_local TEXT NOT NULL,
    notes TEXT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES daily_tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (completed_by) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE task_evaluations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    task_id INTEGER NOT NULL UNIQUE,
    evaluated_by INTEGER NOT NULL,
    score INTEGER NOT NULL CHECK(score BETWEEN 1 AND 10),
    notes TEXT NULL,
    evaluated_at_utc TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES daily_tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (evaluated_by) REFERENCES users(id) ON DELETE CASCADE
);
');

echo "تم إنشاء قاعدة البيانات.\n";

MigrationRunner::ensureLatest();
echo "تم تطبيق التحديثات.\n";
echo "فعّل SETUP_ENABLED=true ثم افتح /setup.php لإنشاء حساب المسؤول.\n";
