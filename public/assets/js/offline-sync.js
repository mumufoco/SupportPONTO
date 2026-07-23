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
