<?php

declare(strict_types=1);

namespace ByteXR\DynamicReporter\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
