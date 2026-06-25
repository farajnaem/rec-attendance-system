<?php $title = 'حضور الفريق'; ?>
<h1>حضور الفريق</h1>

<form method="get" class="no-print" style="margin-bottom:1rem;display:flex;gap:1rem;align-items:end">
    <div class="form-group" style="margin:0">
        <label>التاريخ</label>
        <input type="date" name="date" class="form-control" value="<?= e($date) ?>">
    </div>
    <button type="submit" class="btn">عرض</button>
</form>

<div class="card">
    <table>
        <thead><tr><th>المستخدم</th><th>المنصب</th><th>المنطقة الزمنية</th><th>حضور</th><th>انصراف</th><th>موقع العمل</th><th>الحالة</th></tr></thead>
        <tbody>
        <?php if (empty($attendance)): ?>
            <tr><td colspan="7">لا يوجد مستخدمون أو لا توجد بيانات</td></tr>
        <?php else: foreach ($attendance as $a): ?>
        <?php $complete = $a['check_in_utc'] && $a['check_out_utc']; ?>
        <tr>
            <td><?= e($a['name']) ?></td>
            <td><?= e(RoleHelper::label($a['role'] ?? 'employee')) ?></td>
            <td><?= e(TimezoneHelper::commonTimezones()[$a['timezone']] ?? $a['timezone']) ?></td>
            <td><?= $a['check_in_utc'] ? e(TimezoneHelper::formatArabic($a['check_in_utc'], $a['timezone'])) : '—' ?></td>
            <td><?= $a['check_out_utc'] ? e(TimezoneHelper::formatArabic($a['check_out_utc'], $a['timezone'])) : '—' ?></td>
            <td><?= e($a['work_location_name'] ?? '—') ?></td>
            <td><?= $complete ? '<span class="badge badge-evaluated">حضور كامل</span>' : '<span class="badge badge-pending">ناقص</span>' ?></td>
        </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>
