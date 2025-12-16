<?php

declare(strict_types=1);

namespace ByteXR\DynamicReporter\DTOs;

/**
 * Defines a relationship that can be included in reports.
 *
 * This class provides a user-friendly abstraction over Eloquent relationships,
 * hiding technical details like "joins" and "foreign keys" from non-technical users.
 */
final class RelationshipDefinition
{
    /**
     * @param string $name Internal relationship name (e.g., 'customer')
     * @param string $label Human-readable label (e.g., 'Customer Information')
     * @param string $description Short description for users
     * @param string $path Dot-notation path (e.g., 'customer.address.country')
     * @param string $type Relationship type (belongsTo, hasMany, hasOne, belongsToMany)
     * @param array<int, FieldDefinition> $fields Available fields from this relationship
     * @param array<int, RelationshipDefinition> $children Nested relationships
     * @param int $maxDepth Maximum allowed nesting depth
     * @param bool $isEagerLoadable Whether this relationship can be eager loaded
     * @param array<string, mixed> $meta Additional metadata
     */
    public function __construct(
        public readonly string $name,
        public readonly string $label,
        public readonly string $description = '',
        public readonly string $path = '',
        public readonly string $type = 'belongsTo',
        public readonly array $fields = [],
        public readonly array $children = [],
        public readonly int $maxDepth = 3,
        public readonly bool $isEagerLoadable = true,
        public readonly array $meta = [],
    ) {}

    /**
     * Create a new RelationshipDefinition instance.
     */
    public static function make(string $name): RelationshipDefinitionBuilder
    {
        return new RelationshipDefinitionBuilder($name);
    }

    /**
     * Get the full path including this relationship.
     */
    public function getFullPath(): string
    {
        return $this->path ?: $this->name;
    }

    /**
     * Get the depth of this relationship (number of dots in path + 1).
     */
    public function getDepth(): int
    {
        $path = $this->getFullPath();

        return substr_count($path, '.') + 1;
    }

    /**
     * Check if this relationship has nested children.
     */
    public function hasChildren(): bool
    {
        return ! empty($this->children);
    }

    /**
     * Check if this relationship has available fields.
     */
    public function hasFields(): bool
    {
        return ! empty($this->fields);
    }

    /**
     * Get a breadcrumb-style representation of the path.
     * Example: "Order -> Customer -> Address"
     */
    public function getBreadcrumb(): string
    {
        $parts = explode('.', $this->getFullPath());
        $labels = array_map(
            fn (string $part): string => ucfirst(str_replace('_', ' ', $part)),
            $parts
        );

        return implode(' -> ', $labels);
    }

    /**
     * Check if this relationship exceeds the maximum depth.
     */
    public function exceedsMaxDepth(): bool
    {
        return $this->getDepth() > $this->maxDepth;
    }

    /**
     * Get a warning message if depth is concerning.
     */
    public function getDepthWarning(): ?string
    {
        $depth = $this->getDepth();

        if ($depth >= $this->maxDepth) {
            return "This relationship is {$depth} levels deep. Including very deep relationships may impact performance.";
        }

        if ($depth >= $this->maxDepth - 1) {
            return "This relationship is getting deep ({$depth} levels). Consider if you really need this data.";
        }

        return null;
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
            'path' => $this->getFullPath(),
            'type' => $this->type,
            'fields' => array_map(
                fn (FieldDefinition $field): array => $field->toArray(),
                $this->fields
            ),
            'children' => array_map(
                fn (RelationshipDefinition $child): array => $child->toArray(),
                $this->children
            ),
            'maxDepth' => $this->maxDepth,
            'isEagerLoadable' => $this->isEagerLoadable,
            'depth' => $this->getDepth(),
            'breadcrumb' => $this->getBreadcrumb(),
            'depthWarning' => $this->getDepthWarning(),
            'meta' => $this->meta,
        ];
    }

    /**
     * Convert to a user-friendly array for UI display.
     *
     * @return array<string, mixed>
     */
    public function toUserFriendlyArray(): array
    {
        return [
            'id' => $this->getFullPath(),
            'name' => $this->label,
            'description' => $this->description,
            'path' => $this->getBreadcrumb(),
            'hasChildren' => $this->hasChildren(),
            'children' => array_map(
                fn (RelationshipDefinition $child): array => $child->toUserFriendlyArray(),
                $this->children
            ),
            'warning' => $this->getDepthWarning(),
            'fields' => array_map(
                fn (FieldDefinition $field): array => [
                    'id' => $field->name,
                    'name' => $field->label,
                    'type' => $field->type->value,
                ],
                $this->fields
            ),
        ];
    }
}

/**
 * Builder class for creating RelationshipDefinition instances fluently.
 */
class RelationshipDefinitionBuilder
{
    protected string $name;

    protected string $label;

    protected string $description = '';

    protected string $path = '';

    protected string $type = 'belongsTo';

    /** @var array<int, FieldDefinition> */
    protected array $fields = [];

    /** @var array<int, RelationshipDefinition> */
    protected array $children = [];

    protected int $maxDepth = 3;

    protected bool $isEagerLoadable = true;

    /** @var array<string, mixed> */
    protected array $meta = [];

    public function __construct(string $name)
    {
        $this->name = $name;
        $this->label = ucfirst(str_replace('_', ' ', $name));
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

    public function path(string $path): static
    {
        $this->path = $path;

        return $this;
    }

    public function belongsTo(): static
    {
        $this->type = 'belongsTo';

        return $this;
    }

    public function hasOne(): static
    {
        $this->type = 'hasOne';

        return $this;
    }

    public function hasMany(): static
    {
        $this->type = 'hasMany';

        return $this;
    }

    public function belongsToMany(): static
    {
        $this->type = 'belongsToMany';

        return $this;
    }

    public function type(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    /**
     * @param array<int, FieldDefinition> $fields
     */
    public function fields(array $fields): static
    {
        $this->fields = $fields;

        return $this;
    }

    /**
     * @param array<int, RelationshipDefinition> $children
     */
    public function children(array $children): static
    {
        $this->children = $children;

        return $this;
    }

    public function maxDepth(int $maxDepth): static
    {
        $this->maxDepth = $maxDepth;

        return $this;
    }

    public function notEagerLoadable(): static
    {
        $this->isEagerLoadable = false;

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

    public function build(): RelationshipDefinition
    {
        return new RelationshipDefinition(
            name: $this->name,
            label: $this->label,
            description: $this->description,
            path: $this->path ?: $this->name,
            type: $this->type,
            fields: $this->fields,
            children: $this->children,
            maxDepth: $this->maxDepth,
            isEagerLoadable: $this->isEagerLoadable,
            meta: $this->meta,
        );
    }
}
