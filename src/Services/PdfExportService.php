<?php

declare(strict_types=1);

namespace ByteXR\DynamicReporter\Services;

use ByteXR\DynamicReporter\DTOs\ReportSchema;
use ByteXR\DynamicReporter\Models\SavedReport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\View;

class PdfExportService
{
    protected string $disk;

    protected string $directory;

    protected int $maxRowsPerPage = 50;

    protected int $maxTotalRows = 1000;

    public function __construct()
    {
        $this->disk = config('dynamic-reporter.export.disk', 'local');
        $this->directory = config('dynamic-reporter.export.directory', 'reports/exports');
    }

    /**
     * Generate a PDF from report data.
     * Uses strict paging to prevent memory overflow on large datasets.
     *
     * @param Collection<int, mixed> $data
     * @param array<int, string> $selectedColumns
     * @param array<string, mixed> $options
     * @return string The full path to the generated PDF file
     */
    public function generatePdf(
        Collection $data,
        ReportSchema $schema,
        array $selectedColumns = [],
        array $options = [],
    ): string {
        $this->ensureDompdfAvailable();

        $columns = empty($selectedColumns) ? $schema->getFieldNames() : $selectedColumns;
        $limitedData = $data->take($this->maxTotalRows);

        $html = $this->renderHtml($limitedData, $schema, $columns, $options);

        $filename = $this->generateFilename();
        $fullPath = $this->getFullPath($filename);

        $this->ensureDirectoryExists();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
        $pdf->setPaper($options['paper'] ?? 'a4', $options['orientation'] ?? 'landscape');
        $pdf->save($fullPath);

        return $fullPath;
    }

    /**
     * Generate a PDF and return it as a download response.
     *
     * @param Collection<int, mixed> $data
     * @param array<int, string> $selectedColumns
     * @param array<string, mixed> $options
     * @return \Illuminate\Http\Response
     */
    public function downloadPdf(
        Collection $data,
        ReportSchema $schema,
        array $selectedColumns = [],
        string $filename = 'report.pdf',
        array $options = [],
    ): \Illuminate\Http\Response {
        $this->ensureDompdfAvailable();

        $columns = empty($selectedColumns) ? $schema->getFieldNames() : $selectedColumns;
        $limitedData = $data->take($this->maxTotalRows);

        $html = $this->renderHtml($limitedData, $schema, $columns, $options);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
        $pdf->setPaper($options['paper'] ?? 'a4', $options['orientation'] ?? 'landscape');

        return $pdf->download($filename);
    }

    /**
     * Generate a PDF from a SavedReport model.
     *
     * @param array<string, mixed> $options
     * @return string The full path to the generated PDF file
     */
    public function generateFromSavedReport(
        SavedReport $report,
        Collection $data,
        array $options = [],
    ): string {
        $this->ensureDompdfAvailable();

        $modelClass = $report->model_class;

        if (! class_exists($modelClass)) {
            throw new \InvalidArgumentException("Model class [{$modelClass}] does not exist.");
        }

        $schema = $modelClass::getReportSchema();
        $columns = $report->columns ?? $schema->getFieldNames();

        $options = array_merge([
            'title' => $report->name,
            'generated_at' => now(),
            'filters' => $report->filters ?? [],
        ], $options);

        return $this->generatePdf($data, $schema, $columns, $options);
    }

