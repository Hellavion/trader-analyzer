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
