<?php $title = 'سجل التدقيق'; ?>
<div class="page-header">
    <h1>سجل التدقيق</h1>
    <p class="page-header__subtitle">آخر <?= count($entries) ?> عملية في النظام</p>
</div>

<div class="card">
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>التاريخ</th>
                <th>الموظف</th>
                <th>الإجراء</th>
                <th>الكيان</th>
                <th>IP</th>
                <th>تفاصيل</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($entries)): ?>
            <tr><td colspan="7">لا توجد سجلات تدقيق</td></tr>
        <?php else: foreach ($entries as $entry): ?>
            <tr>
                <td><?= (int) $entry['id'] ?></td>
                <td><?= e($entry['created_at'] ?? '') ?></td>
                <td><?= e($entry['user_name'] ?? '—') ?></td>
                <td><?= e(AuditService::actionLabel($entry['action'])) ?></td>
                <td>
                    <?php if (!empty($entry['entity_type'])): ?>
                        <?= e($entry['entity_type']) ?>
                        <?php if (!empty($entry['entity_id'])): ?>#<?= (int) $entry['entity_id'] ?><?php endif; ?>
                    <?php else: ?>—<?php endif; ?>
                </td>
                <td><code><?= e($entry['ip_address'] ?? '—') ?></code></td>
                <td style="max-width:240px;word-break:break-word;font-size:0.85rem">
                    <?= e($entry['details'] ?? '—') ?>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>
