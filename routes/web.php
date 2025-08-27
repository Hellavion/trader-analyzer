<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

// Debug routes for fixing auth issues
Route::get('/debug-auth', function () {
    return view('auth-debug');
});

Route::post('/debug-login', function (Illuminate\Http\Request $request) {
    $credentials = $request->only('email', 'password');
    
    if (Auth::attempt($credentials, true)) {
        $request->session()->regenerate();
        return redirect('/dashboard');
    }
    
    return back()->withErrors(['Invalid credentials']);
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
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

    // Manual sync endpoint
    Route::post('sync/manual', function () {
        $user = auth()->user();
        
        // Получаем активные биржи пользователя
        $activeExchanges = $user->activeExchanges()->get();
        
        if ($activeExchanges->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Нет активных подключений к биржам'
            ], 400);
        }
        
        $jobsDispatched = 0;
        
        // Запускаем быструю синхронизацию для каждой активной биржи
        foreach ($activeExchanges as $exchange) {
            if ($exchange->exchange === 'bybit') {
                \App\Jobs\QuickSyncBybitJob::dispatch($exchange);
                $jobsDispatched++;
            }
        }
        
        if ($jobsDispatched === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Нет поддерживаемых бирж для синхронизации'
            ], 400);
        }
        
        // Получаем текущие timestamps для отслеживания завершения
        $syncTimestamps = [];
        foreach ($activeExchanges as $exchange) {
            if ($exchange->exchange === 'bybit') {
                $syncTimestamps[] = [
                    'exchange_id' => $exchange->id,
                    'last_sync_before' => $exchange->last_sync_at?->timestamp ?? 0
                ];
            }
        }
        
        return response()->json([
            'success' => true,
            'message' => "Синхронизация запущена для {$jobsDispatched} бирж",
            'sync_timestamps' => $syncTimestamps
        ]);
    })->name('sync.manual');

    // Endpoint для проверки статуса синхронизации
    Route::post('sync/status', function (Illuminate\Http\Request $request) {
        $user = auth()->user();
        $syncTimestamps = $request->input('sync_timestamps', []);
        
        $completed = true;
        $updatedExchanges = 0;
        
        foreach ($syncTimestamps as $syncData) {
            $exchange = $user->exchanges()->find($syncData['exchange_id']);
            if ($exchange) {
                $currentTimestamp = $exchange->last_sync_at?->timestamp ?? 0;
                $beforeTimestamp = $syncData['last_sync_before'];
                
                if ($currentTimestamp > $beforeTimestamp) {
                    $updatedExchanges++;
                } else {
                    $completed = false; // Еще не все джобы завершились
                }
            }
        }
        
        return response()->json([
            'success' => true,
            'completed' => $completed,
            'updated_exchanges' => $updatedExchanges,
            'total_exchanges' => count($syncTimestamps)
        ]);
    })->name('sync.status');

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
