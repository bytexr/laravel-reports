<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Reportable Models
    |--------------------------------------------------------------------------
    |
    | This array contains the list of models that are allowed to be used
    | for reporting. Models can use either:
    | - The #[ReportDefinition] attribute (recommended)
    | - The legacy Reportable trait/interface
    |
    | Both approaches are supported for backward compatibility.
    |
    */
    'models' => [
        // \App\Models\User::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Report Templates
    |--------------------------------------------------------------------------
    |
    | Register custom report templates that provide pre-configured report
    | setups. Templates help users quickly create common reports without
    | starting from scratch.
    |
    */
    'templates' => [
        // \App\Reports\Templates\OrdersOverviewTemplate::class,
        // \App\Reports\Templates\CustomerListTemplate::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Relationship Settings
    |--------------------------------------------------------------------------
    |
    | Configure how relationships are handled in the report builder.
    |
    */
    'relationships' => [
        'max_depth' => env('DYNAMIC_REPORTER_MAX_RELATIONSHIP_DEPTH', 3),
        'warn_at_depth' => env('DYNAMIC_REPORTER_WARN_RELATIONSHIP_DEPTH', 2),
        'excluded_relations' => [
            'pivot',
            'media',
            'notifications',
            'tokens',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | UI Settings
    |--------------------------------------------------------------------------
    |
    | Configure the report builder user interface.
    |
    */
    'ui' => [
        'wizard_enabled' => true,
        'show_advanced_options' => false,
        'preview_limit' => 10,
        'field_examples_enabled' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Model Classes
    |--------------------------------------------------------------------------
    |
    | These settings allow you to customize the model classes used by the
    | package. This is useful if you need to extend the default models
    | with your own custom functionality.
    |
    */
    'model_classes' => [
        'saved_report' => \ByteXR\DynamicReporter\Models\SavedReport::class,
        'report_version' => \ByteXR\DynamicReporter\Models\ReportVersion::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Gemini AI Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the Google Gemini AI integration that powers
    | natural language report generation.
    |
    */
    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-1.5-flash'),
        'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
        'timeout' => env('GEMINI_TIMEOUT', 30),
        'max_tokens' => env('GEMINI_MAX_TOKENS', 2048),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the queue used by scheduled report jobs.
    |
    */
    'queue' => env('DYNAMIC_REPORTER_QUEUE', 'reports'),

    /*
    |--------------------------------------------------------------------------
    | Export Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for CSV export functionality.
    |
    */
    'export' => [
        'disk' => env('DYNAMIC_REPORTER_EXPORT_DISK', 'local'),
        'directory' => env('DYNAMIC_REPORTER_EXPORT_DIR', 'reports/exports'),
        'cleanup_after_hours' => env('DYNAMIC_REPORTER_CLEANUP_HOURS', 24),
    ],

    /*
    |--------------------------------------------------------------------------
    | Schedule Presets
    |--------------------------------------------------------------------------
    |
    | Common cron schedule presets for the UI.
    |
    */
    'schedule_presets' => [
        'daily' => '0 8 * * *',
        'weekly' => '0 8 * * 1',
        'monthly' => '0 8 1 * *',
        'quarterly' => '0 8 1 1,4,7,10 *',
    ],

    /*
    |--------------------------------------------------------------------------
    | Google Drive Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Google Drive export functionality.
    | Requires google/apiclient package to be installed.
    |
    */
    'google_drive' => [
        'credentials_path' => env('GOOGLE_DRIVE_CREDENTIALS_PATH'),
        'folder_id' => env('GOOGLE_DRIVE_FOLDER_ID'),
        'access_token' => env('GOOGLE_DRIVE_ACCESS_TOKEN'),
        'chunk_size' => env('GOOGLE_DRIVE_CHUNK_SIZE', 5 * 1024 * 1024),
    ],

    /*
    |--------------------------------------------------------------------------
    | PDF Export Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for PDF export functionality.
    | Requires barryvdh/laravel-dompdf package to be installed.
    |
    */
    'pdf' => [
        'logo_url' => env('DYNAMIC_REPORTER_PDF_LOGO'),
        'company_name' => env('DYNAMIC_REPORTER_PDF_COMPANY', env('APP_NAME')),
        'paper' => env('DYNAMIC_REPORTER_PDF_PAPER', 'a4'),
        'orientation' => env('DYNAMIC_REPORTER_PDF_ORIENTATION', 'landscape'),
        'max_rows' => env('DYNAMIC_REPORTER_PDF_MAX_ROWS', 1000),
    ],
];
