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
export interface DashboardOverview {
    connections: {
        total: number;
        active: number;
        needs_sync: number;
        exchanges: Array<{
            name: string;
            display_name: string;
            is_active: boolean;
            last_sync: string | null;
            needs_sync: boolean;
        }>;
    };
    trades: {
        total: number;
        open: number;
        closed: number;
        today: number;
        this_week: number;
    };
    performance: {
        total_pnl: number;
        total_fees: number;
        net_pnl: number;
        win_rate: number;
        winning_trades: number;
        losing_trades: number;
    };
    analysis: {
        analyzed_trades: number;
        coverage: number;
        average_score: number;
        high_quality_trades: number;
    };
    market_data: {
        symbols_tracked: number;
        last_update: string | null;
        data_points: number;
    };
    recent_activity: Array<{
        type: 'trade' | 'sync';
        description: string;
        time: string;
        status: string;
        pnl: number | null;
    }>;
}

export interface DashboardMetrics {
    total_trades: number;
    open_positions: number;
    closed_trades: number;
    total_pnl: number;
    total_fees: number;
    net_pnl: number;
    win_rate: number;
    profit_factor: number;
    sharpe_ratio: number;
    max_drawdown: number;
    average_win: number;
    average_loss: number;
    largest_win: number;
    largest_loss: number;
    trading_volume: number;
    period_comparison: {
        trades_change: number;
        pnl_change: number;
        win_rate_change: number;
    };
}

export interface DashboardWidgets {
    quick_stats: {
        today_trades: number;
        today_pnl: number;
        open_positions: number;
        active_exchanges: number;
    };
    recent_trades: Array<{
        id: number;
        symbol: string;
        side: 'buy' | 'sell';
        size: number;
        entry_price: number;
        pnl: number | null;
        status: string;
        entry_time: string;
    }>;
    top_symbols: Array<{
        symbol: string;
        trades: number;
        pnl: number;
        volume: number;
    }>;
    exchange_breakdown: Array<{
        exchange: string;
        trades: number;
        pnl: number;
    }>;
    smart_money_score: {
        average_score: number;
        trend: 'improving' | 'declining' | 'stable' | 'neutral';
        distribution: {
            excellent: number;
            good: number;
            average: number;
            poor: number;
        };
    };
    alerts: Array<{
        type: 'warning' | 'info' | 'error';
        message: string;
        action: string;
    }>;
}

// Exchange related types
export interface ExchangeInfo {
    name: string;
    display_name: string;
    is_supported: boolean;
    requires_api_key: boolean;
    requires_secret: boolean;
    requires_passphrase: boolean;
    supports_testnet: boolean;
}

export interface ExchangeConnection {
    id: number;
    exchange: string;
    display_name: string;
    is_active: boolean;
    is_testnet: boolean;
    last_sync_at: string | null;
    sync_settings: {
        auto_sync: boolean;
        sync_interval: number;
        sync_trades: boolean;
        sync_positions: boolean;
    };
    created_at: string;
    updated_at: string;
}

export interface TradeFilters {
    symbol?: string;
    exchange?: string;
    side?: 'buy' | 'sell';
    status?: 'open' | 'closed';
    date_from?: string;
    date_to?: string;
    min_size?: number;
    max_size?: number;
    min_pnl?: number;
    max_pnl?: number;
    page?: number;
    per_page?: number;
}

export interface TradeStats {
    total_trades: number;
    total_volume: number;
    total_pnl: number;
    total_fees: number;
    win_rate: number;
    profit_factor: number;
    average_win: number;
    average_loss: number;
    largest_win: number;
    largest_loss: number;
    by_symbol: Record<string, {
        trades: number;
        volume: number;
        pnl: number;
    }>;
    by_exchange: Record<string, {
        trades: number;
        volume: number;
        pnl: number;
    }>;
}

// Analysis related types
export interface OrderBlock {
    id: string;
    type: 'bullish' | 'bearish';
    high: number;
    low: number;
    timestamp: string;
    strength: number;
    tested: boolean;
    test_count: number;
}

export interface FairValueGap {
    id: string;
    type: 'bullish' | 'bearish';
    gap_high: number;
    gap_low: number;
    start_time: string;
    end_time: string;
    is_filled: boolean;
    fill_time: string | null;
}

export interface LiquidityLevel {
    id: string;
    level: number;
    type: 'support' | 'resistance' | 'equal_highs' | 'equal_lows';
    strength: number;
    touches: number;
    last_test: string;
    swept: boolean;
}

export interface MarketStructureData {
    symbol: string;
    timeframe: string;
    timestamp: string;
    order_blocks: OrderBlock[];
    liquidity_levels: LiquidityLevel[];
    fvg_zones: FairValueGap[];
}
