<?php

declare(strict_types=1);

namespace ByteXR\DynamicReporter\Enums;

enum FieldType: string
{
    case Text = 'text';
    case Number = 'number';
    case Date = 'date';
    case Datetime = 'datetime';
    case Boolean = 'boolean';
    case Money = 'money';

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Text',
            self::Number => 'Number',
            self::Date => 'Date',
            self::Datetime => 'Date & Time',
            self::Boolean => 'Boolean',
            self::Money => 'Money',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
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
