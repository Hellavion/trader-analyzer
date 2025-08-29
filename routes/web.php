<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        return redirect()->route('analysis.index');
    })->name('dashboard');

    Route::get('exchanges', function () {
        $exchanges = auth()->user()->exchanges()->get()->map(function ($exchange) {
            return [
                'id' => $exchange->id,
                'exchange' => $exchange->exchange,
                'api_key' => $exchange->masked_api_key,
                'is_active' => $exchange->is_active,
                'created_at' => $exchange->created_at->toISOString(),
                'display_name' => $exchange->display_name,
            ];
        });

        return Inertia::render('exchanges/index', [
            'exchanges' => $exchanges,
        ]);
    })->name('exchanges.index');

    Route::get('trades', function () {
        return Inertia::render('trades/index');
    })->name('trades.index');

    Route::get('analysis', function () {
        return Inertia::render('analysis/index');
    })->name('analysis.index');

    // Тестовая страница для проверки real-time WebSocket
    Route::get('test-realtime', function () {
        $existingTrades = \App\Models\Trade::where('user_id', 2)
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->map(function ($trade) {
                return [
                    'id' => $trade->id,
                    'symbol' => $trade->symbol,
                    'side' => $trade->side,
                    'size' => $trade->size,
                    'entry_price' => $trade->entry_price,
                    'exit_price' => $trade->exit_price,
                    'pnl' => $trade->pnl,
                    'fee' => $trade->fee,
                    'entry_time' => $trade->entry_time?->toISOString(),
                    'exit_time' => $trade->exit_time?->toISOString(),
                    'created_at' => $trade->created_at->toISOString(),
                ];
            });

        return Inertia::render('TestRealtime', [
            'existingTrades' => $existingTrades
        ]);
    })->name('test.realtime');

    // Exchange management
    Route::post('exchanges', function (Illuminate\Http\Request $request) {
        $request->validate([
            'exchange' => 'required|in:bybit,mexc',
            'api_key' => 'required|string',
            'api_secret' => 'required|string',
        ]);

        $user = auth()->user();
        
        // Проверяем, есть ли уже подключение к этой бирже
        $existingExchange = $user->exchanges()->where('exchange', $request->exchange)->first();
        
        if ($existingExchange) {
            // Обновляем существующее подключение
            $existingExchange->setApiCredentials([
                'api_key' => $request->api_key,
                'secret' => $request->api_secret,
            ]);
            $existingExchange->is_active = true;
            $existingExchange->save();
        } else {
            // Создаем новое подключение
            $exchange = new \App\Models\UserExchange([
                'user_id' => $user->id,
                'exchange' => $request->exchange,
                'is_active' => true,
            ]);
            $exchange->setApiCredentials([
                'api_key' => $request->api_key,
                'secret' => $request->api_secret,
            ]);
            $exchange->save();
        }

        return redirect()->back()->with('success', 'Биржа успешно подключена!');
    })->name('exchanges.store');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
