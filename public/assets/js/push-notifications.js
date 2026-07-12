/**
 * Push Notifications Client
 * Sistema de Ponto Eletrônico
 */

class PushNotificationManager {
    constructor(vapidPublicKey) {
        this.vapidPublicKey = vapidPublicKey;
        this.registration = null;
        this.subscription = null;
        this.isSupported = 'serviceWorker' in navigator && 'PushManager' in window;
    }

    /**
     * Initialize push notifications
     */
    async init() {
        if (!this.isSupported) {
            console.warn('[Push] Push notifications not supported');
            return false;
        }

        try {
            // Register service worker
            this.registration = await navigator.serviceWorker.register('/sw.js');
            console.log('[Push] Service worker registered');

            // Wait for service worker to be ready
            await navigator.serviceWorker.ready;

            // Check existing subscription
            this.subscription = await this.registration.pushManager.getSubscription();

            if (this.subscription) {
                console.log('[Push] Already subscribed');
                return true;
            }

            return false;
        } catch (error) {
            console.error('[Push] Initialization error:', error);
            return false;
        }
    }

    /**
     * Request notification permission
     */
    async requestPermission() {
        if (!this.isSupported) {
            return { granted: false, error: 'Push notifications not supported' };
        }

        if (Notification.permission === 'granted') {
            return { granted: true };
        }

        if (Notification.permission === 'denied') {
            return { granted: false, error: 'Notification permission denied' };
        }

        try {
            const permission = await Notification.requestPermission();

            if (permission === 'granted') {
                return { granted: true };
            } else {
                return { granted: false, error: 'Permission not granted' };
            }
        } catch (error) {
            console.error('[Push] Permission error:', error);
            return { granted: false, error: error.message };
        }
    }

    /**
     * Subscribe to push notifications
     */
    async subscribe() {
        if (!this.isSupported) {
            return { success: false, error: 'Push notifications not supported' };
        }

        try {
            // Request permission first
            const permissionResult = await this.requestPermission();

            if (!permissionResult.granted) {
                return { success: false, error: permissionResult.error };
            }

            // Subscribe to push
            this.subscription = await this.registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: this.urlBase64ToUint8Array(this.vapidPublicKey)
            });

            console.log('[Push] Subscribed successfully');

            // Send subscription to server
            const result = await this.sendSubscriptionToServer(this.subscription);

            if (result.success) {
                return { success: true, subscription: this.subscription };
            } else {
                return { success: false, error: result.message };
            }
        } catch (error) {
            console.error('[Push] Subscribe error:', error);
            return { success: false, error: error.message };
        }
    }

    /**
     * Unsubscribe from push notifications
     */
    async unsubscribe() {
        if (!this.subscription) {
            return { success: true };
        }

        try {
            // Unsubscribe from push manager
            await this.subscription.unsubscribe();
            console.log('[Push] Unsubscribed successfully');

            // Notify server
            await spFetch('/chat/push/unsubscribe', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    endpoint: this.subscription.endpoint
                })
            });

            this.subscription = null;

            return { success: true };
        } catch (error) {
            console.error('[Push] Unsubscribe error:', error);
            return { success: false, error: error.message };
        }
    }

    /**
     * Send subscription to server
     */
    async sendSubscriptionToServer(subscription) {
        try {
            const response = await spFetch('/chat/push/subscribe', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(subscription.toJSON())
            });

            const data = await response.json();

            return data;
        } catch (error) {
            console.error('[Push] Send subscription error:', error);
            return { success: false, message: error.message };
        }
    }

    /**
     * Test push notification
     */
    async testNotification() {
        try {
            const response = await spFetch('/chat/push/test', {
                method: 'POST'
            });

            const data = await response.json();

            return data;
        } catch (error) {
            console.error('[Push] Test notification error:', error);
            return { success: false, message: error.message };
        }
    }

    /**
     * Check if subscribed
     */
    isSubscribed() {
        return this.subscription !== null;
    }

    /**
     * Get permission status
     */
    getPermissionStatus() {
        if (!this.isSupported) {
            return 'unsupported';
        }

        return Notification.permission;
    }

    /**
     * Convert VAPID key to Uint8Array
     */
    urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding)
            .replace(/\-/g, '+')
            .replace(/_/g, '/');

        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);

        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }

        return outputArray;
    }

    /**
     * Show browser notification (for testing)
     */
    async showTestNotification() {
        if (!this.isSupported || Notification.permission !== 'granted') {
            console.warn('[Push] Cannot show notification');
            return;
        }

        const options = {
            body: 'As notificações push estão funcionando!',
            icon: '/assets/img/icon-192.png',
            badge: '/assets/img/badge-72.png',
            vibrate: [200, 100, 200],
            tag: 'test',
            requireInteraction: false
        };

        if (this.registration && this.registration.showNotification) {
            await this.registration.showNotification('Teste de Notificação', options);
        } else {
            new Notification('Teste de Notificação', options);
        }
    }
}

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = PushNotificationManager;
}
