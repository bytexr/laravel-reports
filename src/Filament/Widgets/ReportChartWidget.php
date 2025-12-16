<?php

declare(strict_types=1);

namespace ByteXR\DynamicReporter\Filament\Widgets;

use ByteXR\DynamicReporter\Models\SavedReport;
use ByteXR\DynamicReporter\Services\ChartDataService;
use Filament\Widgets\Widget;
use Illuminate\Contracts\View\View;

class ReportChartWidget extends Widget
{
    protected static string $view = 'dynamic-reporter::filament.widgets.report-chart';

    public ?SavedReport $report = null;

    protected ChartDataService $chartDataService;

    public function mount(?SavedReport $report = null): void
    {
        $this->report = $report;
        $this->chartDataService = new ChartDataService();
    }

    public function getChartData(): array
    {
        if ($this->report === null || ! $this->report->hasChart()) {
            return ['labels' => [], 'datasets' => []];
        }

        return $this->chartDataService->generateChartData($this->report);
    }

    public function getChartOptions(): array
    {
        if ($this->report === null) {
            return [];
        }

        return $this->chartDataService->getApexChartsOptions($this->report);
    }

    public function getChartType(): string
    {
        return $this->report?->chart_type ?? SavedReport::CHART_TYPE_BAR;
    }

    public function hasChart(): bool
    {
        return $this->report !== null && $this->report->hasChart();
    }

    public static function canView(): bool
    {
        return true;
    }
}
