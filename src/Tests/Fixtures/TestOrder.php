<?php

declare(strict_types=1);

namespace ByteXR\DynamicReporter\Tests\Fixtures;

use ByteXR\DynamicReporter\Concerns\HasReportDefinitions;
use ByteXR\DynamicReporter\Contracts\Reportable;
use ByteXR\DynamicReporter\Support\Field;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Example model demonstrating how to implement the Reportable interface.
 *
 * @property int $id
 * @property float $total
 * @property \Illuminate\Support\Carbon $created_at
 * @property int $customer_id
 */
class TestOrder extends Model implements Reportable
{
    use HasReportDefinitions;

    protected $table = 'orders';

    protected $fillable = [
        'total',
        'customer_id',
    ];

    protected $casts = [
        'total' => 'float',
    ];

    /**
     * Get the customer that owns the order.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(TestCustomer::class, 'customer_id');
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
                ->label('Order ID')
                ->number()
                ->sortable(),

            Field::make('total')
                ->label('Order Total')
                ->number()
                ->sortable()
                ->filterable(),

            Field::make('created_at')
                ->label('Order Date')
                ->datetime()
                ->sortable(),

            Field::make('customer_name')
                ->label('Customer Name')
                ->text()
                ->column('name')
                ->fromRelationship('customer')
                ->sortable(),
        ];
    }

    /**
     * Override the default display name.
     */
    public static function getReportDisplayName(): string
    {
        return 'Orders';
    }

    /**
     * Override the default description.
     */
    protected static function getReportDescription(): string
    {
        return 'Order records with customer information';
    }
}
