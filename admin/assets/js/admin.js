/* ================= DARK MODE ================= */
(function () {
    const body   = document.body;
    const toggle = document.getElementById("themeToggle");
    const icon   = document.getElementById("themeIcon");

    if (!toggle || !icon) return;

    let dark = document.cookie.includes("admin_dark=1");

    if (!document.cookie.includes("admin_dark=")) {
        dark = window.matchMedia("(prefers-color-scheme: dark)").matches;
    }

    applyTheme(dark);

    function applyTheme(d) {
        body.classList.toggle("dark-mode", d);

        if (icon) {
            icon.classList.remove("bi-moon-stars", "bi-sun-fill");
            icon.classList.add(d ? "bi-sun-fill" : "bi-moon-stars");
        }
    }

    toggle.addEventListener("click", () => {
        dark = !dark;
        applyTheme(dark);
        document.cookie = "admin_dark=" + (dark ? 1 : 0) + "; path=/; max-age=31536000";
    });
})();

/* ================= SIDEBAR ================= */
(function () {
    const sidebar    = document.getElementById("adminSidebar");
    const overlay    = document.getElementById("sidebarOverlay");
    const toggle     = document.getElementById("sidebarToggle");
    const mobileOpen = document.getElementById("mobileOpen");

    if (!sidebar || !overlay || !toggle || !mobileOpen) return;

    toggle.addEventListener("click", () => {
        sidebar.classList.toggle("collapsed");
    });

    mobileOpen.addEventListener("click", () => {
        sidebar.classList.add("open");
        overlay.classList.add("show");
    });

    overlay.addEventListener("click", () => {
        sidebar.classList.remove("open");
        overlay.classList.remove("show");
    });

    document.addEventListener("click", (e) => {
        if (
            window.innerWidth <= 991 &&
            !sidebar.contains(e.target) &&
            !mobileOpen.contains(e.target)
        ) {
            sidebar.classList.remove("open");
            overlay.classList.remove("show");
        }
    });
})();

/* ================= SWIPE TO OPEN ================= */
(function () {
    const sidebar = document.getElementById("adminSidebar");
    const overlay = document.getElementById("sidebarOverlay");

    if (!sidebar || !overlay) return;

    let startX = 0;

    window.addEventListener("touchstart", (e) => {
        startX = e.touches[0].clientX;
    });

    window.addEventListener("touchend", (e) => {
        if (startX < 40 && e.changedTouches[0].clientX > 120) {
            sidebar.classList.add("open");
            overlay.classList.add("show");
        }
    });
})();

/* ================= QUICK ACTION BUTTON ================= */
(function () {
    const btn   = document.getElementById("quickBtn");
    const panel = document.getElementById("quickPanel");
    const close = document.getElementById("qpClose");

    if (!btn || !panel) return;

    btn.addEventListener("click", (e) => {
        e.stopPropagation();
        panel.classList.toggle("show");
    });

    if (close) {
        close.addEventListener("click", () => {
            panel.classList.remove("show");
        });
    }

    document.addEventListener("click", (e) => {
        if (!panel.contains(e.target) && !btn.contains(e.target)) {
            panel.classList.remove("show");
        }
    });
})();

/* ================= THEME COLOR PICKER ================= */
(function () {
    const pick = document.getElementById("themePicker");

    if (!pick) return;

    pick.addEventListener("input", (e) => {
        document.documentElement.style.setProperty("--brand-1", e.target.value);
        document.documentElement.style.setProperty("--brand-2", e.target.value);
    });
})();
