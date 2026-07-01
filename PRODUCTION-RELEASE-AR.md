# إصدار الإنتاج — نظام حضور REC

دليل رفع الإصدار الحالي إلى GitHub ونشره على Coolify (أو Docker).

---

## ما يتضمنه هذا الإصدار

- **انتهاء العقود** وتجميد الحسابات تلقائياً بعد المهلة
- **مستندات الموظفين** (حافظة لكل موظف + صلاحيات عرض/إدارة)
- **مغادرة أثناء العمل** (Work Break) مع تتبع GPS
- **التقارير السردية** مع مهلة تقديم قابلة للضبط
- **استعارة الموظفين** بين الدوائر (من تبويب الموظفين)
- **صلاحيات موسّعة** للمشرف والمساعد الإداري والمدير
- **إصلاح واجهة إجراءات الموظفين** (قائمة منسدلة + تمرير أفقي)
- **ترحيل قاعدة البيانات** حتى `phase_15_contracts_docs_breaks`

---

## 1. قبل الرفع على GitHub

تأكد أن الملفات التالية **لا تُرفع** (موجودة في `.gitignore`):

| ملف | السبب |
|-----|--------|
| `.env` | أسرار الإنتاج |
| `database/attendance.sqlite` | بيانات محلية |
| `database/export.json` | تصدير حساس |
| `storage/*` | مستندات مرفوعة |

---

## 2. متغيرات الإنتاج (Coolify)

```env
DATABASE_URL=mysql://mysql:PASSWORD@SERVICE:3306/default
APP_NAME=جمعية مركز الإرشاد التربوي REC
APP_URL=https://employee.rec-soc.org
APP_TIMEZONE=Asia/Riyadh
APP_DEBUG=false
SETUP_ENABLED=false
RUN_MIGRATIONS_ON_REQUEST=false
MAIL_FROM=noreply@yourdomain.com
TRUSTED_PROXIES=
```

| المتغير | الإنتاج |
|---------|---------|
| `APP_DEBUG` | `false` دائماً |
| `SETUP_ENABLED` | `true` للإعداد الأول فقط، ثم `false` |
| `RUN_MIGRATIONS_ON_REQUEST` | `false` — الترحيل يتم عند تشغيل الحاوية |

---

## 3. النشر على Coolify

1. اربط المستودع: `farajnaem/rec-attendance-system`
2. **Build Pack:** Dockerfile
3. **Port:** `3000` (أو ما يعيّنه Coolify)
4. **Health Check:** `/ping.php`
5. اربط خدمة **MySQL 8** وانسخ `DATABASE_URL`
6. اضغط **Deploy**

عند كل إعادة نشر، `docker/init-db.php` يطبّق الترحيلات الجديدة تلقائياً.

---

## 4. بعد أول نشر

1. افتح `https://YOUR-DOMAIN/setup.php`
2. أنشئ حساب **مدير النظام**
3. غيّر `SETUP_ENABLED=false` في Coolify
4. **Redeploy**
5. تحقق من `/login` و `/manager/dashboard`

---

## 5. التحقق السريع

| الفحص | المتوقع |
|-------|---------|
| `GET /ping.php` | `ok` |
| `GET /health` | `{"status":"ok"}` |
| إدارة الموظفين → إجراءات | القائمة تظهر كاملة |
| مستندات موظف | رفع/تحميل يعمل |
| مغادرة أثناء العمل | تسجيل من لوحة الموظف |

---

## 6. النسخ الاحتياطي

- **MySQL:** نسخ Coolify الاحتياطي لقاعدة البيانات
- **المستندات:** مجلد `storage/documents` داخل الحاوية — أضف volume دائم في Coolify إن أردت الاحتفاظ بها خارج الحاوية

---

## 7. استكشاف الأخطاء

| المشكلة | الحل |
|---------|------|
| 500 بعد النشر | راجع Logs؛ تحقق من `DATABASE_URL` |
| ترحيل لم يُطبَّق | أعد Deploy — `init-db.php` يشغّل `MigrationRunner` |
| redirect خاطئ | `APP_URL` يجب أن يطابق النطاق مع `https://` |
| setup معطّل | `SETUP_ENABLED=true` مؤقتاً ثم Redeploy |

---

## 8. Docker Compose محلي (اختبار الإنتاج)

```powershell
copy .env.example .env
# عدّل DB_PASS و APP_URL

docker compose up -d --build
```

افتح: http://localhost:8080/setup.php

---

راجع أيضاً: `COOLIFY-DEPLOY-AR.md` | `README-DEPLOY.md` (Hostinger)
