<details class="rd-user-menu">
    <summary class="rd-user-menu-trigger" aria-haspopup="menu" aria-label="قائمة الحساب">
        <span class="rd-avatar rd-avatar-sm" aria-hidden="true">
            <span><?= e(userInitials(Auth::name())) ?></span>
        </span>
        <span class="rd-user-menu-name"><?= e(Auth::name()) ?></span>
        <svg class="rd-user-menu-caret" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
    </summary>

    <div class="rd-user-menu-panel" role="menu">
        <div class="rd-user-menu-header">
            <span class="rd-avatar rd-avatar-md" aria-hidden="true">
                <span><?= e(userInitials(Auth::name())) ?></span>
            </span>
            <div class="rd-user-menu-identity">
                <div class="rd-user-menu-display"><?= e(Auth::name()) ?></div>
                <div class="rd-user-menu-handle"><?= e(userHandle(Auth::email())) ?> · <?= e(roleLabel(Auth::role())) ?></div>
            </div>
        </div>

        <div class="rd-user-menu-divider" aria-hidden="true"></div>

        <a class="rd-user-menu-item" href="<?= e(url('/account/settings')) ?>" role="menuitem">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="12" cy="12" r="3"/>
                <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>
            </svg>
            <span>إعدادات</span>
        </a>

        <form method="post" action="<?= e(url('/logout')) ?>" class="rd-user-menu-form">
            <?= Csrf::field() ?>
            <button class="rd-user-menu-item rd-user-menu-item--danger" type="submit" role="menuitem">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                    <polyline points="16 17 21 12 16 7"/>
                    <line x1="21" y1="12" x2="9" y2="12"/>
                </svg>
                <span>تسجيل الخروج</span>
            </button>
        </form>
    </div>
</details>
