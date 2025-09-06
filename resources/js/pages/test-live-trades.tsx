import { useState, useEffect } from 'react';
import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';

interface Trade {
    id: string;
    symbol: string;
    side: 'buy' | 'sell';
    size: number;
    entry_price: number;
    exit_price?: number;
    pnl?: number;
    unrealized_pnl?: number;
    fee?: number;
    entry_time: string;
    exit_time?: string;
    status: 'open' | 'closed';
    external_id?: string;
    exchange?: string;
}

export default function TestLiveTrades() {
    const [trades, setTrades] = useState<Trade[]>([]);
    const [connected, setConnected] = useState(false);
    const [connectionError, setConnectionError] = useState<string | null>(null);

    // Функция для отправки тестовой сделки
    const sendTestTrade = async () => {
        try {
            const response = await fetch('/trader-analyzer/public/test/send-fake-trade', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            console.log('✅ Тестовая сделка отправлена:', data);
        } catch (error) {
            console.error('❌ Ошибка отправки тестовой сделки:', error);
        }
    };

    useEffect(() => {
        try {
            // Подключаемся к каналу реальных сделок (публичный)
            const channel = window.Echo.channel('live-trades');
            
            channel.subscribed(() => {
                console.log('✅ Подключение к live-trades каналу успешно');
                setConnected(true);
                setConnectionError(null);
            });

            // Используем правильный формат для Laravel Wave
            channel.listen('.RealTradeUpdate', (event: any) => {
                console.log('🔥 Получена новая сделка:', event);
                
                setTrades(prev => {
                    // Если сделка уже есть - обновляем, иначе добавляем новую
                    const existingIndex = prev.findIndex(trade => trade.id === event.trade.id);
                    
                    if (existingIndex !== -1) {
                        const updated = [...prev];
                        updated[existingIndex] = event.trade;
                        console.log('🔄 Обновлена сделка:', event.trade);
                        return updated;
                    } else {
                        console.log('➕ Добавлена новая сделка:', event.trade);
                        return [event.trade, ...prev].slice(0, 20); // Показываем только последние 20
                    }
                });
            });

            channel.error((error: any) => {
                console.error('❌ Ошибка подключения:', error);
                setConnected(false);
                setConnectionError(error?.message || 'Ошибка подключения');
            });

            return () => {
                channel.unsubscribe();
            };

        } catch (err) {
            console.error('❌ Не удалось инициализировать Echo:', err);
            setConnectionError('Не удалось инициализировать соединение');
        }
    }, []);

    return (
        <>
            <Head title="Тест Live Trading" />
            
            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold text-gray-900">
                        Тест Live Trading
                    </h1>
                    
                    <div className="flex items-center space-x-3">
                        <button
                            onClick={sendTestTrade}
                            className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors"
                        >
                            Отправить тест
                        </button>
                        <div className="flex items-center space-x-2">
                            <div className={`h-2 w-2 rounded-full ${connected ? 'bg-green-500' : 'bg-red-500'}`}></div>
                            <span className="text-sm text-gray-600">
                                {connected ? 'Подключено' : 'Отключено'}
                            </span>
                        </div>
                        
                        {connectionError && (
                            <span className="text-sm text-red-600">
                                {connectionError}
                            </span>
                        )}
                    </div>
                </div>

                {/* Статистика */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div className="bg-white rounded-lg border border-gray-200 p-4">
                        <div className="text-sm text-gray-600">Всего сделок</div>
                        <div className="text-2xl font-semibold">{trades.length}</div>
                    </div>
                    
                    <div className="bg-white rounded-lg border border-gray-200 p-4">
                        <div className="text-sm text-gray-600">Открытых позиций</div>
                        <div className="text-2xl font-semibold">
                            {trades.filter(t => t.status === 'open').length}
                        </div>
                    </div>
                    
                    <div className="bg-white rounded-lg border border-gray-200 p-4">
                        <div className="text-sm text-gray-600">Закрытых позиций</div>
                        <div className="text-2xl font-semibold">
                            {trades.filter(t => t.status === 'closed').length}
                        </div>
                    </div>
                </div>

                {/* Список сделок */}
                <div className="bg-white rounded-lg border border-gray-200">
                    <div className="px-4 py-3 border-b border-gray-200">
                        <h3 className="text-lg font-medium">Последние сделки</h3>
                    </div>
                    
                    <div className="divide-y divide-gray-200">
                        {trades.length === 0 ? (
                            <div className="p-8 text-center text-gray-500">
                                Ожидание сделок...
                            </div>
                        ) : (
                            trades.map((trade) => (
                                <div key={trade.id} className="p-4 hover:bg-gray-50">
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center space-x-4">
                                            <div className={`h-2 w-2 rounded-full ${
                                                trade.status === 'open' ? 'bg-blue-500' : 'bg-gray-400'
                                            }`}></div>
                                            
                                            <div>
                                                <div className="font-medium">{trade.symbol}</div>
                                                <div className="text-sm text-gray-600">
                                                    {new Date(trade.entry_time).toLocaleString()}
                                                    {trade.exit_time && ` - ${new Date(trade.exit_time).toLocaleString()}`}
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div className="text-right">
                                            <div className={`font-medium ${
                                                trade.side === 'buy' ? 'text-green-600' : 'text-red-600'
                                            }`}>
                                                {trade.side.toUpperCase()} {trade.size}
                                            </div>
                                            
                                            <div className="text-sm text-gray-600">
                                                ${trade.entry_price}
                                                {trade.exit_price && ` → $${trade.exit_price}`}
                                            </div>
                                        </div>
                                        
                                        {(trade.pnl !== undefined || trade.unrealized_pnl !== undefined) && (
                                            <div className={`text-right`}>
                                                {trade.pnl !== undefined && (
                                                    <div className={`font-medium ${
                                                        trade.pnl > 0 ? 'text-green-600' : 'text-red-600'
                                                    }`}>
                                                        PnL: {trade.pnl > 0 ? '+' : ''}${trade.pnl}
                                                    </div>
                                                )}
                                                {trade.unrealized_pnl !== undefined && (
                                                    <div className="text-sm text-gray-600">
                                                        Unrealized: {trade.unrealized_pnl > 0 ? '+' : ''}${trade.unrealized_pnl}
                                                    </div>
                                                )}
                                                {trade.fee !== undefined && trade.fee > 0 && (
                                                    <div className="text-xs text-gray-500">
                                                        Fee: ${trade.fee}
                                                    </div>
                                                )}
                                            </div>
                                        )}
                                    </div>
                                </div>
                            ))
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}

TestLiveTrades.layout = (page: any) => <AppLayout children={page} />;