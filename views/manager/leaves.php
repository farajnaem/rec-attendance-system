<?php
$title = 'إدارة الإجازات';
$statusLabels = [
    'pending' => 'قيد الانتظار',
    'approved' => 'موافق عليها',
    'rejected' => 'مرفوضة',
];
?>
<div class="page-header">
    <h1>إدارة الإجازات</h1>
    <p class="page-header__subtitle">طلبات الإجازة ضمن نطاق إشرافك</p>
</div>

<form method="get" class="no-print card" style="margin-bottom:1rem;display:flex;gap:1rem;align-items:end;flex-wrap:wrap">
    <div class="form-group" style="margin:0">
        <label for="status">الحالة</label>
        <select name="status" id="status" class="form-control">
            <option value="">— الكل —</option>
            <?php foreach ($statusLabels as $key => $label): ?>
            <option value="<?= e($key) ?>" <?= ($status ?? '') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <button type="submit" class="btn">تصفية</button>
</form>

<?php if (!empty($pendingBreaks) && Auth::can('approve_work_breaks')): ?>
<div class="card" style="margin-bottom:1rem">
    <h2>مغادرات أثناء العمل — بانتظار الاعتماد</h2>
    <table>
        <thead>
            <tr><th>الموظف</th><th>التاريخ</th><th>خروج</th><th>عودة</th><th>معطي الإذن</th><th>ملاحظات</th><th>إجراء</th></tr>
        </thead>
        <tbody>
        <?php foreach ($pendingBreaks as $wb): ?>
        <tr>
            <td><?= e($wb['employee_name']) ?></td>
            <td><?= e($wb['work_date']) ?></td>
            <td><?= e($wb['exit_time']) ?></td>
            <td><?= e($wb['return_time']) ?></td>
            <td><?= e($wb['authorized_by_name']) ?></td>
            <td><?= e($wb['notes'] ?? '—') ?></td>
            <td class="text-nowrap">
                <form method="post" action="<?= e(url('/manager/work-break/approve')) ?>" style="display:inline">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="break_id" value="<?= (int) $wb['id'] ?>">
                    <button type="submit" class="btn btn-success" style="padding:0.25rem 0.5rem;font-size:0.85rem">موافقة</button>
                </form>
                <form method="post" action="<?= e(url('/manager/work-break/reject')) ?>" style="display:inline">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="break_id" value="<?= (int) $wb['id'] ?>">
                    <button type="submit" class="btn btn-danger" style="padding:0.25rem 0.5rem;font-size:0.85rem">رفض</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<div class="card">
    <h2>طلبات الإجازة</h2>
        <thead>
            <tr>
                <th>الموظف</th>
                <th>النوع</th>
                <th>من</th>
                <th>إلى</th>
                <th>الحالة</th>
                <th>ملاحظات</th>
                <th>إجراء</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($leaves)): ?>
            <tr><td colspan="7">لا توجد طلبات إجازة</td></tr>
        <?php else: foreach ($leaves as $leave): ?>
            <tr>
                <td><?= e($leave['user_name']) ?></td>
                <td><?= e(LeaveHelper::label($leave['leave_type'])) ?></td>
                <td><?= e($leave['start_date']) ?></td>
                <td><?= e($leave['end_date']) ?></td>
                <td>
                    <?php
                    $st = $leave['status'] ?? 'pending';
                    $badge = match ($st) {
                        'approved' => 'badge-evaluated',
                        'rejected' => 'badge-pending',
                        default => 'badge-pending',
                    };
                    ?>
                    <span class="badge <?= $badge ?>"><?= e($statusLabels[$st] ?? $st) ?></span>
                </td>
                <td><?= e($leave['notes'] ?? '—') ?></td>
                <td class="text-nowrap">
                    <?php if ($st === 'pending'): ?>
                    <form method="post" action="<?= e(url('/manager/leaves/approve')) ?>" style="display:inline">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="leave_id" value="<?= (int) $leave['id'] ?>">
                        <button type="submit" class="btn btn-success" style="padding:0.25rem 0.5rem;font-size:0.85rem">موافقة</button>
                    </form>
                    <form method="post" action="<?= e(url('/manager/leaves/reject')) ?>" style="display:inline">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="leave_id" value="<?= (int) $leave['id'] ?>">
                        <button type="submit" class="btn btn-danger" style="padding:0.25rem 0.5rem;font-size:0.85rem">رفض</button>
                    </form>
                    <?php else: ?>—<?php endif; ?>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>
