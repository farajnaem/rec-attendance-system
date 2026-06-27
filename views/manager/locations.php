<?php $title = 'مواقع العمل الجغرافية'; ?>
<h1>مواقع العمل</h1>
<p class="text-muted">
    أدخل <strong>خط العرض</strong> و<strong>خط الطول</strong> لكل موقع عمل.
    عند توقيع الموظف على الحضور، يُقارَن موقعه الحالي بهذه الإحداثيات —
    إذا لم تتطابق تظهر رسالة خطأ ولن يُقبل التسجيل.
</p>

<div class="card" id="addLocationPanel" hidden>
    <h2>إضافة موقع</h2>
    <form method="post" action="<?= e(url('/manager/locations/create')) ?>" id="createLocationForm">
        <?= Csrf::field() ?>
        <div class="grid-2">
            <div class="form-group">
                <label>اسم الموقع *</label>
                <input type="text" name="name" class="form-control" required placeholder="مثال: المركز الرئيسي">
            </div>
            <div class="form-group">
                <label>العنوان *</label>
                <input type="text" name="address" class="form-control" required placeholder="العنوان التفصيلي">
            </div>
            <div class="form-group">
                <label>خط العرض (Latitude) *</label>
                <input type="number" step="any" name="latitude" id="create-lat" class="form-control" required placeholder="31.768319">
                <small class="text-muted">الرقم الأول عند نسخ الإحداثيات من خرائط Google</small>
            </div>
            <div class="form-group">
                <label>خط الطول (Longitude) *</label>
                <input type="number" step="any" name="longitude" id="create-lng" class="form-control" required placeholder="35.213710">
                <small class="text-muted">الرقم الثاني عند نسخ الإحداثيات من خرائط Google</small>
            </div>
            <div class="form-group">
                <label>هامش التطابق (متر)</label>
                <input type="number" name="radius_meters" class="form-control" value="200" min="50" max="5000">
                <small class="text-muted">فرق مسموح بسبب دقة GPS (افتراضي 200 متر)</small>
            </div>
        </div>
        <div style="display:flex;flex-wrap:wrap;gap:0.5rem;margin-bottom:1rem">
            <button type="button" class="btn btn-outline" onclick="fillCurrentLocation('create-lat','create-lng')">
                استخدم موقعي الحالي (أنا في الموقع الآن)
            </button>
        </div>
        <button type="submit" class="btn">إضافة الموقع</button>
        <button type="button" class="btn btn-outline" onclick="toggleAddPanel('addLocationPanel', false)">إلغاء</button>
    </form>
</div>

<div class="card">
    <div class="card-header-row">
        <h2>المواقع المسجّلة</h2>
        <button type="button" class="btn" onclick="toggleAddPanel('addLocationPanel')">+ إضافة</button>
    </div>
    <?php if (empty($locations)): ?>
    <p class="text-muted">لا توجد مواقع بعد. أضف موقعاً لتفعيل التحقق الجغرافي عند الحضور.</p>
    <?php else: foreach ($locations as $loc): ?>
    <form method="post" action="<?= e(url('/manager/locations/update')) ?>"
          style="border:1px solid var(--border);border-radius:8px;padding:1rem;margin-bottom:1rem">
        <?= Csrf::field() ?>
        <input type="hidden" name="location_id" value="<?= (int)$loc['id'] ?>">
        <div class="grid-2">
            <div class="form-group">
                <label>الاسم</label>
                <input type="text" name="name" class="form-control" required value="<?= e($loc['name']) ?>">
            </div>
            <div class="form-group">
                <label>العنوان</label>
                <input type="text" name="address" class="form-control" required value="<?= e($loc['address'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>خط العرض (Latitude)</label>
                <input type="number" step="any" name="latitude" id="lat-<?= (int)$loc['id'] ?>" class="form-control" required value="<?= e((string)$loc['latitude']) ?>">
            </div>
            <div class="form-group">
                <label>خط الطول (Longitude)</label>
                <input type="number" step="any" name="longitude" id="lng-<?= (int)$loc['id'] ?>" class="form-control" required value="<?= e((string)$loc['longitude']) ?>">
            </div>
            <div class="form-group">
                <label>هامش التطابق (متر)</label>
                <input type="number" name="radius_meters" class="form-control" value="<?= (int)$loc['radius_meters'] ?>" min="50" max="5000">
            </div>
            <div class="form-group">
                <label>الحالة</label>
                <label style="display:flex;align-items:center;gap:0.5rem;margin-top:0.5rem">
                    <input type="checkbox" name="is_active" value="1" <?= (int)$loc['is_active'] === 1 ? 'checked' : '' ?>>
                    نشط
                </label>
            </div>
        </div>
        <div style="display:flex;flex-wrap:wrap;gap:0.5rem">
            <button type="button" class="btn btn-outline"
                    onclick="fillCurrentLocation('lat-<?= (int)$loc['id'] ?>','lng-<?= (int)$loc['id'] ?>')">
                استخدم موقعي الحالي
            </button>
            <button type="submit" class="btn btn-outline">حفظ التعديلات</button>
        </div>
    </form>
    <?php endforeach; endif; ?>
</div>

<div class="card">
    <h3>كيفية الحصول على الإحداثيات</h3>
    <ol class="text-muted" style="padding-right:1.25rem">
        <li>افتح <a href="https://www.google.com/maps" target="_blank" rel="noopener">خرائط Google</a> على الهاتف وأنت في موقع العمل.</li>
        <li>اضغط مطولاً على الموقع → انسخ الإحداثيات.</li>
        <li>الصق: <strong>الرقم الأول = خط العرض</strong>، <strong>الرقم الثاني = خط الطول</strong>.</li>
        <li>أو اضغط «استخدم موقعي الحالي» وأنت واقف في الموقع.</li>
    </ol>
</div>

<script>
function fillCurrentLocation(latId, lngId) {
    if (!navigator.geolocation) {
        alert('المتصفح لا يدعم تحديد الموقع.');
        return;
    }
    navigator.geolocation.getCurrentPosition(function(pos) {
        document.getElementById(latId).value = pos.coords.latitude.toFixed(7);
        document.getElementById(lngId).value = pos.coords.longitude.toFixed(7);
        alert('تم تعبئة الإحداثيات من موقعك الحالي.');
    }, function() {
        alert('تعذّر الحصول على الموقع. فعّل إذن الموقع.');
    }, { enableHighAccuracy: true, timeout: 20000, maximumAge: 0 });
}
</script>
