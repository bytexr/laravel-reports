<?php

declare(strict_types=1);

namespace ByteXR\DynamicReporter\Templates;

use ByteXR\DynamicReporter\DTOs\ReportSchema;
use InvalidArgumentException;

/**
 * Registry for report templates.
 *
 * This registry manages available report templates and provides
 * methods to discover and apply them.
 */
class TemplateRegistry
{
    protected static ?self $instance = null;

    /**
     * Registered templates.
     *
     * @var array<string, ReportTemplate>
     */
    protected array $templates = [];

    protected function __construct()
    {
        $this->registerBuiltInTemplates();
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
     * Register a template.
     */
    public function register(ReportTemplate $template): self
    {
        $this->templates[$template->getId()] = $template;

        return $this;
    }

    /**
     * Register multiple templates.
     *
     * @param array<int, ReportTemplate> $templates
     */
    public function registerMany(array $templates): self
    {
        foreach ($templates as $template) {
            $this->register($template);
        }

        return $this;
    }

    /**
     * Get a template by ID.
     */
    public function get(string $id): ReportTemplate
    {
        if (! isset($this->templates[$id])) {
            throw new InvalidArgumentException("Template [{$id}] is not registered.");
        }

        return $this->templates[$id];
    }

    /**
     * Check if a template exists.
     */
    public function has(string $id): bool
    {
        return isset($this->templates[$id]);
    }

    /**
     * Get all registered templates.
     *
     * @return array<string, ReportTemplate>
     */
    public function all(): array
    {
        return $this->templates;
    }

    /**
     * Get templates applicable to a schema.
     *
     * @return array<string, ReportTemplate>
     */
    public function getApplicable(ReportSchema $schema): array
    {
        return array_filter(
            $this->templates,
            fn (ReportTemplate $template): bool => $template->isApplicableTo($schema)
        );
    }

    /**
     * Get templates for a specific model class.
     *
     * @param class-string $modelClass
     * @return array<string, ReportTemplate>
     */
    public function getForModel(string $modelClass): array
    {
        return array_filter(
            $this->templates,
            fn (ReportTemplate $template): bool => $template->getModelClass() === null
                || $template->getModelClass() === $modelClass
        );
    }

    /**
     * Get templates grouped by category.
     *
     * @return array<string, array<string, ReportTemplate>>
     */
    public function getGroupedByCategory(): array
    {
        $grouped = [];

        foreach ($this->templates as $id => $template) {
            $category = $template->getCategory();
            $grouped[$category][$id] = $template;
        }

        return $grouped;
    }

    /**
     * Get templates as options for UI.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getOptions(): array
    {
        return array_map(
            fn (ReportTemplate $template): array => $template->toArray(),
            array_values($this->templates)
        );
    }

    /**
     * Clear all templates (useful for testing).
     */
    public function clear(): self
    {
        $this->templates = [];

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
     * Register built-in templates.
     */
    protected function registerBuiltInTemplates(): void
    {
        // Register default templates from config
        $templateClasses = config('dynamic-reporter.templates', []);

        foreach ($templateClasses as $templateClass) {
            if (class_exists($templateClass) && is_subclass_of($templateClass, ReportTemplate::class)) {
                $this->register(new $templateClass());
            }
        }
    }
}
