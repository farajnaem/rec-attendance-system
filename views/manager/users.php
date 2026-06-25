<?php $title = 'إدارة الموظفين'; ?>
<h1>إدارة الموظفين والمستخدمين</h1>
<p class="text-muted">اختر الوصف الوظيفي ثم حدّد الصلاحيات لكل مستخدم (من مدير النظام أو المدير).</p>

<div class="card">
    <h2>إضافة مستخدم جديد</h2>
    <form method="post" action="<?= e(url('/manager/users/create')) ?>">
        <?= Csrf::field() ?>
        <div class="grid-2">
            <div class="form-group">
                <label>الاسم الكامل</label>
                <input type="text" name="name" class="form-control" required>
            </div>
            <div class="form-group">
                <label>البريد الإلكتروني</label>
                <input type="email" name="email" class="form-control" required>
            </div>
            <div class="form-group">
                <label>كلمة المرور</label>
                <input type="password" name="password" class="form-control" minlength="6" required>
            </div>
            <div class="form-group">
                <label>الوصف الوظيفي (الدور)</label>
                <select name="role" class="form-control" id="userRole" onchange="toggleUserFields()">
                    <?php foreach ($availableRoles as $roleKey => $roleName): ?>
                    <option value="<?= e($roleKey) ?>" <?= $roleKey === 'employee' ? 'selected' : '' ?>>
                        <?= e($roleName) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>الدائرة</label>
                <select name="department_id" class="form-control" id="deptField">
                    <option value="">— اختر الدائرة —</option>
                    <?php foreach ($departments as $d): ?>
                    <option value="<?= (int)$d['id'] ?>"><?= e($d['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>المنطقة الزمنية</label>
                <select name="timezone" class="form-control">
                    <?php foreach (TimezoneHelper::commonTimezones() as $tz => $label): ?>
                    <option value="<?= e($tz) ?>" <?= $tz === TimezoneHelper::defaultTimezone() ? 'selected' : '' ?>>
                        <?= e($label) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" id="managerField">
                <label>المشرف المباشر (اختياري)</label>
                <select name="manager_id" class="form-control">
                    <option value="">— بدون —</option>
                    <?php foreach ($supervisors as $m): ?>
                    <option value="<?= (int)$m['id'] ?>" <?= (int)$m['id'] === Auth::id() ? 'selected' : '' ?>>
                        <?= e($m['name']) ?> (<?= e(RoleHelper::label($m['role'])) ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <?php if ($canAssignPermissions): ?>
        <div class="form-group" style="margin-top:1rem">
            <label>الصلاحيات (اختياري — الافتراضي حسب الدور)</label>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:0.5rem;margin-top:0.5rem">
                <?php foreach (PermissionService::allDefinitions() as $code => $def): ?>
                <label style="font-size:0.9rem">
                    <input type="checkbox" name="permissions[]" value="<?= e($code) ?>" class="perm-cb" data-role-default>
                    <?= e($def['label']) ?>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        <button type="submit" class="btn" style="margin-top:1rem">إضافة المستخدم</button>
    </form>
</div>

<div class="card">
    <h2>قائمة المستخدمين</h2>
    <table>
        <thead>
            <tr>
                <th>الاسم</th>
                <th>البريد</th>
                <th>الدور</th>
                <th>الدائرة</th>
                <th>المنطقة الزمنية</th>
                <th>المشرف</th>
                <th>الحالة</th>
                <th>إجراء</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($users)): ?>
            <tr><td colspan="8">لا يوجد مستخدمون</td></tr>
        <?php else: foreach ($users as $u): ?>
            <tr>
                <td>
                    <a href="<?= e(url('/manager/users/edit?id=' . (int)$u['id'])) ?>" class="fw-bold" style="color:var(--primary);text-decoration:none">
                        <?= e($u['name']) ?>
                    </a>
                </td>
                <td><?= e($u['email']) ?></td>
                <td><?= e(RoleHelper::label($u['role'])) ?></td>
                <td><?= e($u['department_name'] ?? '—') ?></td>
                <td><?= e(TimezoneHelper::commonTimezones()[$u['timezone']] ?? $u['timezone']) ?></td>
                <td><?= e($u['manager_name'] ?? '—') ?></td>
                <td>
                    <?php if ((int)$u['is_active'] === 1): ?>
                        <span class="badge badge-evaluated">نشط</span>
                    <?php else: ?>
                        <span class="badge badge-pending">معطّل</span>
                    <?php endif; ?>
                </td>
                <td class="text-nowrap">
                    <a href="<?= e(url('/manager/users/edit?id=' . (int)$u['id'])) ?>"
                       class="btn btn-outline" style="padding:0.25rem 0.5rem;font-size:0.85rem">تعديل</a>
                    <?php if ($canAssignPermissions && (int)$u['id'] !== Auth::id()): ?>
                    <a href="<?= e(url('/manager/users/permissions?id=' . (int)$u['id'])) ?>"
                       class="btn btn-outline" style="padding:0.25rem 0.5rem;font-size:0.85rem">صلاحيات</a>
                    <?php endif; ?>
                    <?php if ((int)$u['id'] !== Auth::id()): ?>
                    <form method="post" action="<?= e(url('/manager/users/toggle')) ?>" style="display:inline">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                        <button type="submit" class="btn btn-outline" style="padding:0.25rem 0.5rem;font-size:0.85rem">
                            <?= (int)$u['is_active'] === 1 ? 'تعطيل' : 'تفعيل' ?>
                        </button>
                    </form>
                    <?php if ($isSystemAdmin): ?>
                    <form method="post" action="<?= e(url('/manager/users/delete')) ?>" style="display:inline"
                          onsubmit="return confirm('حذف <?= e(addslashes($u['name'])) ?>؟');">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                        <button type="submit" class="btn btn-danger" style="padding:0.25rem 0.5rem;font-size:0.85rem">حذف</button>
                    </form>
                    <?php endif; ?>
                    <?php else: ?>—<?php endif; ?>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<script>
function toggleUserFields() {
    var role = document.getElementById('userRole').value;
    document.getElementById('managerField').style.display = role === 'employee' ? 'block' : 'none';
}
toggleUserFields();
</script>
