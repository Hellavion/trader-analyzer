import { api, type ApiResponse } from './api';
import type { ExchangeInfo, ExchangeConnection } from '@/types';

export interface ExchangeConnectionRequest {
    exchange: string;
    api_key: string;
    api_secret: string;
    api_passphrase?: string;
    is_testnet?: boolean;
    sync_settings?: {
        auto_sync: boolean;
        sync_interval: number;
        sync_trades: boolean;
        sync_positions: boolean;
    };
}

export interface TestConnectionRequest {
    exchange: string;
    api_key: string;
    api_secret: string;
    api_passphrase?: string;
    is_testnet?: boolean;
}

export interface TestConnectionResponse {
    success: boolean;
    message: string;
    account_info?: {
        account_id?: string;
        account_type?: string;
        balance?: number;
        available_balance?: number;
    };
}

export interface SyncStatus {
    is_running: boolean;
    job_id?: string;
    started_at?: string;
    progress?: {
        current: number;
        total: number;
        percentage: number;
    };
    last_sync?: {
        completed_at: string;
        trades_synced: number;
        errors_count: number;
    };
}

export interface SyncStats {
    total_syncs: number;
    successful_syncs: number;
    failed_syncs: number;
    last_24h_syncs: number;
    average_duration: number;
    total_trades_synced: number;
    last_error?: {
        message: string;
        occurred_at: string;
    };
}

export const exchangesService = {
    /**
     * Получает список всех подключений пользователя к биржам
     */
    getConnections: (): Promise<ApiResponse<ExchangeConnection[]>> =>
        api.get('/exchanges'),

    /**
     * Получает список поддерживаемых бирж
     */
    getSupportedExchanges: (): Promise<ApiResponse<ExchangeInfo[]>> =>
        api.get('/exchanges/supported'),

    /**
     * Получает информацию о конкретном подключении
     */
    getConnection: (exchange: string): Promise<ApiResponse<ExchangeConnection>> =>
        api.get(`/exchanges/${exchange}`),

    /**
     * Тестирует подключение к бирже
     */
    testConnection: (data: TestConnectionRequest): Promise<ApiResponse<TestConnectionResponse>> =>
        api.post('/exchanges/test-connection', data),

    /**
     * Подключается к бирже
     */
    connect: (data: ExchangeConnectionRequest): Promise<ApiResponse<ExchangeConnection>> =>
        api.post('/exchanges/connect', data),

    /**
     * Отключает биржу (деактивирует)
     */
    disconnect: (exchange: string): Promise<ApiResponse<{ message: string }>> =>
        api.delete(`/exchanges/${exchange}`),

    /**
     * Полностью удаляет подключение к бирже
     */
    deleteConnection: (exchange: string): Promise<ApiResponse<{ message: string }>> =>
        api.delete(`/exchanges/${exchange}/delete`),

    /**
     * Обновляет настройки синхронизации
     */
    updateSyncSettings: (
        exchange: string, 
        settings: ExchangeConnection['sync_settings']
    ): Promise<ApiResponse<ExchangeConnection>> =>
        api.put(`/exchanges/${exchange}/sync-settings`, { sync_settings: settings }),

    /**
     * Запускает ручную синхронизацию
     */
    sync: (exchange: string): Promise<ApiResponse<{ message: string; job_id: string }>> =>
        api.post(`/exchanges/${exchange}/sync`),

    /**
     * Получает статус текущей синхронизации
     */
    getSyncStatus: (exchange: string): Promise<ApiResponse<SyncStatus>> =>
        api.get(`/exchanges/${exchange}/sync/status`),

    /**
     * Получает статистику синхронизации
     */
    getSyncStats: (exchange: string): Promise<ApiResponse<SyncStats>> =>
        api.get(`/exchanges/${exchange}/sync/stats`),
};