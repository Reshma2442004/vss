/**
 * Service Worker for VSS Hostel Management System
 * Provides offline functionality and caching
 */

const CACHE_NAME = 'vss-hostel-v1.0.0';
const OFFLINE_URL = '/vss/offline.html';

// Files to cache for offline functionality
const CACHE_FILES = [
    '/vss/',
    '/vss/auth/login.php',
    '/vss/assets/style.css',
    '/vss/assets/modern-dashboard.css',
    '/vss/assets/mobile-responsive.css',
    '/vss/assets/mobile-interactions.js',
    '/vss/manifest.json',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css',
    'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap'
];

// Install event - cache resources
self.addEventListener('install', event => {
    console.log('Service Worker: Installing...');
    
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => {
                console.log('Service Worker: Caching files');
                return cache.addAll(CACHE_FILES);
            })
            .then(() => {
                console.log('Service Worker: Cached all files successfully');
                return self.skipWaiting();
            })
            .catch(error => {
                console.error('Service Worker: Cache failed', error);
            })
    );
});

// Activate event - clean up old caches
self.addEventListener('activate', event => {
    console.log('Service Worker: Activating...');
    
    event.waitUntil(
        caches.keys()
            .then(cacheNames => {
                return Promise.all(
                    cacheNames.map(cacheName => {
                        if (cacheName !== CACHE_NAME) {
                            console.log('Service Worker: Deleting old cache', cacheName);
                            return caches.delete(cacheName);
                        }
                    })
                );
            })
            .then(() => {
                console.log('Service Worker: Activated');
                return self.clients.claim();
            })
    );
});

// Fetch event - serve cached content when offline
self.addEventListener('fetch', event => {
    // Skip non-GET requests
    if (event.request.method !== 'GET') {
        return;
    }

    // Skip requests to external domains (except CDNs)
    const url = new URL(event.request.url);
    const isExternal = url.origin !== location.origin;
    const isCDN = url.hostname.includes('cdn.') || 
                  url.hostname.includes('cdnjs.') || 
                  url.hostname.includes('fonts.googleapis.com');

    if (isExternal && !isCDN) {
        return;
    }

    event.respondWith(
        caches.match(event.request)
            .then(response => {
                // Return cached version if available
                if (response) {
                    console.log('Service Worker: Serving from cache', event.request.url);
                    return response;
                }

                // Try to fetch from network
                return fetch(event.request)
                    .then(response => {
                        // Don't cache if not a valid response
                        if (!response || response.status !== 200 || response.type !== 'basic') {
                            return response;
                        }

                        // Clone the response
                        const responseToCache = response.clone();

                        // Add to cache for future use
                        caches.open(CACHE_NAME)
                            .then(cache => {
                                cache.put(event.request, responseToCache);
                            });

                        return response;
                    })
                    .catch(error => {
                        console.log('Service Worker: Fetch failed, serving offline page', error);
                        
                        // Serve offline page for navigation requests
                        if (event.request.destination === 'document') {
                            return caches.match(OFFLINE_URL);
                        }
                        
                        // For other requests, return a generic offline response
                        return new Response('Offline', {
                            status: 503,
                            statusText: 'Service Unavailable',
                            headers: new Headers({
                                'Content-Type': 'text/plain'
                            })
                        });
                    });
            })
    );
});

// Background sync for offline form submissions
self.addEventListener('sync', event => {
    console.log('Service Worker: Background sync', event.tag);
    
    if (event.tag === 'background-sync') {
        event.waitUntil(
            syncOfflineData()
        );
    }
});

// Push notification handling
self.addEventListener('push', event => {
    console.log('Service Worker: Push received');
    
    const options = {
        body: event.data ? event.data.text() : 'New notification from VSS Hostel',
        icon: '/vss/assets/icons/icon-192x192.png',
        badge: '/vss/assets/icons/icon-72x72.png',
        vibrate: [100, 50, 100],
        data: {
            dateOfArrival: Date.now(),
            primaryKey: 1
        },
        actions: [
            {
                action: 'explore',
                title: 'View Details',
                icon: '/vss/assets/icons/view-icon.png'
            },
            {
                action: 'close',
                title: 'Close',
                icon: '/vss/assets/icons/close-icon.png'
            }
        ]
    };
    
    event.waitUntil(
        self.registration.showNotification('VSS Hostel Management', options)
    );
});

// Notification click handling
self.addEventListener('notificationclick', event => {
    console.log('Service Worker: Notification clicked', event);
    
    event.notification.close();
    
    if (event.action === 'explore') {
        event.waitUntil(
            clients.openWindow('/vss/dashboards/')
        );
    } else if (event.action === 'close') {
        // Just close the notification
        return;
    } else {
        // Default action - open the app
        event.waitUntil(
            clients.openWindow('/vss/')
        );
    }
});

// Message handling from main thread
self.addEventListener('message', event => {
    console.log('Service Worker: Message received', event.data);
    
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
    
    if (event.data && event.data.type === 'CACHE_UPDATE') {
        event.waitUntil(
            updateCache()
        );
    }
});

/**
 * Sync offline data when connection is restored
 */
async function syncOfflineData() {
    try {
        // Get offline data from IndexedDB or localStorage
        const offlineData = await getOfflineData();
        
        if (offlineData && offlineData.length > 0) {
            console.log('Service Worker: Syncing offline data', offlineData.length, 'items');
            
            for (const data of offlineData) {
                try {
                    await fetch(data.url, {
                        method: data.method,
                        headers: data.headers,
                        body: data.body
                    });
                    
                    // Remove from offline storage after successful sync
                    await removeOfflineData(data.id);
                } catch (error) {
                    console.error('Service Worker: Failed to sync data', error);
                }
            }
        }
    } catch (error) {
        console.error('Service Worker: Sync failed', error);
    }
}

/**
 * Get offline data from storage
 */
async function getOfflineData() {
    // Implementation would depend on your offline storage strategy
    // This is a placeholder for the actual implementation
    return [];
}

/**
 * Remove synced data from offline storage
 */
async function removeOfflineData(id) {
    // Implementation would depend on your offline storage strategy
    // This is a placeholder for the actual implementation
    console.log('Service Worker: Removing offline data', id);
}

/**
 * Update cache with new resources
 */
async function updateCache() {
    try {
        const cache = await caches.open(CACHE_NAME);
        await cache.addAll(CACHE_FILES);
        console.log('Service Worker: Cache updated successfully');
    } catch (error) {
        console.error('Service Worker: Cache update failed', error);
    }
}

// Periodic background sync (if supported)
self.addEventListener('periodicsync', event => {
    console.log('Service Worker: Periodic sync', event.tag);
    
    if (event.tag === 'content-sync') {
        event.waitUntil(
            syncOfflineData()
        );
    }
});

// Handle unhandled promise rejections
self.addEventListener('unhandledrejection', event => {
    console.error('Service Worker: Unhandled promise rejection', event.reason);
    event.preventDefault();
});

console.log('Service Worker: Loaded successfully');