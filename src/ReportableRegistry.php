<?php

declare(strict_types=1);

namespace ByteXR\DynamicReporter;

use ByteXR\DynamicReporter\Contracts\Reportable;
use ByteXR\DynamicReporter\Definitions\ReportDefinitionBase;
use ByteXR\DynamicReporter\DTOs\ReportSchema;
use ByteXR\DynamicReporter\Services\ReportDefinitionResolver;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Registry for reportable models.
 *
 * This registry supports both the new attribute-based approach and the legacy
 * trait-based approach for backward compatibility.
 */
class ReportableRegistry
{
    protected static ?self $instance = null;

    /**
     * Registered models with their cached schemas.
     *
     * @var array<class-string<Model>, ReportSchema|null>
     */
    protected array $models = [];

    /**
     * Cached definition instances.
     *
     * @var array<class-string<Model>, ReportDefinitionBase>
     */
    protected array $definitions = [];

    /**
     * The definition resolver instance.
     */
    protected ?ReportDefinitionResolver $resolver = null;

    protected function __construct()
    {
        $this->resolver = new ReportDefinitionResolver();
    }

    /**
     * Get the singleton instance.
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Register a reportable model.
     *
     * Supports both attribute-based and trait-based models.
     *
     * @param class-string<Model> $modelClass
     */
    public function register(string $modelClass): self
    {
        $this->validateModelClass($modelClass);
        $this->models[$modelClass] = null;

        return $this;
    }

    /**
     * Register multiple reportable models.
     *
     * @param array<int, class-string<Model>> $modelClasses
     */
    public function registerMany(array $modelClasses): self
    {
        foreach ($modelClasses as $modelClass) {
            $this->register($modelClass);
        }

        return $this;
    }

    /**
     * Check if a model is registered.
     *
     * @param class-string $modelClass
     */
    public function isRegistered(string $modelClass): bool
    {
        return array_key_exists($modelClass, $this->models);
    }

    /**
     * Get all registered model classes.
     *
     * @return array<int, class-string<Model>>
     */
    public function getRegisteredModels(): array
    {
        return array_keys($this->models);
    }

    /**
     * Get the schema for a registered model.
     *
     * @param class-string<Model> $modelClass
     */
    public function getSchema(string $modelClass): ReportSchema
    {
        if (! $this->isRegistered($modelClass)) {
            throw new InvalidArgumentException("Model [{$modelClass}] is not registered as reportable.");
        }

        if ($this->models[$modelClass] === null) {
            $this->models[$modelClass] = $this->resolveSchema($modelClass);
        }

        return $this->models[$modelClass];
    }

    /**
     * Get the report definition for a model.
     *
     * @param class-string<Model> $modelClass
     */
    public function getDefinition(string $modelClass): ReportDefinitionBase
    {
        if (! $this->isRegistered($modelClass)) {
            throw new InvalidArgumentException("Model [{$modelClass}] is not registered as reportable.");
        }

        if (! isset($this->definitions[$modelClass])) {
            $this->definitions[$modelClass] = $this->resolver->resolve($modelClass);
        }

        return $this->definitions[$modelClass];
    }

    /**
     * Check if a model uses the attribute-based approach.
     *
     * @param class-string<Model> $modelClass
     */
    public function usesAttribute(string $modelClass): bool
    {
        return $this->resolver->usesAttribute($modelClass);
    }

    /**
     * Check if a model uses the legacy trait-based approach.
     *
     * @param class-string<Model> $modelClass
     */
    public function usesLegacyTrait(string $modelClass): bool
    {
        return $this->resolver->usesLegacyTrait($modelClass);
    }

    /**
     * Get all registered models with their display names.
     *
     * @return array<class-string<Model>, string>
     */
    public function getModelOptions(): array
    {
        $options = [];

        foreach (array_keys($this->models) as $modelClass) {
            $options[$modelClass] = $this->getDisplayName($modelClass);
        }

        return $options;
    }

    /**
     * Get all registered models grouped by category.
     *
     * @return array<string, array<class-string<Model>, string>>
     */
    public function getModelOptionsGrouped(): array
    {
        $grouped = [];

        foreach (array_keys($this->models) as $modelClass) {
            $group = $this->getModelGroup($modelClass) ?? 'General';
            $grouped[$group][$modelClass] = $this->getDisplayName($modelClass);
        }

        return $grouped;
    }

    /**
     * Get the display name for a model.
     *
     * @param class-string<Model> $modelClass
     */
    public function getDisplayName(string $modelClass): string
    {
        if ($this->resolver->usesAttribute($modelClass)) {
            $attribute = $this->resolver->getReportDefinitionAttribute($modelClass);

            if ($attribute !== null && $attribute->hasLabel()) {
                return $attribute->label;
            }

            $definition = $this->getDefinition($modelClass);

            return $definition->label();
        }

        if (is_subclass_of($modelClass, Reportable::class)) {
            return $modelClass::getReportDisplayName();
        }

        $className = class_basename($modelClass);

        return (string) preg_replace('/(?<!^)[A-Z]/', ' $0', $className);
    }

    /**
     * Get the group/category for a model.
     *
     * @param class-string<Model> $modelClass
     */
    public function getModelGroup(string $modelClass): ?string
    {
        if ($this->resolver->usesAttribute($modelClass)) {
            $attribute = $this->resolver->getReportDefinitionAttribute($modelClass);

            if ($attribute !== null && $attribute->hasGroup()) {
                return $attribute->group;
            }

            $definition = $this->getDefinition($modelClass);

            return $definition->group();
        }

        return null;
    }

    /**
     * Get the icon for a model.
     *
     * @param class-string<Model> $modelClass
     */
    public function getModelIcon(string $modelClass): ?string
    {
        if ($this->resolver->usesAttribute($modelClass)) {
            $attribute = $this->resolver->getReportDefinitionAttribute($modelClass);

            if ($attribute !== null && $attribute->hasIcon()) {
                return $attribute->icon;
            }

            $definition = $this->getDefinition($modelClass);

            return $definition->icon();
        }

        return null;
    }

    /**
     * Clear all registered models (useful for testing).
     */
    public function clear(): self
    {
        $this->models = [];
        $this->definitions = [];
        $this->resolver?->clearCache();

        return $this;
    }

    /**
     * Reset the singleton instance (useful for testing).
     */
    public static function reset(): void
    {
        self::$instance = null;
    }

    /**
     * Resolve the schema for a model class.
     *
     * @param class-string<Model> $modelClass
     */
    protected function resolveSchema(string $modelClass): ReportSchema
    {
        return $this->resolver->getSchema($modelClass);
    }

    /**
     * Validate that a model class can be registered.
     *
     * @param class-string $modelClass
     */
    protected function validateModelClass(string $modelClass): void
    {
        if (! class_exists($modelClass)) {
            throw new InvalidArgumentException("Class [{$modelClass}] does not exist.");
        }

        if (! is_subclass_of($modelClass, Model::class)) {
            throw new InvalidArgumentException("Class [{$modelClass}] must extend Eloquent Model.");
        }

        if (! $this->resolver->hasDefinition($modelClass)) {
            throw new InvalidArgumentException(
                "Class [{$modelClass}] must either have a #[ReportDefinition] attribute or implement the Reportable interface."
            );
        }
    }
}
