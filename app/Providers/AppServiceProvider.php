<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\CareerjetService;
use App\Services\AnalyticsService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register CareerjetService as a singleton
        $this->app->singleton(CareerjetService::class, function ($app) {
            return new CareerjetService();
        });

        // Register AnalyticsService as a singleton
        $this->app->singleton(AnalyticsService::class, function ($app) {
            return new AnalyticsService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Configure caching
        if ($this->app->environment('production')) {
            $this->app->config->set('app.cache_timeout', 3600); // 1 hour in production
        } else {
            $this->app->config->set('app.cache_timeout', 300); // 5 minutes in development
        }
    }
}
