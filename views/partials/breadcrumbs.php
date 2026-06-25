<?php if (!empty($breadcrumbs)): ?>
<nav class="breadcrumbs no-print" aria-label="مسار التنقل">
    <ol class="breadcrumbs__list">
        <li><a href="<?= e(url(RoleHelper::dashboardPath(Auth::role()))) ?>">الرئيسية</a></li>
        <?php foreach ($breadcrumbs as $crumb): ?>
            <?php if (!empty($crumb['url'])): ?>
                <li><a href="<?= e(url($crumb['url'])) ?>"><?= e($crumb['label']) ?></a></li>
            <?php else: ?>
                <li aria-current="page"><?= e($crumb['label']) ?></li>
            <?php endif; ?>
        <?php endforeach; ?>
    </ol>
</nav>
<?php endif; ?>
