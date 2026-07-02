<?php $title = 'التوصيف الوظيفي'; ?>
<h1>التوصيف الوظيفي</h1>
<p class="text-muted">
    لكل موظف: <strong>مسمى وظيفي</strong> و<strong>الوصف الوظيفي / التبعية الوظيفية</strong> في حقل واحد شامل.
    <?php if (Auth::role() === 'program_supervisor'): ?>
    المشرف يرى موظفي دائرته فقط.
    <?php else: ?>
    الصلاحية للمدير والمساعد الإداري.
    <?php endif; ?>
</p>

<div class="card">
    <div class="table-wrap">
    <table class="table">
        <thead>
            <tr>
                <th>الموظف</th>
                <th>المسمى الوظيفي</th>
                <th>الوصف / التبعية</th>
                <th>إجراء</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($users)): ?>
            <tr><td colspan="4">لا يوجد موظفون نشطون</td></tr>
        <?php else: foreach ($users as $u): ?>
            <?php
            $hasDuties = trim((string) ($u['duties_body'] ?? '')) !== '' || (int) ($u['duty_count'] ?? 0) > 0;
            ?>
            <tr>
                <td><?= e($u['name']) ?></td>
                <td><?= e($u['job_title'] ?? '—') ?></td>
                <td><?= $hasDuties ? '<span class="badge badge-evaluated">مُدخل</span>' : '<span class="text-muted">—</span>' ?></td>
                <td>
                    <a href="<?= e(url('/manager/job-description/edit?user_id=' . (int)$u['id'])) ?>"
                       class="btn btn-outline btn-sm">
                        <?= ($u['job_title'] ?? '') !== '' || $hasDuties ? 'تعديل' : 'إدخال' ?>
                    </a>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
    </div>
</div>
