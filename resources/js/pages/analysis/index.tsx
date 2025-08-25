import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { analysisIndex } from '@/routes/custom';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { BarChart3, Calendar, Download, RefreshCw, TrendingDown, TrendingUp } from 'lucide-react';
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

const smartMoneyScoreData = [
    { score: '1-3', count: 5, color: '#ef4444' },
    { score: '4-6', count: 12, color: '#f59e0b' },
    { score: '7-8', count: 18, color: '#84cc16' },
    { score: '9-10', count: 8, color: '#22c55e' },
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
    const [timeframe, setTimeframe] = useState('6m');
    const [isLoading, setIsLoading] = useState(false);

    const handleRefresh = () => {
        setIsLoading(true);
        // Simulate API call
        setTimeout(() => setIsLoading(false), 2000);
    };

    const formatCurrency = (value: number) => {
        return new Intl.NumberFormat('en-US', { 
            style: 'currency', 
            currency: 'USD',
            minimumFractionDigits: 0,
        }).format(value);
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
                        <Select value={timeframe} onValueChange={setTimeframe}>
                            <SelectTrigger className="w-[120px]">
                                <SelectValue placeholder="Период" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="1m">1 месяц</SelectItem>
                                <SelectItem value="3m">3 месяца</SelectItem>
                                <SelectItem value="6m">6 месяцев</SelectItem>
                                <SelectItem value="1y">1 год</SelectItem>
                            </SelectContent>
                        </Select>
                        
                        <Button variant="outline" onClick={handleRefresh} disabled={isLoading}>
                            <RefreshCw className={`h-4 w-4 ${isLoading ? 'animate-spin' : ''}`} />
                        </Button>
                        
                        <Button variant="outline">
                            <Download className="h-4 w-4 mr-2" />
                            Экспорт
                        </Button>
                    </div>
                </div>

                {/* Key Metrics */}
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Общий P&L</CardTitle>
                            <TrendingUp className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-green-600">+$16,700</div>
                            <p className="text-xs text-muted-foreground">
                                +12.3% с прошлого периода
                            </p>
                        </CardContent>
                    </Card>
                    
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Средний SM Счет</CardTitle>
                            <BarChart3 className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">7.3</div>
                            <p className="text-xs text-muted-foreground">
                                +0.8 улучшение
                            </p>
                        </CardContent>
                    </Card>
                    
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Винрейт</CardTitle>
                            <TrendingUp className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">68%</div>
                            <p className="text-xs text-muted-foreground">
                                +5% к предыдущему периоду
                            </p>
                        </CardContent>
                    </Card>
                    
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Всего Сделок</CardTitle>
                            <Calendar className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">128</div>
                            <p className="text-xs text-muted-foreground">
                                22 сделки в этом месяце
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
                                    <AreaChart data={performanceData}>
                                        <CartesianGrid strokeDasharray="3 3" className="stroke-muted" />
                                        <XAxis dataKey="month" />
                                        <YAxis />
                                        <Tooltip content={<CustomTooltip />} />
                                        <Area
                                            type="monotone"
                                            dataKey="pnl"
                                            stroke="#22c55e"
                                            fill="#22c55e"
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
                            <div className="h-[250px]">
                                <ResponsiveContainer width="100%" height="100%">
                                    <PieChart>
                                        <Pie
                                            data={smartMoneyScoreData}
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
                                    <LineChart data={dailyScores}>
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
                        <div className="space-y-4">
                            <div className="p-4 border border-green-200 bg-green-50 rounded-lg dark:border-green-800 dark:bg-green-950">
                                <div className="flex items-start gap-3">
                                    <TrendingUp className="h-5 w-5 text-green-600 mt-0.5" />
                                    <div>
                                        <h4 className="font-medium text-green-900 dark:text-green-100">
                                            Отличное Распознавание Блоков Ордеров
                                        </h4>
                                        <p className="text-sm text-green-700 dark:text-green-200">
                                            Ваши входы с Блоков Ордеров имеют 82% успешность. Продолжайте фокусироваться на чистых реакциях от значимых уровней.
                                        </p>
                                    </div>
                                </div>
                            </div>
                            
                            <div className="p-4 border border-yellow-200 bg-yellow-50 rounded-lg dark:border-yellow-800 dark:bg-yellow-950">
                                <div className="flex items-start gap-3">
                                    <TrendingDown className="h-5 w-5 text-yellow-600 mt-0.5" />
                                    <div>
                                        <h4 className="font-medium text-yellow-900 dark:text-yellow-100">
                                            Улучшите Тайминг Слома Структуры
                                        </h4>
                                        <p className="text-sm text-yellow-700 dark:text-yellow-200">
                                            Входы BOS показывают 58% успеха. Рассмотрите ожидание более глубоких откатов и четкого подтверждения.
                                        </p>
                                    </div>
                                </div>
                            </div>
                            
                            <div className="p-4 border border-blue-200 bg-blue-50 rounded-lg dark:border-blue-800 dark:bg-blue-950">
                                <div className="flex items-start gap-3">
                                    <BarChart3 className="h-5 w-5 text-blue-600 mt-0.5" />
                                    <div>
                                        <h4 className="font-medium text-blue-900 dark:text-blue-100">
                                            Увеличьте Размер Позиции на Высокооцененных Сетапах
                                        </h4>
                                        <p className="text-sm text-blue-700 dark:text-blue-200">
                                            Сделки с оценками Smart Money выше 8.0 имеют 91% винрейт. Рассмотрите больший риск на этих сетапах.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}