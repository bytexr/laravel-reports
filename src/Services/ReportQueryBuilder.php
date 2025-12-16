<?php

declare(strict_types=1);

namespace ByteXR\DynamicReporter\Services;

use ByteXR\DynamicReporter\Contracts\Reportable;
use ByteXR\DynamicReporter\DTOs\FieldDefinition;
use ByteXR\DynamicReporter\DTOs\ReportSchema;
use ByteXR\DynamicReporter\Traits\EvaluatesClosures;
use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use InvalidArgumentException;

class ReportQueryBuilder
{
    use EvaluatesClosures;

    protected FilterFactory $filterFactory;

    protected ?Authenticatable $user = null;

    protected ?Request $request = null;

    /** @var array<string, mixed> */
    protected array $customInjections = [];

    public function __construct(?FilterFactory $filterFactory = null)
    {
        $this->filterFactory = $filterFactory ?? new FilterFactory();
    }

    /**
     * Set the user for closure evaluation.
     */
    public function forUser(?Authenticatable $user): self
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Set the request for closure evaluation.
     */
    public function withRequest(?Request $request): self
    {
        $this->request = $request;

        return $this;
    }

    /**
     * Add custom injections for closure evaluation.
     *
     * @param array<string, mixed> $injections
     */
    public function withInjections(array $injections): self
    {
        $this->customInjections = array_merge($this->customInjections, $injections);

        return $this;
    }

    /**
     * Compile a report query from a reportable model.
     *
     * @param class-string<Model&Reportable> $modelClass
     * @param array<int, array{field: string, operator: string, value: mixed, value2?: mixed}> $filters
     * @param array<int, array{field: string, direction: string}> $sorts
     * @param array<int, string> $selectedFields
     */
    public function compile(
        string $modelClass,
        array $filters = [],
        array $sorts = [],
        array $selectedFields = [],
    ): Builder {
        $this->validateModelClass($modelClass);

        $schema = $this->resolveSchema($modelClass);
        $query = $modelClass::query();

        $this->applySelectedFields($query, $schema, $selectedFields);
        $this->applyFilters($query, $schema, $filters);
        $this->applySorts($query, $schema, $sorts);
        $this->applyRelationships($query, $schema, $selectedFields);

        return $query;
    }

    /**
     * Resolve and evaluate the schema from a model class.
     *
     * @param class-string<Model&Reportable> $modelClass
     */
    public function resolveSchema(string $modelClass): ReportSchema
    {
        $schema = $modelClass::getReportSchema();

        return $this->evaluateSchema($schema);
    }

    /**
     * Evaluate all closures in a schema.
     */
    protected function evaluateSchema(ReportSchema $schema): ReportSchema
    {
        $evaluatedFields = array_map(
            fn (FieldDefinition $field): FieldDefinition => $this->evaluateField($field),
            $schema->fields,
        );

        return new ReportSchema(
            name: $this->evaluateValue($schema->name),
            description: $this->evaluateValue($schema->description),
            fields: $evaluatedFields,
            meta: $this->evaluateArrayValues($schema->meta),
        );
    }

    /**
     * Evaluate all closures in a field definition.
     */
    protected function evaluateField(FieldDefinition $field): FieldDefinition
    {
        return new FieldDefinition(
            name: $field->name,
            label: $this->evaluateValue($field->label),
            dbColumn: $field->dbColumn,
            type: $field->type,
            isSortable: $this->evaluateValue($field->isSortable),
            isFilterable: $this->evaluateValue($field->isFilterable),
            isExportable: $this->evaluateValue($field->isExportable),
            relationship: $field->relationship,
            meta: $this->evaluateArrayValues($field->meta),
        );
    }

    /**
     * Evaluate a single value (resolving closures).
     */
    protected function evaluateValue(mixed $value): mixed
    {
        return $this->evaluate($value, $this->getNamedInjections(), $this->getTypedInjections());
    }

