<?php

declare(strict_types=1);

namespace ByteXR\DynamicReporter\Console\Commands;

use ByteXR\DynamicReporter\Jobs\ProcessScheduledReport;
use ByteXR\DynamicReporter\Models\SavedReport;
use Cron\CronExpression;
use Illuminate\Console\Command;

class DispatchScheduledReports extends Command
{
    protected $signature = 'reports:dispatch-scheduled {--force : Force dispatch all active reports regardless of schedule}';

    protected $description = 'Dispatch scheduled report jobs based on their cron frequency';

    public function handle(): int
    {
        $reports = SavedReport::query()
            ->where('is_active', true)
            ->whereNotNull('frequency')
            ->get();

        if ($reports->isEmpty()) {
            $this->info('No scheduled reports found.');

            return self::SUCCESS;
        }

        $dispatched = 0;

        foreach ($reports as $report) {
            if ($this->option('force') || $this->shouldRun($report)) {
                ProcessScheduledReport::dispatch($report)
                    ->onQueue(config('dynamic-reporter.queue', 'reports'));

                $this->line("Dispatched: {$report->name}");
                $dispatched++;
            }
        }

        $this->info("Dispatched {$dispatched} report(s).");

        return self::SUCCESS;
    }

    protected function shouldRun(SavedReport $report): bool
    {
        if (empty($report->frequency)) {
            return false;
        }

        try {
            $cron = new CronExpression($report->frequency);

            return $cron->isDue();
        } catch (\Exception) {
            $this->warn("Invalid cron expression for report: {$report->name}");

            return false;
        }
    }
}
