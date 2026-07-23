// SupportPONTO - Advanced Service Worker
// Version: 1.1.503
// Description: Professional PWA with advanced caching, offline support, and background sync

const CACHE_VERSION = '1.1.503-offline-punch-sync';
const CACHE_PREFIX = 'supportponto';
const CACHE_CORE = `${CACHE_PREFIX}-core-v${CACHE_VERSION}`;
const CACHE_STATIC = `${CACHE_PREFIX}-static-v${CACHE_VERSION}`;
const CACHE_OFFLINE_SHELL = `${CACHE_PREFIX}-offline-shell-v${CACHE_VERSION}`;
const MAX_CORE_CACHE_SIZE = 20;

// Página "molde" da experiência de ponto offline: sempre que carregada com
// sucesso online, o HTML mais recente é salvo neste cache. É o que
// getOfflineFallback() serve para QUALQUER navegação autenticada que falhar
// por falta de rede — não só para /timesheet/punch em si — para que "todas
// as páginas autenticadas redirecionam para a tela de ponto offline" quando
// o dispositivo perde conexão (ver offline-sync.js no lado do cliente, que
// cobre o caso de a página já estar aberta e ficar offline em primeiro
// plano; este cache cobre o caso de navegação/refresh sem rede alguma).
const OFFLINE_SHELL_PATH = '/timesheet/punch';

// Core assets - cached immediately on install. Estas sao as UNICAS paginas
// HTML que ficam disponiveis offline (landing/acesso rapido); tudo o mais e
// network-only por padrao (ver FETCH EVENT abaixo).
const CORE_ASSETS = [
  '/',
  '/registro-rapido',
  '/assets/img/icon-192.png',
  '/assets/img/icon-512.png',
  '/manifest.webmanifest'
];

// Static assets - cached on-demand with Cache First strategy
const STATIC_CACHE_PATTERNS = [
  /\.css$/,
  /\.js$/,
  /\.woff2?$/,
  /\.ttf$/,
  /\.eot$/,
  /\.svg$/,
  /\.png$/,
  /\.jpg$/,
  /\.jpeg$/,
  /\.gif$/,
  /\.webp$/
];

// Offline fallback pages
const OFFLINE_PAGE = '/registro-rapido';
const OFFLINE_IMAGE = '/assets/img/icon-192.png';

// ============================================================================
// INSTALL EVENT - Cache core assets
// ============================================================================
self.addEventListener('install', (event) => {
  console.log('[SW] Installing v' + CACHE_VERSION + ' (all pages network-only by default)');

  event.waitUntil(
    caches.open(CACHE_CORE)
      .then((cache) => {
        console.log('[SW] Caching core assets');
        return cache.addAll(CORE_ASSETS);
      })
      .catch((error) => {
        console.error('[SW] Failed to cache core assets:', error);
      })
      .then(() => self.skipWaiting())
  );
});

// ============================================================================
// ACTIVATE EVENT - Clean old caches
// ============================================================================
self.addEventListener('activate', (event) => {
  console.log('[SW] Activating Service Worker v' + CACHE_VERSION);

  event.waitUntil(
    caches.keys()
      .then((cacheNames) => {
        return Promise.all(
          cacheNames
            .filter((cacheName) => {
              // Delete old caches that don't match current version
              return cacheName.startsWith(CACHE_PREFIX) &&
                     cacheName !== CACHE_CORE &&
                     cacheName !== CACHE_STATIC &&
                     cacheName !== CACHE_OFFLINE_SHELL;
            })
            .map((cacheName) => {
              console.log('[SW] Deleting old cache:', cacheName);
              return caches.delete(cacheName);
            })
        );
      })
      .then(() => {
        console.log('[SW] Service Worker activated and ready');
        return self.clients.claim();
      })
  );
});

