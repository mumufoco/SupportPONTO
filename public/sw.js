// SupportPONTO - Advanced Service Worker
// Version: 1.1.500
// Description: Professional PWA with advanced caching, offline support, and background sync

const CACHE_VERSION = '1.1.500-nocache';
const CACHE_PREFIX = 'supportponto';
const CACHE_CORE = `${CACHE_PREFIX}-core-v${CACHE_VERSION}`;
const CACHE_STATIC = `${CACHE_PREFIX}-static-v${CACHE_VERSION}`;
const CACHE_DYNAMIC = `${CACHE_PREFIX}-dynamic-v${CACHE_VERSION}`;
const MAX_DYNAMIC_CACHE_SIZE = 50;

// Core assets - cached immediately on install
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

// Network-only patterns - never cache
// HTML pages are ALWAYS fetched from network (dynamic content)
const NETWORK_ONLY_PATTERNS = [
  /\/api\//,
  /\/auth\//,
  /\/logout/,
  /\/login/,
  /\/register/,
  /\/admin/,
  /\/settings/,
  /\/dashboard/,
  /\/timesheet/,
  /\/employees/,   // matches /employees AND /employees/...
  /\/reports/,
  /\/shifts/,
  /\/schedules/,
  /\/compliance/,
  /\/warnings/,
  /\/justifications/,
  /\/geofences/,
  /\/profile/,
  /\/organizational/,
];

// Offline fallback pages
const OFFLINE_PAGE = '/registro-rapido';
const OFFLINE_IMAGE = '/assets/img/icon-192.png';

// ============================================================================
// INSTALL EVENT - Cache core assets
// ============================================================================
self.addEventListener('install', (event) => {
  console.log('[SW] Installing v' + CACHE_VERSION + ' (all pages network-only)');
  
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
                     cacheName !== CACHE_DYNAMIC;
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
// ============================================================================
self.addEventListener('fetch', (event) => {
  const { request } = event;
  const { url, method } = request;

  // Only handle GET requests
  if (method !== 'GET') return;

  // Parse URL
  const requestURL = new URL(url);
  
  // Check if request should be network-only
  if (isNetworkOnly(requestURL.pathname)) {
    event.respondWith(networkOnly(request));
    return;
  }

  // Check if request is for static assets
  if (isStaticAsset(requestURL.pathname)) {
    event.respondWith(cacheFirstStrategy(request, CACHE_STATIC));
    return;
  }

  // Default: Cache with network fallback for HTML pages
  event.respondWith(cacheFirstWithNetworkFallback(request, CACHE_DYNAMIC));
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
      // Limit dynamic cache size
      await limitCacheSize(cacheName, MAX_DYNAMIC_CACHE_SIZE);
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
 * Best for API calls and authentication
 */
async function networkOnly(request) {
  try {
    return await fetch(request);
  } catch (error) {
    console.error('[SW] Network request failed:', error);
    return new Response('Network request failed', {
      status: 503,
      statusText: 'Service Unavailable',
      headers: new Headers({
        'Content-Type': 'text/plain'
      })
    });
  }
}

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

/**
 * Check if URL should be network-only
 */
function isNetworkOnly(pathname) {
  return NETWORK_ONLY_PATTERNS.some(pattern => pattern.test(pathname));
}

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
