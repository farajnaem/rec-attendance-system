<?php $title = 'ساعات الدوام الرسمي'; ?>
<h1>ساعات الدوام الرسمي</h1>
<p class="text-muted">
    يُحسب <strong>التأخير</strong> عند تسجيل الحضور بعد وقت البدء + مهلة السماح.
    التعديل متاح لـ <strong>مدير النظام</strong> و<strong>المدير</strong> فقط.
</p>

<div class="card">
    <form method="post" action="<?= e(url('/manager/work-schedule/save')) ?>">
        <?= Csrf::field() ?>
        <div class="grid-2">
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
        </div>
        <button type="submit" class="btn">حفظ ساعات الدوام</button>
    </form>
</div>

<div class="card">
    <h3>ملاحظة</h3>
    <p class="text-muted mb-0">
        في المرحلة القادمة سيُطبَّق التأخير تلقائياً عند تسجيل الحضور بالموقع الجغرافي.
        أنواع الإجازات المعتمدة: إجازة مرضية، طارئة، عادية.
    </p>
</div>
