/**
 * SupportPONTO — Bundle JS consolidado
 * Ordem: csrf-fetch → pwa-install → uiux-453 → layout → ux-maintenance-478 → offline-sync
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
    var toggles = document.querySelectorAll('[data-sidebar-toggle], #appSidebarCollapseTop, #appSidebarCollapseBottom');
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

/* ─────────────────────────────────────────────────────────
   7. VALIDAÇÃO — CPF, CEP (ViaCEP), formato de e-mail
   Portado de app/Views/employees/partials/_personal_data.php,
   que já validava isso em produção. Extraído aqui para reuso
   em todos os campos do sistema, sem duplicar a lógica.
   ───────────────────────────────────────────────────────── */
(function (window, document) {
  'use strict';

  function setStatus(el, opts, ok, text) {
    if (opts && opts.wrapId) {
      var w = document.getElementById(opts.wrapId);
      if (w) { w.classList.toggle('sp-field-ok', ok === true); w.classList.toggle('sp-field-err', ok === false); }
      if (opts.msgId) {
        var m = document.getElementById(opts.msgId);
        if (m) m.textContent = text || '';
      }
      return;
    }
    // Fallback: sem wrap/msg dedicados, usa as classes nativas do Bootstrap no próprio input.
    el.classList.toggle('is-valid', ok === true);
    el.classList.toggle('is-invalid', ok === false);
  }

  function maskCpf(v) {
    v = v.replace(/\D/g, '').slice(0, 11);
    return v.replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d{1,2})$/, '$1-$2');
  }

  function validateCpf(raw) {
    var s = raw.replace(/\D/g, '');
    if (s.length !== 11 || /^(.)\1{10}$/.test(s)) return false;
    var sum = 0, r, i;
    for (i = 0; i < 9; i++) sum += +s[i] * (10 - i);
    r = 11 - (sum % 11); if (r >= 10) r = 0;
    if (r !== +s[9]) return false;
    sum = 0;
    for (i = 0; i < 10; i++) sum += +s[i] * (11 - i);
    r = 11 - (sum % 11); if (r >= 10) r = 0;
    return r === +s[10];
  }

  function bindCpfField(el, opts) {
    if (!el) return;
    opts = opts || {};
    el.addEventListener('input', function () {
      this.value = maskCpf(this.value);
      var raw = this.value.replace(/\D/g, '');
      if (raw.length < 11) { setStatus(this, opts, null, ''); return; }
      var ok = validateCpf(raw);
      setStatus(this, opts, ok, ok ? '✓ CPF válido' : '✗ CPF inválido');
    });
    el.dispatchEvent(new Event('input'));
  }

  function maskCep(v) {
    v = v.replace(/\D/g, '').slice(0, 8);
    return v.length > 5 ? v.slice(0, 5) + '-' + v.slice(5) : v;
  }

  function bindCepField(el, opts) {
    if (!el) return;
    opts = opts || {};
    var fields = opts.fields || {};

    function setField(id, value) {
      if (!id || !value) return;
      var target = document.getElementById(id);
      if (target) target.value = value;
    }

    el.addEventListener('input', function () {
      this.value = maskCep(this.value);
    });

    function toggleSpinner(spinner, show) {
      if (!spinner) return;
      // Cobre as duas convenções usadas hoje: estilo inline direto e a classe
      // utilitária .d-none do Bootstrap (que usa !important e vence o inline).
      spinner.style.display = show ? 'inline-block' : 'none';
      spinner.classList.toggle('d-none', !show);
    }

    el.addEventListener('blur', async function () {
      var raw = this.value.replace(/\D/g, '');
      if (raw.length !== 8) return;

      var spinner = opts.spinnerId ? document.getElementById(opts.spinnerId) : null;
      toggleSpinner(spinner, true);
      setStatus(this, opts, null, 'Buscando endereço...');
      try {
        var r = await fetch('https://viacep.com.br/ws/' + raw + '/json/', { cache: 'force-cache' });
        var d = await r.json();
        if (d.erro) {
          setStatus(this, opts, false, '✗ CEP não encontrado');
          return;
        }

        if (fields.logradouroCombined) {
          var combined = d.logradouro || '';
          if (d.bairro) combined += (combined ? ', ' : '') + d.bairro;
          setField(fields.logradouroCombined, combined);
        } else {
          setField(fields.logradouro, d.logradouro);
          setField(fields.bairro, d.bairro);
        }
        setField(fields.municipio, d.localidade);

        if (fields.uf) {
          var ufEl = document.getElementById(fields.uf);
          if (ufEl && d.uf) {
            if (ufEl.tagName === 'SELECT') {
              for (var i = 0; i < ufEl.options.length; i++) {
                if (ufEl.options[i].value === d.uf) { ufEl.selectedIndex = i; break; }
              }
            } else {
              ufEl.value = d.uf;
            }
          }
        }

        setStatus(this, opts, true, '✓ Endereço preenchido');
        if (fields.numero) document.getElementById(fields.numero)?.focus();
      } catch (_) {
        setStatus(this, opts, false, '✗ Não foi possível buscar o CEP');
      } finally {
        toggleSpinner(spinner, false);
      }
    });
  }

  function validateEmailFormat(value) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(value);
  }

  function bindEmailFormatField(el, opts) {
    if (!el) return;
    opts = opts || {};
    el.addEventListener('input', function () {
      var v = this.value.trim();
      if (!v) { setStatus(this, opts, null, ''); return; }
      var ok = validateEmailFormat(v);
      setStatus(this, opts, ok, ok ? '✓ E-mail válido' : '✗ Formato inválido');
    });
    el.dispatchEvent(new Event('input'));
  }

  window.SupportPontoValidation = {
    maskCpf: maskCpf,
    validateCpf: validateCpf,
    bindCpfField: bindCpfField,
    maskCep: maskCep,
    bindCepField: bindCepField,
    validateEmailFormat: validateEmailFormat,
    bindEmailFormatField: bindEmailFormatField
  };
})(window, document);

