/**
 * SupportPONTO — Bundle JS consolidado
 * Ordem: csrf-fetch → pwa-install → uiux-453 → layout → ux-maintenance-478
 */

/* ─────────────────────────────────────────────────────────
   1. CSRF-aware fetch  (csrf-fetch.js)
   ───────────────────────────────────────────────────────── */
(function(window, document) {
    'use strict';

    function getMeta(name) {
        const element = document.querySelector(`meta[name="${name}"]`);
        return element ? element.getAttribute('content') || '' : '';
    }

    function getCookie(name) {
        const escaped = name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        const match = document.cookie.match(new RegExp('(?:^|; )' + escaped + '=([^;]*)'));
        return match ? decodeURIComponent(match[1]) : '';
    }

    function getCsrfHeaderName() {
        return getMeta('csrf-header') || 'X-CSRF-TOKEN';
    }

    function getCsrfTokenName() {
        return getMeta('csrf-token-name') || 'csrf_token';
    }

    function getCsrfCookieName() {
        return getMeta('csrf-cookie-name') || 'csrf_cookie_name';
    }

    function getCsrfToken() {
        return getCookie(getCsrfCookieName()) || getMeta('csrf-hash') || '';
    }

    function isMutatingMethod(method) {
        return ['POST', 'PUT', 'PATCH', 'DELETE'].includes(String(method || 'GET').toUpperCase());
    }

    function normalizeHeaders(headers) {
        return new Headers(headers || {});
    }

    function shouldAttachBodyToken(headers, body) {
        if (!body) return false;
        const contentType = (headers.get('Content-Type') || '').toLowerCase();
        return body instanceof FormData || contentType.includes('application/x-www-form-urlencoded');
    }

    function attachBodyToken(body, tokenName, tokenValue) {
        if (!tokenValue) return body;
        if (body instanceof FormData) {
            if (!body.has(tokenName)) body.append(tokenName, tokenValue);
            return body;
        }
        if (body instanceof URLSearchParams) {
            if (!body.has(tokenName)) body.append(tokenName, tokenValue);
            return body;
        }
        if (typeof body === 'string') {
            const params = new URLSearchParams(body);
            if (!params.has(tokenName)) params.append(tokenName, tokenValue);
            return params.toString();
        }
        return body;
    }

    function secureFetch(url, options) {
        const requestOptions = Object.assign({}, options || {});
        const method = String(requestOptions.method || 'GET').toUpperCase();
        const headers = normalizeHeaders(requestOptions.headers);
        const csrfToken = getCsrfToken();
        const csrfHeaderName = getCsrfHeaderName();
        const csrfTokenName = getCsrfTokenName();

        headers.set('X-Requested-With', headers.get('X-Requested-With') || 'XMLHttpRequest');

        if (isMutatingMethod(method) && csrfToken) {
            headers.set(csrfHeaderName, csrfToken);
            if (shouldAttachBodyToken(headers, requestOptions.body)) {
                requestOptions.body = attachBodyToken(requestOptions.body, csrfTokenName, csrfToken);
            }
        }

        requestOptions.method = method;
        requestOptions.headers = headers;

        return window.fetch(url, requestOptions);
    }

    window.SupportPontoSecurity = {
        getCsrfToken,
        getCsrfTokenName,
        getCsrfCookieName,
        getCsrfHeaderName,
        secureFetch,
    };

    window.spFetch = secureFetch;
})(window, document);

/* ─────────────────────────────────────────────────────────
   2. PWA Installer  (pwa-install.js)
   ───────────────────────────────────────────────────────── */
class PWAInstaller {
  constructor() {
    this.deferredPrompt = null;
    this.isInstalled = false;
    this.isStandalone = false;
    this.serviceWorkerRegistration = null;
    this.UPDATE_CHECK_INTERVAL_MS = 30 * 60 * 1000;
    this.installButton = null;
    this.init();
  }

  async init() {
    this.checkStandaloneMode();
    this.setupInstallPrompt();
    await this.registerServiceWorker();
    this.createInstallButton();
    this.startUpdateChecks();
  }

  checkStandaloneMode() {
    this.isStandalone = window.matchMedia('(display-mode: standalone)').matches ||
                       window.navigator.standalone === true ||
                       document.referrer.includes('android-app://');
    this.isInstalled = this.isStandalone;
    if (this.isStandalone) document.body.classList.add('pwa-standalone');
  }

  setupInstallPrompt() {
    window.addEventListener('beforeinstallprompt', (e) => {
      e.preventDefault();
      this.deferredPrompt = e;
      this.showInstallButton();
    });
    window.addEventListener('appinstalled', () => {
      this.isInstalled = true;
      this.hideInstallButton();
      this.showNotification('App instalado com sucesso!', 'success');
    });
  }

