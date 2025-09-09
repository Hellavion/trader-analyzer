import { useEffect, useRef, useState } from 'react';
import { createChart, ColorType, IChartApi, ISeriesApi, LineStyle, CandlestickSeries, LineSeries } from 'lightweight-charts';
import { api } from '@/lib/api';

interface TradingChartProps {
    tradeId: number;
    symbol: string;
    entryPrice: number;
    exitPrice?: number;
    entryTime: string;
    exitTime?: string;
    side: 'buy' | 'sell';
    className?: string;
}

export default function TradingChart({
    tradeId,
    symbol,
    entryPrice,
    exitPrice,
    entryTime,
    exitTime,
    side,
    className = ""
}: TradingChartProps) {

    const chartContainerRef = useRef<HTMLDivElement>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {

        let chart: IChartApi | null = null;
        let cleanupFn: (() => void) | null = null;

        // Инициализируем график
        const initChart = async () => {
            if (!chartContainerRef.current) {
                setError('Не удается инициализировать контейнер графика');
                setLoading(false);
                return;
            }

            const loadChartData = async () => {
                try {
                    setLoading(true);
                    setError(null);

                    // Получаем реальные данные с сервера
                    const response = await api.getTradeChartData(tradeId);
                    
                    if (!response.success) {
                        console.error('❌ Chart API failed:', response.message);
                        throw new Error(response.message || 'Failed to load chart data');
                    }

                    const { ohlcv, trade_info } = response.data;

                    // Создаем график
                    chart = createChart(chartContainerRef.current!, {
                        width: chartContainerRef.current!.clientWidth,
                        height: 400,
                        layout: {
                            background: { type: ColorType.Solid, color: 'transparent' },
                            textColor: 'rgb(120, 113, 108)',
                        },
                        grid: {
                            vertLines: { color: 'rgba(120, 113, 108, 0.2)' },
                            horzLines: { color: 'rgba(120, 113, 108, 0.2)' },
                        },
                        crosshair: {
                            mode: 1,
                        },
                        rightPriceScale: {
                            borderColor: 'rgba(120, 113, 108, 0.5)',
                        },
                        timeScale: {
                            borderColor: 'rgba(120, 113, 108, 0.5)',
                        },
                    });

                    // Добавляем candlestick серию
                    const candlestickSeries = chart.addSeries(CandlestickSeries, {
                        upColor: '#10b981',
                        downColor: '#ef4444',
                        wickUpColor: '#10b981',
                        wickDownColor: '#ef4444',
                    });

                    // Устанавливаем данные свечей
                    candlestickSeries.setData(ohlcv);

                    // Добавляем линии входа и выхода
                    if (trade_info.entry_time && trade_info.entry_price) {
                        const entryLine = chart.addSeries(LineSeries, {
                            color: trade_info.side === 'buy' ? '#10b981' : '#ef4444',
                            lineWidth: 2,
                            title: `Вход: $${trade_info.entry_price}`,
                        });

                        // Создаем горизонтальную линию входа
                        const entryData = [
                            { time: trade_info.entry_time, value: trade_info.entry_price }
                        ];
                        // Если есть время выхода, растягиваем линию до выхода
                        if (trade_info.exit_time && trade_info.exit_time > trade_info.entry_time) {
                            entryData.push({ time: trade_info.exit_time, value: trade_info.entry_price });
                        } else if (ohlcv.length > 0) {
                            // Иначе растягиваем до последней свечи
                            entryData.push({ time: ohlcv[ohlcv.length - 1].time, value: trade_info.entry_price });
                        }
                        entryLine.setData(entryData);
                    }

                    if (trade_info.exit_time && trade_info.exit_price) {
                        const exitLine = chart.addSeries(LineSeries, {
                            color: trade_info.exit_price > trade_info.entry_price ? '#10b981' : '#ef4444',
                            lineWidth: 2,
                            lineStyle: LineStyle.Dashed,
                            title: `Выход: $${trade_info.exit_price}`,
                        });

                        // Создаем точку выхода
                        exitLine.setData([
                            { time: trade_info.exit_time, value: trade_info.exit_price }
                        ]);
                    }

                    // Подгоняем масштаб
                    chart.timeScale().fitContent();

                } catch (err) {
                    console.error('Failed to load chart data:', err);
                    setError(err instanceof Error ? err.message : 'Failed to load chart data');
                } finally {
                    setLoading(false);
                }
            };

            await loadChartData();

            // Добавляем обработчик resize
            const handleResize = () => {
                if (chart && chartContainerRef.current) {
                    chart.applyOptions({
                        width: chartContainerRef.current.clientWidth,
                    });
                }
            };

            window.addEventListener('resize', handleResize);

            cleanupFn = () => {
                window.removeEventListener('resize', handleResize);
                chart?.remove();
            };
        };

        initChart();

        return () => {
            if (cleanupFn) {
                cleanupFn();
            }
        };
    }, [tradeId]);

    return (
        <div className={`relative ${className}`}>
            <div 
                ref={chartContainerRef} 
                className="w-full h-96 rounded-lg border bg-card"
            />
            
            {/* Overlay для загрузки */}
            {loading && (
                <div className="absolute inset-0 flex items-center justify-center bg-background/80 backdrop-blur-sm rounded-lg">
                    <div className="flex flex-col items-center gap-2">
                        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
                        <p className="text-sm text-muted-foreground">Загрузка графика...</p>
                    </div>
                </div>
            )}
            
            {/* Overlay для ошибки */}
            {error && (
                <div className="absolute inset-0 flex items-center justify-center bg-background/80 backdrop-blur-sm rounded-lg">
                    <div className="text-center">
                        <p className="text-sm text-red-500 mb-2">{error}</p>
                    </div>
                </div>
            )}
            
            {/* Символ в углу (показывается только когда график загружен) */}
            {!loading && !error && (
                <div className="absolute top-2 left-2 bg-background/80 backdrop-blur-sm rounded px-2 py-1">
                    <span className="text-sm font-medium">{symbol}</span>
                </div>
            )}
        </div>
    );
}