# نظام حضور ومهام — جمعية مركز الإرشاد التربوي REC

نظام إنتاجي لإدارة حضور الموظفين والمهام اليومية والتقييم الشهري.

## المتطلبات

- Docker + Docker Compose (للإنتاج والتطوير)
- أو PHP 8.1+ مع SQLite (للتطوير المحلي فقط)

## التشغيل السريع (Docker)

```powershell
cd rec-attendance-system
copy .env.example .env
# عدّل .env — خاصة DB_PASS و APP_URL

docker compose up -d --build
```

افتح: http://localhost:8080/setup.php

1. أنشئ حساب مسؤول النظام
2. عطّل `SETUP_ENABLED=false` في `.env` وأعد تشغيل الحاوية
3. سجّل الدخول من `/login`

## متغيرات البيئة

| المتغير | الوصف |
|---------|--------|
| `DATABASE_URL` | رابط MySQL كامل (Coolify) — `mysql://user:pass@host:3306/db` |
| `DB_HOST` | عنوان MySQL (بديل عند عدم وجود DATABASE_URL) |
| `DB_NAME` | اسم قاعدة البيانات |
| `DB_USER` / `DB_PASS` | بيانات الاتصال |
| `APP_URL` | رابط الموقع (https://...) |
| `APP_DEBUG` | `false` في الإنتاج |
| `SETUP_ENABLED` | `true` مؤقتاً لإنشاء المسؤول الأول |

راجع `.env.example` للقائمة الكاملة.

## النشر على Coolify

راجع **COOLIFY-DEPLOY-AR.md** — دليل خطوة بخطوة.

## التطوير المحلي (بدون Docker)

```powershell
copy .env.example .env
# في .env: DB_DRIVER=sqlite و SETUP_ENABLED=true

php database\install.php
$env:PHPRC = "php.local.ini"
php -S localhost:8080 -t public public/router.php
```

ثم افتح `/setup.php` لإنشاء حساب المسؤول.

## الهيكل

```
public/     ← جذر الويب
config/     ← الإعدادات (من متغيرات البيئة)
src/        ← منطق التطبيق
views/      ← القوالب
database/   ← schema.sql + أدوات التثبيت
docker/     ← إعدادات الحاوية
```

## الوثائق

- `PRODUCTION-RELEASE-AR.md` — **إصدار الإنتاج الحالي + قائمة التحقق**
- `COOLIFY-DEPLOY-AR.md` — النشر على Coolify (موصى به)
- `README-DEPLOY.md` — النشر على استضافة PHP تقليدية (Hostinger)

## الميزات الرئيسية

- حضور وانصراف مع GPS وتوقيع رقمي
- مهام يومية وتقييم شهري
- إجازات ومغادرة أثناء العمل
- تقارير سردية ومهلة تقديم
- مستندات الموظفين وانتهاء العقود
- استعارة الموظفين بين الدوائر
- صلاحيات حسب الدور (موظف، مساعد إداري، مشرف، مدير، مدير نظام)
