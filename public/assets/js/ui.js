(function () {
    'use strict';

    window.openModal = function (id) {
        var el = document.getElementById(id);
        if (!el) return;
        el.classList.add('is-open');
        document.body.style.overflow = 'hidden';
    };

    window.closeModal = function (id) {
        var el = document.getElementById(id);
        if (!el) return;
        el.classList.remove('is-open');
        document.body.style.overflow = '';
    };

    window.toggleAddPanel = function (panelId, show) {
        var panel = document.getElementById(panelId);
        if (!panel) return;
        if (show === undefined) {
            panel.hidden = !panel.hidden;
        } else {
            panel.hidden = !show;
        }
        if (!panel.hidden) {
            panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    };

    document.addEventListener('click', function (e) {
        var closeBtn = e.target.closest('[data-modal-close]');
        if (closeBtn) {
            var modal = closeBtn.closest('.modal');
            if (modal) closeModal(modal.id);
            return;
        }

        var modal = e.target.classList && e.target.classList.contains('modal') ? e.target : null;
        if (modal && e.target === modal) {
            closeModal(modal.id);
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        document.querySelectorAll('.modal.is-open').forEach(function (m) {
            closeModal(m.id);
        });
        document.querySelectorAll('details.rd-user-menu[open], details.row-actions-menu[open]').forEach(function (m) {
            m.removeAttribute('open');
            if (m.classList.contains('row-actions-menu')) {
                resetRowActionsPanel(m);
            }
        });
    });

    document.addEventListener('click', function (e) {
        var um = e.target.closest('details.rd-user-menu');
        document.querySelectorAll('details.rd-user-menu[open]').forEach(function (openMenu) {
            if (openMenu !== um) {
                openMenu.removeAttribute('open');
            }
        });

        var rowMenu = e.target.closest('details.row-actions-menu');
        document.querySelectorAll('details.row-actions-menu[open]').forEach(function (openMenu) {
            if (openMenu !== rowMenu) {
                openMenu.removeAttribute('open');
                resetRowActionsPanel(openMenu);
            }
        });
    });

    function resetRowActionsPanel(menu) {
        var panel = menu && menu.querySelector('.row-actions-panel');
        if (!panel) return;
        panel.style.position = '';
        panel.style.top = '';
        panel.style.left = '';
        panel.style.right = '';
        panel.style.minWidth = '';
        panel.style.zIndex = '';
    }

    function positionRowActionsPanel(menu) {
        var trigger = menu.querySelector('.row-actions-trigger');
        var panel = menu.querySelector('.row-actions-panel');
        if (!trigger || !panel) return;

        panel.style.position = 'fixed';
        panel.style.zIndex = '1200';
        panel.style.minWidth = '11rem';

        var rect = trigger.getBoundingClientRect();
        var panelWidth = panel.offsetWidth || 176;
        var panelHeight = panel.offsetHeight || 160;
        var gap = 6;
        var margin = 8;
        var top = rect.bottom + gap;
        var left = rect.right - panelWidth;

        if (left < margin) left = margin;
        if (left + panelWidth > window.innerWidth - margin) {
            left = window.innerWidth - panelWidth - margin;
        }
        if (top + panelHeight > window.innerHeight - margin) {
            top = rect.top - panelHeight - gap;
        }

        panel.style.top = Math.max(margin, top) + 'px';
        panel.style.left = Math.max(margin, left) + 'px';
        panel.style.right = 'auto';
    }

    document.querySelectorAll('details.row-actions-menu').forEach(function (menu) {
        menu.addEventListener('toggle', function () {
            if (!menu.open) {
                resetRowActionsPanel(menu);
                return;
            }
            requestAnimationFrame(function () {
                positionRowActionsPanel(menu);
            });
        });
    });

    window.addEventListener('resize', function () {
        document.querySelectorAll('details.row-actions-menu[open]').forEach(positionRowActionsPanel);
    });

    window.addEventListener('scroll', function () {
        document.querySelectorAll('details.row-actions-menu[open]').forEach(positionRowActionsPanel);
    }, true);

    document.querySelectorAll('[data-password-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var wrap = btn.closest('.password-field');
            if (!wrap) return;
            var input = wrap.querySelector('[data-password-input]');
            if (!input) return;
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.textContent = show ? '🙈' : '👁';
            btn.setAttribute('aria-label', show ? 'إخفاء كلمة المرور' : 'إظهار كلمة المرور');
        });
    });

    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            var msg = form.getAttribute('data-confirm');
            if (msg && !window.confirm(msg)) {
                e.preventDefault();
            }
        });
    });

    document.querySelectorAll('.alert').forEach(function (alert) {
        setTimeout(function () {
            alert.style.transition = 'opacity 0.3s ease';
            alert.style.opacity = '0';
            setTimeout(function () { alert.remove(); }, 300);
        }, 5000);
    });
})();
