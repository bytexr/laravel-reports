<?php

declare(strict_types=1);

namespace ByteXR\DynamicReporter\Services;

use ByteXR\DynamicReporter\DTOs\FieldDefinition;
use ByteXR\DynamicReporter\Enums\FieldType;
use ByteXR\DynamicReporter\Enums\FilterOperator;
use InvalidArgumentException;

class FilterFactory
{
    /**
     * @var array<string, array<string, array{label: string, sql: string, requiresValue: bool, requiresSecondValue?: bool}>>
     */
    protected const TYPE_OPERATORS = [
        'text' => [
            'equals' => [
                'label' => 'Equals',
                'sql' => '=',
                'requiresValue' => true,
            ],
            'not_equals' => [
                'label' => 'Not Equals',
                'sql' => '!=',
                'requiresValue' => true,
            ],
            'like' => [
                'label' => 'Contains',
                'sql' => 'LIKE',
                'requiresValue' => true,
            ],
            'not_like' => [
                'label' => 'Does Not Contain',
                'sql' => 'NOT LIKE',
                'requiresValue' => true,
            ],
            'starts_with' => [
                'label' => 'Starts With',
                'sql' => 'LIKE',
                'requiresValue' => true,
            ],
            'ends_with' => [
                'label' => 'Ends With',
                'sql' => 'LIKE',
                'requiresValue' => true,
            ],
            'is_null' => [
                'label' => 'Is Empty',
                'sql' => 'IS NULL',
                'requiresValue' => false,
            ],
            'is_not_null' => [
                'label' => 'Is Not Empty',
                'sql' => 'IS NOT NULL',
                'requiresValue' => false,
            ],
        ],
        'number' => [
            'equals' => [
                'label' => 'Equals',
                'sql' => '=',
                'requiresValue' => true,
            ],
            'not_equals' => [
                'label' => 'Not Equals',
                'sql' => '!=',
                'requiresValue' => true,
            ],
            'greater_than' => [
                'label' => 'Greater Than',
                'sql' => '>',
                'requiresValue' => true,
            ],
            'greater_than_or_equal' => [
                'label' => 'Greater Than or Equal',
                'sql' => '>=',
                'requiresValue' => true,
            ],
            'less_than' => [
                'label' => 'Less Than',
                'sql' => '<',
                'requiresValue' => true,
            ],
            'less_than_or_equal' => [
                'label' => 'Less Than or Equal',
                'sql' => '<=',
                'requiresValue' => true,
            ],
            'between' => [
                'label' => 'Between',
                'sql' => 'BETWEEN',
                'requiresValue' => true,
                'requiresSecondValue' => true,
            ],
            'is_null' => [
                'label' => 'Is Empty',
                'sql' => 'IS NULL',
                'requiresValue' => false,
            ],
            'is_not_null' => [
                'label' => 'Is Not Empty',
                'sql' => 'IS NOT NULL',
                'requiresValue' => false,
            ],
        ],
        'date' => [
            'date_equals' => [
                'label' => 'On Date',
                'sql' => '=',
                'requiresValue' => true,
            ],
            'after' => [
                'label' => 'After',
                'sql' => '>',
                'requiresValue' => true,
            ],
            'after_or_equal' => [
                'label' => 'On or After',
                'sql' => '>=',
                'requiresValue' => true,
            ],
            'before' => [
                'label' => 'Before',
                'sql' => '<',
                'requiresValue' => true,
            ],
            'before_or_equal' => [
                'label' => 'On or Before',
                'sql' => '<=',
                'requiresValue' => true,
            ],
            'between' => [
                'label' => 'Between',
                'sql' => 'BETWEEN',
                'requiresValue' => true,
                'requiresSecondValue' => true,
            ],
            'is_null' => [
                'label' => 'Is Empty',
                'sql' => 'IS NULL',
                'requiresValue' => false,
            ],
            'is_not_null' => [
                'label' => 'Is Not Empty',
                'sql' => 'IS NOT NULL',
                'requiresValue' => false,
            ],
        ],
        'datetime' => [
            'date_equals' => [
                'label' => 'On Date',
                'sql' => '=',
                'requiresValue' => true,
            ],
            'after' => [
                'label' => 'After',
                'sql' => '>',
                'requiresValue' => true,
            ],
            'after_or_equal' => [
                'label' => 'On or After',
                'sql' => '>=',
                'requiresValue' => true,
            ],
            'before' => [
                'label' => 'Before',
                'sql' => '<',
                'requiresValue' => true,
            ],
            'before_or_equal' => [
                'label' => 'On or Before',
                'sql' => '<=',
                'requiresValue' => true,
            ],
            'between' => [
                'label' => 'Between',
                'sql' => 'BETWEEN',
                'requiresValue' => true,
                'requiresSecondValue' => true,
            ],
            'is_null' => [
                'label' => 'Is Empty',
                'sql' => 'IS NULL',
                'requiresValue' => false,
            ],
            'is_not_null' => [
                'label' => 'Is Not Empty',
                'sql' => 'IS NOT NULL',
                'requiresValue' => false,
            ],
        ],
        'boolean' => [
            'is_true' => [
                'label' => 'Is True',
                'sql' => '=',
                'requiresValue' => false,
            ],
            'is_false' => [
                'label' => 'Is False',
                'sql' => '=',
                'requiresValue' => false,
            ],
            'is_null' => [
                'label' => 'Is Empty',
                'sql' => 'IS NULL',
                'requiresValue' => false,
            ],
            'is_not_null' => [
                'label' => 'Is Not Empty',
                'sql' => 'IS NOT NULL',
                'requiresValue' => false,
            ],
        ],
        'money' => [
            'equals' => [
                'label' => 'Equals',
                'sql' => '=',
                'requiresValue' => true,
            ],
            'not_equals' => [
                'label' => 'Not Equals',
                'sql' => '!=',
                'requiresValue' => true,
            ],
            'greater_than' => [
                'label' => 'Greater Than',
                'sql' => '>',
                'requiresValue' => true,
            ],
            'greater_than_or_equal' => [
                'label' => 'Greater Than or Equal',
                'sql' => '>=',
                'requiresValue' => true,
            ],
            'less_than' => [
                'label' => 'Less Than',
                'sql' => '<',
                'requiresValue' => true,
            ],
            'less_than_or_equal' => [
                'label' => 'Less Than or Equal',
                'sql' => '<=',
                'requiresValue' => true,
            ],
            'between' => [
                'label' => 'Between',
                'sql' => 'BETWEEN',
                'requiresValue' => true,
                'requiresSecondValue' => true,
            ],
            'is_null' => [
                'label' => 'Is Empty',
                'sql' => 'IS NULL',
                'requiresValue' => false,
            ],
            'is_not_null' => [
                'label' => 'Is Not Empty',
                'sql' => 'IS NOT NULL',
                'requiresValue' => false,
            ],
        ],
    ];

