import { useEffect, useState } from 'react';
import '../echo';

/**
 * Hook для работы с real-time обновлениями через Laravel Wave
 */
export function useRealtime() {
    const [connected, setConnected] = useState(false);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        try {
            // Тестовое подключение к приватному каналу
            const channel = window.Echo.private('App.Models.User.1');
            
            channel.subscribed(() => {
                console.log('✅ Laravel Wave connected successfully');
                setConnected(true);
                setError(null);
            });

            channel.error((error: any) => {
                console.error('❌ Laravel Wave connection error:', error);
                setConnected(false);
                setError(error?.message || 'Connection failed');
            });

        } catch (err) {
            console.error('❌ Failed to initialize Laravel Wave:', err);
            setError('Failed to initialize connection');
        }

    }, []);

    return { connected, error };
}

/**
 * Hook для получения real-time обновлений сделок
 */
export function useRealtimeTrades(userId: number) {
    const [trades, setTrades] = useState<any[]>([]);
    const [lastTradeReceived, setLastTradeReceived] = useState<any>(null);

    useEffect(() => {
        const channel = window.Echo.private(`user.${userId}.trades`);
        
        channel.listen('TradeExecuted', (event: any) => {
            console.log('🔥 New trade received via WebSocket:', event);
            
            // Добавляем новую сделку в начало списка
            setTrades(prev => [event.trade, ...prev]);
            setLastTradeReceived(event.trade);
            
            // Показываем уведомление (опционально)
            if (window.Notification && Notification.permission === 'granted') {
                new Notification(`Trade Closed: ${event.trade.symbol}`, {
                    body: `PnL: ${event.trade.pnl > 0 ? '+' : ''}${event.trade.pnl}`,
                    icon: event.trade.pnl > 0 ? '/profit-icon.png' : '/loss-icon.png'
                });
            }
        });

        channel.listen('TradeUpdated', (event: any) => {
            console.log('📊 Trade updated:', event);
            setTrades(prev => prev.map(trade => 
                trade.id === event.trade.id ? event.trade : trade
            ));
        });

        // Обработка ошибок подключения
        channel.error((error: any) => {
            console.error('❌ Real-time trades channel error:', error);
        });

        return () => {
            channel.unsubscribe();
        };
    }, [userId]);

    return { 
        trades, 
        lastTradeReceived,
        // Метод для ручного обновления списка (fallback)
        refreshTrades: () => setTrades([])
    };
}

/**
 * Hook для получения real-time обновлений баланса
 */
export function useRealtimeWallet(userId: number) {
    const [walletData, setWalletData] = useState<any>(null);

    useEffect(() => {
        const channel = window.Echo.private(`user.${userId}.wallet`);
        
        channel.listen('WalletUpdated', (event: any) => {
            console.log('💰 Wallet updated:', event);
            setWalletData(event.wallet);
        });

        return () => {
            channel.unsubscribe();
        };
    }, [userId]);

    return walletData;
}