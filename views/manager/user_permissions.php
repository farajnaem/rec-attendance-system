<?php $title = 'صلاحيات: ' . e($user['name']); ?>
<h1>صلاحيات الموظف</h1>
<p class="text-muted">
    <?= e($user['name']) ?> — <?= e(RoleHelper::label($user['role'])) ?> — <?= e($user['email']) ?>
</p>

<div class="card">
    <form method="post" action="<?= e(url('/manager/users/permissions')) ?>" id="permissionsForm">
        <?= Csrf::field() ?>
        <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
        <p class="text-muted">
            اختر من <a href="<?= e(url('/manager/permissions')) ?>">مجموعة صلاحيات النظام</a>
            ما يناسب هذا الموظف. يمكنك استخدام «افتراضيات الدور» ثم التعديل.
        </p>
        <?php partial('partials/permission_picker', [
            'granted' => $granted,
            'targetRole' => $user['role'],
            'showRolePreview' => true,
            'rolePreviewId' => 'rolePreview',
        ]); ?>
        <div style="margin-top:1rem;display:flex;gap:0.5rem;flex-wrap:wrap">
            <button type="submit" class="btn">حفظ الصلاحيات</button>
            <button type="button" class="btn btn-outline" onclick="applyRoleDefaults()">استعادة افتراضيات الدور</button>
            <a href="<?= e(url('/manager/permissions')) ?>" class="btn btn-outline">مجموعة الصلاحيات</a>
            <a href="<?= e(url('/manager/users')) ?>" class="btn btn-outline">رجوع للموظفين</a>
            <a href="<?= e(url('/manager/users/edit?id=' . (int)$user['id'])) ?>" class="btn btn-outline">تعديل البيانات</a>
        </div>
    </form>
</div>

<?php if (!empty($department)): ?>
<div class="card">
    <h3>الدائرة الحالية</h3>
    <p><?= e($department['name']) ?></p>
    <?php if (Auth::can('transfer_employee')): ?>
    <form method="post" action="<?= e(url('/manager/users/transfer')) ?>">
        <?= Csrf::field() ?>
        <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
        <div class="form-group">
            <label>نقل إلى دائرة</label>
            <select name="department_id" class="form-control" required>
                <?php foreach ($departments as $d): ?>
                <option value="<?= (int)$d['id'] ?>" <?= (int)$d['id'] === (int)$department['id'] ? 'selected' : '' ?>>
                    <?= e($d['name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-outline">نقل الموظف</button>
    </form>
    <?php endif; ?>
</div>
<?php endif; ?>
