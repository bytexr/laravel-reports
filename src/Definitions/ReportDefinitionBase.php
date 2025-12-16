<?php

declare(strict_types=1);

namespace ByteXR\DynamicReporter\Definitions;

use ByteXR\DynamicReporter\DTOs\FieldDefinition;
use ByteXR\DynamicReporter\DTOs\MetricDefinition;
use ByteXR\DynamicReporter\DTOs\RelationshipDefinition;
use ByteXR\DynamicReporter\DTOs\ReportSchema;
use ByteXR\DynamicReporter\Support\Field;
use Illuminate\Database\Eloquent\Model;

/**
 * Base class for all report definitions.
 *
 * Report definitions encapsulate all reporting logic for a model,
 * keeping the model itself clean and focused on its core responsibilities.
 *
 * Example usage:
 * ```php
 * class OrderReportDefinition extends ReportDefinitionBase
 * {
 *     public function fields(): array
 *     {
 *         return [
 *             Field::make('id')->label('Order ID')->number(),
 *             Field::make('total')->label('Total')->money(),
 *         ];
 *     }
 * }
 * ```
 */
abstract class ReportDefinitionBase
{
    /**
     * The model class this definition is for.
     *
     * @var class-string<Model>|null
     */
    protected ?string $modelClass = null;

    /**
     * Cached schema instance.
     */
    protected ?ReportSchema $cachedSchema = null;

    /**
     * Define the available fields for reporting.
     *
     * @return array<int, Field|FieldDefinition>
     */
    abstract public function fields(): array;

    /**
     * Define the available relationships for reporting.
     * Override this method to expose related data.
     *
     * @return array<int, RelationshipDefinition>
     */
    public function relationships(): array
    {
        return [];
    }

    /**
     * Define custom business metrics.
     * Override this method to add calculated fields.
     *
     * @return array<int, MetricDefinition>
     */
    public function metrics(): array
    {
        return [];
    }

    /**
     * Define default filters that should be applied.
     * Override this method to set default filtering behavior.
     *
     * @return array<int, array{field: string, operator: string, value: mixed, value2?: mixed}>
     */
    public function defaultFilters(): array
    {
        return [];
    }

    /**
     * Define available filter presets for users.
     * Override this method to provide quick filter options.
     *
     * @return array<string, array{label: string, filters: array}>
     */
    public function filterPresets(): array
    {
        return [];
    }

    /**
     * Define grouping options.
     * Override this method to allow data grouping.
     *
     * @return array<int, array{field: string, label: string}>
     */
    public function groupings(): array
    {
        return [];
    }

    /**
     * Define aggregation options.
     * Override this method to allow data aggregation.
     *
     * @return array<int, array{field: string, function: string, label: string}>
     */
    public function aggregations(): array
    {
        return [];
    }

    /**
     * Define default sorting.
     * Override this method to set default sort order.
     *
     * @return array<int, array{field: string, direction: string}>
     */
    public function defaultSort(): array
    {
        return [];
    }

    /**
     * Get the human-readable label for this report.
     * Override this method to customize the display name.
     */
    public function label(): string
    {
        if ($this->modelClass !== null) {
            $className = class_basename($this->modelClass);

            return (string) preg_replace('/(?<!^)[A-Z]/', ' $0', $className);
        }

        $className = class_basename(static::class);
        $className = str_replace(['ReportDefinition', 'Definition'], '', $className);

        return (string) preg_replace('/(?<!^)[A-Z]/', ' $0', $className);
    }

    /**
     * Get the description for this report.
     * Override this method to customize the description.
     */
    public function description(): string
    {
        return 'Report data from ' . $this->label();
    }

    /**
     * Get the icon for this report (heroicon name).
     * Override this method to customize the icon.
     */
    public function icon(): ?string
    {
        return null;
    }

    /**
     * Get the group/category for this report.
     * Override this method to organize reports into categories.
     */
    public function group(): ?string
    {
        return null;
    }

    /**
     * Get field categories for UI grouping.
     * Override this method to customize how fields are grouped in the UI.
     *
     * @return array<string, array{label: string, description?: string, fields: array<string>}>
     */
    public function fieldCategories(): array
    {
        return [];
    }

    /**
     * Get the maximum allowed relationship depth.
     * Override this method to customize depth limits.
     */
    public function maxRelationshipDepth(): int
    {
        return 3;
    }

    /**
     * Check if a field should be selected by default.
     * Override this method to customize default selections.
     */
    public function isDefaultField(string $fieldName): bool
    {
        return false;
    }

    /**
     * Get commonly used fields that should be highlighted.
     * Override this method to highlight important fields.
     *
     * @return array<int, string>
     */
    public function commonFields(): array
    {
        return [];
    }

    /**
     * Set the model class this definition is for.
     *
     * @param class-string<Model> $modelClass
     */
    public function setModelClass(string $modelClass): static
    {
        $this->modelClass = $modelClass;
        $this->cachedSchema = null;

        return $this;
    }

    /**
     * Get the model class this definition is for.
     *
     * @return class-string<Model>|null
     */
    public function getModelClass(): ?string
    {
        return $this->modelClass;
    }

    /**
     * Build and return the report schema.
     */
    public function getSchema(): ReportSchema
    {
        if ($this->cachedSchema !== null) {
            return $this->cachedSchema;
        }

        $fields = array_map(
            static function (Field|FieldDefinition $field): FieldDefinition {
                if ($field instanceof Field) {
                    return $field->build();
                }

                return $field;
            },
            $this->fields(),
        );

        $this->cachedSchema = new ReportSchema(
            name: $this->label(),
            description: $this->description(),
            fields: $fields,
            metrics: $this->metrics(),
            meta: $this->buildMeta(),
        );

        return $this->cachedSchema;
    }

    /**
     * Build metadata for the schema.
     *
     * @return array<string, mixed>
     */
    protected function buildMeta(): array
    {
        return [
            'icon' => $this->icon(),
            'group' => $this->group(),
            'relationships' => $this->relationships(),
            'defaultFilters' => $this->defaultFilters(),
            'filterPresets' => $this->filterPresets(),
            'groupings' => $this->groupings(),
            'aggregations' => $this->aggregations(),
            'defaultSort' => $this->defaultSort(),
            'fieldCategories' => $this->fieldCategories(),
            'maxRelationshipDepth' => $this->maxRelationshipDepth(),
            'commonFields' => $this->commonFields(),
        ];
    }

    /**
     * Clear the cached schema.
     */
    public function clearCache(): static
    {
        $this->cachedSchema = null;

        return $this;
    }
}
