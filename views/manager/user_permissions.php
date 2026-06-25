<?php $title = 'صلاحيات: ' . e($user['name']); ?>
<h1>صلاحيات المستخدم</h1>
<p class="text-muted">
    <?= e($user['name']) ?> — <?= e(RoleHelper::label($user['role'])) ?> — <?= e($user['email']) ?>
</p>

<div class="card">
    <form method="post" action="<?= e(url('/manager/users/permissions')) ?>">
        <?= Csrf::field() ?>
        <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
        <p class="text-muted">فعّل الصلاحيات المناسبة لهذا المستخدم. يحددها مدير النظام أو المدير فقط.</p>
        <div style="display:grid;gap:0.75rem">
            <?php foreach (PermissionService::allDefinitions() as $code => $def): ?>
            <label style="display:flex;align-items:flex-start;gap:0.5rem;padding:0.5rem;border:1px solid var(--border);border-radius:8px">
                <input type="checkbox" name="permissions[]" value="<?= e($code) ?>"
                    <?= in_array($code, $granted, true) ? 'checked' : '' ?>>
                <span>
                    <strong><?= e($def['label']) ?></strong>
                    <?php if (in_array(RoleHelper::normalizeRole($user['role']), $def['defaults'], true)): ?>
                    <small class="text-muted"> (افتراضي للدور)</small>
                    <?php endif; ?>
                </span>
            </label>
            <?php endforeach; ?>
        </div>
        <div style="margin-top:1rem;display:flex;gap:0.5rem">
            <button type="submit" class="btn">حفظ الصلاحيات</button>
            <a href="<?= e(url('/manager/users')) ?>" class="btn btn-outline">رجوع للمستخدمين</a>
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
