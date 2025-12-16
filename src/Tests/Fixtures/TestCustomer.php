<?php

declare(strict_types=1);

namespace ByteXR\DynamicReporter\Tests\Fixtures;

use ByteXR\DynamicReporter\Concerns\HasReportDefinitions;
use ByteXR\DynamicReporter\Contracts\Reportable;
use ByteXR\DynamicReporter\Support\Field;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Example customer model for testing relationships.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property \Illuminate\Support\Carbon $created_at
 */
class TestCustomer extends Model implements Reportable
{
    use HasReportDefinitions;

    protected $table = 'customers';

    protected $fillable = [
        'name',
        'email',
    ];

    /**
     * Get the orders for this customer.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(TestOrder::class, 'customer_id');
    }

    /**
     * Define the reportable fields for this model.
     *
     * @return array<int, Field>
     */
    protected static function reportFields(): array
    {
        return [
            Field::make('id')
                ->label('Customer ID')
                ->number()
                ->sortable(),

            Field::make('name')
                ->label('Customer Name')
                ->text()
                ->sortable()
                ->filterable(),

            Field::make('email')
                ->label('Email Address')
                ->text()
                ->sortable()
                ->filterable(),

            Field::make('created_at')
                ->label('Registered Date')
                ->datetime()
                ->sortable(),
        ];
    }

    /**
     * Override the default display name.
     */
    public static function getReportDisplayName(): string
    {
        return 'Customers';
    }
}