  async registerServiceWorker() {
    if (!('serviceWorker' in navigator)) return;
    try {
      this.serviceWorkerRegistration = await navigator.serviceWorker.register('/sw.js', { scope: '/' });
      this.serviceWorkerRegistration.addEventListener('updatefound', () => {
        const newWorker = this.serviceWorkerRegistration.installing;
        newWorker.addEventListener('statechange', () => {
          if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
            this.showUpdateNotification();
          }
        });
      });
      navigator.serviceWorker.addEventListener('controllerchange', () => {
        window.location.reload();
      });
    } catch (error) {
      console.error('[PWA] Service Worker registration failed:', error);
    }
  }

  createInstallButton() {
    if (this.isStandalone || this.installButton) return;
    const button = document.createElement('button');
    button.id = 'pwa-install-button';
    button.className = 'pwa-install-btn';
    button.innerHTML = `
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
        <polyline points="7 10 12 15 17 10"></polyline>
        <line x1="12" y1="15" x2="12" y2="3"></line>
      </svg>
      <span>Instalar App</span>
    `;
    button.style.display = 'none';
    button.addEventListener('click', () => this.showInstallPrompt());
    document.body.appendChild(button);
    this.installButton = button;
    if (!document.getElementById('pwa-install-styles')) {
      this.addInstallButtonStyles();
    }
  }

  addInstallButtonStyles() {
    const style = document.createElement('style');
    style.id = 'pwa-install-styles';
    style.textContent = `
      .pwa-install-btn {
        position: fixed; bottom: 20px; right: 20px; z-index: 9998;
        display: flex; align-items: center; gap: 8px; padding: 12px 20px;
        background: linear-gradient(135deg, #3A7AFE 0%, #00B79E 100%);
        color: white; border: none; border-radius: 50px;
        font-size: 14px; font-weight: 600;
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        cursor: pointer; box-shadow: 0 4px 20px rgba(58,122,254,.4);
        transition: all .3s ease; animation: slideInUp .5s ease-out;
      }
      .pwa-install-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 30px rgba(58,122,254,.6); }
      .pwa-install-btn:active { transform: translateY(0); }
      .pwa-install-btn svg { width: 20px; height: 20px; }
      @keyframes slideInUp { from { transform: translateY(100px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
      @media (max-width: 768px) {
        .pwa-install-btn { bottom: 15px; right: 15px; padding: 10px 16px; font-size: 13px; }
        .pwa-install-btn span { display: none; }
        .pwa-install-btn svg { width: 24px; height: 24px; }
      }
      .pwa-update-notification {
        position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%);
        z-index: 9999; background: white; padding: 16px 24px; border-radius: 12px;
        box-shadow: 0 8px 32px rgba(0,0,0,.2); display: flex; align-items: center;
        gap: 16px; max-width: 90%; animation: slideInUp .5s ease-out;
      }
      .pwa-update-notification .message { flex-grow: 1; font-size: 14px; color: #333; }
      .pwa-update-notification .btn-update {
        background: linear-gradient(135deg, #3A7AFE 0%, #00B79E 100%);
        color: white; border: none; padding: 8px 16px; border-radius: 6px;
        font-weight: 600; cursor: pointer; transition: all .3s ease;
      }
      .pwa-update-notification .btn-update:hover { transform: scale(1.05); }
      .pwa-update-notification .btn-dismiss { background: transparent; border: none; color: #999; cursor: pointer; padding: 4px; }
    `;
    document.head.appendChild(style);
  }

  showInstallButton() {
    if (this.installButton && !this.isStandalone) this.installButton.style.display = 'flex';
  }

  hideInstallButton() {
    if (this.installButton) this.installButton.style.display = 'none';
  }

  async showInstallPrompt() {
    if (!this.deferredPrompt) return;
    try {
      this.deferredPrompt.prompt();
      const { outcome } = await this.deferredPrompt.userChoice;
      if (outcome === 'accepted') this.hideInstallButton();
      this.deferredPrompt = null;
    } catch (error) {
      console.error('[PWA] Install prompt error:', error);
    }
  }

  showUpdateNotification() {
    const notification = document.createElement('div');
    notification.className = 'pwa-update-notification';
    notification.innerHTML = `
      <div class="message">
        <strong>Atualização disponível!</strong>
        <p style="margin:4px 0 0;font-size:13px;color:#666;">Uma nova versão do app está pronta.</p>
      </div>
      <button class="btn-update">Atualizar</button>
      <button class="btn-dismiss">&times;</button>
    `;
    document.body.appendChild(notification);
    notification.querySelector('.btn-update').addEventListener('click', () => {
      if (this.serviceWorkerRegistration && this.serviceWorkerRegistration.waiting) {
        this.serviceWorkerRegistration.waiting.postMessage({ type: 'SKIP_WAITING' });
      }
      notification.remove();
    });
    notification.querySelector('.btn-dismiss').addEventListener('click', () => notification.remove());
  }

  startUpdateChecks() {
    if (!this.serviceWorkerRegistration) return;
    setInterval(() => this.serviceWorkerRegistration.update(), this.UPDATE_CHECK_INTERVAL_MS);
  }

  showNotification(message, type = 'info') {
    if (typeof window.showToast === 'function') {
      window.showToast(message, type);
      return;
    }
    console.log(`[PWA] ${type.toUpperCase()}: ${message}`);
  }

  getInstallStatus() {
    return { isInstalled: this.isInstalled, isStandalone: this.isStandalone, canInstall: this.deferredPrompt !== null };
  }

  async getVersion() {
    if (!this.serviceWorkerRegistration) return null;
    return new Promise((resolve) => {
      const messageChannel = new MessageChannel();
      messageChannel.port1.onmessage = (event) => resolve(event.data.version);
      if (this.serviceWorkerRegistration.active) {
        this.serviceWorkerRegistration.active.postMessage({ type: 'GET_VERSION' }, [messageChannel.port2]);
      } else {
        resolve(null);
      }
    });
  }
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => { window.pwaInstaller = new PWAInstaller(); });
} else {
  window.pwaInstaller = new PWAInstaller();
}

