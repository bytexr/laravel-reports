<?php

declare(strict_types=1);

namespace ByteXR\DynamicReporter\Attributes;

use Attribute;
use ByteXR\DynamicReporter\Definitions\ReportDefinitionBase;

/**
 * Attribute to mark a model as reportable and reference its report definition class.
 *
 * This attribute allows models to remain clean of reporting logic while
 * still being fully configurable for the reporting system.
 *
 * Example usage:
 * ```php
 * #[ReportDefinition(OrderReportDefinition::class)]
 * class Order extends Model
 * {
 *     // No reporting logic needed in the model
 * }
 * ```
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class ReportDefinition
{
    /**
     * @param class-string<ReportDefinitionBase> $definitionClass The report definition class
     * @param string|null $label Optional human-readable name override
     * @param string|null $description Optional description override
     * @param string|null $icon Optional icon for UI display (heroicon name)
     * @param string|null $group Optional group/category for organizing reports
     */
    public function __construct(
        public readonly string $definitionClass,
        public readonly ?string $label = null,
        public readonly ?string $description = null,
        public readonly ?string $icon = null,
        public readonly ?string $group = null,
    ) {}

    /**
     * Get the definition class.
     *
     * @return class-string<ReportDefinitionBase>
     */
    public function getDefinitionClass(): string
    {
        return $this->definitionClass;
    }

    /**
     * Check if a custom label is provided.
     */
    public function hasLabel(): bool
    {
        return $this->label !== null;
    }

    /**
     * Check if a custom description is provided.
     */
    public function hasDescription(): bool
    {
        return $this->description !== null;
    }

    /**
     * Check if an icon is provided.
     */
    public function hasIcon(): bool
    {
        return $this->icon !== null;
    }

    /**
     * Check if a group is provided.
     */
    public function hasGroup(): bool
    {
        return $this->group !== null;
    }
}
