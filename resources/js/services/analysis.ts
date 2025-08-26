import { api, type ApiResponse } from './api';
import type { MarketStructureData, OrderBlock, FairValueGap, LiquidityLevel } from '@/types';

export interface AnalysisFilters {
    symbol?: string;
    timeframe?: '1m' | '5m' | '15m' | '1h' | '4h' | '1d';
    from?: string;
    to?: string;
    limit?: number;
}

export interface MarketDataCollectionRequest {
    symbols: string[];
    timeframes: string[];
    exchanges?: string[];
    start_date?: string;
    end_date?: string;
}

export interface TradeAnalysisReport {
    trade_id: number;
    symbol: string;
    analysis: {
        smart_money_score: number;
        entry_quality: {
            score: number;
            factors: {
                order_block_reaction: boolean;
                liquidity_sweep: boolean;
                fvg_entry: boolean;
                market_structure_alignment: boolean;
            };
        };
        exit_quality: {
            score: number;
            factors: {
                profit_target_hit: boolean;
                stop_loss_hit: boolean;
                structure_break: boolean;
                momentum_divergence: boolean;
            };
        };
        context: {
            market_session: 'asian' | 'london' | 'ny' | 'overlap';
            volatility_level: 'low' | 'medium' | 'high';
            trend_alignment: 'with' | 'against' | 'neutral';
        };
        recommendations: string[];
    };
}

export const analysisService = {
    /**
     * Получает список доступных символов для анализа
     */
    getAvailableSymbols: (): Promise<ApiResponse<string[]>> =>
        api.get('/analysis/symbols'),

    /**
     * Получает данные рыночной структуры
     */
    getMarketStructure: (filters: AnalysisFilters): Promise<ApiResponse<MarketStructureData[]>> =>
        api.get('/analysis/market-structure', { params: filters }),

    /**
     * Получает Order Blocks для символа
     */
    getOrderBlocks: (filters: AnalysisFilters): Promise<ApiResponse<OrderBlock[]>> =>
        api.get('/analysis/order-blocks', { params: filters }),

    /**
     * Получает Fair Value Gaps для символа
     */
    getFairValueGaps: (filters: AnalysisFilters): Promise<ApiResponse<FairValueGap[]>> =>
        api.get('/analysis/fvg', { params: filters }),

    /**
     * Получает уровни ликвидности для символа
     */
    getLiquidityLevels: (filters: AnalysisFilters): Promise<ApiResponse<LiquidityLevel[]>> =>
        api.get('/analysis/liquidity', { params: filters }),

    /**
     * Получает отчет по анализу сделки
     */
    getTradeAnalysisReport: (tradeId: number): Promise<ApiResponse<TradeAnalysisReport>> =>
        api.get(`/analysis/report?trade_id=${tradeId}`),

    /**
     * Запускает сбор рыночных данных
     */
    collectMarketData: (data: MarketDataCollectionRequest): Promise<ApiResponse<{ 
        message: string; 
        job_id: string; 
        symbols: string[];
        timeframes: string[];
    }>> =>
        api.post('/analysis/collect-market-data', data),
};