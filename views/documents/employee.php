<?php $title = 'حافظة: ' . e($owner['name']); ?>
<h1>حافظة مستندات — <?= e($owner['name']) ?></h1>
<p class="text-muted">
    <a href="<?= e(url('/documents')) ?>">← جميع الحافظات</a>
</p>

<?php if (!empty($canManageDocs)): ?>
<div class="card" id="addDocPanel" hidden>
    <h2>رفع مستند</h2>
    <form method="post" action="<?= e(url('/documents/upload')) ?>" enctype="multipart/form-data">
        <?= Csrf::field() ?>
        <input type="hidden" name="owner_user_id" value="<?= (int) $owner['id'] ?>">
        <div class="grid-2">
            <div class="form-group">
                <label>التصنيف *</label>
                <select name="category" class="form-control" required>
                    <?php foreach ($categories as $key => $label): ?>
                    <option value="<?= e($key) ?>"><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>عنوان المستند *</label>
                <input type="text" name="title" class="form-control" required placeholder="مثال: عقد العمل 2026">
            </div>
            <div class="form-group" style="grid-column:1/-1">
                <label>الملف *</label>
                <input type="file" name="document" class="form-control" required
                       accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.webp">
            </div>
        </div>
        <div style="display:flex;gap:0.5rem;margin-top:1rem">
            <button type="submit" class="btn">رفع</button>
            <button type="button" class="btn btn-outline" onclick="toggleAddPanel('addDocPanel', false)">إلغاء</button>
        </div>
    </form>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header-row">
        <h2>المستندات</h2>
        <?php if (!empty($canManageDocs)): ?>
        <button type="button" class="btn" onclick="toggleAddPanel('addDocPanel')">+ رفع مستند</button>
        <?php endif; ?>
    </div>
    <?php if (empty($documents)): ?>
    <p class="text-muted">لا توجد مستندات في هذه الحافظة بعد.</p>
    <?php else: ?>
    <table>
        <thead>
            <tr><th>التصنيف</th><th>العنوان</th><th>الملف</th><th>رفعه</th><th>التاريخ</th><th>إجراء</th></tr>
        </thead>
        <tbody>
        <?php foreach ($documents as $doc): ?>
        <tr>
            <td><?= e(DocumentService::categoryLabel($doc['category'] ?? 'other')) ?></td>
            <td class="fw-bold"><?= e($doc['title']) ?></td>
            <td><?= e($doc['original_filename']) ?></td>
            <td><?= e($doc['uploader_name']) ?></td>
            <td><?= e(substr($doc['created_at'], 0, 10)) ?></td>
            <td class="text-nowrap">
                <a href="<?= e(url('/documents/download?id=' . (int) $doc['id'])) ?>" class="btn btn-outline" style="padding:0.25rem 0.5rem;font-size:0.85rem">تحميل</a>
                <?php if (!empty($canManageDocs)): ?>
                <form method="post" action="<?= e(url('/documents/delete')) ?>" style="display:inline" data-confirm="حذف المستند؟">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="document_id" value="<?= (int) $doc['id'] ?>">
                    <input type="hidden" name="owner_user_id" value="<?= (int) $owner['id'] ?>">
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