    /**
     * Render the HTML for the PDF.
     *
     * @param Collection<int, mixed> $data
     * @param array<int, string> $columns
     * @param array<string, mixed> $options
     */
    protected function renderHtml(
        Collection $data,
        ReportSchema $schema,
        array $columns,
        array $options,
    ): string {
        $headers = $this->buildHeaders($schema, $columns);
        $rows = $this->buildRows($data, $schema, $columns);
        $stats = $this->calculateStats($data, $schema, $columns);

        $viewData = [
            'title' => $options['title'] ?? $schema->name,
            'description' => $options['description'] ?? $schema->description,
            'headers' => $headers,
            'rows' => $rows,
            'stats' => $stats,
            'generatedAt' => $options['generated_at'] ?? now(),
            'filters' => $options['filters'] ?? [],
            'dateRange' => $options['date_range'] ?? null,
            'logoUrl' => $options['logo_url'] ?? config('dynamic-reporter.pdf.logo_url'),
            'companyName' => $options['company_name'] ?? config('dynamic-reporter.pdf.company_name', config('app.name')),
            'totalRows' => $data->count(),
            'maxRows' => $this->maxTotalRows,
            'isLimited' => $data->count() >= $this->maxTotalRows,
        ];

        if (View::exists('dynamic-reporter::pdf.report')) {
            return View::make('dynamic-reporter::pdf.report', $viewData)->render();
        }

        return $this->renderDefaultTemplate($viewData);
    }

