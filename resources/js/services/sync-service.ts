import axios from 'axios';

// Сервис для синхронизации данных
class SyncService {
    private baseURL = '/';
    
    constructor() {
        // Настройка interceptor для CSRF токена
        this.setupCSRF();
    }

    private setupCSRF() {
        axios.interceptors.request.use(
            (config) => {
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                if (token && config.url?.startsWith('/')) {
                    config.headers['X-CSRF-TOKEN'] = token;
                }
                return config;
            },
            (error) => Promise.reject(error)
        );
    }

    // Запуск ручной синхронизации
    async triggerManualSync(): Promise<{ success: boolean; message: string; sync_timestamps: any[] }> {
        try {
            const response = await axios.post('/sync/manual', {}, {
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                }
            });
            
            return response.data;
        } catch (error) {
            console.error('Sync error:', error);
            throw new Error(
                error.response?.data?.message || 'Ошибка синхронизации'
            );
        }
    }

    // Проверка статуса синхронизации
    async checkSyncStatus(syncTimestamps: any[]): Promise<{ success: boolean; completed: boolean; updated_exchanges: number; total_exchanges: number }> {
        try {
            const response = await axios.post('/sync/status', { sync_timestamps: syncTimestamps }, {
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                }
            });
            
            return response.data;
        } catch (error) {
            console.error('Sync status check error:', error);
            throw new Error(
                error.response?.data?.message || 'Ошибка проверки статуса синхронизации'
            );
        }
    }

    // Ожидание завершения синхронизации с polling
    async waitForSyncCompletion(syncTimestamps: any[]): Promise<void> {
        const maxAttempts = 60; // Максимум 30 секунд (60 * 500ms)
        let attempts = 0;

        return new Promise((resolve, reject) => {
            const poll = async () => {
                try {
                    attempts++;
                    
                    if (attempts > maxAttempts) {
                        console.warn('Sync polling timeout after 30 seconds');
                        resolve(); // Не бросаем ошибку, просто завершаем
                        return;
                    }

                    const status = await this.checkSyncStatus(syncTimestamps);
                    
                    if (status.completed) {
                        console.log(`Sync completed! Updated ${status.updated_exchanges}/${status.total_exchanges} exchanges`);
                        resolve();
                        return;
                    }

                    console.log(`Sync in progress... ${status.updated_exchanges}/${status.total_exchanges} exchanges completed`);
                    
                    // Проверяем снова через 500ms
                    setTimeout(poll, 500);

                } catch (error) {
                    console.error('Sync polling error:', error);
                    reject(error);
                }
            };

            // Начинаем polling
            poll();
        });
    }
}

export const syncService = new SyncService();