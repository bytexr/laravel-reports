<?php

declare(strict_types=1);

namespace ByteXR\DynamicReporter\Enums;

enum FilterOperator: string
{
    case Equals = 'equals';
    case NotEquals = 'not_equals';
    case Like = 'like';
    case NotLike = 'not_like';
    case StartsWith = 'starts_with';
    case EndsWith = 'ends_with';
    case GreaterThan = 'greater_than';
    case GreaterThanOrEqual = 'greater_than_or_equal';
    case LessThan = 'less_than';
    case LessThanOrEqual = 'less_than_or_equal';
    case Between = 'between';
    case DateEquals = 'date_equals';
    case After = 'after';
    case AfterOrEqual = 'after_or_equal';
    case Before = 'before';
    case BeforeOrEqual = 'before_or_equal';
    case IsTrue = 'is_true';
    case IsFalse = 'is_false';
    case IsNull = 'is_null';
    case IsNotNull = 'is_not_null';

    public function label(): string
    {
        return match ($this) {
            self::Equals => 'Equals',
            self::NotEquals => 'Not Equals',
            self::Like => 'Contains',
            self::NotLike => 'Does Not Contain',
            self::StartsWith => 'Starts With',
            self::EndsWith => 'Ends With',
            self::GreaterThan => 'Greater Than',
            self::GreaterThanOrEqual => 'Greater Than or Equal',
            self::LessThan => 'Less Than',
            self::LessThanOrEqual => 'Less Than or Equal',
            self::Between => 'Between',
            self::DateEquals => 'On Date',
            self::After => 'After',
            self::AfterOrEqual => 'On or After',
            self::Before => 'Before',
            self::BeforeOrEqual => 'On or Before',
            self::IsTrue => 'Is True',
            self::IsFalse => 'Is False',
            self::IsNull => 'Is Empty',
            self::IsNotNull => 'Is Not Empty',
        };
    }

    public function sql(): string
    {
        return match ($this) {
            self::Equals => '=',
            self::NotEquals => '!=',
            self::Like, self::StartsWith, self::EndsWith => 'LIKE',
            self::NotLike => 'NOT LIKE',
            self::GreaterThan, self::After => '>',
            self::GreaterThanOrEqual, self::AfterOrEqual => '>=',
            self::LessThan, self::Before => '<',
            self::LessThanOrEqual, self::BeforeOrEqual => '<=',
            self::Between => 'BETWEEN',
            self::DateEquals => '=',
            self::IsTrue, self::IsFalse => '=',
            self::IsNull => 'IS NULL',
            self::IsNotNull => 'IS NOT NULL',
        };
    }

    public function requiresValue(): bool
    {
        return match ($this) {
            self::IsNull, self::IsNotNull, self::IsTrue, self::IsFalse => false,
            default => true,
        };
    }

    public function requiresSecondValue(): bool
    {
        return $this === self::Between;
    }

    public static function forFieldType(FieldType $type): array
    {
        return match ($type) {
            FieldType::Text => [
                self::Equals,
                self::NotEquals,
                self::Like,
                self::NotLike,
                self::StartsWith,
                self::EndsWith,
                self::IsNull,
                self::IsNotNull,
            ],
            FieldType::Number, FieldType::Money => [
                self::Equals,
                self::NotEquals,
                self::GreaterThan,
                self::GreaterThanOrEqual,
                self::LessThan,
                self::LessThanOrEqual,
                self::Between,
                self::IsNull,
                self::IsNotNull,
            ],
            FieldType::Date, FieldType::Datetime => [
                self::DateEquals,
                self::After,
                self::AfterOrEqual,
                self::Before,
                self::BeforeOrEqual,
                self::Between,
                self::IsNull,
                self::IsNotNull,
            ],
            FieldType::Boolean => [
                self::IsTrue,
                self::IsFalse,
                self::IsNull,
                self::IsNotNull,
            ],
        };
    }

    public static function optionsForFieldType(FieldType $type): array
    {
        $options = [];
        foreach (self::forFieldType($type) as $operator) {
            $options[$operator->value] = $operator->label();
        }

        return $options;
    }
}