    /**
     * @return array<string, array{label: string, sql: string, requiresValue: bool, requiresSecondValue?: bool}>
     */
    public function getOperatorsForType(FieldType|string $type): array
    {
        $typeValue = $type instanceof FieldType ? $type->value : $type;

        return self::TYPE_OPERATORS[$typeValue] ?? self::TYPE_OPERATORS['text'];
    }

    /**
     * @return array<string, array{label: string, sql: string, requiresValue: bool, requiresSecondValue?: bool}>
     */
    public function getOperatorsForField(FieldDefinition $field): array
    {
        return $this->getOperatorsForType($field->type);
    }

    public function isValidOperator(FieldType|string $type, string $operator): bool
    {
        $operators = $this->getOperatorsForType($type);

        return array_key_exists($operator, $operators);
    }

    /**
     * @return array{label: string, sql: string, requiresValue: bool, requiresSecondValue?: bool}
     * @throws InvalidArgumentException
     */
    public function getOperator(FieldType|string $type, string $operator): array
    {
        $typeValue = $type instanceof FieldType ? $type->value : $type;
        $operators = $this->getOperatorsForType($type);

        if (! array_key_exists($operator, $operators)) {
            throw new InvalidArgumentException(
                "Operator [{$operator}] is not valid for type [{$typeValue}]."
            );
        }

        return $operators[$operator];
    }

    /**
     * @return array<int, FieldType>
     */
    public function getSupportedTypes(): array
    {
        return FieldType::cases();
    }

    /**
     * @return array<string, string>
     */
    public function getOperatorLabels(FieldType|string $type): array
    {
        $operators = $this->getOperatorsForType($type);
        $labels = [];

        foreach ($operators as $key => $config) {
            $labels[$key] = $config['label'];
        }

        return $labels;
    }

    /**
     * @return array<FilterOperator>
     */
    public function getOperatorsEnumForType(FieldType $type): array
    {
        return FilterOperator::forFieldType($type);
    }
}
