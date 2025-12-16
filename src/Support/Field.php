<?php

declare(strict_types=1);

namespace ByteXR\DynamicReporter\Support;

use ByteXR\DynamicReporter\DTOs\FieldDefinition;
use ByteXR\DynamicReporter\Enums\FieldType;

class Field
{
    protected string $name;

    protected string $label;

    protected ?string $dbColumn = null;

    protected FieldType $type = FieldType::Text;

    protected bool $isSortable = false;

    protected bool $isFilterable = true;

    protected bool $isExportable = true;

    protected ?string $relationship = null;

    /** @var array<string, mixed> */
    protected array $meta = [];

    protected function __construct(string $name)
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

    public function column(string $dbColumn): static
    {
        $this->dbColumn = $dbColumn;

        return $this;
    }

    public function text(): static
    {
        $this->type = FieldType::Text;

        return $this;
    }

    public function number(): static
    {
        $this->type = FieldType::Number;

        return $this;
    }

    public function date(): static
    {
        $this->type = FieldType::Date;

        return $this;
    }

    public function datetime(): static
    {
        $this->type = FieldType::Datetime;

        return $this;
    }

    public function boolean(): static
    {
        $this->type = FieldType::Boolean;

        return $this;
    }

    public function money(): static
    {
        $this->type = FieldType::Money;

        return $this;
    }

    public function type(FieldType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function sortable(bool $sortable = true): static
    {
        $this->isSortable = $sortable;

        return $this;
    }

    public function notSortable(): static
    {
        $this->isSortable = false;

        return $this;
    }

    public function filterable(bool $filterable = true): static
    {
        $this->isFilterable = $filterable;

        return $this;
    }

    public function notFilterable(): static
    {
        $this->isFilterable = false;

        return $this;
    }

    public function exportable(bool $exportable = true): static
    {
        $this->isExportable = $exportable;

        return $this;
    }

    public function notExportable(): static
    {
        $this->isExportable = false;

        return $this;
    }

    public function fromRelationship(string $relationship): static
    {
        $this->relationship = $relationship;

        return $this;
    }

    /**
     * @param array<string, mixed> $meta
     */
    public function meta(array $meta): static
    {
        $this->meta = array_merge($this->meta, $meta);

        return $this;
    }

    public function build(): FieldDefinition
    {
        return new FieldDefinition(
            name: $this->name,
            label: $this->label,
            dbColumn: $this->dbColumn,
            type: $this->type,
            isSortable: $this->isSortable,
            isFilterable: $this->isFilterable,
            isExportable: $this->isExportable,
            relationship: $this->relationship,
            meta: $this->meta,
        );
    }

    protected function humanize(string $value): string
    {
        $value = str_replace(['_', '-'], ' ', $value);
        $value = (string) preg_replace('/([a-z])([A-Z])/', '$1 $2', $value);

        return ucwords($value);
    }
}
