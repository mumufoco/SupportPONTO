/**
 * SupportPONTO - PWA Installation Manager
 * 
 * Manages PWA installation, service worker updates, and provides a custom
 * install prompt for better user experience.
 * 
 * Features:
 * - Custom install button
 * - Standalone mode detection
 * - Service worker registration and updates
 * - Update notifications
 * - Automatic update checks
 */

class PWAInstaller {
  constructor() {
    this.deferredPrompt = null;
    this.isInstalled = false;
    this.isStandalone = false;
    this.serviceWorkerRegistration = null;
    
    // Configuration constants
    this.UPDATE_CHECK_INTERVAL_MS = 30 * 60 * 1000; // 30 minutes
    
    this.installButton = null;
    
    this.init();
  }

  /**
   * Initialize PWA installer
   */
  async init() {
    this.checkStandaloneMode();
    this.setupInstallPrompt();
    await this.registerServiceWorker();
    this.createInstallButton();
    this.startUpdateChecks();
    
    console.log('[PWA] Installer initialized');
  }

  /**
   * Check if app is running in standalone mode
   */
  checkStandaloneMode() {
    this.isStandalone = window.matchMedia('(display-mode: standalone)').matches ||
                       window.navigator.standalone === true ||
                       document.referrer.includes('android-app://');
    
    this.isInstalled = this.isStandalone;
    
    if (this.isStandalone) {
      console.log('[PWA] Running in standalone mode');
      document.body.classList.add('pwa-standalone');
    }
  }

  /**
   * Setup beforeinstallprompt event listener
   */
  setupInstallPrompt() {
    window.addEventListener('beforeinstallprompt', (e) => {
      console.log('[PWA] Install prompt available');
      
      // Prevent automatic prompt
      e.preventDefault();
      
      // Store the event for later use
      this.deferredPrompt = e;
      
      // Show custom install button
      this.showInstallButton();
    });

    // Listen for app installed event
    window.addEventListener('appinstalled', () => {
      console.log('[PWA] App successfully installed');
      this.isInstalled = true;
      this.hideInstallButton();
      this.showNotification('App instalado com sucesso! 🎉', 'success');
    });
  }

