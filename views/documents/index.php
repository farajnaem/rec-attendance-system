<?php $title = 'حافظات مستندات الموظفين'; ?>
<h1>حافظات المستندات</h1>
<p class="text-muted">لكل موظف حافظة خاصة: عقد العمل، تقارير، هوية، وغيرها — حسب الصلاحيات.</p>

<div class="card">
    <?php if (empty($employees)): ?>
    <p class="text-muted">لا يوجد موظفون في نطاقك.</p>
    <?php else: ?>
    <table>
        <thead>
            <tr><th>الموظف</th><th>الدائرة</th><th>عدد المستندات</th><th>إجراء</th></tr>
        </thead>
        <tbody>
        <?php foreach ($employees as $emp): ?>
        <tr>
            <td class="fw-bold"><?= e($emp['name']) ?></td>
            <td><?= e($emp['department_name'] ?? '—') ?></td>
            <td><?= DocumentService::countForEmployee((int) $emp['id']) ?></td>
            <td>
                <a href="<?= e(url('/documents/employee?id=' . (int) $emp['id'])) ?>" class="btn btn-outline" style="padding:0.25rem 0.5rem;font-size:0.85rem">فتح الحافظة</a>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
