import { api, type ApiResponse, type PaginatedResponse } from './api';
import type { Trade, TradeAnalysis, TradeFilters, TradeStats } from '@/types';

export interface TradeWithAnalysis extends Trade {
    analysis?: TradeAnalysis;
}

export interface PnlChartParams {
    period?: '1d' | '7d' | '30d' | '90d' | '1y';
    exchange?: string;
    symbol?: string;
    granularity?: 'hourly' | 'daily' | 'weekly';
}

export interface PnlChartData {
    timestamp: string;
    cumulative_pnl: number;
    period_pnl: number;
    trade_count: number;
}

export const tradesService = {
    /**
     * Получает список сделок с фильтрацией и пагинацией
     */
    getTrades: (filters: TradeFilters = {}): Promise<PaginatedResponse<TradeWithAnalysis>> =>
        api.get('/trades', { params: filters }),

    /**
     * Получает детали конкретной сделки
     */
    getTrade: (tradeId: number): Promise<ApiResponse<TradeWithAnalysis>> =>
        api.get(`/trades/${tradeId}`),

    /**
     * Получает статистику по сделкам
     */
    getStats: (filters: Omit<TradeFilters, 'page' | 'per_page'> = {}): Promise<ApiResponse<TradeStats>> =>
        api.get('/trades/stats', { params: filters }),

    /**
     * Получает данные для графика P&L
     */
    getPnlChart: (params: PnlChartParams = {}): Promise<ApiResponse<PnlChartData[]>> =>
        api.get('/trades/pnl-chart', { params }),

    /**
     * Запускает синхронизацию всех бирж
     */
    syncAll: (): Promise<ApiResponse<{ message: string; jobs_queued: number }>> =>
        api.post('/trades/sync'),

    /**
     * Запускает синхронизацию конкретной биржи
     */
    syncExchange: (exchange: string): Promise<ApiResponse<{ message: string; job_id: string }>> =>
        api.post(`/trades/sync/${exchange}`),
};