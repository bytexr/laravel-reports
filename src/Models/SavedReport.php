<?php

declare(strict_types=1);

namespace ByteXR\DynamicReporter\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedReport extends Model
{
    public const CHART_TYPE_LINE = 'line';

    public const CHART_TYPE_BAR = 'bar';

    public const CHART_TYPE_PIE = 'pie';

    public const CHART_TYPE_AREA = 'area';

    public const CHART_TYPE_DONUT = 'donut';

    public const CHART_TYPES = [
        self::CHART_TYPE_LINE => 'Line Chart',
        self::CHART_TYPE_BAR => 'Bar Chart',
        self::CHART_TYPE_PIE => 'Pie Chart',
        self::CHART_TYPE_AREA => 'Area Chart',
        self::CHART_TYPE_DONUT => 'Donut Chart',
    ];

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
        return self::CHART_TYPES;
    }
}
