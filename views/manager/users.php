<?php $title = 'إدارة الموظفين'; ?>
<h1>إدارة الموظفين</h1>
<p class="text-muted">
    <?php if (!empty($canManageAllUsers)): ?>
    اختر الوصف الوظيفي — تُحدَّد الصلاحيات تلقائياً حسب الدور ويمكن تعديلها قبل الإضافة.
    <?php elseif (!empty($canEditDeptUsers)): ?>
    يمكنك مشاهدة وتعديل موظفي دائرتك فقط (بدون إضافة أو حذف).
    <?php elseif (Auth::can('view_all_users')): ?>
    يمكنك مشاهدة جميع الموظفين (قراءة فقط).
    <?php else: ?>
    يمكنك مشاهدة موظفي دائرتك فقط.
    <?php endif; ?>
</p>

<?php if (!empty($userOverview)): ?>
<div class="stats" style="margin-bottom:1.25rem">
    <div class="stat-box">
        <div class="value"><?= (int) $userOverview['total'] ?></div>
        <div class="label">إجمالي الموظفين</div>
    </div>
    <div class="stat-box">
        <div class="value"><?= (int) $userOverview['active'] ?></div>
        <div class="label">نشط</div>
    </div>
    <div class="stat-box">
        <div class="value"><?= (int) $userOverview['inactive'] ?></div>
        <div class="label">معطّل</div>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($canManageAllUsers)): ?>
<div class="card" id="addUserPanel" hidden>
    <h2>إضافة موظف جديد</h2>
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
                <input type="password" name="password" class="form-control" minlength="<?= passwordMinLength() ?>" required>
            </div>
            <div class="form-group">
                <label>الوصف الوظيفي (الدور)</label>
                <select name="role" class="form-control" id="userRole">
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
            <div class="form-group" id="contractField">
                <label>تاريخ انتهاء العقد</label>
                <input type="date" name="contract_end_date" class="form-control">
                <small class="text-muted">اختياري — يُجمَّد الحساب بعد مهلة التجميد من إعدادات الدوام</small>
            </div>
        </div>
        <?php if (!empty($canEditPermissions)): ?>
        <div class="form-group" style="margin-top:1rem">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:0.5rem;flex-wrap:wrap;margin-bottom:0.5rem">
                <label style="margin:0">الصلاحيات — اختر من مجموعة النظام</label>
                <button type="button" class="btn btn-outline" style="padding:0.25rem 0.75rem;font-size:0.85rem" onclick="applyRoleDefaults()">استعادة افتراضيات الدور</button>
            </div>
            <?php partial('partials/permission_picker', [
                'granted' => PermissionService::defaultCodesForRole('employee'),
                'targetRole' => 'employee',
                'showRolePreview' => true,
                'rolePreviewId' => 'userRolePreview',
            ]); ?>
        </div>
        <?php endif; ?>
        <button type="submit" class="btn" style="margin-top:1rem">إضافة الموظف</button>
        <button type="button" class="btn btn-outline" style="margin-top:1rem" onclick="toggleAddPanel('addUserPanel', false)">إلغاء</button>
    </form>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header-row">
        <h2>قائمة الموظفين</h2>
        <?php if (!empty($canManageAllUsers)): ?>
        <button type="button" class="btn" onclick="toggleAddPanel('addUserPanel'); setTimeout(function(){ var r=document.getElementById('userRole'); if(r) r.dispatchEvent(new Event('change')); }, 50);">+ إضافة</button>
        <?php endif; ?>
    </div>
    <div class="form-group" style="max-width:320px;margin-bottom:1rem">
        <label for="userSearch">بحث</label>
        <input type="search" id="userSearch" class="form-control" placeholder="ابحث بالاسم أو البريد..." autocomplete="off">
    </div>
    <div class="table-wrap">
    <table id="usersTable" class="table">
        <thead>
            <tr>
                <th class="table-actions-col table-actions-col-first">إجراءات</th>
                <th>الاسم</th>
                <th>البريد</th>
                <th>الدور</th>
                <th>الدائرة</th>
                <th>المنطقة الزمنية</th>
                <th>المشرف</th>
                <th>انتهاء العقد</th>
                <th>الحالة</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($pagination['items'])): ?>
            <tr><td colspan="9">لا يوجد موظفون</td></tr>
        <?php else: foreach ($pagination['items'] as $u): ?>
            <?php
            $canEditRow = (!empty($canEditDeptUsers) || !empty($canManageAllUsers))
                && (int)$u['id'] !== Auth::id();
            $canEditSelf = (int)$u['id'] === Auth::id() && (!empty($canManageAllUsers));
            $showActions = $canEditRow || $canEditSelf
                || (!empty($canManageAllUsers) && (int)$u['id'] !== Auth::id())
                || Auth::can('manage_job_description')
                || Auth::can('view_employee_documents')
                || Auth::can('manage_employee_documents');
            ?>
            <tr data-search="<?= e(mb_strtolower($u['name'] . ' ' . $u['email'])) ?>">
                <td class="table-actions-col table-actions-col-first">
                    <?php if ($showActions): ?>
                    <div class="user-actions-bar">
                        <?php if ($canEditRow || $canEditSelf): ?>
                        <a class="btn btn-outline btn-sm" href="<?= e(url('/manager/users/edit?id=' . (int)$u['id'])) ?>" title="تعديل">تعديل</a>
                        <?php endif; ?>
                        <?php if (Auth::can('manage_job_description') && RoleHelper::isEmployee($u['role'])): ?>
                        <a class="btn btn-outline btn-sm" href="<?= e(url('/manager/job-description/edit?user_id=' . (int)$u['id'])) ?>" title="التوصيف الوظيفي">توصيف</a>
                        <?php endif; ?>
                        <?php if (RoleHelper::isEmployee($u['role']) && (Auth::can('view_employee_documents') || Auth::can('manage_employee_documents'))): ?>
                        <a class="btn btn-outline btn-sm" href="<?= e(url('/documents/employee?id=' . (int)$u['id'])) ?>" title="حافظة المستندات">مستندات</a>
                        <?php endif; ?>
                        <?php if (!empty($canEditPermissions) && !empty($canManageAllUsers) && (int)$u['id'] !== Auth::id()): ?>
                        <a class="btn btn-outline btn-sm" href="<?= e(url('/manager/users/permissions?id=' . (int)$u['id'])) ?>" title="صلاحيات">صلاحيات</a>
                        <?php endif; ?>
                        <?php if (!empty($canManageAllUsers) && (int)$u['id'] !== Auth::id()): ?>
                        <form method="post" action="<?= e(url('/manager/users/toggle')) ?>" class="user-actions-inline-form">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                            <button type="submit" class="btn btn-outline btn-sm" title="<?= (int)$u['is_active'] === 1 ? 'تعطيل' : 'تفعيل' ?>">
                                <?= (int)$u['is_active'] === 1 ? 'تعطيل' : 'تفعيل' ?>
                            </button>
                        </form>
                        <?php if ($isSystemAdmin): ?>
                        <form method="post" action="<?= e(url('/manager/users/delete')) ?>" class="user-actions-inline-form"
                              data-confirm="حذف <?= e($u['name']) ?>؟">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm" title="حذف">حذف</button>
                        </form>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                    <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (!empty($canEditDeptUsers) || !empty($canManageAllUsers)): ?>
                    <a href="<?= e(url('/manager/users/edit?id=' . (int)$u['id'])) ?>" class="fw-bold" style="color:var(--primary);text-decoration:none">
                        <?= e($u['name']) ?>
                    </a>
                    <?php else: ?>
                    <?= e($u['name']) ?>
                    <?php endif; ?>
                </td>
                <td><?= e($u['email']) ?></td>
                <td><?= e(RoleHelper::label($u['role'])) ?></td>
                <td><?= e($u['department_name'] ?? '—') ?></td>
                <td><?= e(TimezoneHelper::commonTimezones()[$u['timezone']] ?? $u['timezone']) ?></td>
                <td><?= e($u['manager_name'] ?? '—') ?></td>
                <td><?= e($u['contract_end_date'] ?? '—') ?></td>
                <td>
                    <?php if ((int)$u['is_active'] === 1): ?>
                        <span class="badge badge-evaluated">نشط</span>
                    <?php else: ?>
                        <span class="badge badge-pending">معطّل</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
    </div>
    <?php if (($pagination['pages'] ?? 1) > 1): ?>
    <nav class="pagination" style="margin-top:1rem;display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap">
        <?php if ($pagination['page'] > 1): ?>
        <a href="<?= e(url('/manager/users?page=' . ($pagination['page'] - 1))) ?>" class="btn btn-outline">السابق</a>
        <?php endif; ?>
        <span class="text-muted">صفحة <?= (int) $pagination['page'] ?> من <?= (int) $pagination['pages'] ?> (<?= (int) $pagination['total'] ?> موظف)</span>
        <?php if ($pagination['page'] < $pagination['pages']): ?>
        <a href="<?= e(url('/manager/users?page=' . ($pagination['page'] + 1))) ?>" class="btn btn-outline">التالي</a>
        <?php endif; ?>
    </nav>
    <?php endif; ?>
