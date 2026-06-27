<?php
$title = 'ساعات الدوام الرسمي';
$canSchedule = Auth::can('manage_work_schedule') || RoleHelper::isSystemAdmin(Auth::role());
$canDeadline = Auth::can('manage_report_deadline') || RoleHelper::isSystemAdmin(Auth::role());
?>
<h1>ساعات الدوام الرسمي</h1>
<p class="text-muted">
    يُحسب <strong>التأخير</strong> عند تسجيل الحضور بعد وقت البدء + مهلة السماح.
</p>

<div class="card">
    <form method="post" action="<?= e(url('/manager/work-schedule/save')) ?>">
        <?= Csrf::field() ?>
        <div class="grid-2">
            <?php if ($canSchedule): ?>
            <div class="form-group">
                <label>وقت بدء الدوام *</label>
                <input type="time" name="work_start_time" class="form-control"
                       value="<?= e($schedule['work_start_time'] ?? '08:00') ?>" required>
            </div>
            <div class="form-group">
                <label>وقت انتهاء الدوام *</label>
                <input type="time" name="work_end_time" class="form-control"
                       value="<?= e($schedule['work_end_time'] ?? '16:00') ?>" required>
            </div>
            <div class="form-group">
                <label>مهلة التأخير (دقائق)</label>
                <input type="number" name="late_grace_minutes" class="form-control" min="0" max="120"
                       value="<?= (int)($schedule['late_grace_minutes'] ?? 15) ?>">
                <small class="text-muted">مثال: 15 = لا يُحسب متأخراً قبل 08:15</small>
            </div>
            <div class="form-group">
                <label>أيام العمل</label>
                <div style="display:flex;flex-wrap:wrap;gap:0.5rem;margin-top:0.5rem">
                    <?php
                    $selected = explode(',', $schedule['work_days'] ?? '0,1,2,3,4');
                    foreach (WorkScheduleService::workDayLabels() as $num => $label):
                    ?>
                    <label style="display:flex;align-items:center;gap:0.25rem">
                        <input type="checkbox" name="work_days[]" value="<?= e($num) ?>"
                            <?= in_array($num, $selected, true) ? 'checked' : '' ?>>
                        <?= e($label) ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php else: ?>
            <input type="hidden" name="work_start_time" value="<?= e($schedule['work_start_time'] ?? '08:00') ?>">
            <input type="hidden" name="work_end_time" value="<?= e($schedule['work_end_time'] ?? '16:00') ?>">
            <input type="hidden" name="late_grace_minutes" value="<?= (int)($schedule['late_grace_minutes'] ?? 15) ?>">
            <?php foreach (explode(',', $schedule['work_days'] ?? '0,1,2,3,4') as $wd): ?>
            <input type="hidden" name="work_days[]" value="<?= e(trim($wd)) ?>">
            <?php endforeach; ?>
            <?php endif; ?>
            <?php if ($canDeadline): ?>
            <div class="form-group">
                <label>مهلة تقديم التقرير السردي (أيام بعد نهاية الشهر)</label>
                <input type="number" name="report_submission_days" class="form-control" min="1" max="31"
                       value="<?= (int)($schedule['report_submission_days'] ?? 5) ?>">
                <small class="text-muted">عدد الأيام التي يستطيع فيها الموظف رفع تقريره السردي بعد انتهاء الشهر</small>
            </div>
            <?php endif; ?>
        </div>
        <button type="submit" class="btn">حفظ الإعدادات</button>
        <a href="<?= e(url('/manager/system')) ?>" class="btn btn-outline">رجوع لإدارة النظام</a>
    </form>
</div>

<?php if (!$canSchedule && $canDeadline): ?>
<p class="text-muted">يمكنك تعديل مهلة التقرير السردي فقط — ساعات الدوام من اختصاص المدير.</p>
<?php endif; ?>
