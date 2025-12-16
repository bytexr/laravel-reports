<?php

declare(strict_types=1);

namespace ByteXR\DynamicReporter;

use ByteXR\DynamicReporter\Console\Commands\DispatchScheduledReports;
use ByteXR\DynamicReporter\Contracts\ExternalExportDriver;
use ByteXR\DynamicReporter\Contracts\Reportable;
use ByteXR\DynamicReporter\Drivers\GoogleDriveExportDriver;
use ByteXR\DynamicReporter\Livewire\ReportBuilder;
use ByteXR\DynamicReporter\Models\SavedReport;
use ByteXR\DynamicReporter\Observers\SavedReportObserver;
use ByteXR\DynamicReporter\Services\ChartDataService;
use ByteXR\DynamicReporter\Services\FilterFactory;
use ByteXR\DynamicReporter\Services\GeminiReportInterpreter;
use ByteXR\DynamicReporter\Services\PdfExportService;
use ByteXR\DynamicReporter\Services\RelationshipTreeBuilder;
use ByteXR\DynamicReporter\Services\ReportCsvExporter;
use ByteXR\DynamicReporter\Services\ReportDefinitionResolver;
use ByteXR\DynamicReporter\Services\ReportQueryBuilder;
use ByteXR\DynamicReporter\Services\StreamCsvExport;
use ByteXR\DynamicReporter\Templates\TemplateRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class DynamicReporterServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/dynamic-reporter.php',
            'dynamic-reporter'
        );

        $this->registerCoreServices();
        $this->registerExportServices();
        $this->registerDefinitionServices();
    }

    protected function registerCoreServices(): void
    {
        $this->app->singleton(ReportableRegistry::class, function (): ReportableRegistry {
            return ReportableRegistry::getInstance();
        });

        $this->app->singleton(FilterFactory::class, function (): FilterFactory {
            return new FilterFactory();
        });

        $this->app->singleton(ReportQueryBuilder::class, function ($app): ReportQueryBuilder {
            return new ReportQueryBuilder($app->make(FilterFactory::class));
        });

        $this->app->singleton(GeminiReportInterpreter::class, function ($app): GeminiReportInterpreter {
            return new GeminiReportInterpreter($app->make(FilterFactory::class));
        });

        $this->app->singleton(ChartDataService::class, function ($app): ChartDataService {
            return new ChartDataService($app->make(ReportQueryBuilder::class));
        });
    }

    protected function registerExportServices(): void
    {
        $this->app->singleton(ReportCsvExporter::class, function (): ReportCsvExporter {
            return new ReportCsvExporter();
        });

        $this->app->singleton(StreamCsvExport::class, function (): StreamCsvExport {
            return new StreamCsvExport();
        });

        $this->app->singleton(PdfExportService::class, function (): PdfExportService {
            return new PdfExportService();
        });

        $this->app->bind(ExternalExportDriver::class, function (): ExternalExportDriver {
            return new GoogleDriveExportDriver();
        });
    }

    protected function registerDefinitionServices(): void
    {
        $this->app->singleton(ReportDefinitionResolver::class, function (): ReportDefinitionResolver {
            return new ReportDefinitionResolver();
        });

        $this->app->singleton(RelationshipTreeBuilder::class, function (): RelationshipTreeBuilder {
            return new RelationshipTreeBuilder();
        });

        $this->app->singleton(TemplateRegistry::class, function (): TemplateRegistry {
            return TemplateRegistry::getInstance();
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/dynamic-reporter.php' => config_path('dynamic-reporter.php'),
        ], 'dynamic-reporter-config');

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'dynamic-reporter');

        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/dynamic-reporter'),
        ], 'dynamic-reporter-views');

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'dynamic-reporter-migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                DispatchScheduledReports::class,
            ]);
        }

        $this->registerReportableModels();
        $this->registerObservers();
        $this->registerLivewireComponents();
    }

    protected function registerLivewireComponents(): void
    {
        if (class_exists(Livewire::class)) {
            Livewire::component('dynamic-reporter::report-builder', ReportBuilder::class);
        }
    }

    protected function registerObservers(): void
    {
        $savedReportClass = static::getSavedReportModel();
        $savedReportClass::observe(SavedReportObserver::class);
    }

    protected function registerReportableModels(): void
    {
        /** @var array<int, class-string<Model&Reportable>> $models */
        $models = config('dynamic-reporter.models', []);

        if (! empty($models)) {
            ReportableRegistry::getInstance()->registerMany($models);
        }
    }

    /**
     * @return class-string<SavedReport>
     */
    public static function getSavedReportModel(): string
    {
        /** @var class-string<SavedReport> $class */
        $class = config('dynamic-reporter.model_classes.saved_report', SavedReport::class);

        return $class;
    }

    /**
     * @return class-string<\ByteXR\DynamicReporter\Models\ReportVersion>
     */
    public static function getReportVersionModel(): string
    {
        /** @var class-string<\ByteXR\DynamicReporter\Models\ReportVersion> $class */
        $class = config('dynamic-reporter.model_classes.report_version', \ByteXR\DynamicReporter\Models\ReportVersion::class);

        return $class;
    }
}