</div>

<?php if (!empty($canBorrowEmployee)): ?>
<?php partial('partials/borrow_employee_section', [
    'borrowable' => $borrowable ?? [],
    'active' => $activeBorrowings ?? [],
    'targetDepartments' => $borrowTargetDepartments ?? [],
]); ?>
<?php endif; ?>

<script>
var roleDefaults = <?= json_encode($roleDefaultsMap ?? PermissionService::roleDefaultsMap(), JSON_UNESCAPED_UNICODE) ?>;

function toggleUserFields() {
    var role = document.getElementById('userRole').value;
    document.getElementById('managerField').style.display = role === 'employee' ? 'block' : 'none';
}

function applyRoleDefaults() {
    var roleEl = document.getElementById('userRole');
    if (!roleEl) return;
    var defaults = roleDefaults[roleEl.value] || [];
    document.querySelectorAll('.perm-cb').forEach(function (cb) {
        cb.checked = defaults.indexOf(cb.value) !== -1;
    });
}

(function () {
    var roleEl = document.getElementById('userRole');
    var previewEl = document.getElementById('userRolePreview');
    function syncRoleToPreview() {
        if (roleEl && previewEl) {
            previewEl.value = roleEl.value;
            previewEl.dispatchEvent(new Event('change'));
        }
    }
    if (roleEl) {
        roleEl.addEventListener('change', function () {
            toggleUserFields();
            syncRoleToPreview();
        });
        toggleUserFields();
        syncRoleToPreview();
    }

    var input = document.getElementById('userSearch');
    var table = document.getElementById('usersTable');
    if (!input || !table) return;
    input.addEventListener('input', function () {
        var q = input.value.trim().toLowerCase();
        table.querySelectorAll('tbody tr[data-search]').forEach(function (row) {
            var hay = row.getAttribute('data-search') || '';
            row.style.display = q === '' || hay.indexOf(q) !== -1 ? '' : 'none';
        });
    });
})();
<?php if (!empty($canBorrowEmployee)): ?>
if (window.location.hash === '#borrow-employees') {
    var borrowSection = document.getElementById('borrow-employees');
    if (borrowSection) {
        borrowSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}
<?php endif; ?>
</script>
