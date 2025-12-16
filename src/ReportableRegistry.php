<?php

declare(strict_types=1);

namespace ByteXR\DynamicReporter;

use ByteXR\DynamicReporter\Contracts\Reportable;
use ByteXR\DynamicReporter\DTOs\ReportSchema;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class ReportableRegistry
{
    protected static ?self $instance = null;

    /** @var array<class-string<Model&Reportable>, ReportSchema|null> */
    protected array $models = [];

    protected function __construct()
    {
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
     * @param class-string<Model&Reportable> $modelClass
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
     * @param array<int, class-string<Model&Reportable>> $modelClasses
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
     * @return array<int, class-string<Model&Reportable>>
     */
    public function getRegisteredModels(): array
    {
        return array_keys($this->models);
    }

    /**
     * Get the schema for a registered model.
     *
     * @param class-string<Model&Reportable> $modelClass
     */
    public function getSchema(string $modelClass): ReportSchema
    {
        if (! $this->isRegistered($modelClass)) {
            throw new InvalidArgumentException("Model [{$modelClass}] is not registered as reportable.");
        }

        if ($this->models[$modelClass] === null) {
            $this->models[$modelClass] = $modelClass::getReportSchema();
        }

        return $this->models[$modelClass];
    }

    /**
     * Get all registered models with their display names.
     *
     * @return array<class-string<Model&Reportable>, string>
     */
    public function getModelOptions(): array
    {
        $options = [];

        foreach (array_keys($this->models) as $modelClass) {
            $options[$modelClass] = $modelClass::getReportDisplayName();
        }

        return $options;
    }

    /**
     * Clear all registered models (useful for testing).
     */
    public function clear(): self
    {
        $this->models = [];

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

        if (! is_subclass_of($modelClass, Reportable::class)) {
            throw new InvalidArgumentException("Class [{$modelClass}] must implement Reportable interface.");
        }
    }
}
