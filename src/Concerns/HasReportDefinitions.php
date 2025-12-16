<?php

declare(strict_types=1);

namespace ByteXR\DynamicReporter\Concerns;

use ByteXR\DynamicReporter\DTOs\FieldDefinition;
use ByteXR\DynamicReporter\DTOs\ReportSchema;
use ByteXR\DynamicReporter\Support\Field;

trait HasReportDefinitions
{
    /**
     * Get the report schema for this model.
     * Override this method in your model to define the reportable fields.
     *
     * @return array<int, Field|FieldDefinition>
     */
    abstract protected static function reportFields(): array;

    /**
     * Get the report schema defining which fields are exposed for reporting.
     */
    public static function getReportSchema(): ReportSchema
    {
        $fields = array_map(
            static function (Field|FieldDefinition $field): FieldDefinition {
                if ($field instanceof Field) {
                    return $field->build();
                }

                return $field;
            },
            static::reportFields(),
        );

        return new ReportSchema(
            name: static::getReportDisplayName(),
            description: static::getReportDescription(),
            fields: $fields,
        );
    }

    /**
     * Get the display name for this reportable model.
     * Override this method to customize the display name.
     */
    public static function getReportDisplayName(): string
    {
        $className = class_basename(static::class);

        return (string) preg_replace('/(?<!^)[A-Z]/', ' $0', $className);
    }

    /**
     * Get a unique identifier for this reportable model.
     * Override this method to customize the identifier.
     */
    public static function getReportIdentifier(): string
    {
        return static::class;
    }

    /**
     * Get the description for this reportable model.
     * Override this method to customize the description.
     */
    protected static function getReportDescription(): string
    {
        return 'Report data from ' . static::getReportDisplayName();
    }
}
