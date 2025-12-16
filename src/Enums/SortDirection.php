<?php

declare(strict_types=1);

namespace ByteXR\DynamicReporter\Enums;

enum SortDirection: string
{
    case Asc = 'asc';
    case Desc = 'desc';

    public function label(): string
    {
        return match ($this) {
            self::Asc => 'Ascending',
            self::Desc => 'Descending',
        };
    }

    public static function options(): array
    {
        return [
            self::Asc->value => self::Asc->label(),
            self::Desc->value => self::Desc->label(),
        ];
    }
}