    /**
     * Evaluate all values in an array.
     *
     * @param array<string, mixed> $array
     * @return array<string, mixed>
     */
    protected function evaluateArrayValues(array $array): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $result[$key] = $this->evaluateArrayValues($value);
            } else {
                $result[$key] = $this->evaluateValue($value);
            }
        }

        return $result;
    }

    /**
     * Get named injections for closure evaluation.
     *
     * @return array<string, mixed>
     */
    protected function getNamedInjections(): array
    {
        return array_merge([
            'user' => $this->user,
            'request' => $this->request,
        ], $this->customInjections);
    }

    /**
     * Get typed injections for closure evaluation.
     *
     * @return array<class-string, mixed>
     */
    protected function getTypedInjections(): array
    {
        $injections = [];

        if ($this->user !== null) {
            $injections[Authenticatable::class] = $this->user;
            $injections[get_class($this->user)] = $this->user;
        }

        if ($this->request !== null) {
            $injections[Request::class] = $this->request;
        }

        return $injections;
    }

    /**
     * Apply selected fields to the query.
     *
     * @param array<int, string> $selectedFields
     */
    protected function applySelectedFields(
        Builder $query,
        ReportSchema $schema,
        array $selectedFields,
    ): void {
        if (empty($selectedFields)) {
            return;
        }

        $columns = [];

        foreach ($selectedFields as $fieldName) {
            $field = $schema->getField($fieldName);

            if ($field === null || $field->isRelationship()) {
                continue;
            }

            $columns[] = $field->getDbColumn();
        }

        if (! empty($columns)) {
            $model = $query->getModel();
            $primaryKey = $model->getKeyName();

            if (! in_array($primaryKey, $columns, true)) {
                array_unshift($columns, $primaryKey);
            }

            $query->select($columns);
        }
    }

    /**
     * Apply filters to the query.
     *
     * @param array<int, array{field: string, operator: string, value: mixed, value2?: mixed}> $filters
     */
    protected function applyFilters(
        Builder $query,
        ReportSchema $schema,
        array $filters,
    ): void {
        foreach ($filters as $filter) {
            $field = $schema->getField($filter['field']);

            if ($field === null || ! $field->isFilterable) {
                continue;
            }

            if (! $this->filterFactory->isValidOperator($field->type, $filter['operator'])) {
                continue;
            }

            $this->applyFilter($query, $field, $filter);
        }
    }

    /**
     * Apply a single filter to the query.
     *
     * @param array{field: string, operator: string, value: mixed, value2?: mixed} $filter
     */
    protected function applyFilter(
        Builder $query,
        FieldDefinition $field,
        array $filter,
    ): void {
        $column = $field->getDbColumn();
        $operator = $filter['operator'];
        $value = $filter['value'] ?? null;
        $value2 = $filter['value2'] ?? null;

        if ($field->isRelationship()) {
            $this->applyRelationshipFilter($query, $field, $filter);

            return;
        }

        match ($operator) {
            'equals', 'date_equals' => $query->where($column, '=', $value),
            'not_equals' => $query->where($column, '!=', $value),
            'like' => $query->where($column, 'LIKE', "%{$value}%"),
            'not_like' => $query->where($column, 'NOT LIKE', "%{$value}%"),
            'starts_with' => $query->where($column, 'LIKE', "{$value}%"),
            'ends_with' => $query->where($column, 'LIKE', "%{$value}"),
            'greater_than', 'after' => $query->where($column, '>', $value),
            'greater_than_or_equal', 'after_or_equal' => $query->where($column, '>=', $value),
            'less_than', 'before' => $query->where($column, '<', $value),
            'less_than_or_equal', 'before_or_equal' => $query->where($column, '<=', $value),
            'between' => $query->whereBetween($column, [$value, $value2]),
            'is_null' => $query->whereNull($column),
            'is_not_null' => $query->whereNotNull($column),
            'is_true' => $query->where($column, '=', true),
            'is_false' => $query->where($column, '=', false),
            default => null,
        };
    }

    /**
     * Apply a filter on a relationship field.
     *
     * @param array{field: string, operator: string, value: mixed, value2?: mixed} $filter
     */
    protected function applyRelationshipFilter(
        Builder $query,
        FieldDefinition $field,
        array $filter,
    ): void {
        $relationship = $field->relationship;

        if ($relationship === null) {
            return;
        }

        $query->whereHas($relationship, function (Builder $relationQuery) use ($field, $filter): void {
            $this->applyFilter(
                $relationQuery,
                new FieldDefinition(
                    name: $field->name,
                    label: $field->label,
                    dbColumn: $field->dbColumn,
                    type: $field->type,
                    isSortable: $field->isSortable,
                    isFilterable: $field->isFilterable,
                    isExportable: $field->isExportable,
                    relationship: null,
                    meta: $field->meta,
                ),
                $filter,
            );
        });
    }

    /**
     * Apply sorts to the query.
     *
     * @param array<int, array{field: string, direction: string}> $sorts
     */
    protected function applySorts(
        Builder $query,
        ReportSchema $schema,
        array $sorts,
    ): void {
        foreach ($sorts as $sort) {
            $field = $schema->getField($sort['field']);

            if ($field === null || ! $field->isSortable) {
                continue;
            }

            $direction = strtolower($sort['direction']) === 'desc' ? 'desc' : 'asc';

            if ($field->isRelationship()) {
                continue;
            }

            $query->orderBy($field->getDbColumn(), $direction);
        }
    }

    /**
     * Apply eager loading for relationship fields.
     *
     * @param array<int, string> $selectedFields
     */
    protected function applyRelationships(
        Builder $query,
        ReportSchema $schema,
        array $selectedFields,
    ): void {
        $relationships = [];
        $fieldsToCheck = empty($selectedFields) ? $schema->getFieldNames() : $selectedFields;

        foreach ($fieldsToCheck as $fieldName) {
            $field = $schema->getField($fieldName);

            if ($field !== null && $field->isRelationship() && $field->relationship !== null) {
                $relationships[] = $field->relationship;
            }
        }

        if (! empty($relationships)) {
            $query->with(array_unique($relationships));
        }
    }

    /**
     * Validate that the model class implements Reportable.
     *
     * @param class-string $modelClass
     */
    protected function validateModelClass(string $modelClass): void
    {
        if (! class_exists($modelClass)) {
            throw new InvalidArgumentException("Class [{$modelClass}] does not exist.");
        }

        if (! is_subclass_of($modelClass, Model::class)) {
            throw new InvalidArgumentException("Class [{$modelClass}] must extend Eloquent Model.");
        }

        if (! is_subclass_of($modelClass, Reportable::class)) {
            throw new InvalidArgumentException("Class [{$modelClass}] must implement Reportable interface.");
        }
    }
}
