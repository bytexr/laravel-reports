<?php

declare(strict_types=1);

namespace ByteXR\DynamicReporter\Services;

use ByteXR\DynamicReporter\DTOs\ReportSchema;
use Generator;
use Illuminate\Support\LazyCollection;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StreamCsvExport
{
    protected string $disk;

    protected string $directory;

    public function __construct()
    {
        $this->disk = config('dynamic-reporter.export.disk', 'local');
        $this->directory = config('dynamic-reporter.export.directory', 'reports/exports');
    }

    /**
     * Export a lazy collection to CSV using streaming to avoid memory overflow.
     * Uses spatie/simple-excel's addRows method to write chunk by chunk.
     *
     * @param LazyCollection<int, mixed> $cursor
     * @param array<int, string> $selectedColumns
     * @return string The full path to the generated CSV file
     */
    public function exportFromCursor(
        LazyCollection $cursor,
        ReportSchema $schema,
        array $selectedColumns = [],
    ): string {
        $columns = empty($selectedColumns) ? $schema->getFieldNames() : $selectedColumns;
        $filename = $this->generateFilename('csv');
        $fullPath = $this->getFullPath($filename);

        $this->ensureDirectoryExists();

        $writer = SimpleExcelWriter::create($fullPath);

        $headers = $this->buildHeaders($schema, $columns);
        $headerRow = array_combine($headers, $headers);
        $writer->addRow($headerRow);

        foreach ($cursor as $row) {
            $csvRow = $this->buildRow($row, $schema, $columns);
            $rowData = array_combine($headers, $csvRow);
            $writer->addRow($rowData);
        }

        $writer->close();

        return $fullPath;
    }

    /**
     * Export to CSV by streaming directly to php://output for download.
     *
     * @param LazyCollection<int, mixed> $cursor
     * @param array<int, string> $selectedColumns
     */
    public function streamToOutput(
        LazyCollection $cursor,
        ReportSchema $schema,
        array $selectedColumns = [],
        string $filename = 'report.csv',
    ): void {
        $columns = empty($selectedColumns) ? $schema->getFieldNames() : $selectedColumns;

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');

        if ($output === false) {
            throw new \RuntimeException('Unable to open php://output for writing');
        }

        $headers = $this->buildHeaders($schema, $columns);
        fputcsv($output, $headers);

        foreach ($cursor as $row) {
            $csvRow = $this->buildRow($row, $schema, $columns);
            fputcsv($output, $csvRow);
        }

        fclose($output);
    }

    /**
     * Export to Excel format using streaming.
     *
     * @param LazyCollection<int, mixed> $cursor
     * @param array<int, string> $selectedColumns
     * @return string The full path to the generated Excel file
     */
    public function exportToExcel(
        LazyCollection $cursor,
        ReportSchema $schema,
        array $selectedColumns = [],
    ): string {
        $columns = empty($selectedColumns) ? $schema->getFieldNames() : $selectedColumns;
        $filename = $this->generateFilename('xlsx');
        $fullPath = $this->getFullPath($filename);

        $this->ensureDirectoryExists();

        $writer = SimpleExcelWriter::create($fullPath);

        $headers = $this->buildHeaders($schema, $columns);

        foreach ($cursor as $row) {
            $csvRow = $this->buildRow($row, $schema, $columns);
            $rowData = array_combine($headers, $csvRow);
            $writer->addRow($rowData);
        }

        $writer->close();

        return $fullPath;
    }

    /**
     * Create a generator that yields rows for streaming export.
     * This is useful for very large datasets where even cursor() might be too slow.
     *
     * @param LazyCollection<int, mixed> $cursor
     * @param array<int, string> $selectedColumns
     * @return Generator<int, array<string, mixed>>
     */
    public function createRowGenerator(
        LazyCollection $cursor,
        ReportSchema $schema,
        array $selectedColumns = [],
    ): Generator {
        $columns = empty($selectedColumns) ? $schema->getFieldNames() : $selectedColumns;
        $headers = $this->buildHeaders($schema, $columns);

        yield array_combine($headers, $headers);

        foreach ($cursor as $row) {
            $csvRow = $this->buildRow($row, $schema, $columns);

            yield array_combine($headers, $csvRow);
        }
    }

    /**
     * Create a StreamedResponse for downloading CSV without loading all data into memory.
     * Uses Laravel's streamDownload callback to write rows one by one.
     *
     * @param LazyCollection<int, mixed> $cursor
     * @param array<int, string> $selectedColumns
     */
    public function streamDownload(
        LazyCollection $cursor,
        ReportSchema $schema,
        array $selectedColumns = [],
        string $filename = 'report.csv',
    ): StreamedResponse {
        $columns = empty($selectedColumns) ? $schema->getFieldNames() : $selectedColumns;

        return response()->streamDownload(function () use ($cursor, $schema, $columns): void {
            $output = fopen('php://output', 'w');

            if ($output === false) {
                throw new \RuntimeException('Unable to open php://output for writing');
            }

            $headers = $this->buildHeaders($schema, $columns);
            fputcsv($output, $headers);

            foreach ($cursor as $row) {
                $csvRow = $this->buildRow($row, $schema, $columns);
                fputcsv($output, $csvRow);

                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            }

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    /**
     * Create a StreamedResponse for downloading Excel without loading all data into memory.
     *
     * @param LazyCollection<int, mixed> $cursor
     * @param array<int, string> $selectedColumns
     */
    public function streamDownloadExcel(
        LazyCollection $cursor,
        ReportSchema $schema,
        array $selectedColumns = [],
        string $filename = 'report.xlsx',
    ): StreamedResponse {
        $columns = empty($selectedColumns) ? $schema->getFieldNames() : $selectedColumns;

        $tempFile = $this->exportToExcel($cursor, $schema, $columns);

        return response()->streamDownload(function () use ($tempFile): void {
            $handle = fopen($tempFile, 'r');

            if ($handle === false) {
                throw new \RuntimeException('Unable to open temp file for reading');
            }

            while (! feof($handle)) {
                echo fread($handle, 8192);

                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            }

            fclose($handle);
            @unlink($tempFile);
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
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

    protected function generateFilename(string $extension): string
    {
        return 'report_' . now()->format('Y-m-d_His') . '_' . uniqid() . '.' . $extension;
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
}
