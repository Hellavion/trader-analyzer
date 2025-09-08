import axios, { AxiosInstance, AxiosResponse } from 'axios';
import { ApiResponse, Trade, TradeAnalysis } from '@/types';

/**
 * HTTP клиент для API запросов
 */
class ApiClient {
    private client: AxiosInstance;

    constructor() {
        // Получаем базовый API URL из метатега
        const apiBaseUrl = document.head.querySelector('meta[name="api-base-url"]')?.getAttribute('content') || '/api';
        
        this.client = axios.create({
            baseURL: apiBaseUrl,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            withCredentials: true, // для CSRF и session auth
        });

        // Добавляем CSRF токен в заголовки
        const csrfToken = document.head.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        if (csrfToken) {
            this.client.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken;
        }

        // Interceptors для обработки ошибок
        this.client.interceptors.response.use(
            (response) => response,
            (error) => {
                if (error.response?.status === 401) {
                    // Перенаправляем на страницу логина при ошибке авторизации
                    window.location.href = '/login';
                }
                return Promise.reject(error);
            }
        );
    }

    /**
     * Получить список сделок с фильтрацией
     */
    async getTrades(filters?: {
        exchange?: string;
        symbol?: string;
        side?: 'buy' | 'sell';
        status?: 'open' | 'closed';
        start_date?: string;
        end_date?: string;
        limit?: number;
        page?: number;
        sort_by?: string;
        sort_order?: 'asc' | 'desc';
    }): Promise<ApiResponse<{
        data: Array<Trade & { analysis?: TradeAnalysis }>;
        pagination: {
            current_page: number;
            last_page: number;
            per_page: number;
            total: number;
            from: number;
            to: number;
        };
    }>> {
        const response = await this.client.get('/trades', { params: filters });
        return response.data;
    }

    /**
     * Получить детальную информацию о сделке
     */
    async getTrade(tradeId: number): Promise<ApiResponse<Trade & { 
        analysis?: TradeAnalysis;
        is_closed: boolean;
        is_open: boolean;
    }>> {
        const response = await this.client.get(`/trades/${tradeId}`);
        return response.data;
    }

    /**
     * Получить статистику по сделкам
     */
    async getTradeStats(filters?: {
        exchange?: string;
        period?: '7d' | '30d' | '90d' | '1y' | 'all';
        start_date?: string;
        end_date?: string;
    }): Promise<ApiResponse<{
        total_trades: number;
        open_trades: number;
        closed_trades: number;
        winning_trades: number;
        losing_trades: number;
        breakeven_trades: number;
        total_pnl: number;
        total_fees: number;
        win_rate: number;
        average_trade_size: number;
        largest_win: number;
        largest_loss: number;
        exchanges: Record<string, number>;
        symbols: Record<string, number>;
        analysis_stats: {
            analyzed_trades: number;
            average_smart_money_score: number;
            quality_distribution: {
                excellent: number;
                good: number;
                average: number;
                poor: number;
            };
        };
    }>> {
        const response = await this.client.get('/trades/stats', { params: filters });
        return response.data;
    }

    /**
     * Получить данные для графика P&L
     */
    async getPnlChart(filters?: {
        exchange?: string;
        period?: '7d' | '30d' | '90d' | '1y';
        interval?: 'day' | 'week' | 'month';
    }): Promise<ApiResponse<Array<{
        date: string;
        pnl: number;
        cumulative_pnl: number;
        trades_count: number;
        winning_trades: number;
        losing_trades: number;
    }>>> {
        const response = await this.client.get('/trades/pnl-chart', { params: filters });
        return response.data;
    }

    /**
     * Запустить синхронизацию всех бирж
     */
    async syncAllTrades(options?: {
        start_date?: string;
        end_date?: string;
        force?: boolean;
    }): Promise<ApiResponse<{
        exchanges_synced: number;
        exchanges_skipped: number;
        start_time: string;
        end_time: string;
    }>> {
        const response = await this.client.post('/trades/sync', options);
        return response.data;
    }

    /**
     * Запустить синхронизацию конкретной биржи
     */
    async syncExchangeTrades(exchange: string, options?: {
        start_date?: string;
        end_date?: string;
        force?: boolean;
    }): Promise<ApiResponse<{
        exchange: string;
        start_time: string;
        end_time: string;
    }>> {
        const response = await this.client.post(`/trades/sync/${exchange}`, options);
        return response.data;
    }

    /**
     * Получить список доступных бирж пользователя
     */
    async getExchanges(): Promise<ApiResponse<Array<{
        id: number;
        exchange: string;
        api_key: string;
        is_active: boolean;
        created_at: string;
        display_name: string;
    }>>> {
        const response = await this.client.get('/exchanges');
        return response.data;
    }
}

// Создаём singleton экземпляр
export const api = new ApiClient();

// Экспортируем типы для удобства
export type TradesResponse = Awaited<ReturnType<typeof api.getTrades>>;
export type TradeResponse = Awaited<ReturnType<typeof api.getTrade>>;
export type TradeStatsResponse = Awaited<ReturnType<typeof api.getTradeStats>>;
export type PnlChartResponse = Awaited<ReturnType<typeof api.getPnlChart>>;