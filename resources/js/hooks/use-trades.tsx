import { useState, useEffect, useCallback } from 'react';
import { api, type TradesResponse } from '@/lib/api';
import type { Trade, TradeAnalysis } from '@/types';

interface TradeWithAnalysis extends Trade {
    analysis?: TradeAnalysis;
}

interface TradeFilters {
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
}

interface UseTradesResult {
    trades: TradeWithAnalysis[];
    pagination: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number;
        to: number;
    } | null;
    loading: boolean;
    error: string | null;
    refetch: () => Promise<void>;
    syncTrades: (exchange?: string) => Promise<void>;
    isSyncing: boolean;
}

export function useTrades(filters: TradeFilters = {}): UseTradesResult {
    const [trades, setTrades] = useState<TradeWithAnalysis[]>([]);
    const [pagination, setPagination] = useState<UseTradesResult['pagination']>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [isSyncing, setIsSyncing] = useState(false);

    const fetchTrades = useCallback(async () => {
        try {
            setLoading(true);
            setError(null);

            const response = await api.getTrades(filters);

            if (response.success && response.data) {
                setTrades(response.data);
                setPagination(response.pagination);
            } else {
                setError(response.message || 'Failed to fetch trades');
                setTrades([]);
                setPagination(null);
            }
        } catch (err: any) {
            console.error('Error fetching trades:', err);
            setError(err.response?.data?.message || err.message || 'An error occurred while fetching trades');
            setTrades([]);
            setPagination(null);
        } finally {
            setLoading(false);
        }
    }, [JSON.stringify(filters)]);

    const syncTrades = useCallback(async (exchange?: string) => {
        try {
            setIsSyncing(true);
            setError(null);

            let response;
            if (exchange) {
                response = await api.syncExchangeTrades(exchange);
            } else {
                response = await api.syncAllTrades();
            }

            if (response.success) {
                // После успешной синхронизации перезагружаем данные через небольшую задержку
                setTimeout(() => {
                    fetchTrades();
                }, 2000);
            } else {
                setError(response.message || 'Failed to sync trades');
            }
        } catch (err: any) {
            console.error('Error syncing trades:', err);
            setError(err.response?.data?.message || err.message || 'An error occurred while syncing trades');
        } finally {
            setIsSyncing(false);
        }
    }, [fetchTrades]);

    // Загружаем данные при изменении фильтров
    useEffect(() => {
        fetchTrades();
    }, [fetchTrades]);

    return {
        trades,
        pagination,
        loading,
        error,
        refetch: fetchTrades,
        syncTrades,
        isSyncing,
    };
}

// Hook для получения статистики по сделкам
export function useTradeStats(filters?: {
    exchange?: string;
    period?: '7d' | '30d' | '90d' | '1y' | 'all';
    start_date?: string;
    end_date?: string;
}) {
    const [stats, setStats] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    const fetchStats = useCallback(async () => {
        try {
            setLoading(true);
            setError(null);

            const response = await api.getTradeStats(filters);

            if (response.success && response.data) {
                setStats(response.data);
            } else {
                setError(response.message || 'Failed to fetch trade statistics');
                setStats(null);
            }
        } catch (err: any) {
            console.error('Error fetching trade stats:', err);
            setError(err.response?.data?.message || err.message || 'An error occurred while fetching statistics');
            setStats(null);
        } finally {
            setLoading(false);
        }
    }, [JSON.stringify(filters)]);

    useEffect(() => {
        fetchStats();
    }, [fetchStats]);

    return {
        stats,
        loading,
        error,
        refetch: fetchStats,
    };
}

// Hook для получения конкретной сделки
export function useTrade(tradeId: number | null) {
    const [trade, setTrade] = useState<TradeWithAnalysis | null>(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const fetchTrade = useCallback(async () => {
        if (!tradeId) return;

        try {
            setLoading(true);
            setError(null);

            const response = await api.getTrade(tradeId);

            if (response.success && response.data) {
                setTrade(response.data);
            } else {
                setError(response.message || 'Failed to fetch trade details');
                setTrade(null);
            }
        } catch (err: any) {
            console.error('Error fetching trade:', err);
            setError(err.response?.data?.message || err.message || 'An error occurred while fetching trade details');
            setTrade(null);
        } finally {
            setLoading(false);
        }
    }, [tradeId]);

    useEffect(() => {
        fetchTrade();
    }, [fetchTrade]);

    return {
        trade,
        loading,
        error,
        refetch: fetchTrade,
    };
}