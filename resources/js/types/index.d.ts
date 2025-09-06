import { InertiaLinkProps } from '@inertiajs/react';
import { LucideIcon } from 'lucide-react';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface NavItem {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon | null;
    isActive?: boolean;
}

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    sidebarOpen: boolean;
    [key: string]: unknown;
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown; // This allows for additional properties...
}

export interface UserExchange {
    id: number;
    user_id: number;
    exchange: 'bybit' | 'mexc';
    api_key: string;
    is_active: boolean;
    created_at: string;
    updated_at: string;
}

export interface Trade {
    id: number;
    user_id: number;
    exchange: string;
    symbol: string;
    side: 'buy' | 'sell';
    size: number;
    entry_price: number;
    exit_price?: number;
    timestamp: string;
    external_id: string;
    created_at: string;
    updated_at: string;
}

export interface TradeAnalysis {
    id: number;
    trade_id: number;
    smart_money_score: number;
    entry_context_json: string;
    exit_context_json?: string;
    patterns_json: string;
    created_at: string;
    updated_at: string;
}

export interface MarketStructure {
    id: number;
    symbol: string;
    timeframe: string;
    timestamp: string;
    order_blocks_json: string;
    liquidity_levels_json: string;
    fvg_zones_json: string;
    created_at: string;
    updated_at: string;
}

// API Response Types
export interface ApiResponse<T = unknown> {
    success: boolean;
    data?: T;
    message?: string;
    meta?: Record<string, unknown>;
    errors?: Record<string, string[]>;
}

export interface TradeAnalysisReport {
    overview: {
        total_trades: number;
        analyzed_trades: number;
        analysis_coverage: number;
    };
    smart_money_analysis: {
        average_score: number;
        max_score: number;
        min_score: number;
        score_distribution: {
            excellent: number;
            good: number;
            average: number;
            poor: number;
        };
        score_vs_pnl_correlation: number;
    };
    pattern_analysis: {
        most_common_patterns: Record<string, number>;
        total_patterns_detected: number;
        unique_patterns: number;
    };
    quality_analysis: {
        entry_analysis: {
            average_quality: number;
            distribution: QualityDistribution;
        };
        exit_analysis: {
            average_quality: number;
            distribution: QualityDistribution;
        };
    };
    recommendations: Recommendation[];
    market_structure_insights: {
        bias_distribution: Record<string, number>;
        structure_alignment: {
            total_analyzed: number;
            aligned_trades: number;
        };
    };
    performance_correlation: {
        score_vs_pnl: number;
        entry_quality_vs_pnl: number;
    };
    pnl_timeline?: Array<{
        period: string;
        pnl: number;
        trades: number;
        date: string;
    }>;
}

export interface QualityDistribution {
    excellent: number;
    good: number;
    average: number;
    poor: number;
}

export interface Recommendation {
    type: string;
    title: string;
    description: string;
    priority: 'high' | 'medium' | 'low';
}

export interface OrderBlock {
    id: string;
    type: 'bullish' | 'bearish';
    high: number;
    low: number;
    timestamp: string;
    is_active: boolean;
    strength: number;
}

export interface FairValueGap {
    id: string;
    type: 'bullish' | 'bearish';
    gap_high: number;
    gap_low: number;
    start_index: number;
    end_index: number;
    is_filled: boolean;
    fill_index?: number;
}

export interface LiquidityLevel {
    id: string;
    type: 'support' | 'resistance';
    level: number;
    strength: number;
    touches: number[];
    is_swept: boolean;
}
