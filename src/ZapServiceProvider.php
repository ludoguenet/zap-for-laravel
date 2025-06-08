<?php

namespace Zap;

use Illuminate\Support\ServiceProvider;
use Zap\Services\ConflictDetectionService;
use Zap\Services\ScheduleService;
use Zap\Services\ValidationService;

class ZapServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/zap.php', 'zap');

        // Register core services
        $this->app->singleton(ScheduleService::class);
        $this->app->singleton(ConflictDetectionService::class);
        $this->app->singleton(ValidationService::class);

        // Register the facade
        $this->app->bind('zap', ScheduleService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/zap.php' => config_path('zap.php'),
            ], 'zap-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'zap-migrations');
        }
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            'zap',
            ScheduleService::class,
            ConflictDetectionService::class,
            ValidationService::class,
        ];
    }
}
