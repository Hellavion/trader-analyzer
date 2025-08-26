import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { useDashboard } from '@/hooks/use-dashboard';
import { MetricCard } from '@/components/dashboard/metric-card';
import { RecentActivity } from '@/components/dashboard/recent-activity';
import { PerformanceChart } from '@/components/dashboard/performance-chart';
import { DollarSign, TrendingUp, Activity, Zap, RefreshCw } from 'lucide-react';
import { cn } from '@/lib/utils';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

export default function Dashboard() {
    const { overview, metrics, widgets, loading, error, refresh } = useDashboard();

    if (loading) {
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="Dashboard" />
                <div className="flex h-full flex-1 items-center justify-center">
                    <div className="flex items-center gap-2 text-muted-foreground">
                        <div className="h-4 w-4 border-2 border-current border-t-transparent animate-spin rounded-full" />
                        <span>Загрузка дашборда...</span>
                    </div>
                </div>
            </AppLayout>
        );
    }

    if (error) {
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="Dashboard" />
                <div className="flex h-full flex-1 items-center justify-center">
                    <div className="text-center">
                        <p className="text-red-600 dark:text-red-400 mb-4">{error}</p>
                        <button 
                            onClick={refresh}
                            className="px-4 py-2 bg-primary text-primary-foreground rounded-md hover:bg-primary/90"
                        >
                            Попробовать снова
                        </button>
                    </div>
                </div>
            </AppLayout>
        );
    }

    const formatCurrency = (value: number) => {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD',
        }).format(value);
    };

    const formatNumber = (value: number) => {
        return new Intl.NumberFormat('en-US').format(value);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">Торговый дашборд</h1>
                        <p className="text-muted-foreground">
                            Обзор вашей торговой производительности и активности
                            <span className="ml-2 text-xs opacity-60">• Автосинхронизация каждые 60 сек</span>
                        </p>
                    </div>
                    <button 
                        onClick={refresh}
                        disabled={loading}
                        className="flex items-center gap-2 px-3 py-2 text-sm bg-muted hover:bg-muted/80 rounded-md transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <RefreshCw className={cn("h-4 w-4", loading && "animate-spin")} />
                        Синхронизировать
                    </button>
                </div>

                {/* Key Metrics Cards */}
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <MetricCard
                        title="Общий PnL"
                        value={overview?.performance ? formatCurrency(overview.performance.net_pnl) : '$0.00'}
                        subtitle={overview?.performance ? 
                            `Реализованный: ${formatCurrency(overview.performance.realized_pnl || 0)} | Нереализованный: ${formatCurrency(overview.performance.unrealized_pnl || 0)}` : 
                            undefined
                        }
                        change={metrics?.period_comparison ? {
                            value: metrics.period_comparison.pnl_change,
                            percentage: true
                        } : undefined}
                        icon={<DollarSign className="h-4 w-4" />}
                    />
                    <MetricCard
                        title="Винрейт"
                        value={overview?.performance ? `${overview.performance.win_rate}%` : '0%'}
                        change={metrics?.period_comparison ? {
                            value: metrics.period_comparison.win_rate_change,
                            percentage: true
                        } : undefined}
                        icon={<TrendingUp className="h-4 w-4" />}
                    />
                    <MetricCard
                        title="Всего сделок"
                        value={overview?.trades ? formatNumber(overview.trades.total) : '0'}
                        change={metrics?.period_comparison ? {
                            value: metrics.period_comparison.trades_change
                        } : undefined}
                        icon={<Activity className="h-4 w-4" />}
                    />
                    <MetricCard
                        title="Активные позиции"
                        value={overview?.trades ? formatNumber(overview.trades.open) : '0'}
                        icon={<Zap className="h-4 w-4" />}
                    />
                </div>

                {/* Charts and Activity */}
                <div className="grid gap-6 lg:grid-cols-3">
                    {/* Performance Chart - Takes 2 columns */}
                    <div className="lg:col-span-2">
                        <PerformanceChart 
                            data={widgets?.exchange_breakdown || []}
                        />
                    </div>
                    
                    {/* Recent Activity - Takes 1 column */}
                    <div className="lg:col-span-1">
                        <RecentActivity 
                            activities={overview?.recent_activity || []}
                        />
                    </div>
                </div>

                {/* Additional Stats */}
                {overview && (
                    <div className="grid gap-4 md:grid-cols-3">
                        <div className="rounded-xl border border-sidebar-border/70 bg-background/50 p-6 dark:border-sidebar-border">
                            <h3 className="text-lg font-semibold mb-4">Подключения к биржам</h3>
                            <div className="space-y-3">
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">Всего подключений</span>
                                    <span className="font-medium">{overview.connections.total}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">Активных</span>
                                    <span className={cn(
                                        'font-medium',
                                        overview.connections.active > 0 
                                            ? 'text-green-600 dark:text-green-400' 
                                            : 'text-gray-500'
                                    )}>
                                        {overview.connections.active}
                                    </span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">Нуждаются в синхронизации</span>
                                    <span className={cn(
                                        'font-medium',
                                        overview.connections.needs_sync > 0 
                                            ? 'text-yellow-600 dark:text-yellow-400' 
                                            : 'text-green-600 dark:text-green-400'
                                    )}>
                                        {overview.connections.needs_sync}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div className="rounded-xl border border-sidebar-border/70 bg-background/50 p-6 dark:border-sidebar-border">
                            <h3 className="text-lg font-semibold mb-4">Анализ Smart Money</h3>
                            <div className="space-y-3">
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">Проанализировано сделок</span>
                                    <span className="font-medium">{overview.analysis.analyzed_trades}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">Покрытие</span>
                                    <span className="font-medium">{overview.analysis.coverage}%</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">Средний скор</span>
                                    <span className="font-medium">{overview.analysis.average_score}/10</span>
                                </div>
                            </div>
                        </div>

                        <div className="rounded-xl border border-sidebar-border/70 bg-background/50 p-6 dark:border-sidebar-border">
                            <h3 className="text-lg font-semibold mb-4">Рыночные данные</h3>
                            <div className="space-y-3">
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">Отслеживаемые символы</span>
                                    <span className="font-medium">{overview.market_data.symbols_tracked}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">Точек данных</span>
                                    <span className="font-medium">{formatNumber(overview.market_data.data_points)}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">Последнее обновление</span>
                                    <span className="font-medium text-xs">
                                        {overview.market_data.last_update || 'Нет данных'}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
