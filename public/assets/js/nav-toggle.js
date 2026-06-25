(function () {
    "use strict";

    function init() {
        var body = document.querySelector("[data-rd-nav]");
        if (!body) return;

        var panel = body.querySelector("[data-rd-nav-panel]");
        var backdrop = body.querySelector("[data-rd-nav-backdrop]");
        var toggle = body.querySelector("[data-rd-nav-toggle]");
        if (!panel || !toggle) return;

        var open = false;

        function apply() {
            panel.classList.toggle("is-open", open);
            if (backdrop) backdrop.classList.toggle("is-visible", open);
            toggle.setAttribute("aria-expanded", open ? "true" : "false");
        }

        function setOpen(next) {
            if (next === open) return;
            open = !!next;
            apply();
        }

        toggle.addEventListener("click", function () {
            setOpen(!open);
        });

        if (backdrop) {
            backdrop.addEventListener("click", function () {
                setOpen(false);
            });
        }

        document.addEventListener("keydown", function (e) {
            if (e.key === "Escape" || e.key === "Esc") setOpen(false);
        });

        apply();
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})();
