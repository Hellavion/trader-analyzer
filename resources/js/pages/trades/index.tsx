import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { PlaceholderPattern } from '@/components/ui/placeholder-pattern';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { index as tradesIndex } from '@/routes/trades';
import { type BreadcrumbItem, type Trade, type TradeAnalysis } from '@/types';
import { Head } from '@inertiajs/react';
import { Calendar, Search, TrendingDown, TrendingUp } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Trades',
        href: tradesIndex().url,
    },
];

interface TradeWithAnalysis extends Trade {
    analysis?: TradeAnalysis;
}

interface Props {
    trades: TradeWithAnalysis[];
    filters?: {
        symbol?: string;
        exchange?: string;
        side?: string;
        date_from?: string;
        date_to?: string;
    };
}

const mockTrades: TradeWithAnalysis[] = [
    {
        id: 1,
        user_id: 1,
        exchange: 'bybit',
        symbol: 'BTCUSDT',
        side: 'buy',
        size: 0.1,
        entry_price: 43250.5,
        exit_price: 44120.25,
        timestamp: '2024-01-15T10:30:00Z',
        external_id: 'bybit_123456',
        created_at: '2024-01-15T10:30:00Z',
        updated_at: '2024-01-15T10:30:00Z',
        analysis: {
            id: 1,
            trade_id: 1,
            smart_money_score: 8.5,
            entry_context_json: '{"order_block_reaction": true, "liquidity_sweep": true}',
            exit_context_json: '{"profit_target": true}',
            patterns_json: '{"entry_type": "order_block", "setup_quality": "high"}',
            created_at: '2024-01-15T10:31:00Z',
            updated_at: '2024-01-15T10:31:00Z',
        },
    },
    {
        id: 2,
        user_id: 1,
        exchange: 'bybit',
        symbol: 'ETHUSDT',
        side: 'sell',
        size: 2.5,
        entry_price: 2650.75,
        exit_price: 2580.30,
        timestamp: '2024-01-14T14:20:00Z',
        external_id: 'bybit_789012',
        created_at: '2024-01-14T14:20:00Z',
        updated_at: '2024-01-14T14:20:00Z',
        analysis: {
            id: 2,
            trade_id: 2,
            smart_money_score: 6.2,
            entry_context_json: '{"order_block_reaction": false, "fvg_entry": true}',
            exit_context_json: '{"stop_loss": true}',
            patterns_json: '{"entry_type": "fvg", "setup_quality": "medium"}',
            created_at: '2024-01-14T14:21:00Z',
            updated_at: '2024-01-14T14:21:00Z',
        },
    },
];

