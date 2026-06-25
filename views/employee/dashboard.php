<?php $title = 'لوحتي'; ?>
<h1>مرحباً <?= e(Auth::name()) ?></h1>
<p class="text-muted">
    <?= e(RoleHelper::label(Auth::role())) ?> —
    المنطقة الزمنية: <?= e(TimezoneHelper::commonTimezones()[$tz] ?? $tz) ?> —
    اليوم: <?= e($status['local_date']) ?>
</p>
<?php if (!empty($crossAssignment)): ?>
<p class="alert alert-success" style="margin-bottom:1rem">
    تعيين مؤقت: تعمل حالياً في دائرة <strong><?= e($crossAssignment['target_department_name']) ?></strong>
    (دائرتك الأساسية: <?= e($crossAssignment['home_department_name']) ?>)
</p>
<?php endif; ?>

<?php if (Auth::can('sign_attendance')): ?>
<div class="card" id="attendance">
    <h2>تسجيل الحضور والانصراف</h2>
    <?php if ($gpsRequired): ?>
    <p class="text-muted">
        عند التوقيع يُقارَن موقعك الحالي (خط العرض وخط الطول) مع الإحداثيات التي سجّلها المشرف.
        إذا لم تتطابق، لن يُقبل التسجيل.
    </p>
    <?php if (!empty($workLocations)): ?>
    <details class="text-muted" style="margin-bottom:1rem">
        <summary>المواقع المعتمدة للحضور</summary>
        <ul style="margin:0.5rem 0 0;padding-right:1.25rem">
        <?php foreach ($workLocations as $wl): ?>
            <li>
                <strong><?= e($wl['name']) ?></strong> —
                خط العرض: <?= e((string)$wl['latitude']) ?>،
                خط الطول: <?= e((string)$wl['longitude']) ?>
            </li>
        <?php endforeach; ?>
        </ul>
    </details>
    <?php endif; ?>
    <?php else: ?>
    <p class="text-muted">ارسم توقيعك في المربع ثم اضغط الزر المناسب.</p>
    <?php endif; ?>

    <div class="grid-2" style="margin-bottom:1rem">
        <div class="stat-box">
            <div class="label">الحضور</div>
            <?php if ($status['check_in']): ?>
                <div class="value" style="font-size:1.1rem;color:var(--success)">
                    <?= e(TimezoneHelper::formatArabic($status['check_in']['signed_at_utc'], $tz)) ?>
                </div>
            <?php else: ?>
                <div class="value" style="font-size:1rem;color:var(--warning)">لم يُسجَّل بعد</div>
            <?php endif; ?>
        </div>
        <div class="stat-box">
            <div class="label">الانصراف</div>
            <?php if ($status['check_out']): ?>
                <div class="value" style="font-size:1.1rem;color:var(--success)">
                    <?= e(TimezoneHelper::formatArabic($status['check_out']['signed_at_utc'], $tz)) ?>
                </div>
            <?php else: ?>
                <div class="value" style="font-size:1rem;color:var(--warning)">لم يُسجَّل بعد</div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$status['check_in']): ?>
    <form method="post" action="<?= e(url('/employee/attendance')) ?>" class="attendance-form gps-attendance-form">
        <?= Csrf::field() ?>
        <input type="hidden" name="type" value="check_in">
        <input type="hidden" name="signature_data" id="signature-data-checkin">
        <input type="hidden" name="latitude" id="latitude-checkin">
        <input type="hidden" name="longitude" id="longitude-checkin">
        <h3>1 — توقيع الحضور</h3>
        <label>ارسم توقيعك هنا:</label>
        <canvas id="signature-checkin" class="signature-pad" width="600" height="150"></canvas>
        <div style="margin-top:0.75rem">
            <button type="button" id="clear-checkin" class="btn btn-outline">مسح التوقيع</button>
            <button type="submit" class="btn btn-success">تسجيل الحضور الآن</button>
        </div>
    </form>
    <?php elseif (!$status['check_out']): ?>
    <form method="post" action="<?= e(url('/employee/attendance')) ?>" class="attendance-form gps-attendance-form">
        <?= Csrf::field() ?>
        <input type="hidden" name="type" value="check_out">
        <input type="hidden" name="signature_data" id="signature-data-checkout">
        <input type="hidden" name="latitude" id="latitude-checkout">
        <input type="hidden" name="longitude" id="longitude-checkout">
        <h3>2 — توقيع الانصراف</h3>
        <p class="text-muted">تم تسجيل حضورك. عند الانتهاء من العمل، وقّع الانصراف.</p>
        <label>ارسم توقيعك هنا:</label>
        <canvas id="signature-checkout" class="signature-pad" width="600" height="150"></canvas>
        <div style="margin-top:0.75rem">
            <button type="button" id="clear-checkout" class="btn btn-outline">مسح التوقيع</button>
            <button type="submit" class="btn btn-warning">تسجيل الانصراف الآن</button>
        </div>
    </form>
    <?php else: ?>
    <div class="alert alert-success">اكتمل تسجيل حضور وانصراف اليوم. شكراً لالتزامك!</div>
    <?php endif; ?>
