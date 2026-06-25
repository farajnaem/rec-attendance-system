<?php
$title = 'التقرير الشهري';
$months = [1=>'يناير',2=>'فبراير',3=>'مارس',4=>'أبريل',5=>'مايو',6=>'يونيو',7=>'يوليو',8=>'أغسطس',9=>'سبتمبر',10=>'أكتوبر',11=>'نوفمبر',12=>'ديسمبر'];
?>
<div class="no-print" style="margin-bottom:1rem">
    <form method="get" class="grid-2">
        <div class="form-group">
            <label>السنة</label>
            <input type="number" name="year" class="form-control" value="<?= (int)$year ?>" min="2020" max="2100">
        </div>
        <div class="form-group">
            <label>الشهر</label>
            <select name="month" class="form-control">
                <?php foreach ($months as $m => $label): ?>
                <option value="<?= $m ?>" <?= $month === $m ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div><button type="submit" class="btn">عرض</button>
        <button type="button" class="btn btn-outline" onclick="window.print()">طباعة</button></div>
    </form>
</div>

<h1>التقرير الشهري — <?= e($report['user']['name']) ?></h1>
<p><?= e($months[$month] ?? '') ?> <?= (int)$year ?></p>

<div class="stats">
    <div class="stat-box">
        <div class="value"><?= e((string)$report['attendance']['attendance_score']) ?>%</div>
        <div class="label">درجة الحضور</div>
    </div>
    <div class="stat-box">
        <div class="value"><?= $report['performance']['performance_score'] !== null ? e((string)$report['performance']['performance_score']) . '%' : '—' ?></div>
        <div class="label">درجة الأداء</div>
    </div>
    <div class="stat-box">
        <div class="value"><?= (int)$report['attendance']['full_days'] ?>/<?= (int)$report['attendance']['expected_workdays'] ?></div>
        <div class="label">أيام حضور كامل</div>
    </div>
    <div class="stat-box">
        <div class="value"><?= (int)$report['performance']['evaluated_count'] ?></div>
        <div class="label">مهام مُقيَّمة</div>
    </div>
</div>

<div class="card">
    <h2>سجل الحضور اليومي</h2>
    <table>
        <thead><tr><th>التاريخ</th><th>يوم عمل</th><th>إجازة</th><th>حضور</th><th>انصراف</th><th>كامل</th><th>متأخر</th></tr></thead>
        <tbody>
        <?php foreach ($report['attendance']['daily'] as $d): if (!$d['is_workday']) continue; ?>
        <tr>
            <td><?= e($d['date']) ?></td>
            <td>نعم</td>
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
    <h2>المهام والتقييمات</h2>
    <table>
        <thead><tr><th>التاريخ</th><th>المهمة</th><th>الحالة</th><th>الدرجة</th><th>ملاحظات التقييم</th></tr></thead>
        <tbody>
        <?php if (empty($report['performance']['tasks'])): ?>
            <tr><td colspan="5">لا توجد مهام هذا الشهر</td></tr>
        <?php else: foreach ($report['performance']['tasks'] as $t): ?>
        <tr>
            <td><?= e($t['task_date']) ?></td>
            <td><?= e($t['title']) ?></td>
            <td><?= e(statusLabel($t['status'])) ?></td>
            <td><?= $t['score'] !== null ? e((string)$t['score']) . '/10' : '—' ?></td>
            <td><?= e($t['evaluation_notes'] ?? '') ?></td>
        </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>
