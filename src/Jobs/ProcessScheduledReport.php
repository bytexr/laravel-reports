<?php

declare(strict_types=1);

namespace ByteXR\DynamicReporter\Jobs;

use ByteXR\DynamicReporter\Contracts\Reportable;
use ByteXR\DynamicReporter\Models\SavedReport;
use ByteXR\DynamicReporter\Notifications\SendReportNotification;
use ByteXR\DynamicReporter\Services\ReportCsvExporter;
use ByteXR\DynamicReporter\Services\ReportQueryBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class ProcessScheduledReport implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        protected SavedReport $report,
    ) {
        $this->onQueue(config('dynamic-reporter.queue', 'reports'));
    }

    public function handle(ReportQueryBuilder $queryBuilder, ReportCsvExporter $exporter): void
    {
        if (! $this->report->is_active) {
            Log::info("Skipping inactive report: {$this->report->name}");

            return;
        }

        $modelClass = $this->report->model_class;

        if (! class_exists($modelClass) || ! is_subclass_of($modelClass, Reportable::class)) {
            Log::error("Invalid model class for report: {$this->report->name}", [
                'model_class' => $modelClass,
            ]);

            return;
        }

        try {
            $query = $queryBuilder->compile(
                $modelClass,
                $this->report->filters ?? [],
                $this->report->sort ?? [],
                $this->report->columns ?? [],
                $this->report->group_by ?? [],
            );

            if ($this->report->limit !== null) {
                $query->limit($this->report->limit);
            }

            $results = $query->get();

            $schema = $modelClass::getReportSchema();
            $csvPath = $exporter->export($results, $schema, $this->report->columns ?? []);

            $this->sendNotifications($csvPath, $results->count());

            $exporter->scheduleCleanup($csvPath);

            Log::info("Successfully processed scheduled report: {$this->report->name}", [
                'row_count' => $results->count(),
            ]);
        } catch (\Throwable $e) {
            Log::error("Failed to process scheduled report: {$this->report->name}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    protected function sendNotifications(string $csvPath, int $rowCount): void
    {
        $notification = new SendReportNotification($this->report, $csvPath, $rowCount);

        if ($this->report->hasEmailRecipients()) {
            $recipients = $this->report->email_recipients ?? [];

            Notification::route('mail', $recipients)
                ->notify($notification);
        }

        if ($this->report->hasSlackWebhook() && SavedReport::isSlackConfigured()) {
            Notification::route('slack', $this->report->slack_webhook_url)
                ->notify($notification);
        }
    }

    public function tags(): array
    {
        return [
            'report',
            'report:' . $this->report->id,
            'model:' . class_basename($this->report->model_class),
        ];
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("Scheduled report job failed permanently: {$this->report->name}", [
            'report_id' => $this->report->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
