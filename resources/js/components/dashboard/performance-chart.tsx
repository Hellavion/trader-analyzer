import React from 'react';
import { cn } from '@/lib/utils';
import { PieChart, Pie, Cell, ResponsiveContainer, BarChart, Bar, XAxis, YAxis, Tooltip } from 'recharts';

interface ExchangeData {
    exchange: string;
    trades: number;
    pnl: number;
}

interface PerformanceChartProps {
    data: ExchangeData[];
    className?: string;
}

const COLORS = ['#0088FE', '#00C49F', '#FFBB28', '#FF8042', '#8884D8'];

export function PerformanceChart({ data, className }: PerformanceChartProps) {
    if (!data || data.length === 0) {
        return (
            <div className={cn(
                'rounded-xl border border-sidebar-border/70 bg-background/50 p-6 dark:border-sidebar-border',
                className
            )}>
                <h3 className="text-lg font-semibold mb-4">Производительность бирж</h3>
                <div className="flex items-center justify-center h-64 text-muted-foreground">
                    <p>Нет данных о биржах</p>
                </div>
            </div>
        );
    }

    const pieData = data.map(item => ({
        name: item.exchange.toUpperCase(),
        value: item.trades,
        pnl: item.pnl,
    }));

    const barData = data.map(item => ({
        name: item.exchange.toUpperCase(),
        trades: item.trades,
        pnl: Math.round(item.pnl * 100) / 100,
    }));

    const CustomTooltip = ({ active, payload, label }: {
        active?: boolean;
        payload?: Array<{ color: string; dataKey: string; value: number }>;
        label?: string | number;
    }) => {
        if (active && payload && payload.length) {
            return (
                <div className="bg-background border border-border rounded-lg p-3 shadow-lg">
                    <p className="font-medium">{label}</p>
                    {payload.map((entry, index: number) => (
                        <p key={index} className="text-sm" style={{ color: entry.color }}>
                            {entry.dataKey === 'trades' ? 'Trades: ' : 'PnL: $'}
                            {entry.value}
                        </p>
                    ))}
                </div>
            );
        }
        return null;
    };

    return (
        <div className={cn(
            'rounded-xl border border-sidebar-border/70 bg-background/50 p-6 dark:border-sidebar-border',
            className
        )}>
            <h3 className="text-lg font-semibold mb-4">Производительность бирж</h3>
            
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                {/* Pie Chart - Trades Distribution */}
                <div>
                    <h4 className="text-sm font-medium text-muted-foreground mb-3">Распределение сделок</h4>
                    <ResponsiveContainer width="100%" height={200}>
                        <PieChart>
                            <Pie
                                data={pieData}
                                cx="50%"
                                cy="50%"
                                labelLine={false}
                                label={({ name, percent }) => `${name} ${percent ? (percent * 100).toFixed(0) : 0}%`}
                                outerRadius={70}
                                fill="#8884d8"
                                dataKey="value"
                            >
                                {pieData.map((_, index) => (
                                    <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
                                ))}
                            </Pie>
                            <Tooltip content={CustomTooltip} />
                        </PieChart>
                    </ResponsiveContainer>
                </div>

                {/* Bar Chart - PnL Comparison */}
                <div>
                    <h4 className="text-sm font-medium text-muted-foreground mb-3">Сравнение PnL</h4>
                    <ResponsiveContainer width="100%" height={200}>
                        <BarChart data={barData}>
                            <XAxis dataKey="name" />
                            <YAxis />
                            <Tooltip content={CustomTooltip} />
                            <Bar dataKey="pnl" fill="#8884d8" />
                        </BarChart>
                    </ResponsiveContainer>
                </div>
            </div>

            {/* Summary Table */}
            <div className="mt-4 overflow-x-auto">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-b border-border">
                            <th className="text-left py-2">Биржа</th>
                            <th className="text-right py-2">Сделки</th>
                            <th className="text-right py-2">PnL</th>
                        </tr>
                    </thead>
                    <tbody>
                        {data.map((item, index) => (
                            <tr key={index} className="border-b border-border/50">
                                <td className="py-2 font-medium">{item.exchange.toUpperCase()}</td>
                                <td className="text-right py-2">{item.trades}</td>
                                <td className={cn(
                                    'text-right py-2 font-medium',
                                    item.pnl >= 0 
                                        ? 'text-green-600 dark:text-green-400' 
                                        : 'text-red-600 dark:text-red-400'
                                )}>
                                    ${item.pnl.toFixed(2)}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}