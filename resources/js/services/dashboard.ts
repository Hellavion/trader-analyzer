import { api, type ApiResponse } from './api';
import type { DashboardOverview, DashboardMetrics, DashboardWidgets } from '@/types';

export interface DashboardOverviewParams {
    period?: '7d' | '30d' | '90d' | '1y' | 'all';
}

export interface DashboardMetricsParams {
    period?: '1d' | '7d' | '30d' | '90d' | '1y';
    exchange?: 'bybit' | 'mexc';
}

export const dashboardService = {
    /**
     * Получает общую статистику для дашборда
     */
    getOverview: (params: DashboardOverviewParams = {}): Promise<ApiResponse<DashboardOverview>> =>
        api.get('/dashboard/overview', { params }),

    /**
     * Получает детальные метрики производительности
     */
    getMetrics: (params: DashboardMetricsParams = {}): Promise<ApiResponse<DashboardMetrics>> =>
        api.get('/dashboard/metrics', { params }),

    /**
     * Получает данные для виджетов дашборда
     */
    getWidgets: (): Promise<ApiResponse<DashboardWidgets>> =>
        api.get('/dashboard/widgets'),
};