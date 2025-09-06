<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;

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
        $this->configureUrlGeneration();
    }

    private function configureUrlGeneration(): void
    {
        $appUrl = config('app.url');
        $parsedUrl = parse_url($appUrl);
        
        // Применяем forceRootUrl только для развертывания в подпапках
        // На продакшне где APP_URL=https://domain.com это не выполнится
        if (isset($parsedUrl['path']) && $parsedUrl['path'] !== '/') {
            URL::forceRootUrl($appUrl);
        }
    }
}
