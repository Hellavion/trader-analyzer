import Echo from 'laravel-echo';
import { WaveConnector } from 'laravel-wave';

/**
 * Laravel Echo настроен для работы с Laravel Wave (SSE)
 * вместо WebSocket для real-time обновлений
 */
const waveBaseUrl = document.querySelector('meta[name="wave-base-url"]')?.getAttribute('content') || '';

window.Echo = new Echo({
    broadcaster: WaveConnector,
    endpoint: `${waveBaseUrl}/wave`,
    namespace: 'App.Events',
    auth: {
        headers: {},
    },
    authEndpoint: `${waveBaseUrl}/broadcasting/auth`,
    csrfToken: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
    pauseInActive: false,
    debug: true,
});

// Глобальные типы для TypeScript
declare global {
    interface Window {
        Echo: Echo;
    }
}

export default window.Echo;