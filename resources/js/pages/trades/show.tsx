import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type Trade, type TradeAnalysis } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, TrendingDown, TrendingUp, Clock, DollarSign, BarChart3, Target, AlertCircle, Loader2 } from 'lucide-react';
import { useState, useEffect } from 'react';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { api } from '@/lib/api';
import TradingChart from '@/components/charts/TradingChart';

interface TradeWithAnalysis extends Trade {
    analysis?: TradeAnalysis;
}

interface Props {
    tradeId: number;
}

export default function TradeShow({ tradeId }: Props) {
    const [trade, setTrade] = useState<TradeWithAnalysis | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Сделки',
            href: route('trades.index'),
        },
        {
            title: trade ? `${trade.symbol} #${trade.id}` : 'Загрузка...',
            href: route('trades.show', tradeId),
        },
    ];

    useEffect(() => {
        fetchTradeDetails();
    }, [tradeId]);

    const fetchTradeDetails = async () => {
        try {
            setLoading(true);
            setError(null);

            const response = await api.getTrade(tradeId);

            if (response.success) {
                setTrade(response.data);
            } else {
                throw new Error(response.message || 'Не удалось загрузить данные сделки');
            }
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Произошла ошибка');
        } finally {
            setLoading(false);
        }
    };

    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat('en-US', { 
            style: 'currency', 
            currency: 'USD',
            minimumFractionDigits: 2,
        }).format(amount);
    };

    const formatDateTime = (dateString: string) => {
        return new Date(dateString).toLocaleString('ru-RU', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
        });
    };

    const getScoreColor = (score: number) => {
        if (score >= 8) return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
        if (score >= 6) return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200';
        return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';
    };

    const getPnL = (trade: Trade) => {
        return trade.pnl || 0;
    };

    if (loading) {
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="Загрузка сделки..." />
                <div className="flex h-full flex-1 items-center justify-center p-6">
                    <div className="flex flex-col items-center gap-4">
                        <Loader2 className="h-8 w-8 animate-spin text-muted-foreground" />
                        <p className="text-muted-foreground">Загрузка деталей сделки...</p>
                    </div>
                </div>
            </AppLayout>
        );
    }

    if (error) {
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="Ошибка" />
                <div className="flex h-full flex-1 flex-col gap-6 p-6">
                    <Alert variant="destructive">
                        <AlertCircle className="h-4 w-4" />
                        <AlertDescription>
                            {error}
                        </AlertDescription>
                    </Alert>
                    <div className="flex gap-4">
                        <Button onClick={fetchTradeDetails}>
                            Попробовать снова
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href={route('trades.index')}>
                                <ArrowLeft className="mr-2 h-4 w-4" />
                                Вернуться к сделкам
                            </Link>
                        </Button>
                    </div>
                </div>
            </AppLayout>
        );
    }

    if (!trade) {
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="Сделка не найдена" />
                <div className="flex h-full flex-1 flex-col gap-6 p-6">
                    <Alert variant="destructive">
                        <AlertCircle className="h-4 w-4" />
                        <AlertDescription>
                            Сделка не найдена
                        </AlertDescription>
                    </Alert>
                    <Button variant="outline" asChild>
                        <Link href={route('trades.index')}>
                            <ArrowLeft className="mr-2 h-4 w-4" />
                            Вернуться к сделкам
                        </Link>
                    </Button>
                </div>
            </AppLayout>
        );
    }

    const pnl = getPnL(trade);
    const isProfitable = pnl > 0;
    const isOpen = trade.status === 'open';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${trade.symbol} #${trade.id}`} />
            
            <div className="flex h-full flex-1 flex-col gap-6 p-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Button variant="outline" asChild>
                            <Link href={route('trades.index')}>
                                <ArrowLeft className="mr-2 h-4 w-4" />
                                Назад
                            </Link>
                        </Button>
                        <div>
                            <div className="flex items-center gap-3">
                                {trade.side === 'buy' ? (
                                    <TrendingUp className="h-6 w-6 text-green-600" />
                                ) : (
                                    <TrendingDown className="h-6 w-6 text-red-600" />
                                )}
                                <h1 className="text-2xl font-bold">{trade.symbol}</h1>
                                <Badge variant="secondary" className="uppercase">
                                    {trade.side === 'buy' ? 'ПОКУПКА' : 'ПРОДАЖА'}
                                </Badge>
                                <Badge variant="outline" className="capitalize">
                                    {trade.exchange}
                                </Badge>
                                <Badge variant={isOpen ? "default" : "secondary"}>
                                    {isOpen ? 'ОТКРЫТА' : 'ЗАКРЫТА'}
                                </Badge>
                            </div>
                            <p className="text-muted-foreground mt-1">
                                ID: {trade.external_id}
                            </p>
                        </div>
                    </div>
                    
                    {!isOpen && (
                        <div className={`text-right ${isProfitable ? 'text-green-600' : 'text-red-600'}`}>
                            <div className="text-3xl font-bold">
                                {isProfitable ? '+' : ''}{formatCurrency(pnl)}
                            </div>
                            <div className="text-sm">
                                {((pnl / (trade.entry_price * trade.size)) * 100).toFixed(2)}% доходность
                            </div>
                        </div>
                    )}
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Main Trade Info */}
                    <div className="lg:col-span-2 space-y-6">
                        {/* Price Chart */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <BarChart3 className="h-5 w-5" />
                                    График цены
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <TradingChart
                                    tradeId={trade.id}
                                    symbol={trade.symbol}
                                    entryPrice={trade.entry_price}
                                    exitPrice={trade.exit_price}
                                    entryTime={trade.entry_time}
                                    exitTime={trade.exit_time}
                                    side={trade.side}
                                />
                            </CardContent>
                        </Card>

                        {/* Trade Details */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <BarChart3 className="h-5 w-5" />
                                    Детали сделки
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="grid grid-cols-2 md:grid-cols-4 gap-6">
                                    <div>
                                        <div className="text-sm text-muted-foreground mb-1">Размер позиции</div>
                                        <div className="text-lg font-semibold">{trade.size}</div>
                                    </div>
                                    
                                    <div>
                                        <div className="text-sm text-muted-foreground mb-1">Цена входа</div>
                                        <div className="text-lg font-semibold">{formatCurrency(trade.entry_price)}</div>
                                    </div>
                                    
                                    <div>
                                        <div className="text-sm text-muted-foreground mb-1">Цена выхода</div>
                                        <div className="text-lg font-semibold">
                                            {trade.exit_price ? formatCurrency(trade.exit_price) : 'Открыта'}
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <div className="text-sm text-muted-foreground mb-1">Комиссии</div>
                                        <div className="text-lg font-semibold">{formatCurrency(trade.fee)}</div>
                                    </div>
                                </div>
                                
                                {trade.funding_fees && trade.funding_fees > 0 && (
                                    <div className="mt-4 pt-4 border-t">
                                        <div className="text-sm text-muted-foreground mb-1">Фандинговые комиссии</div>
                                        <div className="text-lg font-semibold">{formatCurrency(trade.funding_fees)}</div>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {/* Timing */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Clock className="h-5 w-5" />
                                    Временные рамки
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <div className="text-sm text-muted-foreground mb-1">Время входа</div>
                                        <div className="text-lg font-semibold">{formatDateTime(trade.entry_time)}</div>
                                    </div>
                                    
                                    {trade.exit_time && (
                                        <div>
                                            <div className="text-sm text-muted-foreground mb-1">Время выхода</div>
                                            <div className="text-lg font-semibold">{formatDateTime(trade.exit_time)}</div>
                                        </div>
                                    )}
                                </div>
                                
                                {trade.exit_time && (
                                    <div className="mt-4 pt-4 border-t">
                                        <div className="text-sm text-muted-foreground mb-1">Длительность позиции</div>
                                        <div className="text-lg font-semibold">
                                            {(() => {
                                                const duration = new Date(trade.exit_time).getTime() - new Date(trade.entry_time).getTime();
                                                const hours = Math.floor(duration / (1000 * 60 * 60));
                                                const minutes = Math.floor((duration % (1000 * 60 * 60)) / (1000 * 60));
                                                return `${hours}ч ${minutes}м`;
                                            })()}
                                        </div>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {/* Smart Money Analysis */}
                        {trade.analysis && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Target className="h-5 w-5" />
                                        Smart Money Анализ
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="flex items-center justify-between">
                                        <span className="text-sm font-medium">Smart Money Score</span>
                                        <Badge className={getScoreColor(trade.analysis.smart_money_score)}>
                                            {trade.analysis.smart_money_score.toFixed(1)}/10
                                        </Badge>
                                    </div>
                                    
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <div className="text-sm text-muted-foreground mb-1">Качество входа</div>
                                            <div className="text-lg font-semibold">{trade.analysis.entry_quality}/10</div>
                                        </div>
                                        
                                        {trade.analysis.exit_quality && (
                                            <div>
                                                <div className="text-sm text-muted-foreground mb-1">Качество выхода</div>
                                                <div className="text-lg font-semibold">{trade.analysis.exit_quality}/10</div>
                                            </div>
                                        )}
                                    </div>
                                    
                                    {trade.analysis.patterns && (
                                        <div className="space-y-2">
                                            <div className="text-sm font-medium">Обнаруженные паттерны</div>
                                            <div className="flex flex-wrap gap-2">
                                                {trade.analysis.patterns.split(',').map((pattern, index) => (
                                                    <Badge key={index} variant="outline" className="text-xs">
                                                        {pattern.trim()}
                                                    </Badge>
                                                ))}
                                            </div>
                                        </div>
                                    )}
                                    
                                    {trade.analysis.recommendations && (
                                        <div className="space-y-2">
                                            <div className="text-sm font-medium">Рекомендации</div>
                                            <div className="text-sm text-muted-foreground whitespace-pre-line">
                                                {trade.analysis.recommendations}
                                            </div>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        )}
                    </div>

                    {/* Sidebar */}
                    <div className="space-y-6">
                        {/* P&L Summary */}
                        {!isOpen && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <DollarSign className="h-5 w-5" />
                                        Финансовый результат
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="text-center">
                                        <div className={`text-2xl font-bold ${isProfitable ? 'text-green-600' : 'text-red-600'}`}>
                                            {isProfitable ? '+' : ''}{formatCurrency(pnl)}
                                        </div>
                                        <div className="text-sm text-muted-foreground">
                                            {((pnl / (trade.entry_price * trade.size)) * 100).toFixed(2)}%
                                        </div>
                                    </div>
                                    
                                    <div className="space-y-3">
                                        <div className="flex justify-between">
                                            <span className="text-sm text-muted-foreground">Стоимость позиции</span>
                                            <span className="text-sm font-medium">
                                                {formatCurrency(trade.entry_price * trade.size)}
                                            </span>
                                        </div>
                                        
                                        <div className="flex justify-between">
                                            <span className="text-sm text-muted-foreground">Комиссии</span>
                                            <span className="text-sm font-medium">
                                                {formatCurrency(trade.fee)}
                                            </span>
                                        </div>
                                        
                                        {trade.funding_fees && trade.funding_fees > 0 && (
                                            <div className="flex justify-between">
                                                <span className="text-sm text-muted-foreground">Фандинг</span>
                                                <span className="text-sm font-medium">
                                                    {formatCurrency(trade.funding_fees)}
                                                </span>
                                            </div>
                                        )}
                                        
                                        <div className="border-t pt-3">
                                            <div className="flex justify-between">
                                                <span className="text-sm font-medium">Итого P&L</span>
                                                <span className={`text-sm font-bold ${isProfitable ? 'text-green-600' : 'text-red-600'}`}>
                                                    {isProfitable ? '+' : ''}{formatCurrency(pnl)}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        )}

                        {/* Actions */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Действия</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                <Button className="w-full" onClick={fetchTradeDetails}>
                                    <Loader2 className="mr-2 h-4 w-4" />
                                    Обновить данные
                                </Button>
                                
                                <Button variant="outline" className="w-full">
                                    Экспорт данных
                                </Button>
                                
                                {trade.analysis && (
                                    <Button variant="outline" className="w-full">
                                        Подробный анализ
                                    </Button>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}