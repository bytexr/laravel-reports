<?php

declare(strict_types=1);

namespace ByteXR\DynamicReporter\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportVersion extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'saved_report_id',
        'version_number',
        'configuration',
        'change_summary',
        'changed_by',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'configuration' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<SavedReport, self>
     */
    public function savedReport(): BelongsTo
    {
        return $this->belongsTo(SavedReport::class);
    }

    /**
     * @return BelongsTo<Model, self>
     */
    public function changedByUser(): BelongsTo
    {
        $userModel = config('auth.providers.users.model', 'App\\Models\\User');

        return $this->belongsTo($userModel, 'changed_by');
    }

    /**
     * Get the configuration as a structured array.
     *
     * @return array<string, mixed>
     */
    public function getConfiguration(): array
    {
        return $this->configuration ?? [];
    }

    /**
     * Get a specific configuration value.
     */
    public function getConfigValue(string $key, mixed $default = null): mixed
    {
        return $this->configuration[$key] ?? $default;
    }

    /**
     * Get the columns from this version.
     *
     * @return array<int, string>
     */
    public function getColumns(): array
    {
        return $this->getConfigValue('columns', []);
    }

    /**
     * Get the filters from this version.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getFilters(): array
    {
        return $this->getConfigValue('filters', []);
    }

    /**
     * Get the sort configuration from this version.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getSort(): array
    {
        return $this->getConfigValue('sort', []);
    }

    /**
     * Get a human-readable summary of changes.
     */
    public function getChangeSummaryText(): string
    {
        if ($this->change_summary !== null && $this->change_summary !== '') {
            return $this->change_summary;
        }

        return 'Configuration updated';
    }

    /**
     * Get the formatted creation date.
     */
    public function getFormattedDate(): string
    {
        return $this->created_at?->format('M j, Y g:i A') ?? '';
    }

    /**
     * Get the user who made the change.
     */
    public function getChangedByName(): string
    {
        $user = $this->changedByUser;

        if ($user === null) {
            return 'System';
        }

        return $user->name ?? $user->email ?? 'Unknown User';
    }

    /**
     * Compare this version with another and return differences.
     *
     * @return array<string, array{old: mixed, new: mixed}>
     */
    public function compareWith(ReportVersion $other): array
    {
        $differences = [];
        $thisConfig = $this->getConfiguration();
        $otherConfig = $other->getConfiguration();

        $allKeys = array_unique(array_merge(array_keys($thisConfig), array_keys($otherConfig)));

        foreach ($allKeys as $key) {
            $thisValue = $thisConfig[$key] ?? null;
            $otherValue = $otherConfig[$key] ?? null;

            if ($thisValue !== $otherValue) {
                $differences[$key] = [
                    'old' => $thisValue,
                    'new' => $otherValue,
                ];
            }
        }

        return $differences;
    }
}
