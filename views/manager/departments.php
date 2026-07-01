<?php
$title = !empty($canManageDepartments) ? 'إدارة الدوائر' : 'الدوائر';
$canManage = !empty($canManageDepartments);
?>
<h1><?= e($title) ?></h1>
<p class="text-muted">
    <?php if ($canManage): ?>
    أضف دوائر المؤسسة وعيّن مشرف برنامج لكل دائرة. متاح للمدير فقط.
    <?php else: ?>
    الاطلاع على الدوائر المسجّلة ومشرفيها وعدد الأعضاء — قراءة فقط.
    <?php endif; ?>
</p>

<?php if ($canManage): ?>
<div class="card" id="addDeptPanel" hidden>
    <h2>دائرة جديدة</h2>
    <form method="post" action="<?= e(url('/manager/departments/create')) ?>">
        <?= Csrf::field() ?>
        <div class="grid-2">
            <div class="form-group">
                <label>اسم الدائرة *</label>
                <input type="text" name="name" class="form-control" required placeholder="مثال: دائرة الإرشاد">
            </div>
            <div class="form-group">
                <label>مشرف الدائرة (مشرف برنامج)</label>
                <select name="supervisor_id" class="form-control">
                    <option value="">— لاحقاً —</option>
                    <?php foreach ($supervisors as $s): ?>
                    <option value="<?= (int)$s['id'] ?>">
                        <?= e($s['name']) ?> (<?= e(RoleHelper::label($s['role'])) ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="grid-column:1/-1">
                <label>الوصف</label>
                <input type="text" name="description" class="form-control" placeholder="وصف مختصر للدائرة">
            </div>
        </div>
        <div style="display:flex;gap:0.5rem;margin-top:1rem">
            <button type="submit" class="btn">إضافة الدائرة</button>
            <button type="button" class="btn btn-outline" onclick="toggleAddPanel('addDeptPanel', false)">إلغاء</button>
        </div>
    </form>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header-row">
        <h2>الدوائر المسجّلة</h2>
        <?php if ($canManage): ?>
        <button type="button" class="btn" onclick="toggleAddPanel('addDeptPanel')">+ إضافة</button>
        <?php endif; ?>
    </div>
    <?php if (empty($departments)): ?>
        <p class="text-muted">لا توجد دوائر بعد.</p>
    <?php else: ?>
    <table>
        <thead>
            <tr>
                <th>الدائرة</th>
                <th>مشرف البرنامج</th>
                <th>الأعضاء</th>
                <th>الوصف</th>
                <?php if ($canManage): ?><th>إجراء</th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($departments as $d): ?>
            <tr>
                <td class="fw-bold"><?= e($d['name']) ?></td>
                <td><?= e($d['supervisor_name'] ?? '—') ?></td>
                <td><?= (int)($d['member_count'] ?? 0) ?></td>
                <td><?= e($d['description'] ?? '—') ?></td>
                <?php if ($canManage): ?>
                <td class="text-nowrap">
                    <button type="button" class="btn btn-outline" style="padding:0.25rem 0.5rem;font-size:0.85rem"
                            onclick="editDept(<?= (int)$d['id'] ?>, <?= e(json_encode($d['name'], JSON_UNESCAPED_UNICODE)) ?>, <?= e(json_encode($d['description'] ?? '', JSON_UNESCAPED_UNICODE)) ?>, <?= (int)($d['supervisor_id'] ?? 0) ?>)">
                        تعديل
                    </button>
                    <form method="post" action="<?= e(url('/manager/departments/delete')) ?>" style="display:inline"
                          onsubmit="return confirm('تعطيل الدائرة <?= e(addslashes($d['name'])) ?>؟');">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="department_id" value="<?= (int)$d['id'] ?>">
                        <button type="submit" class="btn btn-danger" style="padding:0.25rem 0.5rem;font-size:0.85rem">حذف</button>
                    </form>
                </td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php if ($canManage): ?>
<div class="card" id="editPanel" style="display:none">
    <h2>تعديل دائرة</h2>
    <form method="post" action="<?= e(url('/manager/departments/update')) ?>" id="editForm">
        <?= Csrf::field() ?>
        <input type="hidden" name="department_id" id="editDeptId">
        <div class="grid-2">
            <div class="form-group">
                <label>اسم الدائرة *</label>
                <input type="text" name="name" id="editName" class="form-control" required>
            </div>
            <div class="form-group">
                <label>مشرف الدائرة</label>
                <select name="supervisor_id" id="editSupervisor" class="form-control">
                    <option value="">— بدون —</option>
                    <?php foreach ($supervisors as $s): ?>
                    <option value="<?= (int)$s['id'] ?>">
                        <?= e($s['name']) ?> (<?= e(RoleHelper::label($s['role'])) ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="grid-column:1/-1">
                <label>الوصف</label>
                <input type="text" name="description" id="editDesc" class="form-control">
            </div>
        </div>
        <button type="submit" class="btn">حفظ التعديلات</button>
        <button type="button" class="btn btn-outline" onclick="document.getElementById('editPanel').style.display='none'">إلغاء</button>
    </form>
</div>

<script>
function editDept(id, name, desc, supervisorId) {
    document.getElementById('editDeptId').value = id;
    document.getElementById('editName').value = name;
    document.getElementById('editDesc').value = desc || '';
    document.getElementById('editSupervisor').value = supervisorId || '';
    document.getElementById('editPanel').style.display = 'block';
    document.getElementById('editPanel').scrollIntoView({behavior:'smooth'});
}
</script>
<?php endif; ?>
