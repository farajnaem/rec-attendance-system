<?php $title = 'نسخ احتياطي واستيراد'; ?>
<div class="page-header">
    <h1>نسخ احتياطي واستيراد قاعدة البيانات</h1>
    <p class="page-header__subtitle">
        متاح لـ <strong>مدير النظام</strong> فقط — <?= e($driverLabel) ?>
    </p>
</div>

<div class="alert alert-error">
    <strong>تحذير:</strong> ملف التصدير يحتوي بيانات حساسة (كلمات مرور مشفّرة، سجلات حضور...).
    لا تشاركه علناً ولا ترفعه على GitHub. الاستيراد بالوضع «استبدال كامل» يحذف كل البيانات الحالية.
</div>

<div class="grid-2">
    <div class="card">
        <h2>تصدير (Export)</h2>
        <p class="text-muted">
            تنزيل نسخة JSON من كل جداول النظام: مستخدمون، دوائر، حضور، مهام، صلاحيات، وغيرها.
        </p>

        <?php if (!empty($stats)): ?>
        <table style="margin:1rem 0">
            <thead><tr><th>الجدول</th><th>عدد السجلات</th></tr></thead>
            <tbody>
            <?php foreach ($stats as $table => $count): ?>
                <tr>
                    <td><code><?= e($table) ?></code></td>
                    <td><?= (int) $count ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <form method="post" action="<?= e(url('/manager/database/export')) ?>" style="margin-top:1rem">
            <?= Csrf::field() ?>
            <div class="form-group">
                <label for="export_admin_password">كلمة مرور المسؤول *</label>
                <input type="password" id="export_admin_password" name="admin_password" class="form-control" required autocomplete="current-password">
            </div>
            <button type="submit" class="btn btn-success">تنزيل ملف JSON</button>
        </form>
    </div>

    <div class="card">
        <h2>استيراد (Import)</h2>
        <p class="text-muted">
            رفع ملف JSON سابقاً مُصدَّراً من هذا النظام أو من بيئة أخرى.
        </p>

        <form method="post" action="<?= e(url('/manager/database/import')) ?>" enctype="multipart/form-data">
            <?= Csrf::field() ?>

            <div class="form-group">
                <label for="import_admin_password">كلمة مرور المسؤول *</label>
                <input type="password" id="import_admin_password" name="admin_password" class="form-control" required autocomplete="current-password">
            </div>

            <div class="form-group">
                <label for="import_file">ملف JSON *</label>
                <input type="file" id="import_file" name="import_file" class="form-control" accept=".json,application/json" required>
            </div>

            <div class="form-group">
                <label>وضع الاستيراد</label>
                <label style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.5rem;font-weight:400">
                    <input type="radio" name="import_mode" value="replace" checked>
                    <span><strong>استبدال كامل</strong> — حذف البيانات الحالية ثم استيراد الملف</span>
                </label>
                <label style="display:flex;align-items:center;gap:0.5rem;font-weight:400">
                    <input type="radio" name="import_mode" value="merge">
                    <span><strong>دمج</strong> — إضافة دون حذف (قد يسبب تعارضات في المفاتيح)</span>
                </label>
            </div>

            <div class="form-group" id="confirm-replace-group">
                <label for="confirm_text">اكتب <strong>استبدال</strong> للتأكيد *</label>
                <input type="text" id="confirm_text" name="confirm_text" class="form-control" autocomplete="off" placeholder="استبدال">
            </div>

            <label style="display:flex;align-items:flex-start;gap:0.5rem;margin-bottom:1rem;font-weight:400">
                <input type="checkbox" name="confirm_ack" value="1" required style="margin-top:0.25rem">
                <span>أفهم أن الاستيراد قد يغيّر أو يحذف بيانات النظام الحالية ولا يمكن التراجع بسهولة.</span>
            </label>

            <button type="submit" class="btn btn-warning">استيراد البيانات</button>
        </form>
    </div>
</div>

<div class="card">
    <h3>ملاحظات</h3>
    <ul class="text-muted" style="margin:0;padding-right:1.25rem;line-height:1.9">
        <li>يُنصح بتصدير نسخة احتياطية قبل أي استيراد.</li>
        <li>يمكنك تعديل ملف JSON يدوياً قبل الاستيراد (لا تغيّر <code>id</code> إلا إذا كنت تعرف العلاقات).</li>
        <li>بعد الاستيراد، تحقق من تسجيل الدخول والمستخدمين والدوائر.</li>
        <li>الحد الأقصى لحجم الملف: <?= (int) ($maxUploadMb ?? 25) ?> ميجابايت.</li>
    </ul>
</div>

<script>
(function () {
    var radios = document.querySelectorAll('input[name="import_mode"]');
    var confirmGroup = document.getElementById('confirm-replace-group');
    var confirmInput = document.getElementById('confirm_text');

    function syncConfirm() {
        var replace = document.querySelector('input[name="import_mode"][value="replace"]').checked;
        confirmGroup.style.display = replace ? '' : 'none';
        confirmInput.required = replace;
        if (!replace) confirmInput.value = '';
    }

    radios.forEach(function (r) { r.addEventListener('change', syncConfirm); });
    syncConfirm();
})();
</script>
