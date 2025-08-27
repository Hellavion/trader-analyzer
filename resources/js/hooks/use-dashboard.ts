import React, { useState, useEffect } from 'react';
import { dashboardService } from '@/services/dashboard';
import { syncService } from '@/services/sync-service';
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

    // Auto refresh data silently (без синхронизации с биржей - это делает scheduler)
    useEffect(() => {
        if (!autoRefresh) return;

        const interval = setInterval(async () => {
            try {
                console.log('Background dashboard refresh started...');
                    
                // Тихо обновляем данные Dashboard
                await fetchDashboardData(true); // silent = true
                
                console.log('Background dashboard refresh completed');
                
            } catch (error) {
                console.error('Background dashboard refresh failed:', error);
                // Не показываем ошибки пользователю при фоновом обновлении
            }
        }, refreshInterval);

        return () => clearInterval(interval);
    }, [autoRefresh, refreshInterval, fetchDashboardData]);

    const refresh = async () => {
        try {
            setLoading(true);
            
            console.log('Manual sync with exchange started...');
            
            // 1. Запускаем джобу синхронизации с биржей  
            const syncResult = await syncService.triggerManualSync();
            
            if (syncResult.success && syncResult.sync_timestamps) {
                console.log(`Manual sync: ${syncResult.message}`);
                
                // 2. Ждем завершения синхронизации динамически через polling
                console.log('Waiting for sync completion...');
                await syncService.waitForSyncCompletion(syncResult.sync_timestamps);
                
                // 3. Обновляем данные Dashboard один раз после завершения
                await fetchDashboardData();
                
                console.log('Manual sync completed successfully');
                
            } else {
                console.warn('Manual sync failed:', syncResult.message);
                // Даже если синхронизация не удалась, обновляем UI
                await fetchDashboardData();
            }
            
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