export default function TradesIndex({ trades = [] }: Props) {
    const [searchQuery, setSearchQuery] = useState('');
    const [selectedExchange, setSelectedExchange] = useState<string>('all');
    const [selectedSide, setSelectedSide] = useState<string>('all');
    
    // Use mock data for demonstration
    const displayTrades = trades.length > 0 ? trades : mockTrades;
    
    const filteredTrades = displayTrades.filter((trade) => {
        if (searchQuery && !trade.symbol.toLowerCase().includes(searchQuery.toLowerCase())) {
            return false;
        }
        if (selectedExchange !== 'all' && trade.exchange !== selectedExchange) {
            return false;
        }
        if (selectedSide !== 'all' && trade.side !== selectedSide) {
            return false;
        }
        return true;
    });

    const getScoreColor = (score: number) => {
        if (score >= 8) return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
        if (score >= 6) return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200';
        return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';
    };

    const getPnL = (trade: Trade) => {
        if (!trade.exit_price) return 0;
        const multiplier = trade.side === 'buy' ? 1 : -1;
        return (trade.exit_price - trade.entry_price) * trade.size * multiplier;
    };

    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat('en-US', { 
            style: 'currency', 
            currency: 'USD',
            minimumFractionDigits: 2,
        }).format(amount);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Trades" />
            
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Trade History</h1>
                        <p className="text-muted-foreground">
                            Analyze your trading performance with Smart Money insights
                        </p>
                    </div>
                </div>

                {/* Filters */}
                <Card>
                    <CardHeader className="pb-4">
                        <CardTitle className="text-lg">Filters</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="flex gap-4 flex-wrap">
                            <div className="flex-1 min-w-[200px]">
                                <div className="relative">
                                    <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        placeholder="Search symbol..."
                                        value={searchQuery}
                                        onChange={(e) => setSearchQuery(e.target.value)}
                                        className="pl-10"
                                    />
                                </div>
                            </div>
                            
                            <Select value={selectedExchange} onValueChange={setSelectedExchange}>
                                <SelectTrigger className="w-[150px]">
                                    <SelectValue placeholder="Exchange" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Exchanges</SelectItem>
                                    <SelectItem value="bybit">Bybit</SelectItem>
                                    <SelectItem value="mexc">MEXC</SelectItem>
                                </SelectContent>
                            </Select>
                            
                            <Select value={selectedSide} onValueChange={setSelectedSide}>
                                <SelectTrigger className="w-[130px]">
                                    <SelectValue placeholder="Side" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Sides</SelectItem>
                                    <SelectItem value="buy">Buy</SelectItem>
                                    <SelectItem value="sell">Sell</SelectItem>
                                </SelectContent>
                            </Select>
                            
                            <Button variant="outline" className="flex items-center gap-2">
                                <Calendar className="h-4 w-4" />
                                Date Range
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                {filteredTrades.length === 0 ? (
                    <Card className="relative overflow-hidden">
                        <CardContent className="flex flex-col items-center justify-center py-16">
                            <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/10 dark:stroke-neutral-100/10" />
                            <div className="relative flex flex-col items-center text-center">
                                <TrendingUp className="h-12 w-12 text-muted-foreground mb-4" />
                                <h3 className="text-lg font-semibold mb-2">No trades found</h3>
                                <p className="text-muted-foreground max-w-sm">
                                    Connect your exchanges and sync your trades to see them here with Smart Money analysis.
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="space-y-4">
                        {filteredTrades.map((trade) => {
                            const pnl = getPnL(trade);
                            const isProfitable = pnl > 0;
                            
                            return (
                                <Card key={trade.id} className="hover:shadow-md transition-shadow">
                                    <CardContent className="p-6">
                                        <div className="flex items-center justify-between">
                                            <div className="flex items-center gap-4">
                                                <div className="flex items-center gap-2">
                                                    {trade.side === 'buy' ? (
                                                        <TrendingUp className="h-5 w-5 text-green-600" />
                                                    ) : (
                                                        <TrendingDown className="h-5 w-5 text-red-600" />
                                                    )}
                                                    <div>
                                                        <div className="flex items-center gap-2">
                                                            <h3 className="font-semibold text-lg">{trade.symbol}</h3>
                                                            <Badge variant="secondary" className="uppercase">
                                                                {trade.side}
                                                            </Badge>
                                                            <Badge variant="outline" className="capitalize">
                                                                {trade.exchange}
                                                            </Badge>
                                                        </div>
                                                        <p className="text-sm text-muted-foreground">
                                                            {new Date(trade.timestamp).toLocaleString()}
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div className="text-right">
                                                {trade.analysis && (
                                                    <div className="mb-2">
                                                        <Badge 
                                                            variant="secondary" 
                                                            className={getScoreColor(trade.analysis.smart_money_score)}
                                                        >
                                                            SM Score: {trade.analysis.smart_money_score.toFixed(1)}
                                                        </Badge>
                                                    </div>
                                                )}
                                                {trade.exit_price && (
                                                    <div className={`text-lg font-semibold ${
                                                        isProfitable ? 'text-green-600' : 'text-red-600'
                                                    }`}>
                                                        {isProfitable ? '+' : ''}{formatCurrency(pnl)}
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                        
                                        <div className="mt-4 grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                                            <div>
                                                <p className="text-muted-foreground">Size</p>
                                                <p className="font-medium">{trade.size}</p>
                                            </div>
                                            <div>
                                                <p className="text-muted-foreground">Entry Price</p>
                                                <p className="font-medium">{formatCurrency(trade.entry_price)}</p>
                                            </div>
                                            <div>
                                                <p className="text-muted-foreground">Exit Price</p>
                                                <p className="font-medium">
                                                    {trade.exit_price ? formatCurrency(trade.exit_price) : 'Open'}
                                                </p>
                                            </div>
                                            <div>
                                                <p className="text-muted-foreground">Return %</p>
                                                <p className={`font-medium ${
                                                    trade.exit_price 
                                                        ? (isProfitable ? 'text-green-600' : 'text-red-600') 
                                                        : ''
                                                }`}>
                                                    {trade.exit_price 
                                                        ? `${((pnl / (trade.entry_price * trade.size)) * 100).toFixed(2)}%`
                                                        : '-'
                                                    }
                                                </p>
                                            </div>
                                        </div>
                                        
                                        {trade.analysis && (
                                            <div className="mt-4 pt-4 border-t">
                                                <div className="flex items-center gap-4 text-sm">
                                                    <div>
                                                        <p className="text-muted-foreground">Entry Analysis</p>
                                                        <div className="flex gap-2 mt-1">
                                                            {JSON.parse(trade.analysis.entry_context_json).order_block_reaction && (
                                                                <Badge variant="outline" className="text-xs">Order Block</Badge>
                                                            )}
                                                            {JSON.parse(trade.analysis.entry_context_json).liquidity_sweep && (
                                                                <Badge variant="outline" className="text-xs">Liquidity Sweep</Badge>
                                                            )}
                                                            {JSON.parse(trade.analysis.entry_context_json).fvg_entry && (
                                                                <Badge variant="outline" className="text-xs">FVG Entry</Badge>
                                                            )}
                                                        </div>
                                                    </div>
                                                    <div className="ml-auto">
                                                        <Button variant="ghost" size="sm">
                                                            View Details
                                                        </Button>
                                                    </div>
                                                </div>
                                            </div>
                                        )}
                                    </CardContent>
                                </Card>
                            );
                        })}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}