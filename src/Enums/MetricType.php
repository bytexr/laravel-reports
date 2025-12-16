<?php

declare(strict_types=1);

namespace ByteXR\DynamicReporter\Enums;

enum MetricType: string
{
    case Number = 'number';
    case Percentage = 'percentage';
    case Currency = 'currency';
    case Count = 'count';
    case Average = 'average';

    public function label(): string
    {
        return match ($this) {
            self::Number => 'Number',
            self::Percentage => 'Percentage',
            self::Currency => 'Currency',
            self::Count => 'Count',
            self::Average => 'Average',
        };
    }

    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
