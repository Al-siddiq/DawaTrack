import { StatusPanel } from '../components/status-panel.js';

const { createApp } = window.Vue;

createApp({
    components: { StatusPanel },
    setup() {
        return {};
    },
}).mount('#shop-app');
