<?php $title = 'توصيف: ' . e($user['name']); ?>
<h1>التوصيف الوظيفي — <?= e($user['name']) ?></h1>
<p class="text-muted"><?= e($user['email']) ?></p>

<div class="card">
    <h2>المسمى الوظيفي</h2>
    <form method="post" action="<?= e(url('/manager/job-description/title')) ?>">
        <?= Csrf::field() ?>
        <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
        <div class="form-group">
            <label>المسمى الوظيفي *</label>
            <input type="text" name="job_title" class="form-control" required
                   value="<?= e($profile['job_title'] ?? '') ?>"
                   placeholder="مثال: محاسب، منسق برامج، سكرتير تنفيذي">
        </div>
        <button type="submit" class="btn">حفظ المسمى الوظيفي</button>
    </form>
</div>

<div class="card">
    <h2>المهام الفرعية</h2>
    <form method="post" action="<?= e(url('/manager/job-description/create')) ?>" style="margin-bottom:1.5rem">
        <?= Csrf::field() ?>
        <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
        <div class="form-group" style="display:flex;gap:0.5rem;align-items:end;flex-wrap:wrap">
            <div style="flex:1;min-width:200px">
                <label>مهمة فرعية جديدة</label>
                <input type="text" name="title" class="form-control" required
                       placeholder="مثال: إعداد التقارير الشهرية">
            </div>
            <button type="submit" class="btn">إضافة</button>
        </div>
    </form>

    <?php if (empty($tasks)): ?>
    <p class="text-muted">لا توجد مهام فرعية بعد.</p>
    <?php else: ?>
    <ul style="list-style:none;padding:0;margin:0;display:grid;gap:0.5rem">
        <?php foreach ($tasks as $i => $t): ?>
        <li style="display:flex;align-items:center;gap:0.5rem;flex-wrap:wrap;padding:0.5rem;border:1px solid var(--border);border-radius:8px">
            <span class="badge badge-evaluated"><?= (int)$i + 1 ?></span>
            <form method="post" action="<?= e(url('/manager/job-description/update')) ?>"
                  style="display:flex;gap:0.5rem;flex:1;align-items:center;flex-wrap:wrap">
                <?= Csrf::field() ?>
                <input type="hidden" name="duty_id" value="<?= (int)$t['id'] ?>">
                <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                <input type="text" name="title" class="form-control" required value="<?= e($t['title']) ?>" style="flex:1;min-width:180px">
                <button type="submit" class="btn btn-outline" style="padding:0.25rem 0.5rem">حفظ</button>
            </form>
            <form method="post" action="<?= e(url('/manager/job-description/delete')) ?>"
                  onsubmit="return confirm('حذف هذه المهمة؟');">
                <?= Csrf::field() ?>
                <input type="hidden" name="duty_id" value="<?= (int)$t['id'] ?>">
                <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                <button type="submit" class="btn btn-outline" style="padding:0.25rem 0.5rem;color:var(--danger,#c00)">حذف</button>
            </form>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</div>

<div style="margin-top:1rem">
    <a href="<?= e(url('/manager/job-description')) ?>" class="btn btn-outline">رجوع للقائمة</a>
</div>
