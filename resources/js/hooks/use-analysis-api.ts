import { useState, useEffect, useCallback } from 'react';
import { ApiResponse, TradeAnalysisReport } from '@/types';

interface UseAnalysisApiOptions {
    period?: string;
    exchange?: string;
    symbol?: string;
    autoFetch?: boolean;
}

export function useAnalysisApi(options: UseAnalysisApiOptions = {}) {
    const [data, setData] = useState<TradeAnalysisReport | null>(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const fetchAnalysisReport = useCallback(async (customOptions?: UseAnalysisApiOptions) => {
        setLoading(true);
        setError(null);

        const queryParams = new URLSearchParams();
        const params = { ...options, ...customOptions };
        
        if (params.period) queryParams.append('period', params.period);
        if (params.exchange) queryParams.append('exchange', params.exchange);
        if (params.symbol) queryParams.append('symbol', params.symbol);

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            
            const response = await fetch(`/api/analysis/report?${queryParams.toString()}`, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const result: ApiResponse<TradeAnalysisReport> = await response.json();

            if (result.success && result.data) {
                setData(result.data);
            } else {
                setError(result.message || 'Ошибка загрузки данных');
            }
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Ошибка сети');
        } finally {
            setLoading(false);
        }
    }, [options]);

    const fetchOrderBlocks = async (symbol: string, timeframe?: string, activeOnly?: boolean) => {
        const params = new URLSearchParams({ symbol });
        if (timeframe) params.append('timeframe', timeframe);
        if (activeOnly) params.append('active_only', 'true');

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const response = await fetch(`/api/analysis/order-blocks?${params.toString()}`, {
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });
        return response.json();
    };

    const fetchFairValueGaps = async (symbol: string, timeframe?: string, unfilledOnly?: boolean) => {
        const params = new URLSearchParams({ symbol });
        if (timeframe) params.append('timeframe', timeframe);
        if (unfilledOnly) params.append('unfilled_only', 'true');

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const response = await fetch(`/api/analysis/fvg?${params.toString()}`, {
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });
        return response.json();
    };

    const fetchLiquidityLevels = async (symbol: string, timeframe?: string, minStrength?: number) => {
        const params = new URLSearchParams({ symbol });
        if (timeframe) params.append('timeframe', timeframe);
        if (minStrength) params.append('min_strength', minStrength.toString());

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const response = await fetch(`/api/analysis/liquidity?${params.toString()}`, {
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });
        return response.json();
    };

    const fetchAvailableSymbols = async () => {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const response = await fetch('/api/analysis/symbols', {
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });
        return response.json();
    };

    const collectMarketData = async (symbols?: string[], timeframes?: string[]) => {
        const body: { symbols?: string[]; timeframes?: string[] } = {};
        if (symbols) body.symbols = symbols;
        if (timeframes) body.timeframes = timeframes;

        const response = await fetch('/api/analysis/collect-market-data', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            },
            body: JSON.stringify(body)
        });
        return response.json();
    };

    useEffect(() => {
        if (options.autoFetch !== false) {
            fetchAnalysisReport();
        }
    // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [options.period, options.exchange, options.symbol, options.autoFetch]);

    return {
        data,
        loading,
        error,
        refresh: fetchAnalysisReport,
        fetchOrderBlocks,
        fetchFairValueGaps,
        fetchLiquidityLevels,
        fetchAvailableSymbols,
        collectMarketData,
    };
}

export default useAnalysisApi;