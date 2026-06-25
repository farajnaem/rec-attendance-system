<?php
$title = 'تفاصيل سجل الحضور';
$tz = $record['timezone'] ?? $record['user_timezone'] ?? TimezoneHelper::defaultTimezone();
$isManual = str_starts_with((string) ($record['signature_data'] ?? ''), 'MANUAL');
$localTime = TimezoneHelper::toLocal($record['signed_at_utc'], $tz)->format('Y-m-d H:i:s');
?>
<div class="page-header">
    <h1>تفاصيل سجل الحضور</h1>
    <p class="page-header__subtitle">
        <?= e($record['user_name']) ?> — <?= $record['type'] === 'check_in' ? 'حضور' : 'انصراف' ?>
        <?= e($record['local_work_date']) ?>
    </p>
</div>

<div class="grid-2">
    <div class="card">
        <h2>البيانات</h2>
        <table>
            <tbody>
            <tr><th>الموظف</th><td><?= e($record['user_name']) ?></td></tr>
            <tr><th>البريد</th><td><?= e($record['user_email']) ?></td></tr>
            <tr><th>النوع</th><td><?= $record['type'] === 'check_in' ? 'حضور' : 'انصراف' ?></td></tr>
            <tr><th>التاريخ المحلي</th><td><?= e($record['local_work_date']) ?></td></tr>
            <tr><th>التوقيت المحلي</th><td><?= e($localTime) ?></td></tr>
            <tr><th>المنطقة الزمنية</th><td><?= e(TimezoneHelper::commonTimezones()[$tz] ?? $tz) ?></td></tr>
            <tr><th>موقع العمل</th><td><?= e($record['work_location_name'] ?? '—') ?></td></tr>
            <tr><th>IP</th><td><code><?= e($record['ip_address'] ?? '—') ?></code></td></tr>
            <?php if ($isManual): ?>
            <tr><th>تصحيح يدوي</th><td><span class="badge badge-pending">نعم</span></td></tr>
            <tr><th>السبب</th><td><?= e(preg_replace('/^MANUAL:?/', '', (string) $record['signature_data'])) ?></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        <p style="margin-top:1rem">
            <a href="<?= e(url('/manager/attendance')) ?>" class="btn btn-outline">← العودة لسجل الحضور</a>
        </p>
    </div>

    <div class="card">
        <h2>الموقع (GPS)</h2>
        <?php if ($record['latitude'] !== null && $record['longitude'] !== null): ?>
            <?php
            $lat = (float) $record['latitude'];
            $lng = (float) $record['longitude'];
            $delta = 0.01;
            $bbox = ($lng - $delta) . ',' . ($lat - $delta) . ',' . ($lng + $delta) . ',' . ($lat + $delta);
            ?>
            <p><strong>الإحداثيات:</strong> <?= e((string) $record['latitude']) ?>, <?= e((string) $record['longitude']) ?></p>
            <iframe
                title="خريطة الموقع"
                width="100%"
                height="280"
                style="border:1px solid var(--border, #e2e8f0);border-radius:8px"
                loading="lazy"
                src="https://www.openstreetmap.org/export/embed.html?bbox=<?= e($bbox) ?>&amp;layer=mapnik&amp;marker=<?= e((string) $lat) ?>,<?= e((string) $lng) ?>">
            </iframe>
            <p class="text-muted" style="font-size:0.9rem;margin-top:0.5rem">
                <a href="https://www.google.com/maps?q=<?= e((string) $record['latitude']) ?>,<?= e((string) $record['longitude']) ?>" target="_blank" rel="noopener">فتح في Google Maps</a>
            </p>
        <?php else: ?>
            <p class="text-muted">لا توجد إحداثيات GPS مسجّلة.</p>
        <?php endif; ?>
    </div>
</div>

<?php if (!$isManual && !empty($record['signature_data']) && strlen($record['signature_data']) > 20): ?>
<div class="card">
    <h2>التوقيع الإلكتروني</h2>
    <div style="background:#fff;border:1px solid var(--border, #e2e8f0);border-radius:8px;padding:1rem;max-width:480px">
        <img src="<?= e($record['signature_data']) ?>" alt="التوقيع الإلكتروني" style="max-width:100%;height:auto;display:block">
    </div>
</div>
<?php elseif ($isManual): ?>
<div class="card">
    <h2>التوقيع</h2>
    <p class="text-muted">سجل يدوي — لا يوجد توقيع إلكتروني.</p>
</div>
<?php endif; ?>
