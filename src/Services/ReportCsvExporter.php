<?php

declare(strict_types=1);

namespace ByteXR\DynamicReporter\Services;

use ByteXR\DynamicReporter\DTOs\ReportSchema;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class ReportCsvExporter
{
    protected string $disk;

    protected string $directory;

    public function __construct()
    {
        $this->disk = config('dynamic-reporter.export.disk', 'local');
        $this->directory = config('dynamic-reporter.export.directory', 'reports/exports');
    }

    /**
     * Export results to a CSV file.
     *
     * @param Collection<int, mixed> $results
     * @param array<int, string> $selectedColumns
     * @return string The full path to the generated CSV file
     */
    public function export(Collection $results, ReportSchema $schema, array $selectedColumns = []): string
    {
        $columns = empty($selectedColumns) ? $schema->getFieldNames() : $selectedColumns;
        $filename = $this->generateFilename();
        $fullPath = $this->getFullPath($filename);

        $this->ensureDirectoryExists();

        $handle = fopen($fullPath, 'w');

        if ($handle === false) {
            throw new \RuntimeException("Unable to create CSV file: {$fullPath}");
        }

        try {
            $headers = $this->buildHeaders($schema, $columns);
            fputcsv($handle, $headers);

            foreach ($results as $row) {
                $csvRow = $this->buildRow($row, $schema, $columns);
                fputcsv($handle, $csvRow);
            }
        } finally {
            fclose($handle);
        }

        return $fullPath;
    }

    /**
     * Build CSV headers from schema fields.
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
     * Build a CSV row from a result model.
     *
     * @param array<int, string> $columns
     * @return array<int, mixed>
     */
    protected function buildRow(mixed $row, ReportSchema $schema, array $columns): array
    {
        $csvRow = [];

        foreach ($columns as $columnName) {
            $field = $schema->getField($columnName);

            if ($field === null) {
                $csvRow[] = '';

                continue;
            }

            $value = $this->extractValue($row, $field->dbColumn ?? $columnName, $field->relationship);
            $csvRow[] = $this->formatValue($value, $field->type);
        }

        return $csvRow;
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
     * Format a value for CSV output based on field type.
     */
    protected function formatValue(mixed $value, string $type): string
    {
        if ($value === null) {
            return '';
        }

        return match ($type) {
            'date' => $value instanceof \DateTimeInterface
                ? $value->format('Y-m-d')
                : (string) $value,
            'datetime' => $value instanceof \DateTimeInterface
                ? $value->format('Y-m-d H:i:s')
                : (string) $value,
            'boolean' => $value ? 'Yes' : 'No',
            'money' => is_numeric($value)
                ? number_format((float) $value, 2)
                : (string) $value,
            'number' => is_numeric($value)
                ? (string) $value
                : '',
            default => (string) $value,
        };
    }

    protected function generateFilename(): string
    {
        return 'report_' . now()->format('Y-m-d_His') . '_' . uniqid() . '.csv';
    }

    protected function getFullPath(string $filename): string
    {
        $basePath = Storage::disk($this->disk)->path('');

        return rtrim($basePath, '/') . '/' . trim($this->directory, '/') . '/' . $filename;
    }

    protected function ensureDirectoryExists(): void
    {
        $path = Storage::disk($this->disk)->path($this->directory);

        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }

    /**
     * Schedule cleanup of a temporary CSV file.
     */
    public function scheduleCleanup(string $path, int $delayMinutes = 60): void
    {
        dispatch(function () use ($path): void {
            if (file_exists($path)) {
                unlink($path);
            }
        })->delay(now()->addMinutes($delayMinutes));
    }

    /**
     * Clean up old export files.
     */
    public function cleanupOldExports(int $olderThanHours = 24): int
    {
        $directory = Storage::disk($this->disk)->path($this->directory);
        $count = 0;

        if (! is_dir($directory)) {
            return 0;
        }

        $files = glob($directory . '/*.csv');

        if ($files === false) {
            return 0;
        }

        $threshold = now()->subHours($olderThanHours)->timestamp;

        foreach ($files as $file) {
            if (filemtime($file) < $threshold) {
                unlink($file);
                $count++;
            }
        }

        return $count;
    }
}
