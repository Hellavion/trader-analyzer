<?php

use App\Http\Controllers\Api\BybitController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function () {
    
    // Bybit API routes
    Route::prefix('bybit')->group(function () {
        // Connection management
        Route::post('test-connection', [BybitController::class, 'testConnection']);
        Route::post('connect', [BybitController::class, 'connectExchange']);
        Route::delete('disconnect', [BybitController::class, 'disconnectExchange']);
        Route::get('status', [BybitController::class, 'getConnectionStatus']);
        
        // Data synchronization
        Route::post('sync-trades', [BybitController::class, 'syncTrades']);
        Route::post('collect-market-data', [BybitController::class, 'collectMarketData']);
        
        // Account data
        Route::get('wallet-balance', [BybitController::class, 'getWalletBalance']);
        Route::get('positions', [BybitController::class, 'getPositions']);
        
        // Settings
        Route::put('sync-settings', [BybitController::class, 'updateSyncSettings']);
    });
    
});