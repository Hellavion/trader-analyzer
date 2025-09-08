import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { PlaceholderPattern } from '@/components/ui/placeholder-pattern';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type Trade, type TradeAnalysis } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Calendar, Search, TrendingDown, TrendingUp, RefreshCw, AlertCircle, Loader2 } from 'lucide-react';
import { useState, useMemo } from 'react';
import { useTrades } from '@/hooks/use-trades';
import { Alert, AlertDescription } from '@/components/ui/alert';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Сделки',
        href: route('trades.index'),
    },
];

interface TradeWithAnalysis extends Trade {
    analysis?: TradeAnalysis;
}

interface Props {
    // Эти props теперь опциональны, так как данные загружаются через API
    trades?: TradeWithAnalysis[];
    filters?: {
        symbol?: string;
        exchange?: string;
        side?: string;
        date_from?: string;
        date_to?: string;
    };
}

export default function TradesIndex({ filters: initialFilters }: Props) {
    const [searchQuery, setSearchQuery] = useState(initialFilters?.symbol || '');
    const [selectedExchange, setSelectedExchange] = useState<string>(initialFilters?.exchange || 'all');
    const [selectedSide, setSelectedSide] = useState<string>(initialFilters?.side || 'all');
    
    // API фильтры на основе состояния UI
    const apiFilters = useMemo(() => ({
        symbol: searchQuery || undefined,
        exchange: selectedExchange !== 'all' ? selectedExchange : undefined,
        side: selectedSide !== 'all' ? (selectedSide as 'buy' | 'sell') : undefined,
        limit: 50,
        sort_by: 'entry_time',
        sort_order: 'desc' as const,
    }), [searchQuery, selectedExchange, selectedSide]);
    
    // Получаем данные через API
    const { trades, loading, error, refetch, syncTrades, isSyncing, pagination } = useTrades(apiFilters);
    
    // Фильтрация теперь происходит на сервере, поэтому используем trades напрямую
    const filteredTrades = trades || [];

    const getScoreColor = (score: number) => {
        if (score >= 8) return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
        if (score >= 6) return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200';
        return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';
    };

    const getPnL = (trade: Trade) => {
        return trade.pnl || 0;
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
                        <h1 className="text-2xl font-bold tracking-tight">История сделок</h1>
                        <p className="text-muted-foreground">
                            Анализируйте свою торговую эффективность с помощью Smart Money инсайтов
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Button
                            variant="outline"
                            onClick={() => refetch()}
                            disabled={loading}
                            className="flex items-center gap-2"
                        >
                            <RefreshCw className={`h-4 w-4 ${loading ? 'animate-spin' : ''}`} />
                            Обновить
                        </Button>
                        <Button
                            onClick={() => syncTrades()}
                            disabled={isSyncing}
                            className="flex items-center gap-2"
                        >
                            {isSyncing ? (
                                <Loader2 className="h-4 w-4 animate-spin" />
                            ) : (
                                <RefreshCw className="h-4 w-4" />
                            )}
                            {isSyncing ? 'Синхронизация...' : 'Синхронизировать сделки'}
                        </Button>
                    </div>
                </div>

                {/* Filters */}
                <Card>
                    <CardHeader className="pb-4">
                        <CardTitle className="text-lg">Фильтры</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="flex gap-4 flex-wrap">
                            <div className="flex-1 min-w-[200px]">
                                <div className="relative">
                                    <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        placeholder="Поиск по символу..."
                                        value={searchQuery}
                                        onChange={(e) => setSearchQuery(e.target.value)}
                                        className="pl-10"
                                    />
                                </div>
                            </div>
                            
                            <Select value={selectedExchange} onValueChange={setSelectedExchange}>
                                <SelectTrigger className="w-[150px]">
                                    <SelectValue placeholder="Биржа" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Все биржи</SelectItem>
                                    <SelectItem value="bybit">Bybit</SelectItem>
                                    <SelectItem value="mexc">MEXC</SelectItem>
                                </SelectContent>
                            </Select>
                            
                            <Select value={selectedSide} onValueChange={setSelectedSide}>
                                <SelectTrigger className="w-[130px]">
                                    <SelectValue placeholder="Сторона" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Все</SelectItem>
                                    <SelectItem value="buy">Покупка</SelectItem>
                                    <SelectItem value="sell">Продажа</SelectItem>
                                </SelectContent>
                            </Select>
                            
                            <Button variant="outline" className="flex items-center gap-2">
                                <Calendar className="h-4 w-4" />
                                Период дат
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                {/* Error Alert */}
                {error && (
                    <Alert variant="destructive">
                        <AlertCircle className="h-4 w-4" />
                        <AlertDescription>
                            {error}
                        </AlertDescription>
                    </Alert>
                )}

                {/* Loading State */}
                {loading ? (
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-16">
                            <Loader2 className="h-8 w-8 animate-spin text-muted-foreground mb-4" />
                            <p className="text-muted-foreground">Загрузка сделок...</p>
                        </CardContent>
                    </Card>
                ) : filteredTrades.length === 0 ? (
                    <Card className="relative overflow-hidden">
                        <CardContent className="flex flex-col items-center justify-center py-16">
                            <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/10 dark:stroke-neutral-100/10" />
                            <div className="relative flex flex-col items-center text-center">
                                <TrendingUp className="h-12 w-12 text-muted-foreground mb-4" />
                                <h3 className="text-lg font-semibold mb-2">Сделки не найдены</h3>
                                <p className="text-muted-foreground max-w-sm">
                                    Подключите свои биржи и синхронизируйте сделки, чтобы увидеть их здесь с анализом Smart Money.
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
                                <Card key={trade.id} className="hover:shadow-md transition-shadow cursor-pointer" asChild>
                                    <Link href={route('trades.show', trade.id)}>
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
                                                                {trade.side === 'buy' ? 'ПОКУПКА' : 'ПРОДАЖА'}
                                                            </Badge>
                                                            <Badge variant="outline" className="capitalize">
                                                                {trade.exchange}
                                                            </Badge>
                                                        </div>
                                                        <p className="text-sm text-muted-foreground">
                                                            {new Date(trade.entry_time).toLocaleString()}
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
                                                <p className="text-muted-foreground">Размер</p>
                                                <p className="font-medium">{trade.size}</p>
                                            </div>
                                            <div>
                                                <p className="text-muted-foreground">Цена входа</p>
                                                <p className="font-medium">{formatCurrency(trade.entry_price)}</p>
                                            </div>
                                            <div>
                                                <p className="text-muted-foreground">Цена выхода</p>
                                                <p className="font-medium">
                                                    {trade.exit_price ? formatCurrency(trade.exit_price) : 'Открыта'}
                                                </p>
                                            </div>
                                            <div>
                                                <p className="text-muted-foreground">Доходность %</p>
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
                                                        <p className="text-muted-foreground">Анализ входа</p>
                                                        <div className="flex gap-2 mt-1">
                                                            {(() => {
                                                                try {
                                                                    const entryContext = JSON.parse(trade.analysis.entry_context_json);
                                                                    return (
                                                                        <>
                                                                            {entryContext.order_block_reaction && (
                                                                                <Badge variant="outline" className="text-xs">Order Block</Badge>
                                                                            )}
                                                                            {entryContext.liquidity_sweep && (
                                                                                <Badge variant="outline" className="text-xs">Liquidity Sweep</Badge>
                                                                            )}
                                                                            {entryContext.fvg_entry && (
                                                                                <Badge variant="outline" className="text-xs">FVG Entry</Badge>
                                                                            )}
                                                                        </>
                                                                    );
                                                                } catch {
                                                                    return <Badge variant="outline" className="text-xs">Анализ доступен</Badge>;
                                                                }
                                                            })()}
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        )}
                                        </CardContent>
                                    </Link>
                                </Card>
                            );
                        })}
                    </div>
                )}

                {/* Pagination */}
                {pagination && pagination.last_page && pagination.last_page > 1 && (
                    <Card>
                        <CardContent className="flex items-center justify-between py-4">
                            <div className="text-sm text-muted-foreground">
                                Показано {pagination.from || 0} - {pagination.to || 0} из {pagination.total || 0} сделок
                            </div>
                            <div className="flex items-center gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => {
                                        // TODO: Implement pagination navigation
                                        console.log('Previous page');
                                    }}
                                    disabled={(pagination.current_page || 1) <= 1 || loading}
                                >
                                    Назад
                                </Button>
                                <div className="text-sm">
                                    Страница {pagination.current_page || 1} из {pagination.last_page || 1}
                                </div>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => {
                                        // TODO: Implement pagination navigation
                                        console.log('Next page');
                                    }}
                                    disabled={(pagination.current_page || 1) >= (pagination.last_page || 1) || loading}
                                >
                                    Вперёд
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}