// ============================================================================
// FETCH EVENT - Advanced caching strategies
//
// Estrategia (invertida — antes dependia de uma lista NETWORK_ONLY_PATTERNS
// manual que precisava listar toda rota dinamica do app, e ficava
// desatualizada toda vez que uma rota nova era criada, fazendo paginas
// esquecidas ficarem presas em cache desatualizado indefinidamente, sem
// nenhuma revalidacao — ja aconteceu em /lgpd/consents e /my-schedules):
//
//   1. Asset estatico (css/js/fonte/imagem)  -> cache-first
//   2. Pagina de entrada do PWA (CORE_ASSETS) -> cache-first com fallback de rede
//   3. Pagina "molde" do ponto offline        -> network-first, atualiza o
//      (OFFLINE_SHELL_PATH)                      cache do shell a cada acesso online
//   4. Qualquer outra coisa (toda pagina HTML dinamica do app)
//                                              -> SEMPRE rede (network-only)
// ============================================================================
self.addEventListener('fetch', (event) => {
  const { request } = event;
  const { url, method } = request;

  // Only handle GET requests
  if (method !== 'GET') return;

  // Parse URL
  const requestURL = new URL(url);

  // 1) Assets estaticos: cache-first, raramente mudam.
  if (isStaticAsset(requestURL.pathname)) {
    event.respondWith(cacheFirstStrategy(request, CACHE_STATIC));
    return;
  }

  // 2) Paginas de entrada do PWA cacheadas no install: mantem fallback de
  //    cache para funcionar offline.
  if (CORE_ASSETS.includes(requestURL.pathname)) {
    event.respondWith(cacheFirstWithNetworkFallback(request, CACHE_CORE));
    return;
  }

  // 3) Pagina de registro de ponto autenticada: busca da rede sempre que
  //    possivel (conteudo dinamico: CSRF token, nome do colaborador, metodos
  //    habilitados) mas atualiza uma copia offline a cada sucesso, para servir
  //    de fallback quando qualquer pagina autenticada falhar por falta de rede.
  if (requestURL.pathname === OFFLINE_SHELL_PATH) {
    event.respondWith(networkFirstAndUpdateOfflineShell(request));
    return;
  }

  // 4) Padrao: qualquer outra pagina (dashboard, timesheet, employees,
  //    my-schedules, lgpd, notifications, chat, audit, qrcode, o que vier a
  //    ser criado no futuro, etc.) e sempre buscada da rede.
  event.respondWith(networkOnly(request));
});

// ============================================================================
// CACHING STRATEGIES
// ============================================================================

/**
 * Cache First Strategy - Try cache, then network
 * Best for static assets that rarely change
 */
async function cacheFirstStrategy(request, cacheName) {
  try {
    const cache = await caches.open(cacheName);
    const cachedResponse = await cache.match(request);

    if (cachedResponse) {
      console.log('[SW] Cache hit:', request.url);
      return cachedResponse;
    }

    console.log('[SW] Cache miss, fetching:', request.url);
    const networkResponse = await fetch(request);

    if (networkResponse && networkResponse.status === 200) {
      cache.put(request, networkResponse.clone());
    }

    return networkResponse;
  } catch (error) {
    console.error('[SW] Cache first strategy failed:', error);
    return await getOfflineFallback(request);
  }
}

/**
 * Cache First with Network Fallback
 * Try cache, then network, then offline fallback
 */
async function cacheFirstWithNetworkFallback(request, cacheName) {
  try {
    const cache = await caches.open(cacheName);
    const cachedResponse = await cache.match(request);

    if (cachedResponse) {
      return cachedResponse;
    }

    const networkResponse = await fetch(request);

    if (networkResponse && networkResponse.status === 200 && networkResponse.type === 'basic') {
      // Limit core cache size (paginas de entrada do PWA — lista curta e fixa)
      await limitCacheSize(cacheName, MAX_CORE_CACHE_SIZE);
      cache.put(request, networkResponse.clone());
    }

    return networkResponse;
  } catch (error) {
    console.log('[SW] Offline, serving fallback for:', request.url);
    return await getOfflineFallback(request);
  }
}

/**
 * Network First + atualiza o shell offline do ponto
 * Busca a rede normalmente; em caso de sucesso, guarda uma copia em
 * CACHE_OFFLINE_SHELL (sobrescrevendo a anterior). Se a rede falhar, serve a
 * ultima copia salva — e so ai, na falta de qualquer copia, cai no fallback
 * generico (getOfflineFallback).
 */
async function networkFirstAndUpdateOfflineShell(request) {
  try {
    const networkResponse = await fetch(request);

    if (networkResponse && networkResponse.status === 200) {
      const cache = await caches.open(CACHE_OFFLINE_SHELL);
      cache.put(OFFLINE_SHELL_PATH, networkResponse.clone());
    }

    return networkResponse;
  } catch (error) {
    console.log('[SW] Offline: servindo shell salvo de', OFFLINE_SHELL_PATH);
    const cache = await caches.open(CACHE_OFFLINE_SHELL);
    const cachedShell = await cache.match(OFFLINE_SHELL_PATH);
    return cachedShell || await getOfflineFallback(request);
  }
}

/**
 * Network Only Strategy - Always fetch from network
 * Default strategy for every dynamic HTML page in the app.
 */
async function networkOnly(request) {
  try {
    return await fetch(request);
  } catch (error) {
    console.error('[SW] Network request failed:', error);
    return await getOfflineFallback(request);
  }
}

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

