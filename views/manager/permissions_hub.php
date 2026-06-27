<?php $title = 'مجموعة الصلاحيات'; ?>
<h1>مجموعة صلاحيات النظام</h1>
<p class="text-muted">
    هذه كل الصلاحيات المتاحة في النظام. اختر الموظف من القائمة ثم حدّد له ما يناسبه من المجموعة أدناه.
    متاح لـ <strong>المدير</strong> و<strong>مدير النظام</strong>.
</p>

<div class="card">
    <h2>الصلاحيات المتاحة للإسناد</h2>
    <?php foreach (PermissionService::groupedForPicker() as $group): ?>
    <?php if ($group['codes'] === []) continue; ?>
    <h3 style="font-size:1rem;margin:1.25rem 0 0.5rem;color:var(--muted)"><?= e($group['title']) ?></h3>
    <ul style="margin:0 0 1rem;padding-right:1.25rem;columns:2;gap:1rem">
        <?php foreach ($group['codes'] as $code): ?>
        <li style="break-inside:avoid;margin-bottom:0.35rem"><?= e(PermissionService::label($code)) ?></li>
        <?php endforeach; ?>
    </ul>
    <?php endforeach; ?>
</div>

<div class="card">
    <h2>إسناد الصلاحيات لموظف</h2>
    <div class="form-group" style="max-width:400px;margin-bottom:1rem">
        <label for="employeePick">اختر الموظف</label>
        <select id="employeePick" class="form-control" onchange="goAssignPermissions(this)">
            <option value="">— اختر موظفاً —</option>
            <?php foreach ($employees as $emp): ?>
            <option value="<?= (int)$emp['id'] ?>">
                <?= e($emp['name']) ?> — <?= e(RoleHelper::label($emp['role'])) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    <table>
        <thead>
            <tr><th>الموظف</th><th>الدور</th><th>الدائرة</th><th>عدد الصلاحيات</th><th>إجراء</th></tr>
        </thead>
        <tbody>
        <?php if (empty($employees)): ?>
            <tr><td colspan="5">لا يوجد موظفون</td></tr>
        <?php else: foreach ($employees as $emp): ?>
            <tr>
                <td class="fw-bold"><?= e($emp['name']) ?></td>
                <td><?= e(RoleHelper::label($emp['role'])) ?></td>
                <td><?= e($emp['department_name'] ?? '—') ?></td>
                <td><?= (int)($emp['permission_count'] ?? 0) ?></td>
                <td>
                    <?php if ((int)$emp['id'] !== Auth::id()): ?>
                    <a href="<?= e(url('/manager/users/permissions?id=' . (int)$emp['id'])) ?>" class="btn btn-outline" style="padding:0.25rem 0.5rem;font-size:0.85rem">
                        اختيار الصلاحيات
                    </a>
                    <?php else: ?>—<?php endif; ?>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<script>
function goAssignPermissions(sel) {
    if (!sel.value) return;
    window.location.href = <?= json_encode(url('/manager/users/permissions?id=')) ?> + sel.value;
}
</script>
