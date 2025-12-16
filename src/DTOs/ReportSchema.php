<?php

declare(strict_types=1);

namespace ByteXR\DynamicReporter\DTOs;

final readonly class ReportSchema
{
    /**
     * @param string $name The display name for the report schema
     * @param string $description A description of what this report schema represents
     * @param array<int, FieldDefinition> $fields The field definitions for this schema
     * @param array<string, mixed> $meta Additional metadata for the schema
     */
    public function __construct(
        public string $name,
        public string $description,
        public array $fields,
        public array $meta = [],
    ) {}

    /**
     * Create a new ReportSchema instance.
     *
     * @param array<int, FieldDefinition> $fields
     * @param array<string, mixed> $meta
     */
    public static function make(
        string $name,
        string $description,
        array $fields,
        array $meta = [],
    ): self {
        return new self(
            name: $name,
            description: $description,
            fields: $fields,
            meta: $meta,
        );
    }

    /**
     * Get a field definition by name.
     */
    public function getField(string $name): ?FieldDefinition
    {
        foreach ($this->fields as $field) {
            if ($field->name === $name) {
                return $field;
            }
        }

        return null;
    }

    /**
     * Check if a field exists in the schema.
     */
    public function hasField(string $name): bool
    {
        return $this->getField($name) !== null;
    }

    /**
     * Get all sortable fields.
     *
     * @return array<int, FieldDefinition>
     */
    public function getSortableFields(): array
    {
        return array_values(array_filter(
            $this->fields,
            static fn (FieldDefinition $field): bool => $field->isSortable,
        ));
    }

    /**
     * Get all filterable fields.
     *
     * @return array<int, FieldDefinition>
     */
    public function getFilterableFields(): array
    {
        return array_values(array_filter(
            $this->fields,
            static fn (FieldDefinition $field): bool => $field->isFilterable,
        ));
    }

    /**
     * Get all exportable fields.
     *
     * @return array<int, FieldDefinition>
     */
    public function getExportableFields(): array
    {
        return array_values(array_filter(
            $this->fields,
            static fn (FieldDefinition $field): bool => $field->isExportable,
        ));
    }

    /**
     * Get all field names.
     *
     * @return array<int, string>
     */
    public function getFieldNames(): array
    {
        return array_map(
            static fn (FieldDefinition $field): string => $field->name,
            $this->fields,
        );
    }

    /**
     * Convert the schema to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'fields' => array_map(
                static fn (FieldDefinition $field): array => $field->toArray(),
                $this->fields,
            ),
            'meta' => $this->meta,
        ];
    }
}
