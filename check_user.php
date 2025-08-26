<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Users ===\n";
$users = \App\Models\User::all();
foreach ($users as $user) {
    echo sprintf("User %d: %s\n", $user->id, $user->email);
}

echo "\n=== UserExchanges ===\n";
$exchanges = \App\Models\UserExchange::with('user')->get();
foreach ($exchanges as $ex) {
    echo sprintf(
        "Exchange %d: User %d (%s) - %s - Active: %s - Last Sync: %s\n", 
        $ex->id,
        $ex->user_id, 
        $ex->user->email,
        $ex->exchange, 
        $ex->is_active ? 'Yes' : 'No',
        $ex->last_sync_at ? $ex->last_sync_at->format('Y-m-d H:i:s') : 'Never'
    );
}

echo "\n=== Looking for hellavion@gmail.com Bybit connection ===\n";
$hellavionUser = \App\Models\User::where('email', 'hellavion@gmail.com')->first();
if ($hellavionUser) {
    echo "Found user: {$hellavionUser->id} - {$hellavionUser->email}\n";
    
    $bybitExchange = \App\Models\UserExchange::where('user_id', $hellavionUser->id)
        ->where('exchange', 'bybit')
        ->where('is_active', true)
        ->first();
        
    if ($bybitExchange) {
        echo "Found active Bybit connection: Exchange ID {$bybitExchange->id}\n";
    } else {
        echo "NO ACTIVE BYBIT CONNECTION FOUND!\n";
        
        // Check if there are any Bybit connections for this user
        $anyBybit = \App\Models\UserExchange::where('user_id', $hellavionUser->id)
            ->where('exchange', 'bybit')
            ->get();
        echo "Any Bybit connections: " . $anyBybit->count() . "\n";
        foreach ($anyBybit as $ex) {
            echo "  - ID {$ex->id}: Active={$ex->is_active}\n";
        }
    }
} else {
    echo "User hellavion@gmail.com NOT FOUND!\n";
}