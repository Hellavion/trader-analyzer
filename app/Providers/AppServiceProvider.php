<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Отключаем Wave маршруты на основном сервере (8000)
        // SSE работает только на отдельном сервере (8001)
        if (app()->environment('local') && !isset($_ENV['SSE_SERVER_MODE'])) {
            config(['wave.path' => null]); // Отключаем Wave маршруты
        }
    }
}
