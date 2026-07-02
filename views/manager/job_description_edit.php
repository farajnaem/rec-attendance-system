<?php $title = 'توصيف: ' . e($user['name']); ?>
<h1>التوصيف الوظيفي — <?= e($user['name']) ?></h1>
<p class="text-muted"><?= e($user['email']) ?></p>

<div class="card">
    <form method="post" action="<?= e(url('/manager/job-description/save')) ?>">
        <?= Csrf::field() ?>
        <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
        <div class="form-group">
            <label>المسمى الوظيفي *</label>
            <input type="text" name="job_title" class="form-control" required
                   value="<?= e($profile['job_title'] ?? '') ?>"
                   placeholder="مثال: محاسب، منسق برامج، سكرتير تنفيذي">
        </div>
        <div class="form-group">
            <label for="duties_body">الوصف الوظيفي / التبعية الوظيفية</label>
            <p class="text-muted" style="margin-top:0;font-size:0.875rem">
                اكتب مهام الموظف ومسؤولياته وتبعيته الإدارية — سطر لكل نقطة أو فقرة حسب الحاجة.
            </p>
            <textarea id="duties_body" name="duties_body" class="form-control job-duties-editor" rows="22"
                      placeholder="مثال:&#10;• إعداد التقارير الشهرية&#10;• متابعة حضور المتطوعين&#10;• التنسيق مع مشرف البرنامج"><?= e($dutiesBody ?? '') ?></textarea>
        </div>
        <div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-top:1rem">
            <button type="submit" class="btn">حفظ التوصيف الوظيفي</button>
            <a href="<?= e(url('/manager/job-description')) ?>" class="btn btn-outline">رجوع للقائمة</a>
        </div>
    </form>
</div>
