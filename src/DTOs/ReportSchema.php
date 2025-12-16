<?php

declare(strict_types=1);

namespace ByteXR\DynamicReporter\DTOs;

final readonly class ReportSchema
{
    /**
     * @param string $name The display name for the report schema
     * @param string $description A description of what this report schema represents
     * @param array<int, FieldDefinition> $fields The field definitions for this schema
     * @param array<int, MetricDefinition> $metrics The metric definitions for calculated fields
     * @param array<string, mixed> $meta Additional metadata for the schema
     */
    public function __construct(
        public string $name,
        public string $description,
        public array $fields,
        public array $metrics = [],
        public array $meta = [],
    ) {}

    /**
     * Create a new ReportSchema instance.
     *
     * @param array<int, FieldDefinition> $fields
     * @param array<int, MetricDefinition> $metrics
     * @param array<string, mixed> $meta
     */
    public static function make(
        string $name,
        string $description,
        array $fields,
        array $metrics = [],
        array $meta = [],
    ): self {
        return new self(
            name: $name,
            description: $description,
            fields: $fields,
            metrics: $metrics,
            meta: $meta,
        );
    }

    /**
     * Get a field definition by name.
     */
    public function getField(string $name): ?FieldDefinition
    {
        foreach ($this->fields as $field) {
            if ($field->name === $name) {
                return $field;
            }
        }

        return null;
    }

    /**
     * Check if a field exists in the schema.
     */
    public function hasField(string $name): bool
    {
        return $this->getField($name) !== null;
    }

    /**
     * Get all sortable fields.
     *
     * @return array<int, FieldDefinition>
     */
    public function getSortableFields(): array
    {
        return array_values(array_filter(
            $this->fields,
            static fn (FieldDefinition $field): bool => $field->isSortable,
        ));
    }

    /**
     * Get all filterable fields.
     *
     * @return array<int, FieldDefinition>
     */
    public function getFilterableFields(): array
    {
        return array_values(array_filter(
            $this->fields,
            static fn (FieldDefinition $field): bool => $field->isFilterable,
        ));
    }

    /**
     * Get all exportable fields.
     *
     * @return array<int, FieldDefinition>
     */
    public function getExportableFields(): array
    {
        return array_values(array_filter(
            $this->fields,
            static fn (FieldDefinition $field): bool => $field->isExportable,
        ));
    }

    /**
     * Get all field names.
     *
     * @return array<int, string>
     */
    public function getFieldNames(): array
    {
        return array_map(
            static fn (FieldDefinition $field): string => $field->name,
            $this->fields,
        );
    }

    /**
     * Get a metric definition by name.
     */
    public function getMetric(string $name): ?MetricDefinition
    {
        foreach ($this->metrics as $metric) {
            if ($metric->getName() === $name) {
                return $metric;
            }
        }

        return null;
    }

    /**
     * Check if a metric exists in the schema.
     */
    public function hasMetric(string $name): bool
    {
        return $this->getMetric($name) !== null;
    }

    /**
     * Get all metric names.
     *
     * @return array<int, string>
     */
    public function getMetricNames(): array
    {
        return array_map(
            static fn (MetricDefinition $metric): string => $metric->getName(),
            $this->metrics,
        );
    }

    /**
     * Get all exportable metrics.
     *
     * @return array<int, MetricDefinition>
     */
    public function getExportableMetrics(): array
    {
        return array_values(array_filter(
            $this->metrics,
            static fn (MetricDefinition $metric): bool => $metric->isExportable(),
        ));
    }

    /**
     * Check if a name exists as either a field or metric.
     */
    public function hasFieldOrMetric(string $name): bool
    {
        return $this->hasField($name) || $this->hasMetric($name);
    }

    /**
     * Get field or metric by name.
     */
    public function getFieldOrMetric(string $name): FieldDefinition|MetricDefinition|null
    {
        return $this->getField($name) ?? $this->getMetric($name);
    }

    /**
     * Get all field and metric names combined.
     *
     * @return array<int, string>
     */
    public function getAllSelectableNames(): array
    {
        return array_merge($this->getFieldNames(), $this->getMetricNames());
    }

    /**
     * Convert the schema to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'fields' => array_map(
                static fn (FieldDefinition $field): array => $field->toArray(),
                $this->fields,
            ),
            'metrics' => array_map(
                static fn (MetricDefinition $metric): array => $metric->toArray(),
                $this->metrics,
            ),
            'meta' => $this->meta,
        ];
    }

    /**
     * Get metrics formatted for AI context.
     *
     * @return array<int, array<string, string>>
     */
    public function getMetricsForAiContext(): array
    {
        return array_map(
            static fn (MetricDefinition $metric): array => $metric->toAiContext(),
            $this->metrics,
        );
    }
}
