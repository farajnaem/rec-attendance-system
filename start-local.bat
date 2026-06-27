@echo off
chcp 65001 >nul
title نظام حضور REC — محلي
cd /d "%~dp0"

where php >nul 2>&1
if errorlevel 1 (
    echo [خطأ] PHP غير مثبت أو غير موجود في PATH.
    echo ثبّت PHP 8.1+ من https://windows.php.net/download/
    pause
    exit /b 1
)

if not exist ".env" (
    echo [معلومة] إنشاء ملف .env من الإعدادات المحلية...
    (
        echo DB_DRIVER=sqlite
        echo DB_SQLITE_PATH=database/attendance.sqlite
        echo APP_URL=http://localhost:8080
        echo APP_DEBUG=true
        echo SETUP_ENABLED=true
        echo RUN_MIGRATIONS_ON_REQUEST=true
        echo APP_NAME=جمعية مركز الإرشاد التربوي REC
        echo APP_TIMEZONE=Asia/Riyadh
    ) > .env
)

echo [معلومة] تثبيت/التحقق من قاعدة البيانات...
php database\install.php
if errorlevel 1 (
    echo [خطأ] فشل تثبيت قاعدة البيانات.
    pause
    exit /b 1
)

echo.
echo ========================================
echo   نظام حضور REC يعمل على:
echo   http://localhost:8080
echo.
echo   الإعداد الأول: http://localhost:8080/setup.php
echo   تسجيل الدخول:  http://localhost:8080/login
echo ========================================
echo.
echo اضغط Ctrl+C لإيقاف الخادم
echo.

start "" "http://localhost:8080/setup.php"
php -S localhost:8080 -t public public/router.php
