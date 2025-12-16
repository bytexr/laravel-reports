<?php

declare(strict_types=1);

namespace ByteXR\DynamicReporter\Services;

use ByteXR\DynamicReporter\Contracts\Reportable;
use ByteXR\DynamicReporter\DTOs\ReportSchema;
use ByteXR\DynamicReporter\Models\SavedReport;
use Illuminate\Support\Collection;

class ChartDataService
{
    protected ReportQueryBuilder $queryBuilder;

    public function __construct(?ReportQueryBuilder $queryBuilder = null)
    {
        $this->queryBuilder = $queryBuilder ?? new ReportQueryBuilder();
    }

    /**
     * Generate chart data from a saved report.
     *
     * @return array{labels: array<int, string>, datasets: array<int, array{name: string, data: array<int, mixed>}>}
     */
    public function generateChartData(SavedReport $report): array
    {
        if (! $report->hasChart()) {
            return ['labels' => [], 'datasets' => []];
        }

        $modelClass = $report->model_class;

        if (! class_exists($modelClass) || ! is_subclass_of($modelClass, Reportable::class)) {
            return ['labels' => [], 'datasets' => []];
        }

        $results = $this->queryBuilder->compile(
            $modelClass,
            $report->filters ?? [],
            $report->sort ?? [],
            $report->columns ?? [],
        )->limit($report->limit ?? 100)->get();

        return $this->transformToChartData($results, $report);
    }

    /**
     * Transform query results to chart data format.
     *
     * @param Collection<int, mixed> $results
     * @return array{labels: array<int, string>, datasets: array<int, array{name: string, data: array<int, mixed>}>}
     */
    protected function transformToChartData(Collection $results, SavedReport $report): array
    {
        $xAxis = $report->getChartXAxis();
        $yAxis = $report->getChartYAxis();

        if ($xAxis === null || $yAxis === null) {
            return ['labels' => [], 'datasets' => []];
        }

        $chartType = $report->chart_type;

        if (in_array($chartType, [SavedReport::CHART_TYPE_PIE, SavedReport::CHART_TYPE_DONUT], true)) {
            return $this->transformToPieData($results, $xAxis, $yAxis, $report);
        }

        return $this->transformToSeriesData($results, $xAxis, $yAxis, $report);
    }

    /**
     * Transform data for pie/donut charts.
     *
     * @param Collection<int, mixed> $results
     * @return array{labels: array<int, string>, datasets: array<int, array{name: string, data: array<int, mixed>}>}
     */
    protected function transformToPieData(
        Collection $results,
        string $xAxis,
        string $yAxis,
        SavedReport $report,
    ): array {
        $labels = [];
        $data = [];

        foreach ($results as $row) {
            $label = $this->extractValue($row, $xAxis);
            $value = $this->extractValue($row, $yAxis);

            $labels[] = (string) $label;
            $data[] = is_numeric($value) ? (float) $value : 0;
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'name' => $report->getChartTitle(),
                    'data' => $data,
                ],
            ],
        ];
    }

    /**
     * Transform data for line/bar/area charts.
     *
     * @param Collection<int, mixed> $results
     * @return array{labels: array<int, string>, datasets: array<int, array{name: string, data: array<int, mixed>}>}
     */
    protected function transformToSeriesData(
        Collection $results,
        string $xAxis,
        string $yAxis,
        SavedReport $report,
    ): array {
        $labels = [];
        $data = [];

        foreach ($results as $row) {
            $label = $this->extractValue($row, $xAxis);
            $value = $this->extractValue($row, $yAxis);

            $labels[] = $this->formatLabel($label);
            $data[] = is_numeric($value) ? (float) $value : 0;
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'name' => $yAxis,
                    'data' => $data,
                ],
            ],
        ];
    }

    /**
     * Extract a value from a row.
     */
    protected function extractValue(mixed $row, string $column): mixed
    {
        if (is_array($row)) {
            return $row[$column] ?? null;
        }

        if (is_object($row)) {
            return $row->{$column} ?? null;
        }

        return null;
    }

    /**
     * Format a label for display.
     */
    protected function formatLabel(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return (string) $value;
    }

    /**
     * Suggest a chart type based on field types and grouping.
     *
     * @param array<int, string> $groupBy
     */
    public function suggestChartType(ReportSchema $schema, array $groupBy = []): string
    {
        if (empty($groupBy)) {
            return SavedReport::CHART_TYPE_BAR;
        }

        $groupField = $schema->getField($groupBy[0] ?? '');

        if ($groupField === null) {
            return SavedReport::CHART_TYPE_BAR;
        }

        return match ($groupField->type) {
            'date', 'datetime' => SavedReport::CHART_TYPE_LINE,
            'text' => count($groupBy) === 1 ? SavedReport::CHART_TYPE_PIE : SavedReport::CHART_TYPE_BAR,
            'boolean' => SavedReport::CHART_TYPE_PIE,
            default => SavedReport::CHART_TYPE_BAR,
        };
    }

    /**
     * Get ApexCharts options for a chart type.
     *
     * @return array<string, mixed>
     */
    public function getApexChartsOptions(SavedReport $report): array
    {
        $chartType = $report->chart_type ?? SavedReport::CHART_TYPE_BAR;
        $colors = $report->getChartColors();

        $baseOptions = [
            'chart' => [
                'type' => $this->mapToApexChartType($chartType),
                'height' => 350,
                'toolbar' => [
                    'show' => true,
                ],
            ],
            'colors' => $colors,
            'title' => [
                'text' => $report->getChartTitle(),
                'align' => 'center',
            ],
            'dataLabels' => [
                'enabled' => in_array($chartType, [SavedReport::CHART_TYPE_PIE, SavedReport::CHART_TYPE_DONUT], true),
            ],
        ];

        if (in_array($chartType, [SavedReport::CHART_TYPE_PIE, SavedReport::CHART_TYPE_DONUT], true)) {
            $baseOptions['legend'] = [
                'position' => 'bottom',
            ];
        } else {
            $baseOptions['xaxis'] = [
                'title' => [
                    'text' => $report->getChartXAxis(),
                ],
            ];
            $baseOptions['yaxis'] = [
                'title' => [
                    'text' => $report->getChartYAxis(),
                ],
            ];
            $baseOptions['stroke'] = [
                'curve' => 'smooth',
            ];
        }

        return $baseOptions;
    }

    /**
     * Map internal chart type to ApexCharts type.
     */
    protected function mapToApexChartType(string $chartType): string
    {
        return match ($chartType) {
            SavedReport::CHART_TYPE_LINE => 'line',
            SavedReport::CHART_TYPE_BAR => 'bar',
            SavedReport::CHART_TYPE_PIE => 'pie',
            SavedReport::CHART_TYPE_AREA => 'area',
            SavedReport::CHART_TYPE_DONUT => 'donut',
            default => 'bar',
        };
    }
}
