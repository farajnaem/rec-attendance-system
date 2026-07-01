<?php $title = 'لوحتي'; ?>
<div class="page-header">
    <h1>مرحباً <?= e(Auth::name()) ?></h1>
    <p class="page-header__subtitle">
        <?= e(RoleHelper::label(Auth::role())) ?> —
        المنطقة الزمنية: <?= e(TimezoneHelper::commonTimezones()[$tz] ?? $tz) ?> —
        اليوم: <?= e($status['local_date']) ?>
    </p>
</div>
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
                <?php if (!empty($isLateToday)): ?>
                <span class="badge badge-pending" style="margin-top:0.35rem;display:inline-block">متأخر</span>
                <?php endif; ?>
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

    <?php if (!empty($status['check_in']) && (Auth::can('request_work_break') || Auth::can('sign_attendance'))): ?>
    <div id="work-breaks" style="margin-top:1.5rem;border-top:1px solid var(--border);padding-top:1.25rem">
        <h3>المغادرة أثناء العمل</h3>
        <p class="text-muted">سجّل وقت الخروج والعودة ومن أعطاك الإذن — يتطلب اعتماد المشرف أو المدير.</p>
        <form method="post" action="<?= e(url('/employee/work-break/request')) ?>" class="grid-2" style="margin-bottom:1rem">
            <?= Csrf::field() ?>
            <input type="hidden" name="work_date" value="<?= e($status['local_date']) ?>">
            <div class="form-group">
                <label>وقت الخروج *</label>
                <input type="time" name="exit_time" class="form-control" required>
            </div>
            <div class="form-group">
                <label>وقت العودة *</label>
                <input type="time" name="return_time" class="form-control" required>
            </div>
            <div class="form-group">
                <label>معطي الإذن *</label>
                <select name="authorized_by" class="form-control" required>
                    <option value="">— اختر —</option>
                    <?php foreach ($breakAuthorizers as $m): ?>
                    <option value="<?= (int)$m['id'] ?>"><?= e($m['name']) ?> (<?= e(RoleHelper::label($m['role'])) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>ملاحظات</label>
                <input type="text" name="notes" class="form-control" placeholder="اختياري">
            </div>
            <div style="grid-column:1/-1"><button type="submit" class="btn btn-outline">تسجيل مغادرة</button></div>
        </form>
        <?php if (!empty($workBreaks)): ?>
        <table>
            <thead><tr><th>التاريخ</th><th>خروج</th><th>عودة</th><th>معطي الإذن</th><th>الحالة</th></tr></thead>
            <tbody>
            <?php foreach ($workBreaks as $wb): ?>
            <tr>
                <td><?= e($wb['work_date']) ?></td>
                <td><?= e($wb['exit_time']) ?></td>
                <td><?= e($wb['return_time']) ?></td>
                <td><?= e($wb['authorized_by_name']) ?></td>
                <td><?= e(WorkBreakService::statusLabel($wb['status'])) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
<?php else: ?>
<div class="card">
    <p class="text-muted">تسجيل الحضور غير مفعّل لحسابك. راجع المسؤول إن كان ذلك مطلوباً.</p>
</div>
<?php endif; ?>

<div class="card" id="leaves">
    <h2>طلبات الإجازة</h2>
    <?php
    $leaveStatusLabels = [
        'pending' => 'قيد الانتظار',
        'approved' => 'موافق عليها',
        'rejected' => 'مرفوضة',
    ];
    ?>
    <details class="card" style="margin-bottom:1rem;padding:1rem;background:#f8fafc">
        <summary style="cursor:pointer;font-weight:600">طلب إجازة جديد</summary>
        <form method="post" action="<?= e(url('/employee/leaves/request')) ?>" style="margin-top:1rem">
            <?= Csrf::field() ?>
            <div class="grid-2">
                <div class="form-group">
                    <label>نوع الإجازة</label>
                    <select name="leave_type" class="form-control" required>
                        <?php foreach (LeaveHelper::types() as $type => $label): ?>
                        <option value="<?= e($type) ?>"><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>من تاريخ</label>
                    <input type="date" name="start_date" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>إلى تاريخ</label>
                    <input type="date" name="end_date" class="form-control" required>
                </div>
            </div>
            <div class="form-group">
                <label>ملاحظات (اختياري)</label>
                <textarea name="notes" class="form-control" rows="2"></textarea>
            </div>
            <button type="submit" class="btn">إرسال الطلب</button>
        </form>
    </details>
    <table>
        <thead>
            <tr><th>النوع</th><th>من</th><th>إلى</th><th>الحالة</th><th>ملاحظات</th></tr>
        </thead>
        <tbody>
        <?php if (empty($leaves)): ?>
            <tr><td colspan="5" class="text-muted">لا توجد طلبات إجازة</td></tr>
        <?php else: foreach ($leaves as $leave): ?>
            <?php $st = $leave['status'] ?? 'pending'; ?>
            <tr>
                <td><?= e(LeaveHelper::label($leave['leave_type'])) ?></td>
                <td><?= e($leave['start_date']) ?></td>
                <td><?= e($leave['end_date']) ?></td>
                <td><span class="badge badge-<?= $st === 'approved' ? 'evaluated' : 'pending' ?>"><?= e($leaveStatusLabels[$st] ?? $st) ?></span></td>
                <td><?= e($leave['notes'] ?? '—') ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

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

<div class="card" id="my-tasks">
    <h2>مهامي اليومية</h2>
    <?php if (Auth::can('manage_daily_tasks')): ?>
    <details class="card" style="margin-bottom:1rem;padding:1rem;background:#f8fafc">
        <summary style="cursor:pointer;font-weight:600">إضافة مهمة جديدة</summary>
        <form method="post" action="<?= e(url('/employee/task/create')) ?>" style="margin-top:1rem">
            <?= Csrf::field() ?>
            <div class="grid-2">
                <div class="form-group">
                    <label>تاريخ المهمة</label>
                    <input type="date" name="task_date" class="form-control" value="<?= e(date('Y-m-d')) ?>" required>
                </div>
                <div class="form-group">
                    <label>عنوان المهمة</label>
                    <input type="text" name="title" class="form-control" required>
                </div>
            </div>
            <div class="form-group">
                <label>الوصف (اختياري)</label>
                <textarea name="description" class="form-control" rows="2"></textarea>
            </div>
            <button type="submit" class="btn">إضافة المهمة</button>
        </form>
    </details>
    <?php endif; ?>
    <table>
        <thead>
            <tr><th>التاريخ</th><th>المهمة</th><th>الحالة</th><th>الدرجة</th><th>إجراء</th></tr>
        </thead>
        <tbody>
        <?php if (empty($tasks)): ?>
            <tr><td colspan="5" class="text-muted">لا توجد مهام</td></tr>
        <?php else: foreach ($tasks as $t): ?>
            <?php $replies = $taskReplies[(int)$t['id']] ?? []; ?>
            <tr>
                <td><?= e($t['task_date']) ?></td>
                <td>
                    <strong><?= e($t['title']) ?></strong><br><small><?= e($t['description'] ?? '') ?></small>
                    <?php if (!empty($replies)): ?>
                    <div style="margin-top:0.5rem;font-size:0.85rem;color:var(--muted)">
                        <?php foreach ($replies as $r): ?>
                        <div><strong><?= e($r['author_name']) ?>:</strong> <?= e($r['message']) ?></div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </td>
                <td><span class="badge badge-<?= e($t['status']) ?>"><?= e(statusLabel($t['status'])) ?></span></td>
                <td><?= $t['score'] !== null ? e((string)$t['score']) . '/10' : '—' ?></td>
                <td class="text-nowrap">
                    <?php if ($t['status'] === 'pending' && Auth::can('complete_own_tasks')): ?>
                    <button type="button" class="btn btn-success" onclick="openCompleteModal(<?= (int)$t['id'] ?>, '<?= e(addslashes($t['title'])) ?>')">أتممت العمل</button>
                    <?php endif; ?>
                    <?php if ((int)$t['assigned_by'] !== Auth::id()): ?>
                    <button type="button" class="btn btn-outline" style="margin-top:0.25rem"
                            onclick="openReplyModal(<?= (int)$t['id'] ?>, '<?= e(addslashes($t['title'])) ?>')">رد / ملاحظة</button>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<div id="replyModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:100;align-items:center;justify-content:center;">
    <div class="card" style="max-width:420px;margin:2rem;">
        <h3 id="replyModalTitle">رد على المهمة</h3>
        <form method="post" action="<?= e(url('/employee/task/reply')) ?>">
            <?= Csrf::field() ?>
            <input type="hidden" name="task_id" id="replyTaskId">
            <div class="form-group">
                <label>الرد أو الملاحظة</label>
                <textarea name="message" class="form-control" rows="3" required placeholder="اكتب ردك أو ملاحظتك — ستصل مباشرة لمعطي المهمة"></textarea>
            </div>
            <button type="submit" class="btn">إرسال</button>
            <button type="button" class="btn btn-outline" onclick="closeReplyModal()">إلغاء</button>
        </form>
    </div>
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
function openReplyModal(id, title) {
    document.getElementById('replyTaskId').value = id;
    document.getElementById('replyModalTitle').textContent = 'رد على: ' + title;
    document.getElementById('replyModal').style.display = 'flex';
}
function closeReplyModal() {
    document.getElementById('replyModal').style.display = 'none';
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