/* ─────────────────────────────────────────────────────────
   6. Fila de registro de ponto offline  (offline-sync.js)
   ───────────────────────────────────────────────────────── */
/**
 * SupportPONTO - Fila de registro de ponto offline (PWA)
 *
 * Guarda localmente (IndexedDB) marcações de ponto capturadas sem conexão
 * com o servidor e sincroniza automaticamente assim que a internet volta.
 * Preserva o horário original da captura (client_captured_at), o tipo de
 * marcação, a credencial usada (código/CPF/QR/foto facial) e a localização,
 * exatamente como o fluxo online — só o destino do envio muda enquanto
 * offline (ver app/Views/timesheet/partials/punch_scripts/ui.php).
 *
 * Mesmo nome/versão/store do IndexedDB usados por public/sw.js — os dois
 * precisam concordar sobre o schema, já que o service worker também lê essa
 * fila (Background Sync, caminho complementar para quando a página está
 * fechada).
 */
(function (window, document) {
    'use strict';

    const DB_NAME = 'supportponto-offline';
    const DB_VERSION = 1;
    const STORE_NAME = 'pending_punches';
    const OFFLINE_PAGE_PATH = '/timesheet/punch';
    const GEOLOCATION_TIMEOUT_MS = 4000;
    const DEFAULT_SYNC_ENDPOINT = '/timesheet/punch/sync';

    let dbPromise = null;
    let syncing = false;
    let retryTimer = null;
    const statusListeners = [];

    function openDb() {
        if (dbPromise) return dbPromise;
        dbPromise = new Promise((resolve, reject) => {
            const request = indexedDB.open(DB_NAME, DB_VERSION);
            request.onupgradeneeded = () => {
                const db = request.result;
                if (!db.objectStoreNames.contains(STORE_NAME)) {
                    const store = db.createObjectStore(STORE_NAME, { keyPath: 'client_uuid' });
                    store.createIndex('by_status', 'status', { unique: false });
                }
            };
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
        return dbPromise;
    }

    function requestToPromise(request) {
        return new Promise((resolve, reject) => {
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    async function withStore(mode) {
        const db = await openDb();
        return db.transaction(STORE_NAME, mode).objectStore(STORE_NAME);
    }

    function uuid() {
        if (window.crypto && typeof window.crypto.randomUUID === 'function') {
            return window.crypto.randomUUID();
        }
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
            const r = (Math.random() * 16) | 0;
            const v = c === 'x' ? r : (r & 0x3) | 0x8;
            return v.toString(16);
        });
    }

    function captureGeolocation() {
        return new Promise((resolve) => {
            if (!('geolocation' in navigator)) {
                resolve({ lat: null, lng: null, accuracy: null });
                return;
            }
            let settled = false;
            const finish = (value) => {
                if (settled) return;
                settled = true;
                resolve(value);
            };
            const timer = setTimeout(() => finish({ lat: null, lng: null, accuracy: null }), GEOLOCATION_TIMEOUT_MS);
            try {
                navigator.geolocation.getCurrentPosition(
                    (pos) => { clearTimeout(timer); finish({ lat: pos.coords.latitude, lng: pos.coords.longitude, accuracy: pos.coords.accuracy }); },
                    () => { clearTimeout(timer); finish({ lat: null, lng: null, accuracy: null }); },
                    { enableHighAccuracy: true, timeout: GEOLOCATION_TIMEOUT_MS, maximumAge: 60000 }
                );
            } catch (error) {
                clearTimeout(timer);
                finish({ lat: null, lng: null, accuracy: null });
            }
        });
    }

    /**
     * Grava uma marcação offline. `payload` é exatamente o mesmo objeto que
     * SupportPontoPunchUI.sendPunch() enviaria online (unique_code/cpf/token/
     * photo/punch_type/holiday_override) — assim nenhuma tela precisa saber
     * se está montando um payload "online" ou "offline".
     */
    async function queuePunch(method, payload) {
        const geo = await captureGeolocation();
        const record = {
            client_uuid: uuid(),
            punch_type: payload.punch_type,
            method: method,
            credentials: {
                unique_code: payload.unique_code || null,
                cpf: payload.cpf || null,
                qr_data: payload.token || payload.qr_data || null,
            },
            photo: payload.photo || null,
            holiday_override: payload.holiday_override || 0,
            location_lat: geo.lat,
            location_lng: geo.lng,
            location_accuracy: geo.accuracy,
            client_captured_at: new Date().toISOString(),
            status: 'queued',
            server_response: null,
            error_message: null,
            created_at: new Date().toISOString(),
            updated_at: new Date().toISOString(),
        };

        const store = await withStore('readwrite');
        await requestToPromise(store.add(record));
        notifyStatusChange();
        return record;
    }

    async function getQueue() {
        const store = await withStore('readonly');
        const all = await requestToPromise(store.getAll());
        return all.sort((a, b) => String(a.client_captured_at).localeCompare(String(b.client_captured_at)));
    }

    async function updateItem(clientUuid, changes) {
        const store = await withStore('readwrite');
        const record = await requestToPromise(store.get(clientUuid));
        if (!record) return;
        Object.assign(record, changes, { updated_at: new Date().toISOString() });
        await requestToPromise(store.put(record));
        notifyStatusChange();
    }

    function syncEndpoint() {
        return (window.SupportPontoPunchConfig && window.SupportPontoPunchConfig.endpoints && window.SupportPontoPunchConfig.endpoints.sync)
            || DEFAULT_SYNC_ENDPOINT;
    }

    function payloadFor(item) {
        return {
            client_uuid: item.client_uuid,
            punch_type: item.punch_type,
            method: item.method,
            unique_code: item.credentials.unique_code,
            cpf: item.credentials.cpf,
            qr_data: item.credentials.qr_data,
            photo: item.photo,
            holiday_override: item.holiday_override,
            latitude: item.location_lat,
            longitude: item.location_lng,
            accuracy: item.location_accuracy,
            client_captured_at: item.client_captured_at,
        };
    }

    /**
     * Sincroniza a fila local, um item de cada vez (aguarda a resposta antes
     * do próximo) — a validação de sequência no servidor depende disso: cada
     * chamada já reflete o resultado da anterior, como se o colaborador
     * tivesse batido os pontos em sequência normal.
     */
    async function syncQueue() {
        if (syncing || !navigator.onLine) return;
        syncing = true;

        try {
            const doFetch = window.spFetch || window.fetch;
            const endpoint = syncEndpoint();
            const queue = (await getQueue()).filter((item) => item.status === 'queued' || item.status === 'failed');

            for (const item of queue) {
                if (!navigator.onLine) break;

                await updateItem(item.client_uuid, { status: 'syncing' });

                let response;
                try {
                    response = await doFetch(endpoint, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        body: JSON.stringify(payloadFor(item)),
                    });
                } catch (networkError) {
                    await updateItem(item.client_uuid, { status: 'queued' });
                    break;
                }

                if (response.status === 401) {
                    await updateItem(item.client_uuid, { status: 'queued' });
                    notifySessionExpired();
                    break;
                }

                if (response.status === 429) {
                    const retryAfterHeader = parseInt(response.headers.get('Retry-After') || '30', 10);
                    await updateItem(item.client_uuid, { status: 'queued' });
                    scheduleRetry((isNaN(retryAfterHeader) ? 30 : retryAfterHeader) * 1000);
                    break;
                }

                const json = await response.json().catch(() => ({}));
                if (response.ok && json.success !== false) {
                    await updateItem(item.client_uuid, {
                        status: response.status === 202 ? 'pending_review' : 'synced',
                        server_response: json.data || null,
                        error_message: null,
                    });
                } else {
                    await updateItem(item.client_uuid, {
                        status: 'failed',
                        error_message: (json && json.message) || ('Erro HTTP ' + response.status),
                    });
                }
            }
        } finally {
            syncing = false;
            notifyStatusChange();
        }
    }

    function scheduleRetry(delayMs) {
        if (retryTimer) clearTimeout(retryTimer);
        retryTimer = setTimeout(() => { if (navigator.onLine) syncQueue(); }, delayMs);
    }

    function onStatusChange(callback) {
        if (typeof callback === 'function') statusListeners.push(callback);
    }

    function notifyStatusChange() {
        getQueue().then((queue) => {
            statusListeners.forEach((cb) => { try { cb({ queue, sessionExpired: false }); } catch (_) {} });
        }).catch(() => {});
    }

    function notifySessionExpired() {
        statusListeners.forEach((cb) => { try { cb({ queue: [], sessionExpired: true }); } catch (_) {} });
    }

    function feedbackRegion() {
        return document.getElementById('sp-global-action-feedback');
    }

    function showOfflineBanner() {
        const region = feedbackRegion();
        if (!region) return;
        region.classList.remove('d-none');
        region.innerHTML = '<div class="alert alert-warning mb-0" data-sp-offline-banner>'
            + 'Você está sem conexão. Continue registrando seu ponto normalmente — tudo será salvo neste dispositivo e sincronizado automaticamente quando a internet voltar.'
            + '</div>';
    }

    function hideOfflineBanner() {
        const region = feedbackRegion();
        if (!region) return;
        const banner = region.querySelector('[data-sp-offline-banner]');
        if (banner) {
            banner.remove();
            if (!region.innerHTML.trim()) {
                region.classList.add('d-none');
            }
        }
    }

    function showSessionExpiredNotice() {
        const region = feedbackRegion();
        if (!region) return;
        region.classList.remove('d-none');
        region.innerHTML = '<div class="alert alert-danger mb-0">'
            + 'Sua sessão expirou enquanto você estava offline. '
            + '<a href="' + encodeURI('/login') + '">Faça login novamente</a> para sincronizar os pontos salvos neste dispositivo — eles não serão perdidos.'
            + '</div>';
    }

    function currentOfflinePagePath() {
        return OFFLINE_PAGE_PATH;
    }

    function handleOffline() {
        showOfflineBanner();
        const path = (window.location.pathname || '/').replace(/\/+$/, '') || '/';
        if (path !== currentOfflinePagePath()) {
            window.location.href = currentOfflinePagePath();
        }
    }

    function handleOnline() {
        hideOfflineBanner();
        syncQueue();
        if ('serviceWorker' in navigator && navigator.serviceWorker.ready) {
            navigator.serviceWorker.ready.then((registration) => {
                if (registration.sync && typeof registration.sync.register === 'function') {
                    registration.sync.register('sync-timepunches').catch(() => {});
                }
            }).catch(() => {});
        }
    }

    onStatusChange((event) => {
        if (event.sessionExpired) {
            showSessionExpiredNotice();
        }
    });

    window.addEventListener('offline', handleOffline);
    window.addEventListener('online', handleOnline);

    document.addEventListener('DOMContentLoaded', () => {
        if (!navigator.onLine) {
            handleOffline();
        } else {
            syncQueue();
        }
    });

    window.SupportPontoOffline = {
        queuePunch,
        getQueue,
        syncQueue,
        onStatusChange,
    };
})(window, document);
