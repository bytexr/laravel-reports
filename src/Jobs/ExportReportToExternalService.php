<?php

declare(strict_types=1);

namespace ByteXR\DynamicReporter\Jobs;

use ByteXR\DynamicReporter\Contracts\ExternalExportDriver;
use ByteXR\DynamicReporter\Contracts\Reportable;
use ByteXR\DynamicReporter\Drivers\GoogleDriveExportDriver;
use ByteXR\DynamicReporter\Models\SavedReport;
use ByteXR\DynamicReporter\Services\ReportQueryBuilder;
use ByteXR\DynamicReporter\Services\StreamCsvExport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

class ExportReportToExternalService implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 120;

    public int $timeout = 3600;

    public int $uniqueFor = 3600;

    protected string $format;

    protected string $driver;

    protected ?string $notifyEmail;

    public function __construct(
        protected SavedReport $report,
        string $format = 'csv',
        string $driver = 'google_drive',
        ?string $notifyEmail = null,
    ) {
        $this->format = $format;
        $this->driver = $driver;
        $this->notifyEmail = $notifyEmail;
        $this->onQueue(config('dynamic-reporter.queue', 'reports'));
    }

    public function uniqueId(): string
    {
        return "export-{$this->report->id}-{$this->driver}-{$this->format}";
    }

    public function handle(ReportQueryBuilder $queryBuilder, StreamCsvExport $streamExport): void
    {
        $modelClass = $this->report->model_class;

        if (! class_exists($modelClass) || ! is_subclass_of($modelClass, Reportable::class)) {
            Log::error("Invalid model class for export: {$this->report->name}", [
                'model_class' => $modelClass,
            ]);

            return;
        }

        try {
            Log::info("Starting export for report: {$this->report->name}", [
                'format' => $this->format,
                'driver' => $this->driver,
            ]);

            $cursor = $queryBuilder->compileWithCursor(
                $modelClass,
                $this->report->filters ?? [],
                $this->report->sort ?? [],
                $this->report->columns ?? [],
                $this->report->limit,
            );

            $schema = $modelClass::getReportSchema();

            $filePath = $this->format === 'xlsx'
                ? $streamExport->exportToExcel($cursor, $schema, $this->report->columns ?? [])
                : $streamExport->exportFromCursor($cursor, $schema, $this->report->columns ?? []);

            $fileName = $this->generateFileName();

            $exportDriver = $this->resolveDriver();
            $fileUrl = $exportDriver->uploadChunked(
                $filePath,
                $fileName,
                null,
                function (int $bytesUploaded, int $totalBytes): void {
                    $percent = round(($bytesUploaded / $totalBytes) * 100, 1);
                    Log::debug("Upload progress: {$percent}%", [
                        'report' => $this->report->name,
                        'bytes_uploaded' => $bytesUploaded,
                        'total_bytes' => $totalBytes,
                    ]);
                }
            );

            if (file_exists($filePath)) {
                unlink($filePath);
            }

            $this->sendNotification($fileUrl);

            Log::info("Export completed for report: {$this->report->name}", [
                'file_url' => $fileUrl,
            ]);
        } catch (\Throwable $e) {
            Log::error("Failed to export report: {$this->report->name}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    protected function resolveDriver(): ExternalExportDriver
    {
        return match ($this->driver) {
            'google_drive' => new GoogleDriveExportDriver(),
            default => throw new \InvalidArgumentException("Unknown export driver: {$this->driver}"),
        };
    }

    protected function generateFileName(): string
    {
        $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $this->report->name);
        $extension = $this->format === 'xlsx' ? 'xlsx' : 'csv';

        return $safeName . '_' . now()->format('Y-m-d_His') . '.' . $extension;
    }

    protected function sendNotification(string $fileUrl): void
    {
        if ($this->notifyEmail === null) {
            return;
        }

        try {
            Mail::raw(
                "Your report \"{$this->report->name}\" has been exported and is available at:\n\n{$fileUrl}",
                function ($message): void {
                    $message->to($this->notifyEmail)
                        ->subject("Report Ready: {$this->report->name}");
                }
            );
        } catch (\Throwable $e) {
            Log::warning("Failed to send export notification email", [
                'report' => $this->report->name,
                'email' => $this->notifyEmail,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return [
            'export',
            'report:' . $this->report->id,
            'driver:' . $this->driver,
            'format:' . $this->format,
        ];
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("Export job failed permanently: {$this->report->name}", [
            'report_id' => $this->report->id,
            'driver' => $this->driver,
            'format' => $this->format,
            'error' => $exception->getMessage(),
        ]);
    }
}
