<?php

declare(strict_types=1);

namespace ByteXR\DynamicReporter;

use ByteXR\DynamicReporter\Contracts\Reportable;
use Illuminate\Database\Eloquent\Model;
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

        $this->app->singleton(ReportableRegistry::class, function (): ReportableRegistry {
            return ReportableRegistry::getInstance();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/dynamic-reporter.php' => config_path('dynamic-reporter.php'),
        ], 'dynamic-reporter-config');

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'dynamic-reporter');

        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/dynamic-reporter'),
        ], 'dynamic-reporter-views');

        $this->registerReportableModels();
    }

    /**
     * Register reportable models from config.
     */
    protected function registerReportableModels(): void
    {
        /** @var array<int, class-string<Model&Reportable>> $models */
        $models = config('dynamic-reporter.models', []);

        if (! empty($models)) {
            ReportableRegistry::getInstance()->registerMany($models);
        }
    }
}
