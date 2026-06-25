<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title ?? config('app.name')) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(asset('/assets/css/shell.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('/assets/css/app.css')) ?>">
</head>
<body class="<?= ($page ?? '') === 'login' ? 'page-login' : (Auth::check() ? 'app-shell' : '') ?>">
<?php if (Auth::check() && ($page ?? '') !== 'login'): ?>
<div class="rd-app" data-rd-nav>
    <div class="rd-sidebar-backdrop" data-rd-nav-backdrop aria-hidden="true"></div>

    <aside class="rd-sidebar" data-rd-nav-panel id="rd-sidebar">
        <a class="rd-sidebar-brand" href="<?= e(url(RoleHelper::dashboardPath(Auth::role()))) ?>">
            <span class="rd-sidebar-brand-mark">REC</span>
            <span>
                <div class="rd-sidebar-brand-title"><?= e(config('app.name')) ?></div>
                <div class="rd-sidebar-brand-tagline">نظام الحضور والمهام</div>
            </span>
        </a>

        <?php require __DIR__ . '/partials/sidebar_nav.php'; ?>
    </aside>

    <div class="rd-main">
        <header class="rd-topbar no-print">
            <div class="rd-topbar-start">
                <button
                    type="button"
                    class="rd-menu-btn"
                    data-rd-nav-toggle
                    aria-controls="rd-sidebar"
                    aria-expanded="false"
                    aria-label="فتح القائمة"
                >
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
            </div>
            <div class="rd-topbar-meta">
                <?php require __DIR__ . '/partials/user_menu.php'; ?>
            </div>
        </header>

        <main class="rd-content">
            <?php if ($success = flash('success')): ?>
                <div class="alert alert-success"><?= e($success) ?></div>
            <?php endif; ?>
            <?php if ($error = flash('error')): ?>
                <div class="alert alert-error"><?= e($error) ?></div>
            <?php endif; ?>

            <?php require __DIR__ . '/partials/breadcrumbs.php'; ?>

            <?php require __DIR__ . '/' . ($name ?? 'login') . '.php'; ?>
        </main>
    </div>
</div>
<script src="<?= e(asset('/assets/js/nav-toggle.js')) ?>"></script>
<script src="<?= e(asset('/assets/js/ui.js')) ?>"></script>
<?php elseif (($page ?? '') === 'login'): ?>
    <?php require __DIR__ . '/' . ($name ?? 'login') . '.php'; ?>
<?php else: ?>
<div class="container">
    <?php if ($success = flash('success')): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>
    <?php if ($error = flash('error')): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <?php require __DIR__ . '/' . ($name ?? 'login') . '.php'; ?>
</div>
<?php endif; ?>

<?php if (!empty($loadSignature)): ?>
<script src="<?= e(asset('/assets/js/signature.js')) ?>"></script>
<?php endif; ?>
</body>
</html>
