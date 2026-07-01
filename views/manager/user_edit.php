<?php $title = 'تعديل موظف: ' . e($user['name']); ?>
<h1>تعديل الموظف</h1>
<p class="text-muted">عدّل بيانات <?= e($user['name']) ?> ثم احفظ التغييرات.</p>

<div class="card">
    <form method="post" action="<?= e(url('/manager/users/update')) ?>">
        <?= Csrf::field() ?>
        <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
        <div class="grid-2">
            <div class="form-group">
                <label>الاسم الكامل *</label>
                <input type="text" name="name" class="form-control" required value="<?= e($user['name']) ?>">
            </div>
            <div class="form-group">
                <label>البريد الإلكتروني *</label>
                <input type="email" name="email" class="form-control" required value="<?= e($user['email']) ?>">
            </div>
            <div class="form-group">
                <label>المنطقة الزمنية *</label>
                <select name="timezone" class="form-control" required>
                    <?php
                    $currentTz = $user['timezone'] ?? TimezoneHelper::defaultTimezone();
                    if (!TimezoneHelper::isValid($currentTz)) {
                        $currentTz = TimezoneHelper::defaultTimezone();
                    }
                    foreach (TimezoneHelper::commonTimezones() as $tz => $label):
                    ?>
                    <option value="<?= e($tz) ?>" <?= $currentTz === $tz ? 'selected' : '' ?>>
                        <?= e($label) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($canChangeRole): ?>
            <div class="form-group">
                <label>الوصف الوظيفي (الدور)</label>
                <select name="role" class="form-control" id="editUserRole" onchange="toggleEditManagerField()">
                    <?php foreach (RoleHelper::all() as $roleKey => $roleName): ?>
                    <option value="<?= e($roleKey) ?>" <?= RoleHelper::normalizeRole($user['role']) === $roleKey ? 'selected' : '' ?>>
                        <?= e($roleName) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php else: ?>
            <div class="form-group">
                <label>الدور</label>
                <input type="text" class="form-control" disabled value="<?= e(RoleHelper::label($user['role'])) ?>">
            </div>
            <?php endif; ?>
            <div class="form-group">
                <label>الدائرة</label>
                <?php if (!empty($canChangeDepartment)): ?>
                <select name="department_id" class="form-control">
                    <option value="">— بدون —</option>
                    <?php foreach ($departments as $d): ?>
                    <option value="<?= (int)$d['id'] ?>" <?= (int)($department['id'] ?? 0) === (int)$d['id'] ? 'selected' : '' ?>>
                        <?= e($d['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php else: ?>
                <input type="hidden" name="department_id" value="<?= (int)($department['id'] ?? 0) ?>">
                <input type="text" class="form-control" disabled value="<?= e($department['name'] ?? '—') ?>">
                <?php endif; ?>
            </div>
            <div class="form-group" id="editManagerField">
                <label>المشرف المباشر</label>
                <select name="manager_id" class="form-control">
                    <option value="">— بدون —</option>
                    <?php foreach ($supervisors as $m): ?>
                    <option value="<?= (int)$m['id'] ?>" <?= (int)($user['manager_id'] ?? 0) === (int)$m['id'] ? 'selected' : '' ?>>
                        <?= e($m['name']) ?> (<?= e(RoleHelper::label($m['role'])) ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>كلمة مرور جديدة</label>
                <input type="password" name="password" class="form-control" minlength="6" autocomplete="new-password"
                       placeholder="اتركها فارغة إن لم تُرد التغيير">
            </div>
            <div class="form-group">
                <label>الحالة</label>
                <input type="text" class="form-control" disabled
                       value="<?= (int)$user['is_active'] === 1 ? 'نشط' : 'معطّل' ?>">
            </div>
            <?php if (!empty($canManageAllUsers) && RoleHelper::isEmployee($user['role'])): ?>
            <div class="form-group">
                <label>تاريخ انتهاء العقد</label>
                <input type="date" name="contract_end_date" class="form-control"
                       value="<?= e($user['contract_end_date'] ?? '') ?>">
                <small class="text-muted">يُجمَّد الحساب تلقائياً بعد مهلة التجميد من إعدادات الدوام</small>
            </div>
            <?php endif; ?>
        </div>
        <div style="margin-top:1rem;display:flex;flex-wrap:wrap;gap:0.5rem">
            <button type="submit" class="btn">حفظ التعديلات</button>
            <?php if (!empty($canEditPermissions) && (int)$user['id'] !== Auth::id()): ?>
            <a href="<?= e(url('/manager/users/permissions?id=' . (int)$user['id'])) ?>" class="btn btn-outline">الصلاحيات</a>
            <?php endif; ?>
            <a href="<?= e(url('/manager/users')) ?>" class="btn btn-outline">رجوع للقائمة</a>
        </div>
    </form>
</div>

<?php if (!empty($canBorrowEmployee) && !empty($department)): ?>
<div class="card">
    <h2>استخدام مؤقت في دائرة أخرى</h2>
    <p class="text-muted">
        الدائرة الأساسية: <strong><?= e($department['name']) ?></strong>.
        يبقى الموظف في دائرته الأصلية؛ التعيين المؤقت يتيح للمشرف في الدائرة الأخرى
        إسناد المهام ومتابعة التقارير كالعادة.
    </p>
    <?php if (!empty($activeCross)): ?>
    <p class="alert alert-success">
        تعيين نشط: يعمل في <strong><?= e($activeCross['target_department_name']) ?></strong>
        من <?= e($activeCross['start_date']) ?>
        <?= $activeCross['end_date'] ? 'حتى ' . e($activeCross['end_date']) : '(بدون تاريخ نهاية)' ?>
    </p>
    <form method="post" action="<?= e(url('/manager/users/cross-end')) ?>">
        <?= Csrf::field() ?>
        <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
        <input type="hidden" name="assignment_id" value="<?= (int)$activeCross['id'] ?>">
        <button type="submit" class="btn btn-outline">إنهاء التعيين المؤقت</button>
    </form>
    <?php else: ?>
    <form method="post" action="<?= e(url('/manager/users/cross-assign')) ?>">
        <?= Csrf::field() ?>
        <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
        <div class="grid-2">
            <div class="form-group">
                <label>الدائرة المستهدفة *</label>
                <select name="target_department_id" class="form-control" required>
                    <option value="">— اختر —</option>
                    <?php foreach ($departments as $d): ?>
                    <?php if ((int)$d['id'] === (int)($department['id'] ?? 0)) continue; ?>
                    <option value="<?= (int)$d['id'] ?>"><?= e($d['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>تاريخ البداية *</label>
                <input type="date" name="start_date" class="form-control" required value="<?= e(date('Y-m-d')) ?>">
            </div>
            <div class="form-group">
                <label>تاريخ النهاية (اختياري)</label>
                <input type="date" name="end_date" class="form-control">
            </div>
            <div class="form-group">
                <label>ملاحظات</label>
                <input type="text" name="notes" class="form-control" placeholder="سبب التعيين المؤقت">
            </div>
        </div>
        <button type="submit" class="btn">تعيين مؤقت في دائرة أخرى</button>
    </form>
    <?php endif; ?>

    <?php if (!empty($crossAssignments)): ?>
    <h3 style="margin-top:1.5rem">سجل التعيينات</h3>
    <table>
        <thead><tr><th>من</th><th>إلى</th><th>البداية</th><th>النهاية</th><th>الحالة</th></tr></thead>
        <tbody>
        <?php foreach ($crossAssignments as $c): ?>
        <tr>
            <td><?= e($c['home_department_name']) ?></td>
            <td><?= e($c['target_department_name']) ?></td>
            <td><?= e($c['start_date']) ?></td>
            <td><?= e($c['end_date'] ?? '—') ?></td>
            <td><?= (int)$c['is_active'] === 1 ? 'نشط' : 'منتهٍ' ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
<?php endif; ?>

<script>
function toggleEditManagerField() {
    var roleEl = document.getElementById('editUserRole');
    if (!roleEl) return;
    document.getElementById('editManagerField').style.display =
        roleEl.value === 'employee' ? 'block' : 'none';
}
toggleEditManagerField();
</script>
