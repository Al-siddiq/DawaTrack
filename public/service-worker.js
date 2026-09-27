const SERVICE_WORKER_VERSION = 'dawatrack-phase-0-v1';

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));

// Phase 0 deliberately performs no fetch interception or authenticated-data caching.
