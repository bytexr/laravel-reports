<?php

declare(strict_types=1);

namespace ByteXR\DynamicReporter\Enums;

enum ReportExportFormat: string
{
    case Csv = 'csv';
    case Excel = 'excel';
    case Pdf = 'pdf';
    case GoogleDrive = 'google_drive';

    public function label(): string
    {
        return match ($this) {
            self::Csv => 'CSV',
            self::Excel => 'Excel',
            self::Pdf => 'PDF',
            self::GoogleDrive => 'Google Drive',
        };
    }

    public function extension(): string
    {
        return match ($this) {
            self::Csv => 'csv',
            self::Excel => 'xlsx',
            self::Pdf => 'pdf',
            self::GoogleDrive => 'csv',
        };
    }

    public function mimeType(): string
    {
        return match ($this) {
            self::Csv => 'text/csv',
            self::Excel => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            self::Pdf => 'application/pdf',
            self::GoogleDrive => 'text/csv',
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
