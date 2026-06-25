<?php $page = 'login'; ?>
<div class="login-page">
    <div class="login-panel">
        <div class="login-panel__brand">
            <div class="login-logo" aria-hidden="true">REC</div>
            <h1 class="login-panel__title"><?= e(config('app.name')) ?></h1>
            <p class="login-panel__subtitle">نظام إدارة الحضور والمهام اليومية</p>
        </div>

        <div class="login-card">
            <div class="login-card__header">
                <h2>تسجيل الدخول</h2>
                <p>أدخل بيانات حسابك للمتابعة</p>
            </div>

            <?php if ($error = flash('error')): ?>
                <div class="login-alert login-alert--error" role="alert">
                    <span class="login-alert__icon" aria-hidden="true">!</span>
                    <span><?= e($error) ?></span>
                </div>
            <?php endif; ?>

            <?php if ($success = flash('success')): ?>
                <div class="login-alert login-alert--success" role="alert">
                    <span class="login-alert__icon" aria-hidden="true">✓</span>
                    <span><?= e($success) ?></span>
                </div>
            <?php endif; ?>

            <form method="post" action="<?= e(url('/login')) ?>" class="login-form">
                <?= Csrf::field() ?>

                <div class="form-group">
                    <label for="email">البريد الإلكتروني</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        class="form-control"
                        placeholder="name@example.com"
                        autocomplete="email"
                        required
                        autofocus
                    >
                </div>

                <div class="form-group">
                    <label for="password">كلمة المرور</label>
                    <div class="password-field">
                        <input
                            type="password"
                            id="password"
                            name="password"
                            class="form-control"
                            placeholder="••••••••"
                            autocomplete="current-password"
                            required
                            data-password-input
                        >
                        <button type="button" class="password-field__toggle" data-password-toggle aria-label="إظهار كلمة المرور">👁</button>
                    </div>
                </div>

                <button type="submit" class="btn btn-login">دخول إلى النظام</button>
            </form>

            <?php if (!empty($setupEnabled)): ?>
                <p class="text-center text-muted" style="margin-top:1rem;font-size:0.9rem">
                    أول مرة؟ <a href="<?= e(url('/setup.php')) ?>" style="color:var(--primary);font-weight:600">إنشاء حساب مسؤول النظام</a>
                </p>
            <?php endif; ?>
        </div>

        <p class="login-footer">جمعية مركز الإرشاد التربوي — جميع الحقوق محفوظة</p>
    </div>
</div>
