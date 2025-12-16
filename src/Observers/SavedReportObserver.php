<?php

declare(strict_types=1);

namespace ByteXR\DynamicReporter\Observers;

use ByteXR\DynamicReporter\Models\ReportVersion;
use ByteXR\DynamicReporter\Models\SavedReport;

class SavedReportObserver
{
    /**
     * Fields that should be tracked for versioning.
     *
     * @var array<int, string>
     */
    protected array $versionedFields = [
        'name',
        'model_class',
        'columns',
        'filters',
        'sort',
        'group_by',
        'limit',
        'frequency',
        'email_recipients',
        'slack_webhook_url',
        'chart_type',
        'chart_config',
    ];

    /**
     * Handle the SavedReport "created" event.
     * Create initial version (version 1) when a report is first created.
     */
    public function created(SavedReport $report): void
    {
        $this->createVersion($report, 'Initial version created');
    }

    /**
     * Handle the SavedReport "updating" event.
     * Archive the old configuration before the update is applied.
     */
    public function updating(SavedReport $report): void
    {
        if (! $this->hasVersionableChanges($report)) {
            return;
        }

        $originalConfig = $this->buildConfigurationFromOriginal($report);
        $changeSummary = $this->generateChangeSummary($report);

        $this->archiveVersion($report, $originalConfig, $changeSummary);
    }

    /**
     * Check if the report has changes to versioned fields.
     */
    protected function hasVersionableChanges(SavedReport $report): bool
    {
        foreach ($this->versionedFields as $field) {
            if ($report->isDirty($field)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build configuration array from original (pre-update) values.
     *
     * @return array<string, mixed>
     */
    protected function buildConfigurationFromOriginal(SavedReport $report): array
    {
        $config = [];

        foreach ($this->versionedFields as $field) {
            $config[$field] = $report->getOriginal($field);
        }

        return $config;
    }

    /**
     * Build configuration array from current values.
     *
     * @return array<string, mixed>
     */
    protected function buildConfigurationFromCurrent(SavedReport $report): array
    {
        $config = [];

        foreach ($this->versionedFields as $field) {
            $config[$field] = $report->getAttribute($field);
        }

        return $config;
    }

    /**
     * Generate a human-readable summary of changes.
     */
    protected function generateChangeSummary(SavedReport $report): string
    {
        $changes = [];

        foreach ($this->versionedFields as $field) {
            if ($report->isDirty($field)) {
                $changes[] = $this->formatFieldName($field);
            }
        }

        if (empty($changes)) {
            return 'Configuration updated';
        }

        if (count($changes) === 1) {
            return "Updated {$changes[0]}";
        }

        if (count($changes) <= 3) {
            return 'Updated ' . implode(', ', $changes);
        }

        return 'Updated ' . count($changes) . ' fields';
    }

    /**
     * Format a field name for display.
     */
    protected function formatFieldName(string $field): string
    {
        return match ($field) {
            'model_class' => 'data source',
            'email_recipients' => 'email recipients',
            'slack_webhook_url' => 'Slack webhook',
            'chart_type' => 'chart type',
            'chart_config' => 'chart configuration',
            'group_by' => 'grouping',
            default => str_replace('_', ' ', $field),
        };
    }

    /**
     * Archive the old configuration to the versions table.
     *
     * @param array<string, mixed> $configuration
     */
    protected function archiveVersion(
        SavedReport $report,
        array $configuration,
        string $changeSummary,
    ): void {
        $nextVersion = $this->getNextVersionNumber($report);

        ReportVersion::create([
            'saved_report_id' => $report->id,
            'version_number' => $nextVersion,
            'configuration' => $configuration,
            'change_summary' => $changeSummary,
            'changed_by' => auth()->id(),
            'created_at' => now(),
        ]);
    }

    /**
     * Create a new version entry for the current configuration.
     */
    protected function createVersion(SavedReport $report, string $changeSummary): void
    {
        $configuration = $this->buildConfigurationFromCurrent($report);
        $nextVersion = $this->getNextVersionNumber($report);

        ReportVersion::create([
            'saved_report_id' => $report->id,
            'version_number' => $nextVersion,
            'configuration' => $configuration,
            'change_summary' => $changeSummary,
            'changed_by' => auth()->id(),
            'created_at' => now(),
        ]);
    }

    /**
     * Get the next version number for a report.
     */
    protected function getNextVersionNumber(SavedReport $report): int
    {
        $maxVersion = ReportVersion::where('saved_report_id', $report->id)
            ->max('version_number');

        return ($maxVersion ?? 0) + 1;
    }
}
