// SupportPONTO - Advanced Service Worker
// Version: 1.1.502
// Description: Professional PWA with advanced caching, offline support, and background sync

const CACHE_VERSION = '1.1.502-network-first-default';
const CACHE_PREFIX = 'supportponto';
const CACHE_CORE = `${CACHE_PREFIX}-core-v${CACHE_VERSION}`;
const CACHE_STATIC = `${CACHE_PREFIX}-static-v${CACHE_VERSION}`;
const MAX_CORE_CACHE_SIZE = 20;

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
                     cacheName !== CACHE_STATIC;
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
//   3. Qualquer outra coisa (toda pagina HTML dinamica do app)
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

  // 3) Padrao: qualquer outra pagina (dashboard, timesheet, employees,
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
 */
async function getOfflineFallback(request) {
  const cache = await caches.open(CACHE_CORE);

  if (request.destination === 'document') {
    return await cache.match(OFFLINE_PAGE) || new Response('Offline', { status: 503 });
  }

  if (request.destination === 'image') {
    return await cache.match(OFFLINE_IMAGE) || new Response('Offline', { status: 503 });
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

/**
 * Sync offline time punches when connection is restored
 */
async function syncTimePunches() {
  try {
    console.log('[SW] Syncing offline time punches...');
    // This would integrate with IndexedDB to sync offline data
    // Implementation depends on your backend API structure
    return Promise.resolve();
  } catch (error) {
    console.error('[SW] Failed to sync time punches:', error);
    throw error;
  }
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
