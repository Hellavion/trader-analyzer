import React, { useState } from 'react';
import { useRealtimeTrades } from '@/hooks/use-realtime';

interface Trade {
    id: number;
    symbol: string;
    side: string;
    size: string;
    entry_price: string;
    exit_price: string;
    pnl: string;
    fee?: string;
    entry_time?: string;
    exit_time?: string;
    created_at: string;
}

interface Props {
    existingTrades: Trade[];
}

export default function TestRealtime({ existingTrades }: Props) {
    const { trades: realtimeTrades, lastTradeReceived } = useRealtimeTrades(2);
    const [allTrades, setAllTrades] = useState([...existingTrades]);

    // Объединяем существующие сделки с новыми real-time
    React.useEffect(() => {
        if (realtimeTrades.length > 0) {
            setAllTrades(prev => {
                const newTrades = realtimeTrades.filter(rt => 
                    !prev.some(existing => existing.id === rt.id)
                );
                return [...newTrades, ...prev];
            });
        }
    }, [realtimeTrades]);

    return (
        <div style={{ padding: '20px', fontFamily: 'monospace' }}>
            <h1>🔴 Real-time Trades Test</h1>
            
            <div style={{ marginBottom: '20px', padding: '10px', border: '1px solid #ccc' }}>
                <h3>📊 Database Stats:</h3>
                <p>Existing trades loaded: <strong>{existingTrades.length}</strong></p>
                <p>Real-time trades received: <strong>{realtimeTrades.length}</strong></p>
                <p>Total trades shown: <strong>{allTrades.length}</strong></p>
            </div>

            {lastTradeReceived && (
                <div style={{ marginBottom: '20px', padding: '10px', backgroundColor: '#d4edda', border: '1px solid #c3e6cb' }}>
                    <h3>🔥 Last Trade Received (Real-time):</h3>
                    <p><strong>{lastTradeReceived.symbol}</strong> {lastTradeReceived.side} {lastTradeReceived.size} @ {lastTradeReceived.exit_price}</p>
                    <p>PnL: <span style={{ color: parseFloat(lastTradeReceived.pnl) > 0 ? 'green' : 'red' }}>
                        {parseFloat(lastTradeReceived.pnl) > 0 ? '+' : ''}{lastTradeReceived.pnl}
                    </span></p>
                </div>
            )}

            <div>
                <h3>📈 All Trades ({allTrades.length}):</h3>
                {allTrades.length > 0 ? (
                    <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                        <thead>
                            <tr style={{ borderBottom: '2px solid #ccc' }}>
                                <th>ID</th><th>Symbol</th><th>Side</th><th>Size</th><th>Exit Price</th><th>PnL</th><th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            {allTrades.map((trade) => (
                                <tr key={trade.id} style={{ borderBottom: '1px solid #eee' }}>
                                    <td>{trade.id}</td>
                                    <td>{trade.symbol}</td>
                                    <td>{trade.side}</td>
                                    <td>{trade.size}</td>
                                    <td>{trade.exit_price}</td>
                                    <td style={{ color: parseFloat(trade.pnl) > 0 ? 'green' : 'red' }}>
                                        {parseFloat(trade.pnl) > 0 ? '+' : ''}{trade.pnl}
                                    </td>
                                    <td>{new Date(trade.created_at).toLocaleTimeString()}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                ) : (
                    <p>No trades found...</p>
                )}
            </div>

            <div style={{ marginTop: '20px', fontSize: '12px', color: '#666' }}>
                🟢 WebSocket Status: Connected & Listening<br/>
                👤 User ID: 2<br/>
                📡 Broadcasting: Redis + Laravel Wave<br/>
                🔄 Auto-refresh: Real-time via SSE
            </div>
        </div>
    );
}