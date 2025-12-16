<?php

declare(strict_types=1);

namespace ByteXR\DynamicReporter\DTOs;

final readonly class FieldDefinition
{
    /**
     * @param string $name The internal field name (column name or accessor)
     * @param string $label The human-readable label for the field
     * @param string $type The field type (string, integer, float, boolean, date, datetime, etc.)
     * @param bool $sortable Whether the field can be sorted
     * @param bool $filterable Whether the field can be filtered
     * @param bool $exportable Whether the field can be exported
     * @param array<string, mixed> $meta Additional metadata for the field
     */
    public function __construct(
        public string $name,
        public string $label,
        public string $type = 'string',
        public bool $sortable = true,
        public bool $filterable = true,
        public bool $exportable = true,
        public array $meta = [],
    ) {}

    /**
     * Create a new FieldDefinition instance.
     *
     * @param array<string, mixed> $meta
     */
    public static function make(
        string $name,
        string $label,
        string $type = 'string',
        bool $sortable = true,
        bool $filterable = true,
        bool $exportable = true,
        array $meta = [],
    ): self {
        return new self(
            name: $name,
            label: $label,
            type: $type,
            sortable: $sortable,
            filterable: $filterable,
            exportable: $exportable,
            meta: $meta,
        );
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
            'type' => $this->type,
            'sortable' => $this->sortable,
            'filterable' => $this->filterable,
            'exportable' => $this->exportable,
            'meta' => $this->meta,
        ];
    }
}