/* ─────────────────────────────────────────────────────────
   3. UI/UX interactions  (supportponto-uiux-453.js)
   ───────────────────────────────────────────────────────── */
(function () {
  'use strict';

  const ready = (callback) => {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', callback, { once: true });
      return;
    }
    callback();
  };

  ready(() => {
    // Password toggle
    document.querySelectorAll('[data-password-toggle]').forEach((button) => {
      button.addEventListener('click', () => {
        const fieldId = button.getAttribute('data-password-toggle');
        const field = fieldId ? document.getElementById(fieldId) : null;
        const icon = fieldId ? document.getElementById(fieldId + '-icon') : null;
        if (!field) return;
        const show = field.getAttribute('type') === 'password';
        field.setAttribute('type', show ? 'text' : 'password');
        button.setAttribute('aria-pressed', show ? 'true' : 'false');
        if (icon) {
          icon.classList.toggle('bi-eye', !show);
          icon.classList.toggle('bi-eye-slash', show);
        }
      });
    });

    // Form submit loading state
    document.querySelectorAll('form').forEach((form) => {
      form.addEventListener('submit', () => {
        const submitter = form.querySelector('button[type="submit"], input[type="submit"]');
        if (submitter && !submitter.hasAttribute('data-no-loading')) {
          submitter.setAttribute('data-sp-loading', 'true');
          document.body.classList.add('sp-busy');
        }
      });
    });

    // Confirm dialogs
    document.querySelectorAll('[data-confirm-message]').forEach((element) => {
      element.addEventListener('click', (event) => {
        const message = element.getAttribute('data-confirm-message') || 'Confirma esta ação?';
        if (!window.confirm(message)) {
          event.preventDefault();
          event.stopPropagation();
        }
      });
    });

    // Focus first invalid field
    const firstInvalid = document.querySelector('.is-invalid, [aria-invalid="true"]');
    if (firstInvalid && typeof firstInvalid.focus === 'function') {
      firstInvalid.focus({ preventScroll: true });
      firstInvalid.scrollIntoView({ block: 'center', behavior: 'smooth' });
    }
  });
})();

/* ─────────────────────────────────────────────────────────
   4. Sidebar layout  (supportponto-layout.js)
   ───────────────────────────────────────────────────────── */
