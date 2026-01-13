// admin/assets/js/admin.js
// Controls: dark-mode, sidebar, mobile overlay, swipe-to-open, quick panel, theme-picker.
// Defensive coding: checks for elements before attaching listeners.

(function () {
  // Utility: cookie helpers
  const cookie = {
    get(name) {
      const m = document.cookie.match('(^|;)\\s*' + name + '\\s*=\\s*([^;]+)');
      return m ? decodeURIComponent(m.pop()) : null;
    },
    set(name, value, days = 365) {
      const d = new Date();
      d.setTime(d.getTime() + days * 24 * 60 * 60 * 1000);
      document.cookie = `${name}=${encodeURIComponent(value)};path=/;expires=${d.toUTCString()}`;
    }
  };

  // Elements (may not exist on all pages)
  const body = document.body;
  const sidebar = document.getElementById('adminSidebar');
  const overlay = document.getElementById('sidebarOverlay');
  const sidebarToggle = document.getElementById('sidebarToggle');
  const mobileOpen = document.getElementById('mobileOpen');
  const themeToggle = document.getElementById('themeToggle');
  const themeIcon = document.getElementById('themeIcon');
  const themePicker = document.getElementById('themePicker');
  const quickBtn = document.getElementById('quickBtn');
  const quickPanel = document.getElementById('quickPanel');
  const qpClose = document.getElementById('qpClose');

  // SAFETY: no-op if element missing
  function safeAdd(el, evt, fn) { if (el) el.addEventListener(evt, fn); }

  // ------------- DARK MODE -------------
  (function initTheme() {
    if (!themeToggle || !themeIcon) return;

    let dark = cookie.get('admin_dark') === '1';

    // if no cookie set, follow system
    if (cookie.get('admin_dark') === null) {
      dark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    }

    // if local theme color saved, apply
    const savedColor = localStorage.getItem('admin_brand_color');
    if (savedColor && document.documentElement) {
      document.documentElement.style.setProperty('--brand-1', savedColor);
      document.documentElement.style.setProperty('--brand-2', savedColor);
      if (themePicker) themePicker.value = savedColor;
    }

    applyTheme(dark);

    safeAdd(themeToggle, 'click', () => {
      dark = !dark;
      applyTheme(dark);
      cookie.set('admin_dark', dark ? '1' : '0', 365);
    });

    function applyTheme(isDark) {
      body.classList.toggle('dark-mode', !!isDark);
      if (!themeIcon) return;
      themeIcon.classList.remove('bi-moon-stars', 'bi-sun-fill');
      themeIcon.classList.add(isDark ? 'bi-sun-fill' : 'bi-moon-stars');
      // update aria
      if (themeToggle) themeToggle.setAttribute('aria-pressed', !!isDark);
    }
  })();

  // ------------- THEME COLOR PICKER -------------
  (function initPicker() {
    if (!themePicker) return;
    // set initial color if none
    if (!themePicker.value) {
      const cur = getComputedStyle(document.documentElement).getPropertyValue('--brand-1').trim();
      themePicker.value = rgbToHex(cur) || '#198754';
    }

    themePicker.addEventListener('input', (e) => {
      const v = e.target.value;
      document.documentElement.style.setProperty('--brand-1', v);
      document.documentElement.style.setProperty('--brand-2', v);
      localStorage.setItem('admin_brand_color', v);
    });

    // helper: rgb(...) to hex if needed
    function rgbToHex(v) {
      if (!v) return null;
      v = v.replace(/\s/g, '');
      if (v.startsWith('#')) return v;
      const m = v.match(/rgba?\((\d+),(\d+),(\d+)/i);
      if (!m) return null;
      return '#' + [1,2,3].map(i=>parseInt(m[i]).toString(16).padStart(2,'0')).join('');
    }
  })();

  // ------------- SIDEBAR (desktop collapse + mobile open) -------------
  (function initSidebar() {
    if (!sidebar) return;

    // collapse desktop
    safeAdd(sidebarToggle, 'click', () => {
      sidebar.classList.toggle('collapsed');
    });

    // open mobile
    safeAdd(mobileOpen, 'click', () => {
      sidebar.classList.add('open');
      if (overlay) overlay.classList.add('show');
    });

    // overlay click closes mobile
    safeAdd(overlay, 'click', () => {
      sidebar.classList.remove('open');
      overlay.classList.remove('show');
    });

    // click outside to close (mobile)
    document.addEventListener('click', (e) => {
      if (window.innerWidth <= 991) {
        const target = e.target;
        if (!sidebar.contains(target) && !mobileOpen.contains(target) && sidebar.classList.contains('open')) {
          sidebar.classList.remove('open');
          if (overlay) overlay.classList.remove('show');
        }
      }
    });

    // keyboard: ESC closes mobile panel
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        if (sidebar.classList.contains('open')) {
          sidebar.classList.remove('open');
          if (overlay) overlay.classList.remove('show');
        }
        if (quickPanel && quickPanel.classList.contains('show')) {
          quickPanel.classList.remove('show');
          quickBtn.setAttribute('aria-expanded', 'false');
        }
      }
    });
  })();

  // ------------- SWIPE TO OPEN (mobile) -------------
  (function initSwipe() {
    let startX = 0;
    window.addEventListener('touchstart', (e) => {
      if (!e.touches || !e.touches[0]) return;
      startX = e.touches[0].clientX;
    });
    window.addEventListener('touchend', (e) => {
      if (!e.changedTouches || !e.changedTouches[0]) return;
      const endX = e.changedTouches[0].clientX;
      // Quick swipe from left edge
      if (startX < 40 && endX - startX > 100) {
        if (sidebar) {
          sidebar.classList.add('open');
          if (overlay) overlay.classList.add('show');
        }
      }
    });
  })();

  // ------------- QUICK ACTION PANEL -------------
  (function initQuickPanel() {
    if (!quickBtn || !quickPanel) return;

    safeAdd(quickBtn, 'click', (e) => {
      const show = quickPanel.classList.toggle('show');
      quickBtn.setAttribute('aria-expanded', show ? 'true' : 'false');
      quickPanel.setAttribute('aria-hidden', (!show).toString());
    });

    safeAdd(qpClose, 'click', () => {
      quickPanel.classList.remove('show');
      quickBtn.setAttribute('aria-expanded', 'false');
      quickPanel.setAttribute('aria-hidden', 'true');
    });

    // close on outside click (but allow clicks inside)
    document.addEventListener('click', (e) => {
      if (!quickPanel.contains(e.target) && !quickBtn.contains(e.target)) {
        quickPanel.classList.remove('show');
        quickBtn.setAttribute('aria-expanded', 'false');
        quickPanel.setAttribute('aria-hidden', 'true');
      }
    });
  })();

  // ------------- Safety guards (console friendly) -------------
  // nothing else required
})();