  /**
   * Register service worker
   */
  async registerServiceWorker() {
    if (!('serviceWorker' in navigator)) {
      console.warn('[PWA] Service Worker not supported');
      return;
    }

    try {
      this.serviceWorkerRegistration = await navigator.serviceWorker.register('/sw.js', {
        scope: '/'
      });

      console.log('[PWA] Service Worker registered successfully');

      // Check for updates
      this.serviceWorkerRegistration.addEventListener('updatefound', () => {
        console.log('[PWA] Service Worker update found');
        const newWorker = this.serviceWorkerRegistration.installing;
        
        newWorker.addEventListener('statechange', () => {
          if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
            console.log('[PWA] New Service Worker ready');
            this.showUpdateNotification();
          }
        });
      });

      // Listen for controller change (new SW took over)
      navigator.serviceWorker.addEventListener('controllerchange', () => {
        console.log('[PWA] Service Worker controller changed');
        window.location.reload();
      });

    } catch (error) {
      console.error('[PWA] Service Worker registration failed:', error);
    }
  }

  /**
   * Create floating install button
   */
  createInstallButton() {
    if (this.isStandalone || this.installButton) {
      return;
    }

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
    
    // Add CSS if not already present
    if (!document.getElementById('pwa-install-styles')) {
      this.addInstallButtonStyles();
    }
  }

  /**
   * Add CSS styles for install button
   */
  addInstallButtonStyles() {
    const style = document.createElement('style');
    style.id = 'pwa-install-styles';
    style.textContent = `
      .pwa-install-btn {
        position: fixed;
        bottom: 20px;
        right: 20px;
        z-index: 9998;
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 12px 20px;
        background: linear-gradient(135deg, #3A7AFE 0%, #00B79E 100%);
        color: white;
        border: none;
        border-radius: 50px;
        font-size: 14px;
        font-weight: 600;
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        cursor: pointer;
        box-shadow: 0 4px 20px rgba(58, 122, 254, 0.4);
        transition: all 0.3s ease;
        animation: slideInUp 0.5s ease-out;
      }
      
      .pwa-install-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 30px rgba(58, 122, 254, 0.6);
      }
      
      .pwa-install-btn:active {
        transform: translateY(0);
      }
      
      .pwa-install-btn svg {
        width: 20px;
        height: 20px;
      }
      
      @keyframes slideInUp {
        from {
          transform: translateY(100px);
          opacity: 0;
        }
        to {
          transform: translateY(0);
          opacity: 1;
        }
      }
      
      @media (max-width: 768px) {
        .pwa-install-btn {
          bottom: 15px;
          right: 15px;
          padding: 10px 16px;
          font-size: 13px;
        }
        
        .pwa-install-btn span {
          display: none;
        }
        
        .pwa-install-btn svg {
          width: 24px;
          height: 24px;
        }
      }
      
      /* Update notification styles */
      .pwa-update-notification {
        position: fixed;
        bottom: 20px;
        left: 50%;
        transform: translateX(-50%);
        z-index: 9999;
        background: white;
        padding: 16px 24px;
        border-radius: 12px;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
        display: flex;
        align-items: center;
        gap: 16px;
        max-width: 90%;
        animation: slideInUp 0.5s ease-out;
      }
      
      .pwa-update-notification .message {
        flex-grow: 1;
        font-size: 14px;
        color: #333;
      }
      
      .pwa-update-notification .btn-update {
        background: linear-gradient(135deg, #3A7AFE 0%, #00B79E 100%);
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 6px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
      }
      
      .pwa-update-notification .btn-update:hover {
        transform: scale(1.05);
      }
      
      .pwa-update-notification .btn-dismiss {
        background: transparent;
        border: none;
        color: #999;
        cursor: pointer;
        padding: 4px;
      }
    `;
    document.head.appendChild(style);
  }

  /**
   * Show install button
   */
  showInstallButton() {
    if (this.installButton && !this.isStandalone) {
      this.installButton.style.display = 'flex';
    }
  }

  /**
   * Hide install button
   */
  hideInstallButton() {
    if (this.installButton) {
      this.installButton.style.display = 'none';
    }
  }

  /**
   * Show custom install prompt
   */
  async showInstallPrompt() {
    if (!this.deferredPrompt) {
      console.warn('[PWA] Install prompt not available');
      return;
    }

    try {
      // Show the install prompt
      this.deferredPrompt.prompt();
      
      // Wait for the user's response
      const { outcome } = await this.deferredPrompt.userChoice;
      
      console.log('[PWA] Install prompt outcome:', outcome);
      
      if (outcome === 'accepted') {
        console.log('[PWA] User accepted installation');
        this.hideInstallButton();
      } else {
        console.log('[PWA] User dismissed installation');
      }
      
      // Clear the deferred prompt
      this.deferredPrompt = null;
      
    } catch (error) {
      console.error('[PWA] Install prompt error:', error);
    }
  }

  /**
   * Show update notification
   */
  showUpdateNotification() {
    const notification = document.createElement('div');
    notification.className = 'pwa-update-notification';
    notification.innerHTML = `
      <div class="message">
        <strong>Atualização disponível!</strong>
        <p style="margin: 4px 0 0; font-size: 13px; color: #666;">Uma nova versão do app está pronta.</p>
      </div>
      <button class="btn-update">Atualizar</button>
      <button class="btn-dismiss">&times;</button>
    `;
    
    document.body.appendChild(notification);
    
    // Update button click
    notification.querySelector('.btn-update').addEventListener('click', () => {
      if (this.serviceWorkerRegistration && this.serviceWorkerRegistration.waiting) {
        this.serviceWorkerRegistration.waiting.postMessage({ type: 'SKIP_WAITING' });
      }
      notification.remove();
    });
    
    // Dismiss button click
    notification.querySelector('.btn-dismiss').addEventListener('click', () => {
      notification.remove();
    });
  }

  /**
   * Start automatic update checks
   */
  startUpdateChecks() {
    if (!this.serviceWorkerRegistration) {
      return;
    }

    setInterval(() => {
      console.log('[PWA] Checking for updates...');
      this.serviceWorkerRegistration.update();
    }, this.UPDATE_CHECK_INTERVAL_MS);
  }

  /**
   * Show notification to user
   */
  showNotification(message, type = 'info') {
    // Try to use existing toast system if available
    if (typeof window.showToast === 'function') {
      window.showToast(message, type);
      return;
    }

    // Fallback: create simple notification
    console.log(`[PWA] ${type.toUpperCase()}: ${message}`);
  }

  /**
   * Check if PWA is installed
   */
  getInstallStatus() {
    return {
      isInstalled: this.isInstalled,
      isStandalone: this.isStandalone,
      canInstall: this.deferredPrompt !== null
    };
  }

  /**
   * Get service worker version
   */
  async getVersion() {
    if (!this.serviceWorkerRegistration) {
      return null;
    }

    return new Promise((resolve) => {
      const messageChannel = new MessageChannel();
      messageChannel.port1.onmessage = (event) => {
        resolve(event.data.version);
      };
      
      if (this.serviceWorkerRegistration.active) {
        this.serviceWorkerRegistration.active.postMessage(
          { type: 'GET_VERSION' },
          [messageChannel.port2]
        );
      } else {
        resolve(null);
      }
    });
  }
}

// Auto-initialize PWA installer when DOM is ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => {
    window.pwaInstaller = new PWAInstaller();
  });
} else {
  window.pwaInstaller = new PWAInstaller();
}

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
  module.exports = PWAInstaller;
}
