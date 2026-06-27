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

<div class="report-layout">
    <aside class="report-narrative no-print">
        <div class="card">
            <h2>التقرير السردي</h2>
            <p class="text-muted" style="font-size:0.9rem">
                قدّم ملاحظاتك حول أعمال هذا الشهر. آخر موعد للتقديم:
                <strong><?= e($narrativeDeadline) ?></strong>
                (<?= (int)$submissionDays ?> أيام بعد نهاية الشهر)
            </p>
            <?php if (!$canEditNarrative): ?>
            <p class="alert alert-warning">انتهت مهلة تقديم أو تعديل التقرير السردي لهذا الشهر.</p>
            <?php endif; ?>
            <?php if (!empty($narrative['submitted_at'])): ?>
            <p class="alert alert-success">تم التقديم بتاريخ <?= e(substr($narrative['submitted_at'], 0, 16)) ?></p>
            <?php endif; ?>
            <form method="post" action="<?= e(url('/employee/report/narrative/save')) ?>">
                <?= Csrf::field() ?>
                <input type="hidden" name="year" value="<?= (int)$year ?>">
                <input type="hidden" name="month" value="<?= (int)$month ?>">
                <div class="form-group">
                    <label>ملخص أعمال الشهر</label>
                    <textarea name="work_summary" class="form-control" rows="4"
                              <?= $canEditNarrative ? '' : 'readonly' ?>><?= e($narrative['work_summary'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label>الإيجابيات</label>
                    <textarea name="positives" class="form-control" rows="3"
                              <?= $canEditNarrative ? '' : 'readonly' ?>><?= e($narrative['positives'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label>السلبيات</label>
                    <textarea name="negatives" class="form-control" rows="3"
                              <?= $canEditNarrative ? '' : 'readonly' ?>><?= e($narrative['negatives'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label>ملاحظات التطوير</label>
                    <textarea name="development_notes" class="form-control" rows="3"
                              <?= $canEditNarrative ? '' : 'readonly' ?>><?= e($narrative['development_notes'] ?? '') ?></textarea>
                </div>
                <?php if ($canEditNarrative): ?>
                <div style="display:flex;flex-wrap:wrap;gap:0.5rem">
                    <button type="submit" class="btn btn-outline">حفظ مسودة</button>
                    <button type="submit" name="submit_final" value="1" class="btn">تقديم التقرير</button>
                </div>
                <?php endif; ?>
            </form>
        </div>
    </aside>

    <div class="report-data">
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

        <?php if (!empty($narrative['work_summary']) || !empty($narrative['positives'])): ?>
        <div class="card print-only-narrative">
            <h2>التقرير السردي</h2>
            <?php if (!empty($narrative['work_summary'])): ?>
            <p><strong>ملخص الأعمال:</strong><br><?= nl2br(e($narrative['work_summary'])) ?></p>
            <?php endif; ?>
            <?php if (!empty($narrative['positives'])): ?>
            <p><strong>الإيجابيات:</strong><br><?= nl2br(e($narrative['positives'])) ?></p>
            <?php endif; ?>
            <?php if (!empty($narrative['negatives'])): ?>
            <p><strong>السلبيات:</strong><br><?= nl2br(e($narrative['negatives'])) ?></p>
            <?php endif; ?>
            <?php if (!empty($narrative['development_notes'])): ?>
            <p><strong>ملاحظات التطوير:</strong><br><?= nl2br(e($narrative['development_notes'])) ?></p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

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
    </div>
</div>
