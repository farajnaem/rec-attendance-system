<?php
/**
 * @var array $granted الصلاحيات المفعّلة حالياً
 * @var string|null $targetRole دور الموظف (للتلميح «افتراضي»)
 * @var bool $showRolePreview إظهار قائمة معاينة افتراضيات الدور
 * @var string|null $rolePreviewId معرّف select المعاينة
 */
$granted = $granted ?? [];
$targetRole = $targetRole ?? null;
$showRolePreview = $showRolePreview ?? true;
$rolePreviewId = $rolePreviewId ?? 'rolePreview';
$pool = PermissionService::assignablePool();
$groups = PermissionService::groupedForPicker();
?>
<?php if ($showRolePreview): ?>
<div class="form-group" style="max-width:360px">
    <label for="<?= e($rolePreviewId) ?>">تعبئة سريعة — افتراضيات دور</label>
    <select id="<?= e($rolePreviewId) ?>" class="form-control perm-role-preview">
        <?php foreach (RoleHelper::all() as $roleKey => $roleName): ?>
        <option value="<?= e($roleKey) ?>"
            <?= $targetRole && RoleHelper::normalizeRole($targetRole) === $roleKey ? 'selected' : '' ?>>
            <?= e($roleName) ?>
        </option>
        <?php endforeach; ?>
    </select>
    <small class="text-muted">يُحدّث المربعات أدناه — يمكنك التعديل قبل الحفظ</small>
</div>
<?php endif; ?>

<div class="permission-picker">
<?php foreach ($groups as $groupKey => $group): ?>
<?php if ($group['codes'] === []) continue; ?>
<div class="permission-picker__group">
    <h3 class="permission-picker__title"><?= e($group['title']) ?></h3>
    <div class="permission-picker__grid">
        <?php foreach ($group['codes'] as $code):
            if (!isset($pool[$code])) continue;
            $def = $pool[$code];
        ?>
        <label class="permission-picker__item">
            <input type="checkbox" name="permissions[]" value="<?= e($code) ?>" class="perm-cb"
                <?= in_array($code, $granted, true) ? 'checked' : '' ?>>
            <span>
                <?= e($def['label']) ?>
                <?php if ($targetRole && in_array(RoleHelper::normalizeRole($targetRole), $def['defaults'], true)): ?>
                <small class="text-muted">(افتراضي)</small>
                <?php endif; ?>
            </span>
        </label>
        <?php endforeach; ?>
    </div>
</div>
<?php endforeach; ?>
</div>

<script>
(function () {
    if (typeof roleDefaults === 'undefined') {
        window.roleDefaults = <?= json_encode(PermissionService::roleDefaultsMap(), JSON_UNESCAPED_UNICODE) ?>;
    }
    var preview = document.getElementById(<?= json_encode($rolePreviewId) ?>);
    if (!preview || !preview.classList.contains('perm-role-preview')) return;
    preview.addEventListener('change', function () {
        var defaults = roleDefaults[preview.value] || [];
        document.querySelectorAll('.perm-cb').forEach(function (cb) {
            cb.checked = defaults.indexOf(cb.value) !== -1;
        });
    });
    window.applyRoleDefaults = window.applyRoleDefaults || function () {
        var el = document.querySelector('.perm-role-preview');
        if (!el) return;
        var defaults = roleDefaults[el.value] || [];
        document.querySelectorAll('.perm-cb').forEach(function (cb) {
            cb.checked = defaults.indexOf(cb.value) !== -1;
        });
    };
    window.applyPreviewDefaults = window.applyRoleDefaults;
})();
</script>
