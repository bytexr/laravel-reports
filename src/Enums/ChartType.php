<?php

declare(strict_types=1);

namespace ByteXR\DynamicReporter\Enums;

enum ChartType: string
{
    case Line = 'line';
    case Bar = 'bar';
    case Pie = 'pie';
    case Area = 'area';
    case Donut = 'donut';

    public function label(): string
    {
        return match ($this) {
            self::Line => 'Line Chart',
            self::Bar => 'Bar Chart',
            self::Pie => 'Pie Chart',
            self::Area => 'Area Chart',
            self::Donut => 'Donut Chart',
        };
    }

    public function apexType(): string
    {
        return $this->value;
    }

    public function isPieType(): bool
    {
        return in_array($this, [self::Pie, self::Donut], true);
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
