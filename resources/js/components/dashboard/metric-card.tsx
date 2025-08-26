import React from 'react';
import { cn } from '@/lib/utils';
import { TrendingUp, TrendingDown, Minus } from 'lucide-react';

interface MetricCardProps {
    title: string;
    value: string | number;
    subtitle?: string;
    change?: {
        value: number;
        percentage?: boolean;
    };
    icon?: React.ReactNode;
    className?: string;
}

export function MetricCard({ title, value, subtitle, change, icon, className }: MetricCardProps) {
    const renderTrend = () => {
        if (!change) return null;

        const isPositive = change.value > 0;
        const isNegative = change.value < 0;

        const TrendIcon = isPositive ? TrendingUp : isNegative ? TrendingDown : Minus;
        const trendColor = isPositive 
            ? 'text-green-600 dark:text-green-400' 
            : isNegative 
            ? 'text-red-600 dark:text-red-400' 
            : 'text-gray-500 dark:text-gray-400';

        return (
            <div className={cn('flex items-center gap-1 text-sm', trendColor)}>
                <TrendIcon className="h-3 w-3" />
                <span>
                    {change.percentage ? 
                        `${Math.abs(change.value)}%` : 
                        Math.abs(change.value)
                    }
                </span>
            </div>
        );
    };

    return (
        <div className={cn(
            'rounded-xl border border-sidebar-border/70 bg-background/50 p-4 dark:border-sidebar-border',
            className
        )}>
            <div className="flex items-center justify-between">
                <div className="flex items-center gap-2">
                    {icon && <div className="text-muted-foreground">{icon}</div>}
                    <h3 className="text-sm font-medium text-muted-foreground">{title}</h3>
                </div>
                {renderTrend()}
            </div>
            <div className="mt-2">
                <p className="text-2xl font-bold">{value}</p>
                {subtitle && (
                    <p className="text-xs text-muted-foreground mt-1">{subtitle}</p>
                )}
            </div>
        </div>
    );
}