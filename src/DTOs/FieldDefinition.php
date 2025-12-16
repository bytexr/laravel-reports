<?php

declare(strict_types=1);

namespace ByteXR\DynamicReporter\DTOs;

final readonly class FieldDefinition
{
    /**
     * @param string $name The internal field name (used as identifier)
     * @param string $label The human-readable label for the field
     * @param string|null $dbColumn The database column name (defaults to name if null)
     * @param string $type The field type (text, number, date, datetime, boolean)
     * @param bool $isSortable Whether the field can be sorted
     * @param bool $isFilterable Whether the field can be filtered
     * @param bool $isExportable Whether the field can be exported
     * @param string|null $relationship The relationship name if this field comes from a relation
     * @param array<string, mixed> $meta Additional metadata for the field
     */
    public function __construct(
        public string $name,
        public string $label,
        public ?string $dbColumn = null,
        public string $type = 'text',
        public bool $isSortable = false,
        public bool $isFilterable = true,
        public bool $isExportable = true,
        public ?string $relationship = null,
        public array $meta = [],
    ) {}

    /**
     * Get the database column name (falls back to name if not set).
     */
    public function getDbColumn(): string
    {
        return $this->dbColumn ?? $this->name;
    }

    /**
     * Check if this field belongs to a relationship.
     */
    public function isRelationship(): bool
    {
        return $this->relationship !== null;
    }

    /**
     * Convert the field definition to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'label' => $this->label,
            'db_column' => $this->getDbColumn(),
            'type' => $this->type,
            'is_sortable' => $this->isSortable,
            'is_filterable' => $this->isFilterable,
            'is_exportable' => $this->isExportable,
            'relationship' => $this->relationship,
            'meta' => $this->meta,
        ];
    }
}
