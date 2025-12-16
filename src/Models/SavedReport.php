<?php

declare(strict_types=1);

namespace ByteXR\DynamicReporter\Models;

use ByteXR\DynamicReporter\Enums\ChartType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SavedReport extends Model
{

    protected $fillable = [
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
        'is_active',
        'user_id',
        'chart_type',
        'chart_config',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'columns' => 'array',
            'filters' => 'array',
            'sort' => 'array',
            'group_by' => 'array',
            'email_recipients' => 'array',
            'chart_config' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Model, self>
     */
    public function user(): BelongsTo
    {
        $userModel = config('auth.providers.users.model', 'App\\Models\\User');

        return $this->belongsTo($userModel);
    }

    public function hasSchedule(): bool
    {
        return $this->frequency !== null && $this->frequency !== '';
    }

    public function hasEmailRecipients(): bool
    {
        return ! empty($this->email_recipients);
    }

    public function hasSlackWebhook(): bool
    {
        return $this->slack_webhook_url !== null && $this->slack_webhook_url !== '';
    }

    /**
     * @return array<int, string>
     */
    public function getNotificationChannels(): array
    {
        $channels = [];

        if ($this->hasEmailRecipients()) {
            $channels[] = 'mail';
        }

        if ($this->hasSlackWebhook() && self::isSlackConfigured()) {
            $channels[] = 'slack';
        }

        return $channels;
    }

    public static function isSlackConfigured(): bool
    {
        return config('services.slack') !== null
            || config('logging.channels.slack') !== null;
    }

    public function hasChart(): bool
    {
        return $this->chart_type !== null && $this->chart_type !== '';
    }

    public function getChartXAxis(): ?string
    {
        return $this->chart_config['x_axis'] ?? null;
    }

    public function getChartYAxis(): ?string
    {
        return $this->chart_config['y_axis'] ?? null;
    }

    public function getChartTitle(): string
    {
        return $this->chart_config['title'] ?? $this->name;
    }

    public function getChartColors(): array
    {
        return $this->chart_config['colors'] ?? ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6'];
    }

    /**
     * @return array<string, string>
     */
    public static function getChartTypeOptions(): array
    {
        return ChartType::options();
    }

    public function getChartTypeEnum(): ?ChartType
    {
        if ($this->chart_type === null) {
            return null;
        }

        return ChartType::tryFrom($this->chart_type);
    }

    /**
     * @return HasMany<ReportVersion>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(ReportVersion::class)->orderByDesc('version_number');
    }

    /**
     * Get the latest version number.
     */
    public function getLatestVersionNumber(): int
    {
        return $this->versions()->max('version_number') ?? 0;
    }

    /**
     * Get a specific version by number.
     */
    public function getVersion(int $versionNumber): ?ReportVersion
    {
        return $this->versions()->where('version_number', $versionNumber)->first();
    }

    /**
     * Restore the report to a specific version.
     */
    public function restoreToVersion(int $versionNumber): bool
    {
        $version = $this->getVersion($versionNumber);

        if ($version === null) {
            return false;
        }

        $configuration = $version->getConfiguration();

        foreach ($configuration as $field => $value) {
            if (in_array($field, $this->fillable, true)) {
                $this->setAttribute($field, $value);
            }
        }

        return $this->save();
    }

    /**
     * Restore the report from a ReportVersion instance.
     */
    public function restoreFromVersion(ReportVersion $version): bool
    {
        return $this->restoreToVersion($version->version_number);
    }
}
