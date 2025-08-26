import React from 'react';
import { cn } from '@/lib/utils';
import { translateTimeAgo } from '@/utils/time';
import { TrendingUp, TrendingDown, RefreshCw, ArrowUpDown } from 'lucide-react';
import type { DashboardOverview } from '@/types';

interface RecentActivityProps {
    activities: DashboardOverview['recent_activity'];
    className?: string;
}

export function RecentActivity({ activities, className }: RecentActivityProps) {
    const getActivityIcon = (type: 'trade' | 'sync', status: string, pnl?: number | null) => {
        if (type === 'sync') {
            return <RefreshCw className="h-4 w-4 text-blue-500" />;
        }
        
        if (type === 'trade') {
            if (pnl === null || pnl === undefined) {
                return <ArrowUpDown className="h-4 w-4 text-gray-500" />;
            }
            return pnl > 0 
                ? <TrendingUp className="h-4 w-4 text-green-500" />
                : <TrendingDown className="h-4 w-4 text-red-500" />;
        }

        return <ArrowUpDown className="h-4 w-4 text-gray-500" />;
    };

    const formatPnL = (pnl: number | null) => {
        if (pnl === null || pnl === undefined) return '';
        
        const formatted = Math.abs(pnl).toFixed(2);
        return pnl >= 0 ? `+$${formatted}` : `-$${formatted}`;
    };

    if (!activities || activities.length === 0) {
        return (
            <div className={cn(
                'rounded-xl border border-sidebar-border/70 bg-background/50 p-6 dark:border-sidebar-border',
                className
            )}>
                <h3 className="text-lg font-semibold mb-4">Последняя активность</h3>
                <div className="text-center text-muted-foreground">
                    <p>Нет последней активности</p>
                </div>
            </div>
        );
    }

    return (
        <div className={cn(
            'rounded-xl border border-sidebar-border/70 bg-background/50 p-6 dark:border-sidebar-border',
            className
        )}>
            <h3 className="text-lg font-semibold mb-4">Последняя активность</h3>
            <div className="space-y-3">
                {activities.slice(0, 10).map((activity, index) => (
                    <div key={index} className="flex items-center gap-3 py-2">
                        <div className="flex-shrink-0">
                            {getActivityIcon(activity.type, activity.status, activity.pnl)}
                        </div>
                        <div className="flex-1 min-w-0">
                            <p className="text-sm font-medium truncate">
                                {activity.description}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                {translateTimeAgo(activity.time)}
                            </p>
                        </div>
                        {activity.pnl !== null && activity.pnl !== undefined && (
                            <div className={cn(
                                'text-sm font-medium',
                                activity.pnl >= 0 
                                    ? 'text-green-600 dark:text-green-400' 
                                    : 'text-red-600 dark:text-red-400'
                            )}>
                                {formatPnL(activity.pnl)}
                            </div>
                        )}
                    </div>
                ))}
            </div>
        </div>
    );
}