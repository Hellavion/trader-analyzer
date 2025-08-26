import { api, type ApiResponse } from './api';

export interface ExchangeConnection {
    id: number;
    exchange: string;
    display_name: string;
    is_active: boolean;
    has_valid_credentials: boolean;
    masked_api_key: string;
    last_sync_at: string | null;
    needs_sync: boolean;
    sync_settings: any;
    created_at: string;
    updated_at: string;
}

export interface SyncResponse {
    exchange: string;
    sync_status: 'queued' | 'running' | 'completed' | 'failed';
    last_sync_at: string | null;
    estimated_completion: string;
}

export const exchangeService = {
    /**
     * Получает список всех подключенных бирж
     */
    getAll: (): Promise<ApiResponse<ExchangeConnection[]>> =>
        api.get('/exchanges'),

    /**
     * Получает информацию о конкретной бирже
     */
    get: (exchange: string): Promise<ApiResponse<ExchangeConnection>> =>
        api.get(`/exchanges/${exchange}`),

    /**
     * Запускает ручную синхронизацию с биржей
     */
    sync: (exchange: string): Promise<ApiResponse<SyncResponse>> =>
        api.post(`/exchanges/${exchange}/sync`),

    /**
     * Запускает синхронизацию со всеми активными биржами
     */
    syncAll: async (): Promise<ApiResponse<SyncResponse[]>> => {
        try {
            // Сначала получаем список всех активных бирж
            const exchangesResponse = await api.get('/exchanges');
            if (!exchangesResponse.success || !exchangesResponse.data) {
                throw new Error('Failed to get exchanges list');
            }
            
            const activeExchanges = exchangesResponse.data.filter((exchange: ExchangeConnection) => 
                exchange.is_active && exchange.has_valid_credentials
            );
            
            // Синхронизируем все активные биржи параллельно
            const syncPromises = activeExchanges.map((exchange: ExchangeConnection) =>
                api.post(`/exchanges/${exchange.exchange}/sync`)
            );
            
            const results = await Promise.allSettled(syncPromises);
            const syncResults: SyncResponse[] = [];
            
            results.forEach((result, index) => {
                if (result.status === 'fulfilled' && result.value.success) {
                    syncResults.push(result.value.data);
                }
            });
            
            return {
                success: true,
                data: syncResults,
                message: `Synchronized ${syncResults.length} exchange(s)`
            };
        } catch (error) {
            return {
                success: false,
                message: error instanceof Error ? error.message : 'Sync failed',
                data: []
            };
        }
    },

    /**
     * Получает статус синхронизации
     */
    getSyncStatus: (exchange: string): Promise<ApiResponse<any>> =>
        api.get(`/exchanges/${exchange}/sync/status`),

    /**
     * Получает статистику синхронизации
     */
    getSyncStats: (exchange: string): Promise<ApiResponse<any>> =>
        api.get(`/exchanges/${exchange}/sync/stats`),

    /**
     * Тестирует подключение к бирже
     */
    testConnection: (params: {
        exchange: string;
        api_key: string;
        secret: string;
    }): Promise<ApiResponse<any>> =>
        api.post('/exchanges/test-connection', params),

    /**
     * Подключается к бирже
     */
    connect: (params: {
        exchange: string;
        api_key: string;
        secret: string;
        sync_settings?: any;
    }): Promise<ApiResponse<ExchangeConnection>> =>
        api.post('/exchanges/connect', params),

    /**
     * Отключается от биржи
     */
    disconnect: (exchange: string): Promise<ApiResponse<any>> =>
        api.delete(`/exchanges/${exchange}`),

    /**
     * Получает список поддерживаемых бирж
     */
    getSupportedExchanges: (): Promise<ApiResponse<any>> =>
        api.get('/exchanges/supported'),
};