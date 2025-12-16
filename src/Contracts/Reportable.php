<?php

declare(strict_types=1);

namespace ByteXR\DynamicReporter\Contracts;

use ByteXR\DynamicReporter\DTOs\ReportSchema;

interface Reportable
{
    /**
     * Get the report schema defining which fields are exposed for reporting.
     */
    public static function getReportSchema(): ReportSchema;

    /**
     * Get the display name for this reportable model.
     */
    public static function getReportDisplayName(): string;

    /**
     * Get a unique identifier for this reportable model.
     */
    public static function getReportIdentifier(): string;
}
