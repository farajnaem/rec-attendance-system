<?php $title = 'المستندات'; ?>
<h1>المستندات</h1>
<p class="text-muted">رفع وتحميل مستندات PDF، Excel، Word، أو صور — حسب الصلاحيات الممنوحة.</p>

<?php if (Auth::can('manage_documents')): ?>
<div class="card" id="addDocPanel" hidden>
    <h2>رفع مستند جديد</h2>
    <form method="post" action="<?= e(url('/documents/upload')) ?>" enctype="multipart/form-data">
        <?= Csrf::field() ?>
        <div class="grid-2">
            <div class="form-group">
                <label>عنوان المستند *</label>
                <input type="text" name="title" class="form-control" required placeholder="مثال: نموذج طلب إجازة">
            </div>
            <div class="form-group">
                <label>الملف *</label>
                <input type="file" name="document" class="form-control" required
                       accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.webp">
                <small class="text-muted">PDF، Word، Excel، أو صورة — بحد أقصى 10 ميغابايت</small>
            </div>
        </div>
        <div style="display:flex;gap:0.5rem;margin-top:1rem">
            <button type="submit" class="btn">رفع المستند</button>
            <button type="button" class="btn btn-outline" onclick="toggleAddPanel('addDocPanel', false)">إلغاء</button>
        </div>
    </form>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header-row">
        <h2>المستندات المتاحة</h2>
        <?php if (Auth::can('manage_documents')): ?>
        <button type="button" class="btn" onclick="toggleAddPanel('addDocPanel')">+ رفع مستند</button>
        <?php endif; ?>
    </div>
    <?php if (empty($documents)): ?>
    <p class="text-muted">لا توجد مستندات بعد.</p>
    <?php else: ?>
    <table>
        <thead>
            <tr><th>العنوان</th><th>الملف الأصلي</th><th>رفعه</th><th>التاريخ</th><th>إجراء</th></tr>
        </thead>
        <tbody>
        <?php foreach ($documents as $doc): ?>
        <tr>
            <td class="fw-bold"><?= e($doc['title']) ?></td>
            <td><?= e($doc['original_filename']) ?></td>
            <td><?= e($doc['uploader_name']) ?></td>
            <td><?= e(substr($doc['created_at'], 0, 10)) ?></td>
            <td class="text-nowrap">
                <a href="<?= e(url('/documents/download?id=' . (int)$doc['id'])) ?>" class="btn btn-outline" style="padding:0.25rem 0.5rem;font-size:0.85rem">تحميل</a>
                <?php if (Auth::can('manage_documents') && (RoleHelper::isSystemAdmin(Auth::role()) || (int)$doc['uploaded_by'] === Auth::id())): ?>
                <form method="post" action="<?= e(url('/documents/delete')) ?>" style="display:inline"
                      onsubmit="return confirm('حذف المستند؟');">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="document_id" value="<?= (int)$doc['id'] ?>">
                    <button type="submit" class="btn btn-danger" style="padding:0.25rem 0.5rem;font-size:0.85rem">حذف</button>
                </form>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
