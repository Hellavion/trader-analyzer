import Echo from 'laravel-echo';
import { WaveConnector } from 'laravel-wave';

/**
 * Laravel Echo настроен для работы с Laravel Wave (SSE)
 * вместо WebSocket для real-time обновлений
 */
window.Echo = new Echo({
    broadcaster: WaveConnector,
    endpoint: 'http://127.0.0.1:8001/wave',
    namespace: 'App.Events',
    auth: {
        headers: {},
    },
    authEndpoint: '/broadcasting/auth',
    csrfToken: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
    pauseInactive: false,
    debug: true,
});

// Глобальные типы для TypeScript
declare global {
    interface Window {
        Echo: Echo;
    }
}

export default window.Echo;