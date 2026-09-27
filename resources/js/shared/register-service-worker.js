if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/service-worker.js', { scope: '/' }).catch((error) => {
            console.warn('Service worker registration was not completed.', error);
        });
    });
}
