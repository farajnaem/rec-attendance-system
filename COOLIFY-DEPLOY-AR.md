# النشر على Coolify

دليل نشر نظام REC Attendance على [Coolify](https://coolify.io) باستخدام Docker.

---

## 1. المتطلبات

- خادم VPS مع Coolify مثبّت
- مستودع Git (GitHub / GitLab / Gitea)
- نطاق (اختياري — Coolify يوفّر SSL تلقائياً)

---

## 2. إنشاء قاعدة بيانات MySQL في Coolify

1. من لوحة Coolify → **Resources** → **+ New**
2. اختر **Database** → **MySQL 8**
3. احفظ بيانات الاتصال:
   - Host (اسم الخدمة الداخلي، مثل `mysql-xxxxx`)
   - Database name
   - Username
   - Password
   - Port (عادة `3306`)

> في Coolify، الخدمات على نفس الشبكة تتواصل عبر اسم الخدمة الداخلي — **لا تستخدم** `localhost`.

---

## 3. إنشاء تطبيق Docker

1. **Resources** → **+ New** → **Application**
2. اختر مصدر الكود (Git repository)
3. **Build Pack:** Dockerfile
4. **Ports Exposes:** `3000` (Coolify يعيّن `PORT=3000` افتراضياً)
5. **Health Check Path:** `/ping.php` أو `/health.php` — **لا تستخدم `/` أو `/login`**
6. **Health Check Port:** اتركه فارغاً (يستخدم منفذ الحاوية تلقائياً) أو `3000`
7. **Health Check Interval:** 15s | **Timeout:** 10s | **Start Period:** 120s | **Retries:** 8

---

## 4. متغيرات البيئة

في Coolify → Application → **Environment Variables**:

### الطريقة الموصى بها — رابط واحد (Coolify)

عند ربط MySQL بالتطبيق، Coolify يوفّر رابطاً بهذا الشكل:

```env
DATABASE_URL=mysql://mysql:PASSWORD@haov0644574ouak0covccwe2:3306/default
```

انسخ الرابط كما هو من Coolify — المشروع يستخرج تلقائياً: المستخدم، كلمة المرور، المضيف، المنفذ، واسم القاعدة.

> يقبل أيضاً: `MYSQL_URL` أو `DB_URL`

### الطريقة البديلة — متغيرات منفصلة

```env
DB_DRIVER=mysql
DB_HOST=mysql-xxxxx
DB_PORT=3306
DB_NAME=rec_attendance
DB_USER=rec_user
DB_PASS=YOUR_STRONG_PASSWORD
```

### إعدادات التطبيق (مطلوبة في كلتا الحالتين)

```env
APP_NAME=جمعية مركز الإرشاد التربوي REC
APP_URL=https://employee.rec-soc.org
APP_TIMEZONE=Asia/Riyadh
APP_DEBUG=false
SETUP_ENABLED=true
```

| المتغير | ملاحظة |
|---------|--------|
| `DATABASE_URL` | رابط MySQL من Coolify — **الأولوية** إذا وُجد |
| `APP_URL` | الرابط النهائي مع `https://` |
| `APP_DEBUG` | **يجب** أن يكون `false` في الإنتاج |
| `SETUP_ENABLED` | `true` للإعداد الأول فقط |

---

## 5. النشر

1. اضغط **Deploy**
2. انتظر اكتمال البناء (Dockerfile يبني PHP 8.2 + Apache)
3. عند أول تشغيل، الحاوية تطبّق `database/schema.sql` تلقائياً

---

## 6. إنشاء مسؤول النظام

1. افتح: `https://attendance.yourdomain.com/setup.php`
2. أنشئ حساب المسؤول (بريد + كلمة مرور 8+ أحرف)
3. **فوراً** غيّر في Coolify:
   ```
   SETUP_ENABLED=false
   ```
4. أعد نشر التطبيق (Redeploy)

---

## 7. التحقق

| الفحص | الرابط / الإجراء |
|-------|------------------|
| صحة التطبيق | `GET /health` → `{"status":"ok"}` |
| تسجيل الدخول | `/login` |
| لوحة المسؤول | `/manager/dashboard` |

---

## 8. SSL والنطاق

1. Coolify → Application → **Domains**
2. أضف نطاقك
3. Coolify يصدر شهادة Let's Encrypt تلقائياً
4. تأكد أن `APP_URL` يطابق النطاق مع `https://`

---

## 9. النسخ الاحتياطي

- **قاعدة البيانات:** استخدم نسخ Coolify الاحتياطي لـ MySQL
- **التوقيعات:** مخزّنة في قاعدة البيانات (لا حاجة لمجلد uploads)

---

## 10. استكشاف الأخطاء

| المشكلة | الحل |
|---------|------|
| 500 — خطأ قاعدة البيانات | تحقق من `DATABASE_URL` أو `DB_HOST` وبيانات MySQL |
| setup.php معطّل | عيّن `SETUP_ENABLED=true` مؤقتاً |
| redirect خاطئ | تأكد أن `APP_URL` صحيح مع https |
| health check فاشل | استخدم `/ping.php` أو `/health.php` — ليس `/`؛ زِد Start Period إلى 120s |
| container unhealthy | راجع Logs: غالباً MySQL غير مربوط أو `DATABASE_URL` خاطئ |
| 502 Bad Gateway | الحاوية لا تعمل — راجع Logs؛ فعّل `APP_DEBUG=true` وافتح `/debug/db` |

---

## docker-compose (اختبار محلي)

لاختبار نفس بيئة الإنتاج محلياً:

```powershell
copy .env.example .env
docker compose up -d --build
```

ثم اتبع خطوات الإعداد من `/setup.php`.

---

## Coolify vs docker-compose على الخادم

| | Coolify | docker-compose يدوي |
|---|---------|---------------------|
| MySQL | خدمة منفصلة في Coolify | حاوية `db` في compose |
| SSL | تلقائي | يدوي |
| Env vars | لوحة Coolify | ملف `.env` |
| موصى به | **نعم — للإنتاج** | للاختبار المحلي |
