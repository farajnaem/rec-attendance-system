# نقل بيانات اللوكال إلى السيرفر (Coolify)

## ماذا يُنقل؟

كل الجداول: مستخدمون، دوائر، صلاحيات، حضور، مهام، مواقع GPS، توصيف وظيفي، إلخ.

---

## الخطوة 1 — تصدير من جهازك (Windows)

من مجلد المشروع:

```powershell
cd C:\Users\pc\Desktop\rec-attendance-system
php database/export-data.php
```

يُنشئ الملف: `database/export.json`

### تعديل البيانات (اختياري)

افتح `database/export.json` بمحرر نصوص وعدّل ما تريد، مثلاً:

- `tables.users` — أسماء، بريد، أدوار
- `tables.departments` — أسماء الدوائر
- `tables.user_permissions` — الصلاحيات

> **لا تغيّر** حقل `id` إلا إذا تعرف ما تفعل — العلاقات بين الجداول تعتمد عليه.

---

## الخطوة 2 — رفع الملف للسيرفر

ارفع `export.json` إلى VPS عبر SCP أو WinSCP:

```
المسار على السيرفر: /tmp/export.json
```

أو انسخه داخل حاوية التطبيق:

```bash
docker cp export.json CONTAINER_NAME:/var/www/html/database/export.json
```

(استبدل `CONTAINER_NAME` باسم حاوية rec-attendance في Coolify)

---

## الخطوة 3 — استيراد داخل حاوية التطبيق

من Coolify → التطبيق → **Terminal** (أو SSH للسيرفر ثم `docker exec`):

```bash
cd /var/www/html
php database/import-data.php --file=database/export.json --force
```

`DATABASE_URL` موجود تلقائياً في Coolify — لا حاجة لكتابته.

---

## طريقة بديلة — أمر واحد من جهازك

تعمل **فقط** إذا كان MySQL متاحاً من الإنترنت (رابط عام من Coolify):

```powershell
php database/push-to-mysql.php --database-url="mysql://mysql:PASSWORD@HOST:3306/default" --force
```

> عادةً رابط Coolify الداخلي (`haov0644574ouak0covccwe2`) **لا يعمل من جهازك** — استخدم الطريقة 1–3.

---

## بعد الاستيراد

1. افتح: `https://employee.rec-soc.org/login`
2. سجّل دخول بنفس البريد/كلمة المرور من اللوكال
3. تحقق من المستخدمين والمهام والدوائر
4. في Coolify عيّن:
   ```
   SETUP_ENABLED=false
   APP_URL=https://employee.rec-soc.org
   ```
5. **Redeploy** إذا غيّرت متغيرات البيئة

---

## تحذير

`--force` **يحذف** كل بيانات MySQL الحالية ويستبدلها ببيانات اللوكال. خذ نسخة احتياطية من Coolify إن لزم.

`export.json` يحتوي كلمات مرور مشفّرة وبيانات حساسة — **لا ترفعه على GitHub**.