(function () {
  'use strict';

  var LS_KEY = 'sp_sidebar_collapsed';

  function isDesktop() { return window.innerWidth > 991; }

  function applyCollapsedState(sidebar) {
    if (!sidebar) return;
    var collapsed = localStorage.getItem(LS_KEY) === '1';
    if (isDesktop() && collapsed) {
      sidebar.classList.add('is-collapsed');
    } else {
      sidebar.classList.remove('is-collapsed');
    }
  }

  // Aplicar antes do DOMContentLoaded para evitar flash
  var sidebarEarly = document.getElementById('appSidebar');
  if (sidebarEarly) applyCollapsedState(sidebarEarly);

  document.addEventListener('DOMContentLoaded', function () {
    var sidebar = document.getElementById('appSidebar');
    if (!sidebar) return;

    // Aplicar estado salvo
    applyCollapsedState(sidebar);

    // Toggle button
    var toggles = document.querySelectorAll('[data-sidebar-toggle]');
    toggles.forEach(function (btn) {
      btn.addEventListener('click', function () {
        if (isDesktop()) {
          // Desktop: colapsar/expandir com persistência
          var isNowCollapsed = sidebar.classList.toggle('is-collapsed');
          localStorage.setItem(LS_KEY, isNowCollapsed ? '1' : '0');
          // Atualizar ícone
          var icon = btn.querySelector('i');
          if (icon) {
            icon.className = isNowCollapsed ? 'bi bi-layout-sidebar-reverse' : 'bi bi-list';
          }
        } else {
          // Mobile: abrir/fechar overlay
          sidebar.classList.toggle('is-open');
        }
      });
    });

    // Mobile: fechar ao clicar fora
    document.addEventListener('click', function (e) {
      if (!sidebar || isDesktop()) return;
      var insideSidebar = sidebar.contains(e.target);
      var isToggle = e.target.closest('[data-sidebar-toggle]');
      if (!insideSidebar && !isToggle) sidebar.classList.remove('is-open');
    });

    // Ajustar ao redimensionar janela
    var resizeTimer;
    window.addEventListener('resize', function () {
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(function () {
        if (!isDesktop()) {
          sidebar.classList.remove('is-collapsed');
        } else {
          applyCollapsedState(sidebar);
        }
      }, 150);
    });

    if (window.lucide) {
      window.lucide.createIcons({ attrs: { width: 20, height: 20, 'stroke-width': 1.9 } });
    }
  });
})();

/* ─────────────────────────────────────────────────────────
   5. UX maintenance utilities  (supportponto-ux-maintenance-478.js)
   ───────────────────────────────────────────────────────── */
(function () {
  'use strict';

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function setFeedback(target, message, variant) {
    if (!target || !message) return;
    var safeVariant = ['success', 'danger', 'warning', 'info', 'primary', 'secondary'].indexOf(variant) >= 0 ? variant : 'info';
    target.classList.remove('d-none');
    target.innerHTML = '<div class="alert alert-' + safeVariant + ' mb-0" role="alert">' + escapeHtml(message) + '</div>';
    target.focus && target.focus({ preventScroll: true });
  }

  // data-sp-ux-toggle: aria-controlled collapse
  document.addEventListener('click', function (event) {
    var toggle = event.target.closest('[data-sp-ux-toggle]');
    if (!toggle) return;
    var targetId = toggle.getAttribute('aria-controls');
    var target = targetId ? document.getElementById(targetId) : null;
    if (!target) return;
    var expanded = toggle.getAttribute('aria-expanded') === 'true';
    toggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
    target.hidden = expanded;
  });

  window.SupportPontoUX = window.SupportPontoUX || {};
  window.SupportPontoUX.setFeedback = function (id, message, variant) {
    setFeedback(document.getElementById(id), message, variant);
  };
})();

/* ─────────────────────────────────────────────────────────
   6. THEME SWITCHER — claro / escuro
   ───────────────────────────────────────────────────────── */
(function () {
  'use strict';

  var LS_KEY = 'sp_theme';
  var DEFAULT_THEME = 'dark';

  function applyTheme(theme) {
    var html = document.documentElement;
    html.setAttribute('data-theme', theme);
    var icon = document.getElementById('sp-theme-icon');
    if (icon) {
      icon.className = theme === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-fill';
    }
    var btn = document.getElementById('sp-theme-toggle');
    if (btn) {
      btn.title = theme === 'dark' ? 'Mudar para tema claro' : 'Mudar para tema escuro';
    }
  }

  function getStoredTheme() {
    try { return localStorage.getItem(LS_KEY); } catch(e) { return null; }
  }

  function storeTheme(theme) {
    try { localStorage.setItem(LS_KEY, theme); } catch(e) {}
  }

  // Aplicar tema salvo antes do DOMContentLoaded para evitar flash
  var savedTheme = getStoredTheme() || DEFAULT_THEME;
  applyTheme(savedTheme);

  document.addEventListener('DOMContentLoaded', function () {
    // Aplicar novamente após DOM pronto (para atualizar ícone)
    applyTheme(getStoredTheme() || DEFAULT_THEME);

    var btn = document.getElementById('sp-theme-toggle');
    if (!btn) return;

    btn.addEventListener('click', function () {
      var current = document.documentElement.getAttribute('data-theme') || DEFAULT_THEME;
      var next = current === 'dark' ? 'light' : 'dark';
      storeTheme(next);
      applyTheme(next);
    });
  });
})();