/**
 * Check if URL is a static asset
 */
function isStaticAsset(pathname) {
  return STATIC_CACHE_PATTERNS.some(pattern => pattern.test(pathname));
}

/**
 * Get offline fallback based on request type
 *
 * Para navegacao de documento (qualquer pagina autenticada que falhou por
 * falta de rede), prioriza o shell salvo de /timesheet/punch — e assim a
 * pessoa cai na tela de ponto offline em vez de um erro generico, atendendo
 * "todas as paginas autenticadas redirecionam para a tela de ponto offline"
 * sem precisar cachear cada pagina do sistema individualmente. So se esse
 * shell nunca foi salvo (colaborador nunca abriu /timesheet/punch online)
 * e' que cai no fallback publico antigo (/registro-rapido).
 */
async function getOfflineFallback(request) {
  if (request.destination === 'document') {
    const shellCache = await caches.open(CACHE_OFFLINE_SHELL);
    const cachedShell = await shellCache.match(OFFLINE_SHELL_PATH);
    if (cachedShell) {
      return cachedShell;
    }

    const coreCache = await caches.open(CACHE_CORE);
    return await coreCache.match(OFFLINE_PAGE) || new Response('Offline', { status: 503 });
  }

  if (request.destination === 'image') {
    const coreCache = await caches.open(CACHE_CORE);
    return await coreCache.match(OFFLINE_IMAGE) || new Response('Offline', { status: 503 });
  }

  return new Response('Offline', { status: 503 });
}

/**
 * Limit cache size by removing oldest entries (LRU eviction)
 * Uses iterative approach to avoid stack overflow with large caches
 */
async function limitCacheSize(cacheName, maxSize) {
  const cache = await caches.open(cacheName);
  const keys = await cache.keys();

  // Remove oldest entries if cache exceeds max size
  while (keys.length > maxSize) {
    console.log(`[SW] Cache ${cacheName} exceeds ${maxSize} items, removing oldest entry`);
    await cache.delete(keys.shift()); // Remove and return oldest (first) entry
  }
}

// ============================================================================
// BACKGROUND SYNC - Offline data synchronization
// ============================================================================
self.addEventListener('sync', (event) => {
  console.log('[SW] Background sync triggered:', event.tag);

  if (event.tag === 'sync-timepunches') {
    event.waitUntil(syncTimePunches());
  }
});

// Mesmo nome/versao/store usados pelo modulo de pagina em
// public/assets/js/offline-sync.js — os dois precisam concordar sobre o
// schema do IndexedDB, ja que ambos podem ler/escrever a mesma fila.
const OFFLINE_DB_NAME = 'supportponto-offline';
const OFFLINE_DB_VERSION = 1;
const OFFLINE_STORE_NAME = 'pending_punches';
const OFFLINE_SYNC_ENDPOINT = '/timesheet/punch/sync';
const OFFLINE_CSRF_COOKIE_NAME = 'csrf_cookie_name';
const OFFLINE_CSRF_HEADER_NAME = 'X-CSRF-TOKEN';

/**
 * Sync offline time punches when connection is restored.
 *
 * Caminho COMPLEMENTAR de sincronizacao: o caminho principal e' o listener
 * 'online' em primeiro plano (offline-sync.js, com acesso normal a
 * document.cookie para o token CSRF). Este caminho roda em background sync
 * (paginas fechadas), suportado apenas em navegadores Chromium — e nesse
 * contexto nao ha' acesso a document.cookie, entao o token CSRF e' lido via
 * Cookie Store API quando disponivel; se nao estiver, a tentativa falha com
 * 403 e o item permanece na fila para a proxima sincronizacao em primeiro
 * plano (nunca e' perdido).
 */