    /**
     * Render the default PDF template.
     *
     * @param array<string, mixed> $data
     */
    protected function renderDefaultTemplate(array $data): string
    {
        $title = htmlspecialchars((string) ($data['title'] ?? 'Report'));
        $description = htmlspecialchars((string) ($data['description'] ?? ''));
        $companyName = htmlspecialchars((string) ($data['companyName'] ?? ''));
        $generatedAt = $data['generatedAt'] instanceof \DateTimeInterface
            ? $data['generatedAt']->format('F j, Y \a\t g:i A')
            : (string) $data['generatedAt'];
        $totalRows = (int) ($data['totalRows'] ?? 0);
        $isLimited = (bool) ($data['isLimited'] ?? false);
        $maxRows = (int) ($data['maxRows'] ?? $this->maxTotalRows);

        $headerHtml = '';
        foreach ($data['headers'] ?? [] as $header) {
            $headerHtml .= '<th>' . htmlspecialchars((string) $header) . '</th>';
        }

        $rowsHtml = '';
        foreach ($data['rows'] ?? [] as $row) {
            $rowsHtml .= '<tr>';
            foreach ($row as $cell) {
                $rowsHtml .= '<td>' . htmlspecialchars((string) $cell) . '</td>';
            }
            $rowsHtml .= '</tr>';
        }

        $statsHtml = '';
        foreach ($data['stats'] ?? [] as $stat) {
            $statsHtml .= '<div class="stat-item">';
            $statsHtml .= '<span class="stat-label">' . htmlspecialchars((string) $stat['label']) . '</span>';
            $statsHtml .= '<span class="stat-value">' . htmlspecialchars((string) $stat['value']) . '</span>';
            $statsHtml .= '</div>';
        }

        $filtersHtml = '';
        foreach ($data['filters'] ?? [] as $filter) {
            if (is_array($filter) && isset($filter['field'])) {
                $filtersHtml .= '<span class="filter-badge">';
                $filtersHtml .= htmlspecialchars($filter['field'] . ' ' . ($filter['operator'] ?? '') . ' ' . ($filter['value'] ?? ''));
                $filtersHtml .= '</span>';
            }
        }

        $limitWarning = $isLimited
            ? "<p class=\"limit-warning\">Note: This report is limited to {$maxRows} rows. Total records: {$totalRows}</p>"
            : '';

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 10px;
            line-height: 1.4;
            color: #333;
            padding: 20px;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #3b82f6;
        }
        .header-left {
            flex: 1;
        }
        .header-right {
            text-align: right;
            color: #666;
            font-size: 9px;
        }
        .company-name {
            font-size: 14px;
            font-weight: bold;
            color: #1f2937;
            margin-bottom: 5px;
        }
        .report-title {
            font-size: 18px;
            font-weight: bold;
            color: #1f2937;
            margin-bottom: 5px;
        }
        .report-description {
            color: #666;
            font-size: 10px;
        }
        .meta-info {
            margin-bottom: 15px;
            padding: 10px;
            background: #f8fafc;
            border-radius: 4px;
        }
        .filters-section {
            margin-bottom: 10px;
        }
        .filters-label {
            font-weight: bold;
            margin-right: 10px;
        }
        .filter-badge {
            display: inline-block;
            padding: 2px 8px;
            background: #e0e7ff;
            color: #3730a3;
            border-radius: 3px;
            margin-right: 5px;
            font-size: 9px;
        }
        .stats-section {
            display: flex;
            gap: 20px;
            margin-bottom: 15px;
        }
        .stat-item {
            padding: 10px 15px;
            background: #f0f9ff;
            border-left: 3px solid #3b82f6;
        }
        .stat-label {
            display: block;
            font-size: 9px;
            color: #666;
            text-transform: uppercase;
        }
        .stat-value {
            display: block;
            font-size: 16px;
            font-weight: bold;
            color: #1f2937;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        th {
            background: #3b82f6;
            color: white;
            padding: 8px 10px;
            text-align: left;
            font-weight: bold;
            font-size: 9px;
            text-transform: uppercase;
        }
        td {
            padding: 6px 10px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 9px;
        }
        tr:nth-child(even) {
            background: #f9fafb;
        }
        tr:hover {
            background: #f3f4f6;
        }
        .footer {
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            color: #666;
            font-size: 8px;
        }
        .limit-warning {
            margin-top: 10px;
            padding: 8px;
            background: #fef3c7;
            color: #92400e;
            border-radius: 4px;
            font-size: 9px;
        }
        @page {
            margin: 15mm;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-left">
            <div class="company-name">{$companyName}</div>
            <h1 class="report-title">{$title}</h1>
            <p class="report-description">{$description}</p>
        </div>
        <div class="header-right">
            <p>Generated: {$generatedAt}</p>
            <p>Total Records: {$totalRows}</p>
        </div>
    </div>

    <div class="meta-info">
        <div class="filters-section">
            <span class="filters-label">Applied Filters:</span>
            {$filtersHtml}
            {$limitWarning}
        </div>
    </div>

    <div class="stats-section">
        {$statsHtml}
    </div>

    <table>
        <thead>
            <tr>{$headerHtml}</tr>
        </thead>
        <tbody>
            {$rowsHtml}
        </tbody>
    </table>

    <div class="footer">
        <p>This report was automatically generated by Dynamic Reporter</p>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Build table headers from schema fields.
     *
     * @param array<int, string> $columns
     * @return array<int, string>
     */
    protected function buildHeaders(ReportSchema $schema, array $columns): array
    {
        $headers = [];

        foreach ($columns as $columnName) {
            $field = $schema->getField($columnName);
            $headers[] = $field?->label ?? $columnName;
        }

        return $headers;
    }

    /**
     * Build table rows from data.
     *
     * @param Collection<int, mixed> $data
     * @param array<int, string> $columns
     * @return array<int, array<int, string>>
     */
    protected function buildRows(Collection $data, ReportSchema $schema, array $columns): array
    {
        $rows = [];

        foreach ($data as $row) {
            $rowData = [];

            foreach ($columns as $columnName) {
                $field = $schema->getField($columnName);

                if ($field === null) {
                    $rowData[] = '';

                    continue;
                }

                $value = $this->extractValue($row, $field->dbColumn ?? $columnName, $field->relationship);
                $rowData[] = $this->formatValue($value, $field->type);
            }

            $rows[] = $rowData;
        }

        return $rows;
    }

    /**
     * Calculate summary statistics for numeric columns.
     *
     * @param Collection<int, mixed> $data
     * @param array<int, string> $columns
     * @return array<int, array{label: string, value: string}>
     */
    protected function calculateStats(Collection $data, ReportSchema $schema, array $columns): array
    {
        $stats = [
            ['label' => 'Total Records', 'value' => (string) $data->count()],
        ];

        foreach ($columns as $columnName) {
            $field = $schema->getField($columnName);

            if ($field === null) {
                continue;
            }

            if (in_array($field->type, ['number', 'money'], true)) {
                $values = $data->map(function ($row) use ($field, $columnName) {
                    return $this->extractValue($row, $field->dbColumn ?? $columnName, $field->relationship);
                })->filter(fn ($v) => is_numeric($v));

                if ($values->isNotEmpty()) {
                    $sum = $values->sum();
                    $avg = $values->avg();

                    $formattedSum = $field->type === 'money'
                        ? '$' . number_format((float) $sum, 2)
                        : number_format((float) $sum, 2);

                    $formattedAvg = $field->type === 'money'
                        ? '$' . number_format((float) $avg, 2)
                        : number_format((float) $avg, 2);

                    $stats[] = ['label' => "Sum of {$field->label}", 'value' => $formattedSum];
                    $stats[] = ['label' => "Avg of {$field->label}", 'value' => $formattedAvg];
                }

                break;
            }
        }

        return $stats;
    }

    /**
     * Extract a value from a row, handling relationships.
     */
    protected function extractValue(mixed $row, string $column, ?string $relationship): mixed
    {
        if (is_array($row)) {
            if ($relationship !== null && isset($row[$relationship])) {
                return $row[$relationship][$column] ?? null;
            }

            return $row[$column] ?? null;
        }

        if (is_object($row)) {
            if ($relationship !== null && isset($row->{$relationship})) {
                $related = $row->{$relationship};

                return is_object($related) ? ($related->{$column} ?? null) : null;
            }

            return $row->{$column} ?? null;
        }

        return null;
    }

    /**
     * Format a value for PDF output based on field type.
     */
    protected function formatValue(mixed $value, string $type): string
    {
        if ($value === null) {
            return '-';
        }

        return match ($type) {
            'date' => $value instanceof \DateTimeInterface
                ? $value->format('M j, Y')
                : (string) $value,
            'datetime' => $value instanceof \DateTimeInterface
                ? $value->format('M j, Y g:i A')
                : (string) $value,
            'boolean' => $value ? 'Yes' : 'No',
            'money' => is_numeric($value)
                ? '$' . number_format((float) $value, 2)
                : (string) $value,
            'number' => is_numeric($value)
                ? number_format((float) $value, 2)
                : '',
            default => (string) $value,
        };
    }

    /**
     * Set the maximum rows per page for PDF generation.
     */
    public function setMaxRowsPerPage(int $maxRows): self
    {
        $this->maxRowsPerPage = max(10, $maxRows);

        return $this;
    }

    /**
     * Set the maximum total rows for PDF generation.
     */
    public function setMaxTotalRows(int $maxRows): self
    {
        $this->maxTotalRows = max(100, $maxRows);

        return $this;
    }

    protected function generateFilename(): string
    {
        return 'report_' . now()->format('Y-m-d_His') . '_' . uniqid() . '.pdf';
    }

    protected function getFullPath(string $filename): string
    {
        $basePath = \Illuminate\Support\Facades\Storage::disk($this->disk)->path('');

        return rtrim($basePath, '/') . '/' . trim($this->directory, '/') . '/' . $filename;
    }

    protected function ensureDirectoryExists(): void
    {
        $path = \Illuminate\Support\Facades\Storage::disk($this->disk)->path($this->directory);

        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }

    /**
     * Check if barryvdh/laravel-dompdf is available.
     */
    protected function ensureDompdfAvailable(): void
    {
        if (! class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            throw new \RuntimeException(
                'PDF export requires barryvdh/laravel-dompdf package. ' .
                'Install it with: composer require barryvdh/laravel-dompdf'
            );
        }
    }

    /**
     * Check if PDF export is available.
     */
    public static function isAvailable(): bool
    {
        return class_exists(\Barryvdh\DomPDF\Facade\Pdf::class);
    }
}
