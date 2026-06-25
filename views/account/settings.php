<?php $title = 'إعدادات الحساب'; ?>
<div class="page-header">
    <h1>إعدادات الحساب</h1>
    <p class="page-header__subtitle"><?= e(Auth::name()) ?> — <?= e(Auth::email()) ?></p>
</div>

<div class="card" style="max-width:520px">
    <h2>تغيير كلمة المرور</h2>
    <form method="post" action="<?= e(url('/account/settings')) ?>">
        <?= Csrf::field() ?>
        <div class="form-group">
            <label for="current_password">كلمة المرور الحالية</label>
            <input type="password" id="current_password" name="current_password" class="form-control" required autocomplete="current-password">
        </div>
        <div class="form-group">
            <label for="new_password">كلمة المرور الجديدة</label>
            <input type="password" id="new_password" name="new_password" class="form-control" minlength="<?= passwordMinLength() ?>" required autocomplete="new-password">
            <p class="text-muted" style="margin-top:0.35rem;font-size:0.875rem"><?= passwordMinLength() ?> أحرف على الأقل</p>
        </div>
        <div class="form-group">
            <label for="confirm_password">تأكيد كلمة المرور الجديدة</label>
            <input type="password" id="confirm_password" name="confirm_password" class="form-control" minlength="<?= passwordMinLength() ?>" required autocomplete="new-password">
        </div>
        <button type="submit" class="btn">حفظ كلمة المرور</button>
    </form>
</div>

<script>
(function () {
    var form = document.querySelector('form');
    if (!form) return;
    form.addEventListener('submit', function (e) {
        var a = document.getElementById('new_password').value;
        var b = document.getElementById('confirm_password').value;
        if (a !== b) {
            e.preventDefault();
            alert('كلمتا المرور الجديدتان غير متطابقتين.');
        }
    });
})();
</script>