async function syncTimePunches() {
  console.log('[SW] Syncing offline time punches...');

  const db = await openOfflineDb();
  const queued = await getQueuedPunches(db);

  queued.sort((a, b) => String(a.client_captured_at).localeCompare(String(b.client_captured_at)));

  const csrfToken = await getCsrfTokenForBackgroundSync();

  for (const item of queued) {
    try {
      const headers = { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
      if (csrfToken) {
        headers[OFFLINE_CSRF_HEADER_NAME] = csrfToken;
      }

      const response = await fetch(OFFLINE_SYNC_ENDPOINT, {
        method: 'POST',
        headers,
        credentials: 'same-origin',
        body: JSON.stringify(offlineItemToPayload(item)),
      });

      if (response.status === 401 || response.status === 429) {
        // Sessao expirada ou throttle: para a fila inteira aqui — a proxima
        // sincronizacao em primeiro plano (offline-sync.js) trata esses casos
        // com a UI apropriada (pedir login / respeitar Retry-After).
        break;
      }

      const json = await response.json().catch(() => ({}));
      await updateQueuedPunch(db, item.client_uuid, response.ok && json.success !== false
        ? { status: (response.status === 202 ? 'pending_review' : 'synced'), server_response: json.data || null }
        : { status: 'failed', error_message: json.message || `HTTP ${response.status}` });
    } catch (error) {
      console.error('[SW] Falha ao sincronizar item offline (mantido na fila):', error);
      break;
    }
  }

  db.close();
}

function offlineItemToPayload(item) {
  return {
    client_uuid: item.client_uuid,
    punch_type: item.punch_type,
    method: item.method,
    unique_code: item.credentials ? item.credentials.unique_code : null,
    cpf: item.credentials ? item.credentials.cpf : null,
    qr_data: item.credentials ? item.credentials.qr_data : null,
    photo: item.photo || null,
    latitude: item.location_lat ?? null,
    longitude: item.location_lng ?? null,
    accuracy: item.location_accuracy ?? null,
    client_captured_at: item.client_captured_at,
  };
}

async function getCsrfTokenForBackgroundSync() {
  try {
    if ('cookieStore' in self) {
      const cookie = await self.cookieStore.get(OFFLINE_CSRF_COOKIE_NAME);
      if (cookie && cookie.value) {
        return cookie.value;
      }
    }
  } catch (error) {
    console.warn('[SW] Nao foi possivel ler o cookie de CSRF via cookieStore:', error);
  }
  return null;
}

function openOfflineDb() {
  return new Promise((resolve, reject) => {
    const request = indexedDB.open(OFFLINE_DB_NAME, OFFLINE_DB_VERSION);
    request.onupgradeneeded = () => {
      const db = request.result;
      if (!db.objectStoreNames.contains(OFFLINE_STORE_NAME)) {
        const store = db.createObjectStore(OFFLINE_STORE_NAME, { keyPath: 'client_uuid' });
        store.createIndex('by_status', 'status', { unique: false });
      }
    };
    request.onsuccess = () => resolve(request.result);
    request.onerror = () => reject(request.error);
  });
}

function getQueuedPunches(db) {
  return new Promise((resolve, reject) => {
    const tx = db.transaction(OFFLINE_STORE_NAME, 'readonly');
    const store = tx.objectStore(OFFLINE_STORE_NAME);
    const index = store.index('by_status');
    const request = index.getAll('queued');
    request.onsuccess = () => resolve(request.result || []);
    request.onerror = () => reject(request.error);
  });
}

function updateQueuedPunch(db, clientUuid, changes) {
  return new Promise((resolve, reject) => {
    const tx = db.transaction(OFFLINE_STORE_NAME, 'readwrite');
    const store = tx.objectStore(OFFLINE_STORE_NAME);
    const getRequest = store.get(clientUuid);
    getRequest.onsuccess = () => {
      const record = getRequest.result;
      if (!record) {
        resolve();
        return;
      }
      Object.assign(record, changes, { updated_at: new Date().toISOString() });
      const putRequest = store.put(record);
      putRequest.onsuccess = () => resolve();
      putRequest.onerror = () => reject(putRequest.error);
    };
    getRequest.onerror = () => reject(getRequest.error);
  });
}

// ============================================================================
// PUSH NOTIFICATIONS
// ============================================================================
self.addEventListener('push', (event) => {
  console.log('[SW] Push notification received');

  let notificationData = {
    title: 'SupportPONTO',
    body: 'Você tem uma nova notificação',
    icon: '/assets/img/icon-192.png',
    badge: '/assets/img/icon-72.png',
    vibrate: [200, 100, 200],
    data: {
      dateOfArrival: Date.now(),
      primaryKey: 1
    }
  };

  if (event.data) {
    try {
      notificationData = Object.assign(notificationData, event.data.json());
    } catch (error) {
      console.error('[SW] Failed to parse push data:', error);
    }
  }

  event.waitUntil(
    self.registration.showNotification(notificationData.title, notificationData)
  );
});

self.addEventListener('notificationclick', (event) => {
  console.log('[SW] Notification clicked');
  event.notification.close();

  event.waitUntil(
    clients.openWindow(event.notification.data?.url || '/')
  );
});

// ============================================================================
// MESSAGE HANDLER - Communication with app
// ============================================================================
self.addEventListener('message', (event) => {
  console.log('[SW] Message received:', event.data);

  if (event.data && event.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }

  if (event.data && event.data.type === 'GET_VERSION') {
    event.ports[0].postMessage({
      version: CACHE_VERSION
    });
  }
});
