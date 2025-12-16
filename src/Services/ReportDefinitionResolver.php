<?php

declare(strict_types=1);

namespace ByteXR\DynamicReporter\Services;

use ByteXR\DynamicReporter\Attributes\ReportDefinition;
use ByteXR\DynamicReporter\Concerns\HasReportDefinitions;
use ByteXR\DynamicReporter\Contracts\Reportable;
use ByteXR\DynamicReporter\Definitions\ReportDefinitionBase;
use ByteXR\DynamicReporter\DTOs\ReportSchema;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use ReflectionClass;

/**
 * Service to discover and load report definitions from attributes or traits.
 *
 * This service supports both the new attribute-based approach and the legacy
 * trait-based approach for backward compatibility.
 */
class ReportDefinitionResolver
{
    /**
     * Cache of resolved definition instances.
     *
     * @var array<class-string<Model>, ReportDefinitionBase>
     */
    protected array $definitionCache = [];

    /**
     * Cache of resolved schemas.
     *
     * @var array<class-string<Model>, ReportSchema>
     */
    protected array $schemaCache = [];

    /**
     * Resolve the report definition for a model class.
     *
     * @param class-string<Model> $modelClass
     * @throws InvalidArgumentException If no definition is found
     */
    public function resolve(string $modelClass): ReportDefinitionBase
    {
        if (isset($this->definitionCache[$modelClass])) {
            return $this->definitionCache[$modelClass];
        }

        $attribute = $this->getReportDefinitionAttribute($modelClass);

        if ($attribute !== null) {
            $definitionClass = $attribute->getDefinitionClass();
            $definition = $this->createDefinitionInstance($definitionClass, $modelClass, $attribute);
            $this->definitionCache[$modelClass] = $definition;

            return $definition;
        }

        if ($this->usesLegacyTrait($modelClass)) {
            $definition = $this->createLegacyDefinition($modelClass);
            $this->definitionCache[$modelClass] = $definition;

            return $definition;
        }

        throw new InvalidArgumentException(
            "Model [{$modelClass}] does not have a ReportDefinition attribute or implement the Reportable interface."
        );
    }

    /**
     * Get the report schema for a model class.
     *
     * @param class-string<Model> $modelClass
     */
    public function getSchema(string $modelClass): ReportSchema
    {
        if (isset($this->schemaCache[$modelClass])) {
            return $this->schemaCache[$modelClass];
        }

        $definition = $this->resolve($modelClass);
        $schema = $definition->getSchema();
        $this->schemaCache[$modelClass] = $schema;

        return $schema;
    }

    /**
     * Check if a model has a report definition (attribute or trait).
     *
     * @param class-string<Model> $modelClass
     */
    public function hasDefinition(string $modelClass): bool
    {
        return $this->getReportDefinitionAttribute($modelClass) !== null
            || $this->usesLegacyTrait($modelClass);
    }

    /**
     * Check if a model uses the attribute-based approach.
     *
     * @param class-string<Model> $modelClass
     */
    public function usesAttribute(string $modelClass): bool
    {
        return $this->getReportDefinitionAttribute($modelClass) !== null;
    }

    /**
     * Check if a model uses the legacy trait-based approach.
     *
     * @param class-string<Model> $modelClass
     */
    public function usesLegacyTrait(string $modelClass): bool
    {
        if (! class_exists($modelClass)) {
            return false;
        }

        return is_subclass_of($modelClass, Reportable::class)
            || in_array(HasReportDefinitions::class, class_uses_recursive($modelClass), true);
    }

    /**
     * Get the definition class for a model.
     *
     * @param class-string<Model> $modelClass
     * @return class-string<ReportDefinitionBase>|null
     */
    public function getDefinitionClass(string $modelClass): ?string
    {
        $attribute = $this->getReportDefinitionAttribute($modelClass);

        return $attribute?->getDefinitionClass();
    }

    /**
     * Get the ReportDefinition attribute from a model class.
     *
     * @param class-string<Model> $modelClass
     */
    public function getReportDefinitionAttribute(string $modelClass): ?ReportDefinition
    {
        if (! class_exists($modelClass)) {
            return null;
        }

        $reflection = new ReflectionClass($modelClass);
        $attributes = $reflection->getAttributes(ReportDefinition::class);

        if (empty($attributes)) {
            return null;
        }

        return $attributes[0]->newInstance();
    }

    /**
     * Create a definition instance from a definition class.
     *
     * @param class-string<ReportDefinitionBase> $definitionClass
     * @param class-string<Model> $modelClass
     */
    protected function createDefinitionInstance(
        string $definitionClass,
        string $modelClass,
        ReportDefinition $attribute
    ): ReportDefinitionBase {
        if (! class_exists($definitionClass)) {
            throw new InvalidArgumentException(
                "Report definition class [{$definitionClass}] does not exist."
            );
        }

        if (! is_subclass_of($definitionClass, ReportDefinitionBase::class)) {
            throw new InvalidArgumentException(
                "Report definition class [{$definitionClass}] must extend ReportDefinitionBase."
            );
        }

        $definition = new $definitionClass();
        $definition->setModelClass($modelClass);

        return $definition;
    }

    /**
     * Create a legacy definition wrapper for trait-based models.
     *
     * @param class-string<Model&Reportable> $modelClass
     */
    protected function createLegacyDefinition(string $modelClass): ReportDefinitionBase
    {
        return new class($modelClass) extends ReportDefinitionBase {
            /**
             * @param class-string<Model&Reportable> $modelClass
             */
            public function __construct(
                protected string $legacyModelClass
            ) {
                $this->modelClass = $modelClass;
            }

            public function fields(): array
            {
                $schema = $this->legacyModelClass::getReportSchema();

                return $schema->fields;
            }

            public function metrics(): array
            {
                $schema = $this->legacyModelClass::getReportSchema();

                return $schema->metrics;
            }

            public function label(): string
            {
                return $this->legacyModelClass::getReportDisplayName();
            }

            public function description(): string
            {
                $schema = $this->legacyModelClass::getReportSchema();

                return $schema->description;
            }

            public function getSchema(): ReportSchema
            {
                return $this->legacyModelClass::getReportSchema();
            }
        };
    }

    /**
     * Clear all caches.
     */
    public function clearCache(): void
    {
        $this->definitionCache = [];
        $this->schemaCache = [];
    }

    /**
     * Clear cache for a specific model.
     *
     * @param class-string<Model> $modelClass
     */
    public function clearCacheFor(string $modelClass): void
    {
        unset($this->definitionCache[$modelClass], $this->schemaCache[$modelClass]);
    }
}
