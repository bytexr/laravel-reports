<?php

declare(strict_types=1);

namespace ByteXR\DynamicReporter\Support;

use ByteXR\DynamicReporter\DTOs\FieldDefinition;

final class Field
{
    private string $name;

    private string $label;

    private ?string $dbColumn = null;

    private string $type = 'text';

    private bool $isSortable = false;

    private bool $isFilterable = true;

    private bool $isExportable = true;

    private ?string $relationship = null;

    /** @var array<string, mixed> */
    private array $meta = [];

    private function __construct(string $name)
    {
        $this->name = $name;
        $this->label = $this->humanize($name);
    }

    /**
     * Create a new field builder instance.
     */
    public static function make(string $name): self
    {
        return new self($name);
    }

    /**
     * Set the human-readable label for the field.
     */
    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    /**
     * Set the database column name.
     */
    public function column(string $dbColumn): self
    {
        $this->dbColumn = $dbColumn;

        return $this;
    }

    /**
     * Set the field type to text.
     */
    public function text(): self
    {
        $this->type = 'text';

        return $this;
    }

    /**
     * Set the field type to number.
     */
    public function number(): self
    {
        $this->type = 'number';

        return $this;
    }

    /**
     * Set the field type to date.
     */
    public function date(): self
    {
        $this->type = 'date';

        return $this;
    }

    /**
     * Set the field type to datetime.
     */
    public function datetime(): self
    {
        $this->type = 'datetime';

        return $this;
    }

    /**
     * Set the field type to boolean.
     */
    public function boolean(): self
    {
        $this->type = 'boolean';

        return $this;
    }

    /**
     * Set a custom field type.
     */
    public function type(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Mark the field as sortable.
     */
    public function sortable(bool $sortable = true): self
    {
        $this->isSortable = $sortable;

        return $this;
    }

    /**
     * Mark the field as not sortable.
     */
    public function notSortable(): self
    {
        $this->isSortable = false;

        return $this;
    }

    /**
     * Mark the field as filterable.
     */
    public function filterable(bool $filterable = true): self
    {
        $this->isFilterable = $filterable;

        return $this;
    }

    /**
     * Mark the field as not filterable.
     */
    public function notFilterable(): self
    {
        $this->isFilterable = false;

        return $this;
    }

    /**
     * Mark the field as exportable.
     */
    public function exportable(bool $exportable = true): self
    {
        $this->isExportable = $exportable;

        return $this;
    }

    /**
     * Mark the field as not exportable.
     */
    public function notExportable(): self
    {
        $this->isExportable = false;

        return $this;
    }

    /**
     * Set the relationship name for this field.
     */
    public function fromRelationship(string $relationship): self
    {
        $this->relationship = $relationship;

        return $this;
    }

    /**
     * Add metadata to the field.
     *
     * @param array<string, mixed> $meta
     */
    public function meta(array $meta): self
    {
        $this->meta = array_merge($this->meta, $meta);

        return $this;
    }

    /**
     * Build the FieldDefinition DTO.
     */
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

    /**
     * Convert a snake_case or camelCase string to a human-readable label.
     */
    private function humanize(string $value): string
    {
        $value = str_replace(['_', '-'], ' ', $value);
        $value = (string) preg_replace('/([a-z])([A-Z])/', '$1 $2', $value);

        return ucwords($value);
    }
}
