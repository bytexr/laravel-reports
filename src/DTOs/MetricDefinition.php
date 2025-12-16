<?php

declare(strict_types=1);

namespace ByteXR\DynamicReporter\DTOs;

use Closure;
use Illuminate\Database\Eloquent\Builder;

class MetricDefinition
{
    public const TYPE_NUMBER = 'number';

    public const TYPE_PERCENTAGE = 'percentage';

    public const TYPE_CURRENCY = 'currency';

    public const TYPE_COUNT = 'count';

    public const TYPE_AVERAGE = 'average';

    public const TYPES = [
        self::TYPE_NUMBER => 'Number',
        self::TYPE_PERCENTAGE => 'Percentage',
        self::TYPE_CURRENCY => 'Currency',
        self::TYPE_COUNT => 'Count',
        self::TYPE_AVERAGE => 'Average',
    ];

    protected string $name;

    protected string $label;

    protected string $description = '';

    protected string $type = self::TYPE_NUMBER;

    protected ?Closure $queryModifier = null;

    protected ?string $rawExpression = null;

    protected bool $isExportable = true;

    protected bool $isFilterable = false;

    protected array $meta = [];

    public function __construct(string $name)
    {
        $this->name = $name;
        $this->label = $this->humanize($name);
    }

    public static function make(string $name): static
    {
        return new static($name);
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function description(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function type(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function number(): static
    {
        return $this->type(self::TYPE_NUMBER);
    }

    public function percentage(): static
    {
        return $this->type(self::TYPE_PERCENTAGE);
    }

    public function currency(): static
    {
        return $this->type(self::TYPE_CURRENCY);
    }

    public function count(): static
    {
        return $this->type(self::TYPE_COUNT);
    }

    public function average(): static
    {
        return $this->type(self::TYPE_AVERAGE);
    }

    /**
     * Set the query modifier closure that injects selectRaw logic.
     *
     * @param Closure(Builder): Builder $callback
     */
    public function calculate(Closure $callback): static
    {
        $this->queryModifier = $callback;

        return $this;
    }

    /**
     * Set a raw SQL expression for the metric.
     * This is a simpler alternative to calculate() for basic expressions.
     */
    public function expression(string $rawExpression): static
    {
        $this->rawExpression = $rawExpression;

        return $this;
    }

    public function exportable(bool $exportable = true): static
    {
        $this->isExportable = $exportable;

        return $this;
    }

    public function filterable(bool $filterable = true): static
    {
        $this->isFilterable = $filterable;

        return $this;
    }

    /**
     * @param array<string, mixed> $meta
     */
    public function meta(array $meta): static
    {
        $this->meta = $meta;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getQueryModifier(): ?Closure
    {
        return $this->queryModifier;
    }

    public function getRawExpression(): ?string
    {
        return $this->rawExpression;
    }

    public function isExportable(): bool
    {
        return $this->isExportable;
    }

    public function isFilterable(): bool
    {
        return $this->isFilterable;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMeta(): array
    {
        return $this->meta;
    }

    /**
     * Check if this metric has a query modifier or raw expression.
     */
    public function hasCalculation(): bool
    {
        return $this->queryModifier !== null || $this->rawExpression !== null;
    }

    /**
     * Apply the metric calculation to a query builder.
     */
    public function applyToQuery(Builder $query): Builder
    {
        if ($this->queryModifier !== null) {
            return ($this->queryModifier)($query);
        }

        if ($this->rawExpression !== null) {
            return $query->selectRaw("{$this->rawExpression} as {$this->name}");
        }

        return $query;
    }

    /**
     * Convert to array for serialization.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'label' => $this->label,
            'description' => $this->description,
            'type' => $this->type,
            'isExportable' => $this->isExportable,
            'isFilterable' => $this->isFilterable,
            'hasCalculation' => $this->hasCalculation(),
            'meta' => $this->meta,
        ];
    }

    /**
     * Get a clean representation for AI context.
     *
     * @return array<string, string>
     */
    public function toAiContext(): array
    {
        return [
            'name' => $this->name,
            'label' => $this->label,
            'description' => $this->description,
            'type' => $this->type,
        ];
    }

    /**
     * Convert snake_case to human readable.
     */
    protected function humanize(string $value): string
    {
        return ucfirst(str_replace('_', ' ', $value));
    }
}
