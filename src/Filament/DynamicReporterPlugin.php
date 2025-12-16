<?php

declare(strict_types=1);

namespace ByteXR\DynamicReporter\Filament;

use ByteXR\DynamicReporter\Filament\Pages\CreateReport;
use ByteXR\DynamicReporter\Filament\Pages\ViewReport;
use Filament\Contracts\Plugin;
use Filament\Panel;

class DynamicReporterPlugin implements Plugin
{
    protected bool $hasCreateReportPage = true;

    protected bool $hasViewReportPage = true;

    public static function make(): static
    {
        return new static();
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }

    public function getId(): string
    {
        return 'dynamic-reporter';
    }

    public function register(Panel $panel): void
    {
        $pages = [];

        if ($this->hasCreateReportPage) {
            $pages[] = CreateReport::class;
        }

        if ($this->hasViewReportPage) {
            $pages[] = ViewReport::class;
        }

        if (! empty($pages)) {
            $panel->pages($pages);
        }
    }

    public function boot(Panel $panel): void
    {
    }

    /**
     * Disable the create report page.
     */
    public function withoutCreateReportPage(): static
    {
        $this->hasCreateReportPage = false;

        return $this;
    }

    /**
     * Enable the create report page.
     */
    public function withCreateReportPage(): static
    {
        $this->hasCreateReportPage = true;

        return $this;
    }

    /**
     * Disable the view/saved reports page.
     */
    public function withoutViewReportPage(): static
    {
        $this->hasViewReportPage = false;

        return $this;
    }

    /**
     * Enable the view/saved reports page.
     */
    public function withViewReportPage(): static
    {
        $this->hasViewReportPage = true;

        return $this;
    }
}
