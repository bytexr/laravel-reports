<?php

declare(strict_types=1);

namespace ByteXR\DynamicReporter\Templates;

use ByteXR\DynamicReporter\DTOs\ReportSchema;

/**
 * Base class for report templates.
 *
 * Templates provide pre-configured report setups that users can
 * quickly apply and customize, reducing the blank-state confusion.
 */
abstract class ReportTemplate
{
    /**
     * Get the template identifier.
     */
    abstract public function getId(): string;

    /**
     * Get the template name.
     */
    abstract public function getName(): string;

    /**
     * Get the template description.
     */
    abstract public function getDescription(): string;

    /**
     * Get the model class this template is for.
     *
     * @return class-string|null Null means it can work with any model
     */
    public function getModelClass(): ?string
    {
        return null;
    }

    /**
     * Get the template icon (heroicon name).
     */
    public function getIcon(): string
    {
        return 'heroicon-o-document-text';
    }

    /**
     * Get the template category/group.
     */
    public function getCategory(): string
    {
        return 'General';
    }

    /**
     * Get the default columns to select.
     *
     * @return array<int, string>
     */
    abstract public function getDefaultColumns(): array;

    /**
     * Get the default filters to apply.
     *
     * @return array<int, array{field: string, operator: string, value: mixed, value2?: mixed}>
     */
    public function getDefaultFilters(): array
    {
        return [];
    }

    /**
     * Get the default sorts to apply.
     *
     * @return array<int, array{field: string, direction: string}>
     */
    public function getDefaultSorts(): array
    {
        return [];
    }

    /**
     * Get the default chart configuration.
     *
     * @return array{type?: string, x_axis?: string, y_axis?: string, title?: string}|null
     */
    public function getDefaultChartConfig(): ?array
    {
        return null;
    }

    /**
     * Check if this template is applicable to a given schema.
     */
    public function isApplicableTo(ReportSchema $schema): bool
    {
        $requiredFields = $this->getDefaultColumns();

        foreach ($requiredFields as $field) {
            if (! $schema->hasField($field) && ! $schema->hasMetric($field)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get a preview description of what this template will show.
     */
    public function getPreviewDescription(): string
    {
        $columns = $this->getDefaultColumns();
        $filters = $this->getDefaultFilters();

        $parts = [];

        if (! empty($columns)) {
            $parts[] = 'Shows ' . count($columns) . ' fields';
        }

        if (! empty($filters)) {
            $parts[] = count($filters) . ' filter(s) applied';
        }

        return implode(', ', $parts);
    }

    /**
     * Convert the template to an array for UI display.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'description' => $this->getDescription(),
            'icon' => $this->getIcon(),
            'category' => $this->getCategory(),
            'modelClass' => $this->getModelClass(),
            'columns' => $this->getDefaultColumns(),
            'filters' => $this->getDefaultFilters(),
            'sorts' => $this->getDefaultSorts(),
            'chartConfig' => $this->getDefaultChartConfig(),
            'preview' => $this->getPreviewDescription(),
        ];
    }

    /**
     * Apply this template to a report builder state.
     *
     * @return array<string, mixed>
     */
    public function apply(): array
    {
        return [
            'columns' => $this->getDefaultColumns(),
            'filters' => $this->getDefaultFilters(),
            'sorts' => $this->getDefaultSorts(),
            'chartConfig' => $this->getDefaultChartConfig(),
        ];
    }
}
