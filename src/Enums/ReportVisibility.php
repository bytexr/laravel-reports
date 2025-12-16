<?php

declare(strict_types=1);

namespace ByteXR\DynamicReporter\Enums;

enum ReportVisibility: string
{
    case Private = 'private';
    case Public = 'public';
    case Shared = 'shared';

    public function label(): string
    {
        return match ($this) {
            self::Private => 'Private',
            self::Public => 'Public',
            self::Shared => 'Shared',
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
