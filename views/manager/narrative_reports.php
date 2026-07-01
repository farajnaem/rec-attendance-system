<?php
$title = 'التقارير السردية';
$months = [1=>'يناير',2=>'فبراير',3=>'مارس',4=>'أبريل',5=>'مايو',6=>'يونيو',7=>'يوليو',8=>'أغسطس',9=>'سبتمبر',10=>'أكتوبر',11=>'نوفمبر',12=>'ديسمبر'];
?>
<h1>التقارير السردية للموظفين</h1>
<p class="text-muted">التقارير المقدّمة من الموظفين — للمشرف والمدير.</p>

<form method="get" class="grid-2 no-print" style="margin-bottom:1rem;max-width:480px">
    <div class="form-group">
        <label>السنة</label>
        <input type="number" name="year" class="form-control" value="<?= (int) $year ?>" min="2020" max="2100">
    </div>
    <div class="form-group">
        <label>الشهر</label>
        <select name="month" class="form-control">
            <?php foreach ($months as $m => $label): ?>
            <option value="<?= $m ?>" <?= $month === $m ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div><button type="submit" class="btn">عرض</button></div>
</form>

<div class="card">
    <h2><?= e($months[$month] ?? '') ?> <?= (int) $year ?></h2>
    <?php if (empty($reports)): ?>
    <p class="text-muted">لا توجد تقارير سردية مقدّمة لهذا الشهر.</p>
    <?php else: foreach ($reports as $n): ?>
    <div class="card" style="margin-top:1rem;padding:1rem;background:var(--surface-2)">
        <h3 style="margin:0 0 0.5rem"><?= e($n['user_name']) ?></h3>
        <p class="text-muted" style="font-size:0.85rem;margin:0 0 0.75rem">قدّم بتاريخ <?= e(substr($n['submitted_at'], 0, 16)) ?></p>
        <?php if (!empty($n['work_summary'])): ?>
        <p><strong>ملخص الأعمال:</strong><br><?= nl2br(e($n['work_summary'])) ?></p>
        <?php endif; ?>
        <?php if (!empty($n['difficulties'])): ?>
        <p><strong>الصعوبات:</strong><br><?= nl2br(e($n['difficulties'])) ?></p>
        <?php endif; ?>
        <?php if (!empty($n['positives'])): ?>
        <p><strong>الإيجابيات:</strong><br><?= nl2br(e($n['positives'])) ?></p>
        <?php endif; ?>
        <?php if (!empty($n['negatives'])): ?>
        <p><strong>السلبيات:</strong><br><?= nl2br(e($n['negatives'])) ?></p>
        <?php endif; ?>
        <?php if (!empty($n['development_notes'])): ?>
        <p><strong>ملاحظات التطوير:</strong><br><?= nl2br(e($n['development_notes'])) ?></p>
        <?php endif; ?>
    </div>
    <?php endforeach; endif; ?>
</div>
