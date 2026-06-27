<?php $title = 'التوصيف الوظيفي'; ?>
<h1>التوصيف الوظيفي</h1>
<p class="text-muted">
    لكل موظف: <strong>مسمى وظيفي</strong> ثم <strong>مهام فرعية</strong> (عناوين فقط).
    <?php if (Auth::role() === 'program_supervisor'): ?>
    المشرف يرى موظفي دائرته فقط.
    <?php else: ?>
    الصلاحية للمدير والمساعد الإداري.
    <?php endif; ?>
</p>

<div class="card">
    <table>
        <thead>
            <tr>
                <th>الموظف</th>
                <th>المسمى الوظيفي</th>
                <th>المهام الفرعية</th>
                <th>إجراء</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($users)): ?>
            <tr><td colspan="4">لا يوجد موظفون نشطون</td></tr>
        <?php else: foreach ($users as $u): ?>
            <tr>
                <td><?= e($u['name']) ?></td>
                <td><?= e($u['job_title'] ?? '—') ?></td>
                <td><?= (int) $u['duty_count'] ?></td>
                <td>
                    <a href="<?= e(url('/manager/job-description/edit?user_id=' . (int)$u['id'])) ?>"
                       class="btn btn-outline" style="padding:0.25rem 0.5rem;font-size:0.85rem">
                        <?= ($u['job_title'] ?? '') !== '' || (int)$u['duty_count'] > 0 ? 'تعديل' : 'إدخال' ?>
                    </a>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>
