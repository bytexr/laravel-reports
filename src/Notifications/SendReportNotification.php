<?php

declare(strict_types=1);

namespace ByteXR\DynamicReporter\Notifications;

use ByteXR\DynamicReporter\Models\SavedReport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\SlackMessage;
use Illuminate\Notifications\Notification;

class SendReportNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected SavedReport $report,
        protected string $csvPath,
        protected int $rowCount,
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['mail'];

        if ($this->report->hasSlackWebhook() && SavedReport::isSlackConfigured()) {
            $channels[] = 'slack';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage())
            ->subject("Report Ready: {$this->report->name}")
            ->greeting("Hello!")
            ->line("Your scheduled report \"{$this->report->name}\" has been generated.")
            ->line("The report contains {$this->rowCount} rows of data.")
            ->line("Please find the CSV file attached to this email.");

        if (file_exists($this->csvPath)) {
            $message->attach($this->csvPath, [
                'as' => $this->getFileName(),
                'mime' => 'text/csv',
            ]);
        }

        return $message;
    }

    public function toSlack(object $notifiable): SlackMessage
    {
        return (new SlackMessage())
            ->success()
            ->content("Report Ready: {$this->report->name}")
            ->attachment(function ($attachment): void {
                $attachment
                    ->title($this->report->name)
                    ->fields([
                        'Rows' => (string) $this->rowCount,
                        'Generated At' => now()->toDateTimeString(),
                        'Model' => class_basename($this->report->model_class),
                    ]);
            });
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'report_id' => $this->report->id,
            'report_name' => $this->report->name,
            'csv_path' => $this->csvPath,
            'row_count' => $this->rowCount,
        ];
    }

    protected function getFileName(): string
    {
        $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $this->report->name);

        return $safeName . '_' . now()->format('Y-m-d_His') . '.csv';
    }

    public function routeNotificationForSlack(object $notifiable): ?string
    {
        return $this->report->slack_webhook_url;
    }
}
