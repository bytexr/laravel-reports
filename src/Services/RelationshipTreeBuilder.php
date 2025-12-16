<?php

declare(strict_types=1);

namespace ByteXR\DynamicReporter\Services;

use ByteXR\DynamicReporter\DTOs\FieldDefinition;
use ByteXR\DynamicReporter\DTOs\RelationshipDefinition;
use ByteXR\DynamicReporter\Enums\FieldType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;

/**
 * Service to build expandable relationship trees for UI display.
 *
 * This service discovers relationships on Eloquent models and builds
 * a tree structure that can be displayed to users in a friendly way.
 */
class RelationshipTreeBuilder
{
    /**
     * Default maximum depth for relationship trees.
     */
    public const DEFAULT_MAX_DEPTH = 3;

    /**
     * Depth at which to show warnings.
     */
    public const WARNING_DEPTH = 2;

    /**
     * Cache of discovered relationships.
     *
     * @var array<class-string<Model>, array<string, RelationshipDefinition>>
     */
    protected array $cache = [];

    /**
     * Build a relationship tree for a model class.
     *
     * @param class-string<Model> $modelClass
     * @return array<int, RelationshipDefinition>
     */
    public function buildTree(string $modelClass, int $maxDepth = self::DEFAULT_MAX_DEPTH): array
    {
        return $this->discoverRelationships($modelClass, '', $maxDepth, 0);
    }

    /**
     * Flatten a relationship tree to a list of paths.
     *
     * @param array<int, RelationshipDefinition> $tree
     * @return array<string, RelationshipDefinition>
     */
    public function flattenTree(array $tree): array
    {
        $result = [];

        foreach ($tree as $relationship) {
            $result[$relationship->getFullPath()] = $relationship;

            if ($relationship->hasChildren()) {
                $childResults = $this->flattenTree($relationship->children);
                $result = array_merge($result, $childResults);
            }
        }

        return $result;
    }

    /**
     * Get all available fields for a relationship path.
     *
     * @param class-string<Model> $modelClass
     * @return array<int, FieldDefinition>
     */
    public function getFieldsForPath(string $modelClass, string $path): array
    {
        $relatedModel = $this->resolveRelatedModel($modelClass, $path);

        if ($relatedModel === null) {
            return [];
        }

        return $this->discoverFields($relatedModel, $path);
    }

    /**
     * Validate that a relationship path exists on a model.
     *
     * @param class-string<Model> $modelClass
     */
    public function validatePath(string $modelClass, string $path): bool
    {
        return $this->resolveRelatedModel($modelClass, $path) !== null;
    }

    /**
     * Get a user-friendly tree structure for UI display.
     *
     * @param class-string<Model> $modelClass
     * @return array<int, array<string, mixed>>
     */
    public function buildUserFriendlyTree(string $modelClass, int $maxDepth = self::DEFAULT_MAX_DEPTH): array
    {
        $tree = $this->buildTree($modelClass, $maxDepth);

        return array_map(
            fn (RelationshipDefinition $rel): array => $rel->toUserFriendlyArray(),
            $tree
        );
    }

    /**
     * Discover relationships on a model class.
     *
     * @param class-string<Model> $modelClass
     * @return array<int, RelationshipDefinition>
     */
    protected function discoverRelationships(
        string $modelClass,
        string $parentPath,
        int $maxDepth,
        int $currentDepth
    ): array {
        if ($currentDepth >= $maxDepth) {
            return [];
        }

        if (! class_exists($modelClass)) {
            return [];
        }

        $relationships = [];
        $reflection = new ReflectionClass($modelClass);
        $model = new $modelClass();

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->class !== $modelClass) {
                continue;
            }

            if ($method->getNumberOfParameters() > 0) {
                continue;
            }

            $relationshipDef = $this->tryGetRelationship($model, $method, $parentPath, $maxDepth, $currentDepth);

