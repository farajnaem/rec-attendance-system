<?php

declare(strict_types=1);

/**
 * إعداد لمرة واحدة — إنشاء حساب مسؤول النظام الأول.
 * يعمل فقط عند SETUP_ENABLED=true وقبل وجود أي مستخدم.
 */

session_start();

$configPath = dirname(__DIR__) . '/config/config.php';
if (!is_file($configPath)) {
    http_response_code(500);
    exit('ملف config/config.php غير موجود.');
}

$config = require $configPath;

if ($config['app']['debug'] ?? false) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
}

if (!($config['app']['setup_enabled'] ?? false)) {
    http_response_code(403);
    exit('صفحة الإعداد معطّلة. فعّل SETUP_ENABLED=true لإنشاء حساب المسؤول الأول.');
}

require dirname(__DIR__) . '/src/Database.php';
require dirname(__DIR__) . '/src/PermissionService.php';
require dirname(__DIR__) . '/src/MigrationRunner.php';
require dirname(__DIR__) . '/src/RoleHelper.php';
require dirname(__DIR__) . '/src/DbDiagnostics.php';

$error = null;
$success = null;

if (!empty($_SESSION['flash']['error'])) {
    $error = (string) $_SESSION['flash']['error'];
    unset($_SESSION['flash']['error']);
}

try {
    $pdo = Database::getConnection();
    MigrationRunner::ensureLatest();
    $pdo->query('SELECT 1 FROM users LIMIT 1');
} catch (Throwable $e) {
    $error = 'تعذّر الاتصال بقاعدة البيانات. تأكد من DATABASE_URL أو متغيرات DB_*.';
    if ($config['app']['debug'] ?? false) {
        $error .= ' — ' . $e->getMessage();
    }
}

$userCount = 0;
if (!$error) {
    $userCount = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error && $userCount === 0) {
    $name = trim($_POST['name'] ?? '');
    $email = trim(strtolower($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if ($name === '' || $email === '' || strlen($password) < 8) {
        $error = 'يرجى تعبئة جميع الحقول. كلمة المرور 8 أحرف على الأقل.';
    } else {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $pdo->prepare(
            'INSERT INTO users (name, email, password_hash, role, timezone) VALUES (?, ?, ?, ?, ?)'
        )->execute([$name, $email, $hash, 'system_admin', $config['app']['default_timezone'] ?? 'Asia/Riyadh']);
        $adminId = (int) $pdo->lastInsertId();
        PermissionService::grantDefaults($adminId, 'system_admin');
        $success = true;
    }
}

$loginUrl = rtrim($config['app']['url'] ?? '', '/') . '/login';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إعداد النظام — REC</title>
    <link rel="stylesheet" href="/assets/css/app.css?v=2">
</head>
<body>
<div class="login-page">
    <div class="login-card" style="max-width:480px">
        <h1><?= htmlspecialchars($config['app']['name'] ?? 'REC') ?></h1>
        <p class="text-center text-muted">إعداد لمرة واحدة — إنشاء مسؤول النظام</p>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php if ($config['app']['debug'] ?? false): ?>
                <pre style="font-size:0.8rem;overflow:auto"><?= htmlspecialchars(json_encode(testDatabaseConnection(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                تم إنشاء حساب مسؤول النظام بنجاح.<br>
                <strong>عطّل SETUP_ENABLED فوراً (اجعله false).</strong>
            </div>
            <a href="<?= htmlspecialchars($loginUrl) ?>" class="btn" style="width:100%;text-align:center">الذهاب لتسجيل الدخول</a>
        <?php elseif ($userCount > 0): ?>
            <div class="alert alert-success">النظام مُثبَّت مسبقاً (<?= $userCount ?> مستخدم).</div>
            <p class="text-muted">عطّل <code>SETUP_ENABLED</code> لأسباب أمنية.</p>
            <a href="<?= htmlspecialchars($loginUrl) ?>" class="btn" style="width:100%;text-align:center">تسجيل الدخول</a>
        <?php else: ?>
            <form method="post">
                <div class="form-group">
                    <label>اسم مسؤول النظام</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>البريد الإلكتروني</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>كلمة المرور (8 أحرف على الأقل)</label>
                    <input type="password" name="password" class="form-control" minlength="8" required>
                </div>
                <button type="submit" class="btn" style="width:100%">إنشاء حساب المسؤول</button>
            </form>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
