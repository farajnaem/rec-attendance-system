<?php $title = 'حضور الفريق'; ?>
<h1>حضور الفريق</h1>

<form method="get" class="no-print" style="margin-bottom:1rem;display:flex;gap:1rem;align-items:end;flex-wrap:wrap">
    <div class="form-group" style="margin:0">
        <label>من تاريخ</label>
        <input type="date" name="from" class="form-control" value="<?= e($from) ?>">
    </div>
    <div class="form-group" style="margin:0">
        <label>إلى تاريخ</label>
        <input type="date" name="to" class="form-control" value="<?= e($to) ?>">
    </div>
    <button type="submit" class="btn">عرض</button>
    <a href="<?= e(url('/manager/attendance/export?from=' . urlencode($from) . '&to=' . urlencode($to))) ?>" class="btn btn-outline">تصدير CSV</a>
</form>

<?php if (!empty($canManual) && !empty($staff)): ?>
<div class="card" style="margin-bottom:1rem">
    <h2>تصحيح حضور يدوي</h2>
    <p class="text-muted">لإضافة سجل حضور أو انصراف ناقص مع ذكر السبب.</p>
    <form method="post" action="<?= e(url('/manager/attendance/manual')) ?>">
        <?= Csrf::field() ?>
        <div class="grid-2">
            <div class="form-group">
                <label>الموظف</label>
                <select name="user_id" class="form-control" required>
                    <?php foreach ($staff as $member): ?>
                    <option value="<?= (int) $member['id'] ?>"><?= e($member['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>النوع</label>
                <select name="type" class="form-control" required>
                    <option value="check_in">حضور</option>
                    <option value="check_out">انصراف</option>
                </select>
            </div>
            <div class="form-group">
                <label>التاريخ</label>
                <input type="date" name="local_date" class="form-control" value="<?= e(date('Y-m-d')) ?>" required>
            </div>
            <div class="form-group">
                <label>سبب التصحيح</label>
                <input type="text" name="reason" class="form-control" required placeholder="مثال: نسيان التوقيع">
            </div>
        </div>
        <button type="submit" class="btn btn-warning">تسجيل التصحيح</button>
    </form>
</div>
<?php endif; ?>

<div class="card">
    <table>
        <thead><tr><th>الموظف</th><th>النوع</th><th>التاريخ</th><th>التوقيت</th><th>موقع العمل</th><th>تفاصيل</th></tr></thead>
        <tbody>
        <?php if (empty($records)): ?>
            <tr><td colspan="6">لا توجد سجلات في هذه الفترة</td></tr>
        <?php else: foreach ($records as $r): ?>
        <?php $tz = $r['timezone'] ?? $r['user_timezone'] ?? TimezoneHelper::defaultTimezone(); ?>
        <tr>
            <td><?= e($r['user_name']) ?></td>
            <td><?= $r['type'] === 'check_in' ? 'حضور' : 'انصراف' ?></td>
            <td><?= e($r['local_work_date']) ?></td>
            <td><?= e(TimezoneHelper::formatArabic($r['signed_at_utc'], $tz)) ?></td>
            <td><?= e($r['work_location_name'] ?? '—') ?></td>
            <td>
                <a href="<?= e(url('/manager/attendance/detail?id=' . (int) $r['id'])) ?>" class="btn btn-outline" style="padding:0.25rem 0.5rem;font-size:0.85rem">عرض</a>
            </td>
        </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>
