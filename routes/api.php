<?php

use App\Http\Controllers\Api\BybitController;
use App\Http\Controllers\Api\ExchangeController;
use App\Http\Controllers\Api\TradeController;
use App\Http\Controllers\Api\AnalysisController;
use App\Http\Controllers\Api\DashboardController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware(['auth', 'web']);

// API routes with web authentication (for SPA)
Route::middleware(['auth', 'web'])->group(function () {
    
    // Exchange Management Routes
    Route::prefix('exchanges')->group(function () {
        Route::get('/', [ExchangeController::class, 'index']); // Get all user exchanges
        Route::get('/supported', [ExchangeController::class, 'getSupportedExchanges']); // Get supported exchanges info
        Route::post('/test-connection', [ExchangeController::class, 'testConnection']); // Test connection
        Route::post('/connect', [ExchangeController::class, 'connect']); // Connect to exchange
        
        Route::get('/{exchange}', [ExchangeController::class, 'show']); // Get specific exchange info
        Route::delete('/{exchange}', [ExchangeController::class, 'disconnect']); // Disconnect from exchange
        Route::delete('/{exchange}/delete', [ExchangeController::class, 'delete']); // Delete exchange connection
        Route::put('/{exchange}/sync-settings', [ExchangeController::class, 'updateSyncSettings']); // Update sync settings
        
        // Synchronization management
        Route::post('/{exchange}/sync', [ExchangeController::class, 'sync']); // Trigger manual sync
        Route::get('/{exchange}/sync/status', [ExchangeController::class, 'syncStatus']); // Get sync status
        Route::get('/{exchange}/sync/stats', [ExchangeController::class, 'syncStats']); // Get sync statistics
    });
    
    // Trade Management Routes
    Route::prefix('trades')->group(function () {
        Route::get('/', [TradeController::class, 'index']); // Get trades with filtering
        Route::get('/stats', [TradeController::class, 'getStats']); // Get trade statistics
        Route::get('/pnl-chart', [TradeController::class, 'getPnlChart']); // Get P&L chart data
        Route::post('/sync', [TradeController::class, 'syncAll']); // Sync all exchanges
        Route::post('/sync/{exchange}', [TradeController::class, 'syncExchange']); // Sync specific exchange
        Route::get('/{tradeId}', [TradeController::class, 'show']); // Get specific trade details
    });
    
    // Analysis & Market Data Routes
    Route::prefix('analysis')->group(function () {
        Route::get('/symbols', [AnalysisController::class, 'getAvailableSymbols']); // Get available symbols
        Route::get('/market-structure', [AnalysisController::class, 'getMarketStructure']); // Get market structure data
        Route::get('/order-blocks', [AnalysisController::class, 'getOrderBlocks']); // Get order blocks
        Route::get('/fvg', [AnalysisController::class, 'getFairValueGaps']); // Get Fair Value Gaps
        Route::get('/liquidity', [AnalysisController::class, 'getLiquidityLevels']); // Get liquidity levels
        Route::get('/report', [AnalysisController::class, 'getTradeAnalysisReport']); // Get analysis report
        Route::post('/collect-market-data', [AnalysisController::class, 'collectMarketData']); // Start market data collection
    });
    
    // Dashboard Routes
    Route::prefix('dashboard')->group(function () {
        Route::get('/overview', [DashboardController::class, 'getOverview']); // Get dashboard overview
        Route::get('/metrics', [DashboardController::class, 'getMetrics']); // Get performance metrics
        Route::get('/widgets', [DashboardController::class, 'getWidgets']); // Get dashboard widgets
    });
    
    // Legacy Bybit-specific routes (for backward compatibility)
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