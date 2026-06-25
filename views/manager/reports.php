<?php
$title = 'التقارير الشهرية';
$months = [1=>'يناير',2=>'فبراير',3=>'مارس',4=>'أبريل',5=>'مايو',6=>'يونيو',7=>'يوليو',8=>'أغسطس',9=>'سبتمبر',10=>'أكتوبر',11=>'نوفمبر',12=>'ديسمبر'];
?>
<h1>التقارير الشهرية</h1>

<form method="get" class="no-print card grid-2">
    <div class="form-group">
        <label>الموظف</label>
        <select name="employee_id" class="form-control">
            <?php foreach ($employees as $emp): ?>
            <option value="<?= (int)$emp['id'] ?>" <?= $employeeId === (int)$emp['id'] ? 'selected' : '' ?>>
                <?= e($emp['name']) ?> (<?= e(RoleHelper::label($emp['role'] ?? 'employee')) ?>)
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label>السنة</label>
        <input type="number" name="year" class="form-control" value="<?= (int)$year ?>">
    </div>
    <div class="form-group">
        <label>الشهر</label>
        <select name="month" class="form-control">
            <?php foreach ($months as $m => $label): ?>
            <option value="<?= $m ?>" <?= $month === $m ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div><button type="submit" class="btn">عرض التقرير</button>
    <?php if ($report): ?>
    <button type="button" class="btn btn-outline" onclick="window.print()">طباعة</button>
    <a href="<?= e(url('/manager/reports/export?employee_id=' . (int) $employeeId . '&year=' . (int) $year . '&month=' . (int) $month)) ?>" class="btn btn-outline">تصدير CSV</a>
    <?php endif; ?>
    </div>
</form>

<?php if ($report): ?>
<h2><?= e($report['user']['name']) ?> — <?= e($months[$month] ?? '') ?> <?= (int)$year ?></h2>
<div class="stats">
    <div class="stat-box">
        <div class="value"><?= e((string)$report['attendance']['attendance_score']) ?>%</div>
        <div class="label">درجة الحضور</div>
    </div>
    <div class="stat-box">
        <div class="value"><?= $report['performance']['performance_score'] !== null ? e((string)$report['performance']['performance_score']) . '%' : '—' ?></div>
        <div class="label">درجة الأداء</div>
    </div>
</div>

<div class="card">
    <h3>ملخص الحضور</h3>
    <p>أيام حضور كامل: <?= (int)$report['attendance']['full_days'] ?> من <?= (int)$report['attendance']['expected_workdays'] ?> يوم عمل</p>
</div>

<div class="card">
    <h3>سجل الحضور اليومي</h3>
    <table>
        <thead><tr><th>التاريخ</th><th>إجازة</th><th>حضور</th><th>انصراف</th><th>كامل</th><th>متأخر</th></tr></thead>
        <tbody>
        <?php foreach ($report['attendance']['daily'] as $d): if (!$d['is_workday']) continue; ?>
        <tr>
            <td><?= e($d['date']) ?></td>
            <td><?= $d['on_leave'] ? 'نعم' : '—' ?></td>
            <td><?= $d['check_in'] ? '✓' : '—' ?></td>
            <td><?= $d['check_out'] ? '✓' : '—' ?></td>
            <td><?= $d['complete'] ? '✓' : '✗' ?></td>
            <td><?= $d['late'] ? 'نعم' : '—' ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="card">
    <h3>المهام</h3>
    <table>
        <thead><tr><th>التاريخ</th><th>المهمة</th><th>الحالة</th><th>الدرجة</th></tr></thead>
        <tbody>
        <?php foreach ($report['performance']['tasks'] as $t): ?>
        <tr>
            <td><?= e($t['task_date']) ?></td>
            <td><?= e($t['title']) ?></td>
            <td><?= e(statusLabel($t['status'])) ?></td>
            <td><?= $t['score'] !== null ? e((string)$t['score']) . '/10' : '—' ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php elseif (empty($employees)): ?>
<p class="text-muted">لا يوجد موظفون لعرض تقاريرهم.</p>
<?php endif; ?>