            if ($relationshipDef !== null) {
                $relationships[] = $relationshipDef;
            }
        }

        return $relationships;
    }

    /**
     * Try to get a relationship definition from a method.
     */
    protected function tryGetRelationship(
        Model $model,
        ReflectionMethod $method,
        string $parentPath,
        int $maxDepth,
        int $currentDepth
    ): ?RelationshipDefinition {
        $methodName = $method->getName();

        if ($this->shouldSkipMethod($methodName)) {
            return null;
        }

        try {
            $result = $model->$methodName();

            if (! $result instanceof Relation) {
                return null;
            }

            $relationType = $this->getRelationType($result);
            $relatedModel = $result->getRelated();
            $relatedClass = get_class($relatedModel);

            $path = $parentPath ? "{$parentPath}.{$methodName}" : $methodName;

            $children = [];
            if ($currentDepth + 1 < $maxDepth) {
                $children = $this->discoverRelationships(
                    $relatedClass,
                    $path,
                    $maxDepth,
                    $currentDepth + 1
                );
            }

            $fields = $this->discoverFields($relatedModel, $path);

            return new RelationshipDefinition(
                name: $methodName,
                label: $this->humanizeRelationshipName($methodName),
                description: $this->generateRelationshipDescription($methodName, $relationType, $relatedClass),
                path: $path,
                type: $relationType,
                fields: $fields,
                children: $children,
                maxDepth: $maxDepth,
                isEagerLoadable: $this->isEagerLoadable($relationType),
                meta: [
                    'relatedModel' => $relatedClass,
                    'depth' => $currentDepth + 1,
                ],
            );
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Check if a method should be skipped.
     */
    protected function shouldSkipMethod(string $methodName): bool
    {
        $skipMethods = [
            'getKey',
            'getKeyName',
            'getKeyType',
            'getForeignKey',
            'getQualifiedKeyName',
            'getRouteKey',
            'getRouteKeyName',
            'resolveRouteBinding',
            'resolveSoftDeletableRouteBinding',
            'resolveChildRouteBinding',
            'getTable',
            'getConnection',
            'getConnectionName',
            'setConnection',
            'getQuery',
            'newQuery',
            'newQueryWithoutScopes',
            'newQueryWithoutRelationships',
            'newQueryForRestoration',
            'newEloquentBuilder',
            'newCollection',
            'newPivot',
            'toArray',
            'toJson',
            'jsonSerialize',
            'fresh',
            'refresh',
            'replicate',
            'is',
            'isNot',
            'getAttributes',
            'getOriginal',
            'getDirty',
            'getChanges',
            'wasChanged',
            'isDirty',
            'isClean',
            'getAttribute',
            'setAttribute',
            'hasAttribute',
            'getRelations',
            'getRelation',
            'relationLoaded',
            'setRelation',
            'unsetRelation',
            'setRelations',
            'withoutRelations',
            'getTouchedRelations',
            'touches',
            'touchOwners',
            'getMorphClass',
            'getMorphs',
            'morphTo',
            'morphOne',
            'morphMany',
            'morphToMany',
            'morphedByMany',
            'belongsTo',
            'hasOne',
            'hasMany',
            'belongsToMany',
            'hasOneThrough',
            'hasManyThrough',
        ];

        return in_array($methodName, $skipMethods, true)
            || str_starts_with($methodName, 'get')
            || str_starts_with($methodName, 'set')
            || str_starts_with($methodName, 'scope')
            || str_starts_with($methodName, '__');
    }

    /**
     * Get the relationship type as a string.
     */
    protected function getRelationType(Relation $relation): string
    {
        return match (true) {
            $relation instanceof BelongsTo => 'belongsTo',
            $relation instanceof HasOne => 'hasOne',
            $relation instanceof HasMany => 'hasMany',
            $relation instanceof BelongsToMany => 'belongsToMany',
            $relation instanceof HasOneThrough => 'hasOneThrough',
            $relation instanceof HasManyThrough => 'hasManyThrough',
            $relation instanceof MorphOne => 'morphOne',
            $relation instanceof MorphMany => 'morphMany',
            $relation instanceof MorphTo => 'morphTo',
            $relation instanceof MorphToMany => 'morphToMany',
            default => 'unknown',
        };
    }

    /**
     * Check if a relationship type can be eager loaded efficiently.
     */
    protected function isEagerLoadable(string $relationType): bool
    {
        return in_array($relationType, [
            'belongsTo',
            'hasOne',
            'hasMany',
            'belongsToMany',
            'morphOne',
            'morphMany',
        ], true);
    }

    /**
     * Humanize a relationship name for display.
     */
    protected function humanizeRelationshipName(string $name): string
    {
        $name = Str::snake($name);
        $name = str_replace('_', ' ', $name);

        return Str::title($name);
    }

    /**
     * Generate a user-friendly description for a relationship.
     *
     * @param class-string<Model> $relatedClass
     */
    protected function generateRelationshipDescription(string $name, string $type, string $relatedClass): string
    {
        $relatedName = class_basename($relatedClass);
        $humanName = $this->humanizeRelationshipName($name);

        return match ($type) {
            'belongsTo' => "The {$relatedName} that this record belongs to",
            'hasOne' => "The {$relatedName} associated with this record",
            'hasMany' => "All {$relatedName} records associated with this record",
            'belongsToMany' => "All {$relatedName} records linked to this record",
            'hasOneThrough' => "The {$relatedName} accessible through another relationship",
            'hasManyThrough' => "All {$relatedName} records accessible through another relationship",
            'morphOne' => "The {$relatedName} polymorphically associated with this record",
            'morphMany' => "All {$relatedName} records polymorphically associated with this record",
            default => "Related {$relatedName} information",
        };
    }

    /**
     * Discover fields on a model for reporting.
     *
     * @return array<int, FieldDefinition>
     */
    protected function discoverFields(Model $model, string $relationshipPath): array
    {
        $fields = [];
        $table = $model->getTable();

        try {
            $columns = \Illuminate\Support\Facades\Schema::getColumnListing($table);

            foreach ($columns as $column) {
                if ($this->shouldSkipColumn($column)) {
                    continue;
                }

                $type = $this->guessFieldType($column, $model);

                $fields[] = new FieldDefinition(
                    name: "{$relationshipPath}.{$column}",
                    label: $this->humanizeColumnName($column),
                    dbColumn: $column,
                    type: $type,
                    isSortable: true,
                    isFilterable: true,
                    isExportable: true,
                    relationship: $relationshipPath,
                    meta: [
                        'table' => $table,
                        'originalColumn' => $column,
                    ],
                );
            }
        } catch (\Throwable) {
            // Schema might not be available, return empty
        }

        return $fields;
    }

    /**
     * Check if a column should be skipped.
     */
    protected function shouldSkipColumn(string $column): bool
    {
        $skipColumns = [
            'password',
            'remember_token',
            'api_token',
            'secret',
            'two_factor_secret',
            'two_factor_recovery_codes',
        ];

        return in_array($column, $skipColumns, true);
    }

    /**
     * Guess the field type based on column name and model.
     */
    protected function guessFieldType(string $column, Model $model): FieldType
    {
        $casts = $model->getCasts();

        if (isset($casts[$column])) {
            $cast = $casts[$column];

            return match (true) {
                $cast === 'boolean' || $cast === 'bool' => FieldType::Boolean,
                $cast === 'integer' || $cast === 'int' => FieldType::Number,
                $cast === 'float' || $cast === 'double' || $cast === 'decimal' => FieldType::Number,
                $cast === 'date' => FieldType::Date,
                $cast === 'datetime' || $cast === 'timestamp' => FieldType::Datetime,
                str_contains($cast, 'decimal:') => FieldType::Money,
                default => FieldType::Text,
            };
        }

        return match (true) {
            str_ends_with($column, '_at') => FieldType::Datetime,
            str_ends_with($column, '_date') => FieldType::Date,
            str_ends_with($column, '_id') => FieldType::Number,
            str_starts_with($column, 'is_') || str_starts_with($column, 'has_') => FieldType::Boolean,
            in_array($column, ['price', 'total', 'amount', 'cost', 'fee', 'balance'], true) => FieldType::Money,
            in_array($column, ['count', 'quantity', 'qty', 'number', 'num'], true) => FieldType::Number,
            $column === 'id' => FieldType::Number,
            default => FieldType::Text,
        };
    }

    /**
     * Humanize a column name for display.
     */
    protected function humanizeColumnName(string $column): string
    {
        $column = str_replace('_', ' ', $column);
        $column = preg_replace('/\s+id$/i', ' ID', $column);

        return Str::title($column);
    }

    /**
     * Resolve the related model class for a path.
     *
     * @param class-string<Model> $modelClass
     * @return class-string<Model>|null
     */
    protected function resolveRelatedModel(string $modelClass, string $path): ?string
    {
        if (! class_exists($modelClass)) {
            return null;
        }

        $parts = explode('.', $path);
        $currentClass = $modelClass;

        foreach ($parts as $part) {
            try {
                $model = new $currentClass();

                if (! method_exists($model, $part)) {
                    return null;
                }

                $result = $model->$part();

                if (! $result instanceof Relation) {
                    return null;
                }

                $currentClass = get_class($result->getRelated());
            } catch (\Throwable) {
                return null;
            }
        }

        return $currentClass;
    }

    /**
     * Clear the relationship cache.
     */
    public function clearCache(): void
    {
        $this->cache = [];
    }
}
