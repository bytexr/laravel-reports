<?php

declare(strict_types=1);

namespace ByteXR\DynamicReporter;

use Illuminate\Support\ServiceProvider;

class DynamicReporterServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/dynamic-reporter.php',
            'dynamic-reporter'
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/dynamic-reporter.php' => config_path('dynamic-reporter.php'),
        ], 'dynamic-reporter-config');
    }
}