</div>
<?php else: ?>
<div class="card">
    <p class="text-muted">تسجيل الحضور غير مفعّل لحسابك. راجع المسؤول إن كان ذلك مطلوباً.</p>
</div>
<?php endif; ?>

<div class="grid-2">
    <div class="card">
        <h2>سجل الحضور (7 أيام)</h2>
        <table>
            <thead><tr><th>التاريخ</th><th>النوع</th><th>الوقت</th><th>موقع العمل</th></tr></thead>
            <tbody>
            <?php if (empty($recent)): ?>
                <tr><td colspan="4" class="text-muted">لا توجد سجلات</td></tr>
            <?php else: foreach ($recent as $r): ?>
                <tr>
                    <td><?= e($r['local_work_date']) ?></td>
                    <td><?= $r['type'] === 'check_in' ? 'حضور' : 'انصراف' ?></td>
                    <td><?= e(TimezoneHelper::formatArabic($r['signed_at_utc'], $r['timezone'])) ?></td>
                    <td><?= e($r['work_location_name'] ?? '—') ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card" id="job-description">
    <h2>التوصيف الوظيفي</h2>
    <?php
    $jobTitle = $jobDescription['job_title'] ?? null;
    $jobTasks = $jobDescription['tasks'] ?? [];
    ?>
    <?php if (!$jobTitle && empty($jobTasks)): ?>
    <p class="text-muted">لم يُدخل التوصيف الوظيفي بعد. راجع المدير أو المساعد الإداري.</p>
    <?php else: ?>
    <?php if ($jobTitle): ?>
    <p style="font-size:1.15rem;margin-bottom:1rem">
        <strong>المسمى الوظيفي:</strong> <?= e($jobTitle) ?>
    </p>
    <?php endif; ?>
    <?php if (!empty($jobTasks)): ?>
    <h3 style="font-size:1rem;margin-bottom:0.5rem">المهام الفرعية</h3>
    <ul style="margin:0;padding-right:1.25rem;line-height:1.8">
        <?php foreach ($jobTasks as $t): ?>
        <li><?= e($t['title']) ?></li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
    <?php endif; ?>
</div>

<div class="card">
    <h2>مهامي اليومية</h2>
    <table>
        <thead>
            <tr><th>التاريخ</th><th>المهمة</th><th>الحالة</th><th>الدرجة</th><th>إجراء</th></tr>
        </thead>
        <tbody>
        <?php if (empty($tasks)): ?>
            <tr><td colspan="5" class="text-muted">لا توجد مهام</td></tr>
        <?php else: foreach ($tasks as $t): ?>
            <tr>
                <td><?= e($t['task_date']) ?></td>
                <td><strong><?= e($t['title']) ?></strong><br><small><?= e($t['description'] ?? '') ?></small></td>
                <td><span class="badge badge-<?= e($t['status']) ?>"><?= e(statusLabel($t['status'])) ?></span></td>
                <td><?= $t['score'] !== null ? e((string)$t['score']) . '/10' : '—' ?></td>
                <td>
                    <?php if ($t['status'] === 'pending'): ?>
                    <button type="button" class="btn btn-success" onclick="openCompleteModal(<?= (int)$t['id'] ?>, '<?= e(addslashes($t['title'])) ?>')">أتممت العمل</button>
                    <?php else: ?>—<?php endif; ?>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<div id="completeModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:100;align-items:center;justify-content:center;">
    <div class="card" style="max-width:420px;margin:2rem;">
        <h3 id="modalTitle">إتمام المهمة</h3>
        <form method="post" action="<?= e(url('/employee/task/complete')) ?>">
            <?= Csrf::field() ?>
            <input type="hidden" name="task_id" id="modalTaskId">
            <div class="form-group">
                <label>تاريخ ووقت الإتمام</label>
                <input type="datetime-local" name="completed_at" class="form-control" required
                       value="<?= e(date('Y-m-d\TH:i')) ?>">
            </div>
            <div class="form-group">
                <label>ملاحظات (اختياري)</label>
                <textarea name="notes" class="form-control" rows="2"></textarea>
            </div>
            <button type="submit" class="btn btn-success">تأكيد</button>
            <button type="button" class="btn btn-outline" onclick="closeCompleteModal()">إلغاء</button>
        </form>
    </div>
