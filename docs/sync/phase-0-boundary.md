# Phase 0 offline boundary

The manifest and manually maintained Service Worker establish versioned PWA boundaries. The Phase 0 worker does not intercept fetches, populate caches, store authenticated data, use IndexedDB, create an outbox, or claim offline business support. Dexie is vendored for the approved future architecture but is not loaded by current shells. Offline data and synchronization begin only in their approved roadmap phase.
