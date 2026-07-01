<?php
/** @var array $borrowable @var array $active @var array $targetDepartments */
?>
<div class="card" id="borrow-employees">
    <div class="card-header-row">
        <h2>الاستعارة المؤقتة</h2>
        <?php if (!empty($borrowable)): ?>
        <button type="button" class="btn" onclick="toggleAddPanel('addBorrowPanel')">+ إضافة استعارة</button>
        <?php endif; ?>
    </div>
    <p class="text-muted" style="margin-top:0">
        استعارة موظف من دائرة أخرى للعمل مؤقتاً في دائرتك. يبقى الموظف في دائرته الأصلية
        ويظهر لك في المهام والتقارير خلال فترة الاستعارة.
    </p>

    <div id="addBorrowPanel" hidden>
        <h3 style="margin:1rem 0 0.75rem;font-size:1rem">استعارة موظف</h3>
        <form method="post" action="<?= e(url('/manager/borrow-employee/create')) ?>">
            <?= Csrf::field() ?>
            <div class="grid-2">
                <div class="form-group">
                    <label>الموظف *</label>
                    <select name="user_id" class="form-control" required>
                        <option value="">— اختر الموظف —</option>
                        <?php foreach ($borrowable as $emp): ?>
                        <option value="<?= (int)$emp['id'] ?>">
                            <?= e($emp['name']) ?> — <?= e($emp['department_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>الدائرة المستهدفة *</label>
                    <select name="target_department_id" class="form-control" required>
                        <option value="">— اختر —</option>
                        <?php foreach ($targetDepartments as $d): ?>
                        <option value="<?= (int)$d['id'] ?>"><?= e($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>تاريخ البداية *</label>
                    <input type="date" name="start_date" class="form-control" required value="<?= e(date('Y-m-d')) ?>">
                </div>
                <div class="form-group">
                    <label>تاريخ النهاية (اختياري)</label>
                    <input type="date" name="end_date" class="form-control">
                </div>
                <div class="form-group" style="grid-column:1/-1">
                    <label>ملاحظات</label>
                    <input type="text" name="notes" class="form-control" placeholder="سبب الاستعارة">
                </div>
            </div>
            <div style="display:flex;gap:0.5rem;margin-top:1rem">
                <button type="submit" class="btn">تأكيد الاستعارة</button>
                <button type="button" class="btn btn-outline" onclick="toggleAddPanel('addBorrowPanel', false)">إلغاء</button>
            </div>
        </form>
    </div>

    <?php if (empty($active)): ?>
    <p class="text-muted">لا توجد استعارات نشطة.</p>
    <?php else: ?>
    <table>
        <thead>
            <tr><th>الموظف</th><th>من دائرة</th><th>إلى دائرة</th><th>البداية</th><th>النهاية</th><th>إجراء</th></tr>
        </thead>
        <tbody>
        <?php foreach ($active as $c): ?>
        <tr>
            <td><?= e($c['employee_name']) ?></td>
            <td><?= e($c['home_department_name']) ?></td>
            <td><?= e($c['target_department_name']) ?></td>
            <td><?= e($c['start_date']) ?></td>
            <td><?= e($c['end_date'] ?? '—') ?></td>
            <td>
                <form method="post" action="<?= e(url('/manager/borrow-employee/end')) ?>" class="row-actions-form"
                      data-confirm="إنهاء الاستعارة؟">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="assignment_id" value="<?= (int)$c['id'] ?>">
                    <button type="submit" class="btn btn-outline" style="padding:0.25rem 0.5rem;font-size:0.85rem">إنهاء</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php if (empty($borrowable)): ?>
    <p class="text-muted mb-0">لا يوجد موظفون متاحون للاستعارة من دوائر أخرى حالياً.</p>
    <?php endif; ?>
</div>