</div>

<script>
function openCompleteModal(id, title) {
    document.getElementById('modalTaskId').value = id;
    document.getElementById('modalTitle').textContent = 'إتمام: ' + title;
    document.getElementById('completeModal').style.display = 'flex';
}
function closeCompleteModal() {
    document.getElementById('completeModal').style.display = 'none';
}
<?php if (!empty($gpsRequired) && Auth::can('sign_attendance')): ?>
var WORK_LOCATIONS = <?= json_encode($workLocations ?? [], JSON_UNESCAPED_UNICODE) ?>;

function geoDistanceMeters(lat1, lng1, lat2, lng2) {
    var R = 6371000;
    var dLat = (lat2 - lat1) * Math.PI / 180;
    var dLng = (lng2 - lng1) * Math.PI / 180;
    var a = Math.sin(dLat / 2) * Math.sin(dLat / 2)
        + Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180)
        * Math.sin(dLng / 2) * Math.sin(dLng / 2);
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

function findMatchingWorkLocation(lat, lng) {
    var best = null;
    WORK_LOCATIONS.forEach(function(loc) {
        var dist = geoDistanceMeters(lat, lng, loc.latitude, loc.longitude);
        if (dist <= loc.tolerance_meters && (!best || dist < best.distance)) {
            best = { location: loc, distance: dist };
        }
    });
    return best;
}

function buildLocationMismatchMessage(lat, lng) {
    var msg = 'إحداثيات موقعك (خط العرض: ' + lat.toFixed(6) + '، خط الطول: ' + lng.toFixed(6) + ') لا تطابق المواقع المعتمدة.\n\n';
    WORK_LOCATIONS.forEach(function(loc) {
        var dist = Math.round(geoDistanceMeters(lat, lng, loc.latitude, loc.longitude));
        msg += '«' + loc.name + '»: خط العرض ' + loc.latitude + '، خط الطول ' + loc.longitude;
        msg += ' — المسافة ~' + dist + ' م (المسموح ' + loc.tolerance_meters + ' م)\n';
    });
    msg += '\nتوجّه إلى موقع العمل ثم أعد المحاولة.';
    return msg;
}

document.querySelectorAll('.gps-attendance-form').forEach(function(form) {
    form.addEventListener('submit', function(e) {
        if (form.dataset.gpsReady === '1') return;
        e.preventDefault();
        if (!navigator.geolocation) {
            alert('المتصفح لا يدعم تحديد الموقع الجغرافي.');
            return;
        }
        var latInput = form.querySelector('input[name="latitude"]');
        var lngInput = form.querySelector('input[name="longitude"]');
        var submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'جاري فحص الموقع...';
        }
        navigator.geolocation.getCurrentPosition(function(pos) {
            var lat = pos.coords.latitude;
            var lng = pos.coords.longitude;
            var match = findMatchingWorkLocation(lat, lng);
            if (!match) {
                alert(buildLocationMismatchMessage(lat, lng));
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = form.querySelector('input[name="type"]').value === 'check_in'
                        ? 'تسجيل الحضور الآن' : 'تسجيل الانصراف الآن';
                }
                return;
            }
            latInput.value = lat;
            lngInput.value = lng;
            form.dataset.gpsReady = '1';
            form.submit();
        }, function(err) {
            var reason = 'تعذّر الحصول على موقعك. فعّل إذن الموقع ثم أعد المحاولة.';
            if (err && err.code === 1) reason = 'رفضت إذن الموقع. فعّله من إعدادات المتصفح.';
            alert(reason);
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = form.querySelector('input[name="type"]').value === 'check_in'
                    ? 'تسجيل الحضور الآن' : 'تسجيل الانصراف الآن';
            }
        }, { enableHighAccuracy: true, timeout: 20000, maximumAge: 0 });
    });
});
<?php endif; ?>
</script>
