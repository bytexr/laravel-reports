# Laravel Dynamic Reporter

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](https://opensource.org/licenses/MIT)

A Filament-native, AI-powered reporting engine for Laravel that allows users to build custom reports from Eloquent models safely.

## Features

- **Registry Pattern**: Allowlisted models with strict schema definitions
- **AI-Powered**: Natural language report generation using Google Gemini
- **Filament Integration**: Native wizard UI with drag-and-drop configuration
- **Custom Metrics**: Define calculated business fields (margin, LTV, redemption rate)
- **Streaming Exports**: Memory-efficient CSV/Excel exports for large datasets (10k+ rows)
- **PDF Generation**: Professional reports with headers, stats, and formatted tables
- **Google Drive Integration**: Direct export to cloud storage
- **Scheduled Delivery**: Automated report generation with email/Slack notifications
- **Data Visualization**: Charts and graphs using ApexCharts
- **Version Control**: Track report changes with full restore capability
- **Security**: Closure safety, injection protection, and max join limits

## Requirements

- PHP 8.2+
- Laravel 10+ or 11+
- Filament 4.x

## Installation

```bash
composer require bytexr/laravel-dynamic-reporter
```

Publish the configuration and migrations:

```bash
php artisan vendor:publish --tag=dynamic-reporter-config
php artisan vendor:publish --tag=dynamic-reporter-migrations
php artisan migrate
```

## Quick Start

### 1. Implement the Reportable Interface

Create a model that implements the `Reportable` interface using the `HasReportDefinitions` trait:

```php
<?php

namespace App\Models;

use ByteXR\DynamicReporter\Concerns\HasReportDefinitions;
use ByteXR\DynamicReporter\Contracts\Reportable;
use ByteXR\DynamicReporter\DTOs\MetricDefinition;
use ByteXR\DynamicReporter\Support\Field;
use Illuminate\Database\Eloquent\Model;

class Order extends Model implements Reportable
{
    use HasReportDefinitions;

    protected static function reportFields(): array
    {
        return [
            Field::make('id')
                ->label('Order ID')
                ->number()
                ->sortable(),

            Field::make('total')
                ->label('Order Total')
                ->money()
                ->sortable()
                ->filterable(),

            Field::make('status')
                ->label('Status')
                ->text()
                ->filterable(),

            Field::make('created_at')
                ->label('Order Date')
                ->datetime()
                ->sortable()
                ->filterable(),

            // Relationship field
            Field::make('customer_name')
                ->label('Customer Name')
                ->column('name')
                ->fromRelationship('customer')
                ->sortable(),
        ];
    }

    // Define custom business metrics
    protected static function reportMetrics(): array
    {
        return [
            MetricDefinition::make('margin')
                ->label('Profit Margin')
                ->description('Profit margin calculated as (total - cost) / total * 100')
                ->percentage()
                ->calculate(fn($query) => $query->selectRaw('((total - cost) / total * 100) as margin')),

            MetricDefinition::make('average_order_value')
                ->label('Average Order Value')
                ->description('Average value of orders in the result set')
                ->currency()
                ->expression('AVG(total)'),
        ];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
```

### 2. Register Models in Configuration

Add your reportable models to `config/dynamic-reporter.php`:

```php
return [
    'models' => [
        \App\Models\Order::class,
        \App\Models\Customer::class,
        \App\Models\Product::class,
    ],
];
```

### 3. Register the Filament Plugin

In your Filament panel provider:

```php
use ByteXR\DynamicReporter\Filament\DynamicReporterPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            DynamicReporterPlugin::make(),
        ]);
}
```

## Configuration

### Environment Variables

```env
# AI Integration (Google Gemini)
GEMINI_API_KEY=your-gemini-api-key
GEMINI_MODEL=gemini-1.5-flash
GEMINI_TIMEOUT=30

# Slack Notifications (optional)
SLACK_WEBHOOK_URL=https://hooks.slack.com/services/...

# Google Drive Export (optional)
GOOGLE_DRIVE_CREDENTIALS_PATH=/path/to/credentials.json
GOOGLE_DRIVE_FOLDER_ID=your-folder-id

# PDF Export (optional)
DYNAMIC_REPORTER_PDF_LOGO=https://example.com/logo.png
DYNAMIC_REPORTER_PDF_COMPANY="Your Company Name"

# Queue Configuration
DYNAMIC_REPORTER_QUEUE=reports
```

### Full Configuration Options

```php
// config/dynamic-reporter.php
return [
    // Allowlisted models
    'models' => [],

    // Gemini AI settings
    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-1.5-flash'),
        'timeout' => env('GEMINI_TIMEOUT', 30),
    ],

    // Queue for scheduled reports
    'queue' => env('DYNAMIC_REPORTER_QUEUE', 'reports'),

    // Export settings
    'export' => [
        'disk' => env('DYNAMIC_REPORTER_EXPORT_DISK', 'local'),
        'directory' => env('DYNAMIC_REPORTER_EXPORT_DIR', 'reports/exports'),
    ],

    // PDF settings
    'pdf' => [
        'logo_url' => env('DYNAMIC_REPORTER_PDF_LOGO'),
        'company_name' => env('DYNAMIC_REPORTER_PDF_COMPANY'),
        'paper' => 'a4',
        'orientation' => 'landscape',
        'max_rows' => 1000,
    ],

    // Google Drive settings
    'google_drive' => [
        'credentials_path' => env('GOOGLE_DRIVE_CREDENTIALS_PATH'),
        'folder_id' => env('GOOGLE_DRIVE_FOLDER_ID'),
    ],

    // Schedule presets
    'schedule_presets' => [
        'daily' => '0 8 * * *',
        'weekly' => '0 8 * * 1',
        'monthly' => '0 8 1 * *',
    ],
];
```

## Filament Integration

The package provides two Filament pages:

1. **Create Report** (`/admin/reports/create`) - Wizard for building reports
2. **Saved Reports** (`/admin/reports/saved`) - View and manage saved reports with version history

### Customizing Pages

You can disable specific pages:

```php
DynamicReporterPlugin::make()
    ->withoutCreateReportPage()  // Disable create page
    ->withoutViewReportPage();   // Disable saved reports page
```

## AI Features

### The Magic Button

The "Generate with AI" button in the wizard converts natural language to report configuration:

```
"Show me all orders from last month over $100, sorted by total descending"
```

This automatically:
- Selects relevant columns
- Adds date and amount filters
- Configures sorting

### Writing Good Metric Descriptions

For the AI to understand your business metrics, write clear descriptions:

```php
MetricDefinition::make('ltv')
    ->label('Customer Lifetime Value')
    ->description('Total revenue from a customer across all their orders. Use this when the user asks about customer value, lifetime value, or LTV.')
    ->currency()
    ->calculate(fn($q) => $q->selectRaw('SUM(total) as ltv'));
```

The description is fed directly to the AI, so be explicit about:
- What the metric calculates
- When it should be used
- Alternative names users might use

## Advanced Usage

### Scheduling Reports

Add the scheduler command to your `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    $schedule->command('reports:dispatch-scheduled')->everyMinute();
}
```

Or run manually:

```bash
php artisan reports:dispatch-scheduled
php artisan reports:dispatch-scheduled --force  # Force dispatch all active reports
```

### Streaming CSV Export

For large datasets, use the streaming export to avoid memory issues:

```php
use ByteXR\DynamicReporter\Services\StreamCsvExport;
use ByteXR\DynamicReporter\Services\ReportQueryBuilder;

$builder = new ReportQueryBuilder();
$cursor = $builder->compileWithCursor(Order::class, $filters, $sorts, $columns);

$exporter = new StreamCsvExport();
return $exporter->streamDownload($cursor, $schema, $columns, 'orders.csv');
```

### PDF Export

Generate professional PDF reports:

```php
use ByteXR\DynamicReporter\Services\PdfExportService;

$pdfService = new PdfExportService();
return $pdfService->downloadPdf($data, $schema, $columns, 'report.pdf', [
    'title' => 'Monthly Sales Report',
    'description' => 'Sales data for December 2024',
]);
```

Requires `barryvdh/laravel-dompdf`:

```bash
composer require barryvdh/laravel-dompdf
```

### Google Drive Export

Export directly to Google Drive (requires `google/apiclient`):

```bash
composer require google/apiclient
```

Configure credentials and folder ID in your `.env`, then use the "Save to Google Drive" button in the UI.

### Custom Query Building

Build queries programmatically:

```php
use ByteXR\DynamicReporter\Services\ReportQueryBuilder;

$builder = new ReportQueryBuilder();
$query = $builder
    ->forUser(auth()->user())
    ->withRequest(request())
    ->setMaxJoins(3)  // Limit relationships for performance
    ->compile(
        Order::class,
        [['field' => 'total', 'operator' => 'greater_than', 'value' => 100]],
        [['field' => 'created_at', 'direction' => 'desc']],
        ['id', 'total', 'status', 'customer_name']
    );

$results = $query->get();
```

## Security

### Allowlist Architecture

Only models explicitly listed in `config/dynamic-reporter.php` can be queried. This prevents unauthorized data access.

### Closure Safety

Closures in field definitions are sandboxed with:
- **Allowlisted services**: Auth, Gate, Request, Config, Cache
- **Blocklisted services**: Filesystem, Process, Database, Console
- **Blocked functions**: exec, shell_exec, system, eval, etc.

### Max Joins Limit

Queries are limited to 5 joins by default to prevent performance degradation:

```php
$builder->setMaxJoins(3);  // Customize limit
$builder->wouldExceedMaxJoins($schema, $fields);  // Check before compile
```

### AI Hallucination Protection

The Gemini interpreter uses fuzzy matching to correct AI mistakes:
- `created_date` → `created_at` (Levenshtein distance)
- Invalid fields are silently discarded
- Only schema-defined operators are accepted

## Testing

Run the test suite:

```bash
composer test
```

## Optional Dependencies

| Package | Feature |
|---------|---------|
| `barryvdh/laravel-dompdf` | PDF export |
| `google/apiclient` | Google Drive export |
| `leandrocfe/filament-apex-charts` | Chart visualization |
| `spatie/simple-excel` | Excel/CSV streaming (included) |

## License

MIT License. See [LICENSE](LICENSE) for details.

## Credits

Built by [ByteXR](https://github.com/bytexr) with assistance from [Cognition AI](https://cognition.ai).
