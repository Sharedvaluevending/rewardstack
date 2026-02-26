import './bootstrap';
import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { router } from '@inertiajs/vue3';

const appName = import.meta.env.VITE_APP_NAME || 'Revenue QR';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#0ea5e9',
        showSpinner: true,
    },
});

// Global error handler for CSRF token expiration
router.on('error', (event) => {
    if (event.detail?.errors?.csrf_token || event.detail?.response?.status === 419) {
        // CSRF token expired - refresh the page to get a new one
        window.location.reload();
    }
});

// Also handle fetch/axios 419 errors globally
window.addEventListener('unhandledrejection', (event) => {
    if (event.reason?.response?.status === 419) {
        event.preventDefault();
        window.location.reload();
    }
});

// PWA Service Worker (production only)
// Conservative: this SW only precaches static assets (no portal/API caching).
if (import.meta.env.PROD && 'serviceWorker' in navigator) {
    import('virtual:pwa-register')
        .then(({ registerSW }) => {
            const updateSW = registerSW({
                // SW is generated at site root (/public/sw.js) for full scope.
                immediate: true,
                onNeedRefresh() {
                    const shouldRefresh = window.confirm('A new version is available. Reload now?');
                    if (shouldRefresh) {
                        updateSW(true);
                    }
                },
            });
        })
        .catch(() => {
            // Ignore SW registration errors (never block app boot).
        });
}
