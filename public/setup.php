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

require dirname(__DIR__) . '/src/bootstrap.php';
rec_load_core();

if (rec_is_installed() && !($config['app']['setup_enabled'] ?? false)) {
    http_response_code(403);
    exit('النظام مُثبَّت مسبقاً. صفحة الإعداد معطّلة.');
}

if (!($config['app']['setup_enabled'] ?? false)) {
    http_response_code(403);
    exit('صفحة الإعداد معطّلة. فعّل SETUP_ENABLED=true لإنشاء حساب المسؤول الأول.');
}

require dirname(__DIR__) . '/src/Csrf.php';

$error = null;
$success = null;

if (!empty($_SESSION['flash']['error'])) {
    $error = (string) $_SESSION['flash']['error'];
    unset($_SESSION['flash']['error']);
}

try {
    $pdo = Database::getConnection();
    if (envBool('RUN_MIGRATIONS_ON_REQUEST', false)) {
        MigrationRunner::ensureLatest();
    }
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
    if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
        $error = 'انتهت صلاحية النموذج. أعد المحاولة.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim(strtolower($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';

        if ($name === '' || $email === '' || strlen($password) < passwordMinLength()) {
            $error = 'يرجى تعبئة جميع الحقول. كلمة المرور ' . passwordMinLength() . ' أحرف على الأقل.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $pdo->prepare(
                'INSERT INTO users (name, email, password_hash, role, timezone) VALUES (?, ?, ?, ?, ?)'
            )->execute([$name, $email, $hash, 'system_admin', $config['app']['default_timezone'] ?? 'Asia/Riyadh']);
            $adminId = (int) $pdo->lastInsertId();
            PermissionService::grantDefaults($adminId, 'system_admin');
            rec_mark_installed();
            $success = true;
        }
    }
}

$loginUrl = rtrim($config['app']['url'] ?? '', '/') . '/login';
$appName = htmlspecialchars($config['app']['name'] ?? 'REC', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إعداد النظام — REC</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/shell.css">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="page-login">
<div class="login-page">
    <div class="login-panel">
        <div class="login-panel__brand">
            <div class="login-logo" aria-hidden="true">REC</div>
            <h1 class="login-panel__title"><?= $appName ?></h1>
            <p class="login-panel__subtitle">إعداد لمرة واحدة — إنشاء مسؤول النظام</p>
        </div>

        <div class="login-card" style="max-width:480px">
            <div class="login-card__header">
                <h2>إعداد النظام</h2>
                <p>إنشاء حساب مسؤول النظام الأول</p>
            </div>

        <?php if ($error): ?>
            <div class="login-alert login-alert--error" role="alert">
                <span class="login-alert__icon" aria-hidden="true">!</span>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
            <?php if ($config['app']['debug'] ?? false): ?>
                <pre style="font-size:0.8rem;overflow:auto"><?= htmlspecialchars(json_encode(testDatabaseConnection(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="login-alert login-alert--success" role="alert">
                <span class="login-alert__icon" aria-hidden="true">✓</span>
                <span>تم إنشاء حساب مسؤول النظام. <strong>عطّل SETUP_ENABLED فوراً.</strong></span>
            </div>
            <a href="<?= htmlspecialchars($loginUrl) ?>" class="btn btn-login" style="display:block;text-align:center;text-decoration:none">الذهاب لتسجيل الدخول</a>
        <?php elseif ($userCount > 0): ?>
            <div class="login-alert login-alert--success" role="alert">
                <span class="login-alert__icon" aria-hidden="true">✓</span>
                <span>النظام مُثبَّت مسبقاً (<?= $userCount ?> مستخدم). عطّل SETUP_ENABLED.</span>
            </div>
            <a href="<?= htmlspecialchars($loginUrl) ?>" class="btn btn-login" style="display:block;text-align:center;text-decoration:none">تسجيل الدخول</a>
        <?php else: ?>
            <form method="post" class="login-form">
                <?= Csrf::field() ?>
                <div class="form-group">
                    <label>اسم مسؤول النظام</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>البريد الإلكتروني</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>كلمة المرور (<?= passwordMinLength() ?> أحرف على الأقل)</label>
                    <div class="password-field">
                        <input type="password" name="password" class="form-control" minlength="<?= passwordMinLength() ?>" required data-password-input>
                        <button type="button" class="password-field__toggle" data-password-toggle aria-label="إظهار كلمة المرور">👁</button>
                    </div>
                </div>
                <button type="submit" class="btn btn-login">إنشاء حساب المسؤول</button>
            </form>
        <?php endif; ?>
        </div>

        <p class="login-footer">جمعية مركز الإرشاد التربوي — جميع الحقوق محفوظة</p>
    </div>
</div>
<script src="/assets/js/ui.js"></script>
</body>
</html>
