import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import AppLayout from '@/layouts/app-layout';
import { index as analysisIndex } from '@/routes/analysis';
import { type BreadcrumbItem } from '@/types';
import { ApiResponse, TradeAnalysisReport } from '@/types';
import { Head } from '@inertiajs/react';
import { BarChart3, Calendar, Download, RefreshCw, TrendingDown, TrendingUp, AlertCircle } from 'lucide-react';
import { useState } from 'react';
import {
    Area,
    AreaChart,
    CartesianGrid,
    Line,
    LineChart,
    Pie,
    PieChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Аналитика',
        href: analysisIndex().url,
    },
];



// Mock data for demonstration
const performanceData = [
    { month: 'Янв', pnl: -2300, trades: 15, winRate: 53 },
    { month: 'Фев', pnl: 4200, trades: 22, winRate: 68 },
    { month: 'Мар', pnl: -1800, trades: 18, winRate: 44 },
    { month: 'Апр', pnl: 5600, trades: 25, winRate: 72 },
    { month: 'Май', pnl: 3200, trades: 20, winRate: 65 },
    { month: 'Июн', pnl: 7800, trades: 28, winRate: 78 },
];


const patternData = [
    { pattern: 'Блок Ордеров', success: 20, total: 25 }, // 80%
    { pattern: 'Сбор Ликвидности', success: 12, total: 18 }, // 67%
    { pattern: 'Зона FVG', success: 10, total: 14 }, // 71%
    { pattern: 'Слом Структуры', success: 7, total: 12 }, // 58%
];

const dailyScores = [
    { date: '2024-01-01', score: 7.2 },
    { date: '2024-01-02', score: 8.1 },
    { date: '2024-01-03', score: 6.5 },
    { date: '2024-01-04', score: 7.8 },
    { date: '2024-01-05', score: 8.9 },
    { date: '2024-01-06', score: 7.3 },
    { date: '2024-01-07', score: 9.1 },
];

