import React, { useState, useEffect } from 'react';
import { dashboardService } from '@/services/dashboard';
import { exchangeService } from '@/services/exchange';
import type { DashboardOverview, DashboardMetrics, DashboardWidgets } from '@/types';

interface UseDashboardOptions {
    period?: '7d' | '30d' | '90d' | '1y' | 'all';
    autoRefresh?: boolean;
    refreshInterval?: number;
}

export function useDashboard(options: UseDashboardOptions = {}) {
    const { period = '30d', autoRefresh = true, refreshInterval = 60000 } = options;
    
    const [overview, setOverview] = useState<DashboardOverview | null>(null);
    const [metrics, setMetrics] = useState<DashboardMetrics | null>(null);
    const [widgets, setWidgets] = useState<DashboardWidgets | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    const fetchDashboardData = React.useCallback(async (silent = false) => {
        try {
            if (!silent) {
                setLoading(true);
            }
            setError(null);

            const metricsApiPeriod = period === 'all' ? '1y' : period;
            
            const [overviewResponse, metricsResponse, widgetsResponse] = await Promise.all([
                dashboardService.getOverview({ period }),
                dashboardService.getMetrics({ period: metricsApiPeriod }),
                dashboardService.getWidgets(),
            ]);

            if (overviewResponse.success && overviewResponse.data) {
                setOverview(overviewResponse.data);
            } else {
                throw new Error(overviewResponse.message || 'Failed to fetch overview');
            }

            if (metricsResponse.success && metricsResponse.data) {
                setMetrics(metricsResponse.data);
            } else {
                throw new Error(metricsResponse.message || 'Failed to fetch metrics');
            }

            if (widgetsResponse.success && widgetsResponse.data) {
                setWidgets(widgetsResponse.data);
            } else {
                throw new Error(widgetsResponse.message || 'Failed to fetch widgets');
            }
        } catch (err) {
            console.error('Dashboard fetch error:', err);
            if (!silent) {
                setError(err instanceof Error ? err.message : 'Unknown error occurred');
            }
        } finally {
            if (!silent) {
                setLoading(false);
            }
        }
    }, [period]);

    // Initial fetch
    useEffect(() => {
        fetchDashboardData();
    }, [fetchDashboardData]);

    // Auto sync with exchange and refresh data silently
    useEffect(() => {
        if (!autoRefresh) return;

        const interval = setInterval(async () => {
            try {
                console.log('Background sync with exchange started...');
                
                // 1. Синхронизируем данные с биржей
                const syncResult = await exchangeService.syncAll();
                
                if (syncResult.success) {
                    console.log(`Background sync: ${syncResult.message}`);
                    
                    // 2. Ждем немного для завершения синхронизации job'ов
                    await new Promise(resolve => setTimeout(resolve, 3000));
                    
                    // 3. Тихо обновляем данные Dashboard
                    await fetchDashboardData(true); // silent = true
                    
                    console.log('Background sync completed successfully');
                } else {
                    console.warn('Background sync partially failed:', syncResult.message);
                }
                
            } catch (error) {
                console.error('Background sync failed:', error);
                // Не показываем ошибки пользователю при фоновой синхронизации
            }
        }, refreshInterval);

        return () => clearInterval(interval);
    }, [autoRefresh, refreshInterval, fetchDashboardData]);

    const refresh = async () => {
        try {
            setLoading(true);
            
            console.log('Manual sync with exchange started...');
            
            // 1. Синхронизируем данные с биржей
            const syncResult = await exchangeService.syncAll();
            
            if (syncResult.success) {
                console.log(`Manual sync: ${syncResult.message}`);
                
                // 2. Ждем немного для завершения синхронизации job'ов
                await new Promise(resolve => setTimeout(resolve, 3000));
            } else {
                console.warn('Manual sync failed:', syncResult.message);
            }
            
            // 3. Обновляем данные Dashboard
            await fetchDashboardData();
            
            console.log('Manual sync completed successfully');
            
        } catch (error) {
            console.error('Manual sync failed:', error);
            setError(error instanceof Error ? error.message : 'Sync failed');
            setLoading(false);
        }
    };

    return {
        overview,
        metrics,
        widgets,
        loading,
        error,
        refresh,
    };
}