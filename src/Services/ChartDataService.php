<?php

declare(strict_types=1);

namespace ByteXR\DynamicReporter\Services;

use ByteXR\DynamicReporter\Contracts\Reportable;
use ByteXR\DynamicReporter\DTOs\ReportSchema;
use ByteXR\DynamicReporter\Enums\ChartType;
use ByteXR\DynamicReporter\Enums\FieldType;
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

        $chartType = $report->getChartTypeEnum();

        if ($chartType !== null && $chartType->isPieType()) {
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
     * @param array<int, string> $groupBy
     */
    public function suggestChartType(ReportSchema $schema, array $groupBy = []): ChartType
    {
        if (empty($groupBy)) {
            return ChartType::Bar;
        }

        $groupField = $schema->getField($groupBy[0] ?? '');

        if ($groupField === null) {
            return ChartType::Bar;
        }

        return match ($groupField->type) {
            FieldType::Date, FieldType::Datetime => ChartType::Line,
            FieldType::Text => count($groupBy) === 1 ? ChartType::Pie : ChartType::Bar,
            FieldType::Boolean => ChartType::Pie,
            default => ChartType::Bar,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function getApexChartsOptions(SavedReport $report): array
    {
        $chartType = $report->getChartTypeEnum() ?? ChartType::Bar;
        $colors = $report->getChartColors();

        $baseOptions = [
            'chart' => [
                'type' => $chartType->apexType(),
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
                'enabled' => $chartType->isPieType(),
            ],
        ];

        if ($chartType->isPieType()) {
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
}