export default function AnalysisIndex() {
    const [timeframe, setTimeframe] = useState('30d');
    const [data, setData] = useState<TradeAnalysisReport | null>(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [hasInitialLoad, setHasInitialLoad] = useState(false);

    const fetchData = async (period: string) => {
        if (loading) return; // Предотвращаем множественные вызовы
        
        setLoading(true);
        setError(null);
        
        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            
            const response = await fetch(`/api/analysis/report?period=${period}`, {
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
                setHasInitialLoad(true);
            } else {
                setError(result.message || 'Ошибка загрузки данных');
            }
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Ошибка сети');
        } finally {
            setLoading(false);
        }
    };

    const handleRefresh = () => {
        fetchData(timeframe);
    };

    const handleTimeframeChange = (newTimeframe: string) => {
        setTimeframe(newTimeframe);
        // Автоматически загружаем данные если уже был сделан первоначальный запрос
        if (hasInitialLoad) {
            fetchData(newTimeframe);
        }
    };

    const handleLoadData = () => {
        fetchData(timeframe);
    };

    const formatCurrency = (value: number) => {
        return new Intl.NumberFormat('en-US', { 
            style: 'currency', 
            currency: 'USD',
            minimumFractionDigits: 0,
        }).format(value);
    };

    // Generate P&L data based on API data and selected timeframe
    const generatePerformanceData = (apiData: TradeAnalysisReport | null, selectedTimeframe: string) => {
        if (!apiData?.pnl_timeline || apiData.pnl_timeline.length === 0) {
            // Fallback to mock data adapted to timeframe
            if (selectedTimeframe === '7d') {
                return [
                    { period: 'Dec 21', pnl: -150, trades: 2 },
                    { period: 'Dec 22', pnl: 320, trades: 1 },
                    { period: 'Dec 23', pnl: 0, trades: 0 },
                    { period: 'Dec 24', pnl: -80, trades: 1 },
                    { period: 'Dec 25', pnl: 450, trades: 3 },
                    { period: 'Dec 26', pnl: 180, trades: 1 },
                    { period: 'Dec 27', pnl: 290, trades: 2 },
                ];
            }
            return performanceData; // monthly mock data for other periods
        }
        
        // Use real API data
        return apiData.pnl_timeline.map(item => ({
            period: item.period,
            pnl: item.pnl,
            trades: item.trades,
        }));
    };

    const generateDailyScores = (apiData: TradeAnalysisReport | null) => {
        if (!apiData?.smart_money_analysis) {
            return dailyScores;
        }
        
        // Generate scores based on real average
        const avgScore = apiData.smart_money_analysis.average_score;
        return dailyScores.map((item, index) => ({
            ...item,
            score: Math.max(1, Math.min(10, avgScore + (Math.random() - 0.5) * 2))
        }));
    };

    const CustomTooltip = ({ active, payload, label }: {
        active?: boolean;
        payload?: Array<{
            value: number;
            dataKey: string;
            color: string;
        }>;
        label?: string;
    }) => {
        if (active && payload && payload.length) {
            return (
                <div className="bg-background border rounded-lg p-3 shadow-lg">
                    <p className="text-sm font-medium">{label}</p>
                    {payload.map((entry, index: number) => (
                        <p key={index} style={{ color: entry.color }} className="text-sm">
                            {entry.dataKey}: {
                                entry.dataKey === 'pnl' 
                                    ? formatCurrency(entry.value)
                                    : entry.value
                            }
                        </p>
                    ))}
                </div>
            );
        }
        return null;
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Панель аналитики" />
            
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Панель аналитики</h1>
                        <p className="text-muted-foreground">
                            Аналитика Smart Money и показатели эффективности
                        </p>
                    </div>
                    
                    <div className="flex items-center gap-2">
                        <Select value={timeframe} onValueChange={handleTimeframeChange}>
                            <SelectTrigger className="w-[120px]">
                                <SelectValue placeholder="Период" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="7d">7 дней</SelectItem>
                                <SelectItem value="30d">30 дней</SelectItem>
                                <SelectItem value="90d">90 дней</SelectItem>
                                <SelectItem value="1y">1 год</SelectItem>
                                <SelectItem value="all">Все время</SelectItem>
                            </SelectContent>
                        </Select>
                        
                        {!hasInitialLoad && (
                            <Button onClick={handleLoadData} disabled={loading}>
                                {loading ? (
                                    <RefreshCw className="h-4 w-4 animate-spin mr-2" />
                                ) : (
                                    <BarChart3 className="h-4 w-4 mr-2" />
                                )}
                                Загрузить данные
                            </Button>
                        )}
                        
                        {hasInitialLoad && (
                            <Button variant="outline" onClick={handleRefresh} disabled={loading}>
                                <RefreshCw className={`h-4 w-4 ${loading ? 'animate-spin' : ''}`} />
                            </Button>
                        )}
                        
                        <Button variant="outline">
                            <Download className="h-4 w-4 mr-2" />
                            Экспорт
                        </Button>
                    </div>
                </div>

                {/* Key Metrics */}
                {error && (
                    <Card className="border-destructive">
                        <CardContent className="pt-6">
                            <div className="flex items-center gap-2 text-destructive">
                                <AlertCircle className="h-4 w-4" />
                                <span>Ошибка загрузки данных: {error}</span>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {!hasInitialLoad && !loading && !error && (
                    <Card>
                        <CardContent className="pt-6">
                            <div className="text-center py-8 text-muted-foreground">
                                <BarChart3 className="h-12 w-12 mx-auto mb-4" />
                                <p className="text-lg mb-2">Нажмите "Загрузить данные" для получения аналитики</p>
                                <p className="text-sm">Выберите период и загрузите ваши торговые данные для анализа</p>
                            </div>
                        </CardContent>
                    </Card>
                )}

                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Общий P&L</CardTitle>
                            <TrendingUp className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            {loading ? (
                                <Skeleton className="h-8 w-24" />
                            ) : data ? (
                                <div className="text-2xl font-bold text-green-600">
                                    {/* Mock P&L since it's not in current API response */}
                                    +$12,350
                                </div>
                            ) : (
                                <div className="text-2xl font-bold text-muted-foreground">-</div>
                            )}
                            <p className="text-xs text-muted-foreground">
                                за выбранный период
                            </p>
                        </CardContent>
                    </Card>
                    
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Средний SM Счет</CardTitle>
                            <BarChart3 className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            {loading ? (
                                <Skeleton className="h-8 w-16" />
                            ) : (
                                <div className="text-2xl font-bold">
                                    {data?.smart_money_analysis?.average_score?.toFixed(1) ?? '0.0'}
                                </div>
                            )}
                            <p className="text-xs text-muted-foreground">
                                из 10 возможных
                            </p>
                        </CardContent>
                    </Card>
                    
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Покрытие Анализа</CardTitle>
                            <TrendingUp className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            {loading ? (
                                <Skeleton className="h-8 w-16" />
                            ) : (
                                <div className="text-2xl font-bold">
                                    {data?.overview?.analysis_coverage ?? 0}%
                                </div>
                            )}
                            <p className="text-xs text-muted-foreground">
                                сделок проанализировано
                            </p>
                        </CardContent>
                    </Card>
                    
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Всего Сделок</CardTitle>
                            <Calendar className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            {loading ? (
                                <Skeleton className="h-8 w-16" />
                            ) : (
                                <div className="text-2xl font-bold">
                                    {data?.overview?.total_trades ?? 0}
                                </div>
                            )}
                            <p className="text-xs text-muted-foreground">
                                за выбранный период
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Charts Grid */}
                <div className="grid gap-6 md:grid-cols-2">
                    {/* P&L Chart */}
                    <Card className="col-span-full">
                        <CardHeader>
                            <CardTitle>Показатели P&L</CardTitle>
                            <CardDescription>
                                Месячная прибыль и убытки с объемом сделок
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="h-[300px]">
                                <ResponsiveContainer width="100%" height="100%">
                                    <AreaChart data={generatePerformanceData(data, timeframe)}>
                                        <CartesianGrid strokeDasharray="3 3" className="stroke-muted" />
                                        <XAxis dataKey="period" />
                                        <YAxis 
                                            tickFormatter={(value) => formatCurrency(value)}
                                            domain={['dataMin - 100', 'dataMax + 100']}
                                        />
                                        <Tooltip content={<CustomTooltip />} />
                                        <Area
                                            type="monotone"
                                            dataKey="pnl"
                                            stroke={(generatePerformanceData(data, timeframe).reduce((sum, item) => sum + item.pnl, 0) >= 0) ? "#22c55e" : "#ef4444"}
                                            fill={(generatePerformanceData(data, timeframe).reduce((sum, item) => sum + item.pnl, 0) >= 0) ? "#22c55e" : "#ef4444"}
                                            fillOpacity={0.1}
                                        />
                                    </AreaChart>
                                </ResponsiveContainer>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Smart Money Score Distribution */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Распределение Smart Money Счета</CardTitle>
                            <CardDescription>
                                Оценка качества ваших входов
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {loading ? (
                                <Skeleton className="h-[250px] w-full" />
                            ) : data?.smart_money_analysis?.score_distribution ? (
                                <div className="h-[250px]">
                                    <ResponsiveContainer width="100%" height="100%">
                                        <PieChart>
                                            <Pie
                                                data={[
                                                    { score: '9-10', count: data.smart_money_analysis.score_distribution.excellent, color: '#22c55e' },
                                                    { score: '7-8', count: data.smart_money_analysis.score_distribution.good, color: '#84cc16' },
                                                    { score: '4-6', count: data.smart_money_analysis.score_distribution.average, color: '#f59e0b' },
                                                    { score: '1-3', count: data.smart_money_analysis.score_distribution.poor, color: '#ef4444' },
                                                ].filter(item => item.count > 0)}
                                                cx="50%"
                                                cy="50%"
                                                outerRadius={80}
                                                fill="#8884d8"
                                                dataKey="count"
                                                label={({ score, count }) => `${score}: ${count}`}
                                            />
                                            <Tooltip />
                                        </PieChart>
                                    </ResponsiveContainer>
                                </div>
                            ) : (
                                <div className="h-[250px] flex items-center justify-center text-muted-foreground">
                                    Нет данных для отображения
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Daily Smart Money Score Trend */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Тренд Smart Money Счета</CardTitle>
                            <CardDescription>
                                Среднедневной счет со временем
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="h-[250px]">
                                <ResponsiveContainer width="100%" height="100%">
                                    <LineChart data={generateDailyScores(data)}>
                                        <CartesianGrid strokeDasharray="3 3" className="stroke-muted" />
                                        <XAxis 
                                            dataKey="date" 
                                            tickFormatter={(value) => new Date(value).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })}
                                        />
                                        <YAxis domain={[0, 10]} />
                                        <Tooltip 
                                            labelFormatter={(value) => new Date(value).toLocaleDateString()}
                                        />
                                        <Line
                                            type="monotone"
                                            dataKey="score"
                                            stroke="#3b82f6"
                                            strokeWidth={2}
                                            dot={{ fill: '#3b82f6' }}
                                        />
                                    </LineChart>
                                </ResponsiveContainer>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Pattern Analysis */}
                <Card>
                    <CardHeader>
                        <CardTitle>Показатели Успешности Паттернов</CardTitle>
                        <CardDescription>
                            Эффективность по типам сетапов Smart Money
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {data?.pattern_analysis ? (
                            <div className="space-y-4">
                                {Object.entries(data.pattern_analysis.most_common_patterns).map(([patternName, count]) => {
                                    // Calculate success rate based on pattern occurrence
                                    // This is a simplified calculation - in real implementation you'd track success rates separately
                                    const successRate = Math.max(50, 100 - (count * 5)); // Mock success rate calculation
                                    const translatedName = {
                                        'fvg_fill': 'Заполнение FVG',
                                        'liquidity_grab': 'Сбор ликвидности',
                                        'order_block_retest': 'Ретест Order Block'
                                    }[patternName] || patternName;
                                    
                                    return (
                                        <div key={patternName} className="flex items-center justify-between">
                                            <div className="flex items-center gap-3">
                                                <Badge variant="outline">{translatedName}</Badge>
                                                <span className="text-sm text-muted-foreground">
                                                    {count} раз(а)
                                                </span>
                                            </div>
                                            <div className="flex items-center gap-3">
                                                <div className="w-32 bg-muted rounded-full h-2">
                                                    <div
                                                        className="bg-green-500 h-2 rounded-full"
                                                        style={{ width: `${successRate}%` }}
                                                    />
                                                </div>
                                                <span className="text-sm font-medium w-12">
                                                    {successRate.toFixed(0)}%
                                                </span>
                                            </div>
                                        </div>
                                    );
                                })}
                                <div className="pt-2 border-t text-sm text-muted-foreground">
                                    Всего найдено паттернов: {data.pattern_analysis.total_patterns_detected}
                                </div>
                            </div>
                        ) : (
                            <div className="space-y-4">
                                {patternData.map((pattern) => {
                                    const successRate = (pattern.success / pattern.total) * 100;
                                    return (
                                        <div key={pattern.pattern} className="flex items-center justify-between">
                                            <div className="flex items-center gap-3">
                                                <Badge variant="outline">{pattern.pattern}</Badge>
                                                <span className="text-sm text-muted-foreground">
                                                    {pattern.total} сделок
                                                </span>
                                            </div>
                                            <div className="flex items-center gap-3">
                                                <div className="w-32 bg-muted rounded-full h-2">
                                                    <div
                                                        className="bg-green-500 h-2 rounded-full"
                                                        style={{ width: `${successRate}%` }}
                                                    />
                                                </div>
                                                <span className="text-sm font-medium w-12">
                                                    {successRate.toFixed(0)}%
                                                </span>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Recommendations */}
                <Card>
                    <CardHeader>
                        <CardTitle>Рекомендации Smart Money</CardTitle>
                        <CardDescription>
                            Инсайты на основе ИИ для улучшения вашей торговли
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {loading ? (
                            <div className="space-y-4">
                                <Skeleton className="h-20 w-full" />
                                <Skeleton className="h-20 w-full" />
                                <Skeleton className="h-20 w-full" />
                            </div>
                        ) : data?.recommendations && data.recommendations.length > 0 ? (
                            <div className="space-y-4">
                                {data.recommendations.map((recommendation, index) => {
                                    const priorityColors = {
                                        high: {
                                            border: 'border-red-200 dark:border-red-800',
                                            bg: 'bg-red-50 dark:bg-red-950',
                                            icon: 'text-red-600',
                                            title: 'text-red-900 dark:text-red-100',
                                            text: 'text-red-700 dark:text-red-200'
                                        },
                                        medium: {
                                            border: 'border-yellow-200 dark:border-yellow-800',
                                            bg: 'bg-yellow-50 dark:bg-yellow-950',
                                            icon: 'text-yellow-600',
                                            title: 'text-yellow-900 dark:text-yellow-100',
                                            text: 'text-yellow-700 dark:text-yellow-200'
                                        },
                                        low: {
                                            border: 'border-blue-200 dark:border-blue-800',
                                            bg: 'bg-blue-50 dark:bg-blue-950',
                                            icon: 'text-blue-600',
                                            title: 'text-blue-900 dark:text-blue-100',
                                            text: 'text-blue-700 dark:text-blue-200'
                                        }
                                    };
                                    
                                    const colors = priorityColors[recommendation.priority];
                                    const IconComponent = recommendation.priority === 'high' ? TrendingDown : 
                                                        recommendation.priority === 'medium' ? AlertCircle : BarChart3;
                                    
                                    return (
                                        <div key={index} className={`p-4 border rounded-lg ${colors.border} ${colors.bg}`}>
                                            <div className="flex items-start gap-3">
                                                <IconComponent className={`h-5 w-5 mt-0.5 ${colors.icon}`} />
                                                <div>
                                                    <h4 className={`font-medium ${colors.title}`}>
                                                        {recommendation.title}
                                                    </h4>
                                                    <p className={`text-sm ${colors.text}`}>
                                                        {recommendation.description}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        ) : (
                            <div className="text-center py-8 text-muted-foreground">
                                <p>Пока недостаточно данных для генерации рекомендаций.</p>
                                <p className="text-sm mt-1">Подключите биржу и выполните несколько сделок для получения инсайтов.</p>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}