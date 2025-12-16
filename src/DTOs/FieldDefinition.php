<?php

declare(strict_types=1);

namespace ByteXR\DynamicReporter\DTOs;

use ByteXR\DynamicReporter\Enums\FieldType;

final readonly class FieldDefinition
{
    /**
     * @param array<string, mixed> $meta
     */
    public function __construct(
        public string $name,
        public string $label,
        public ?string $dbColumn = null,
        public FieldType $type = FieldType::Text,
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

    public function getTypeValue(): string
    {
        return $this->type->value;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'label' => $this->label,
            'db_column' => $this->getDbColumn(),
            'type' => $this->type->value,
            'is_sortable' => $this->isSortable,
            'is_filterable' => $this->isFilterable,
            'is_exportable' => $this->isExportable,
            'relationship' => $this->relationship,
            'meta' => $this->meta,
        ];
    }
}
