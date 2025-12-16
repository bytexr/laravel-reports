<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Reportable Models
    |--------------------------------------------------------------------------
    |
    | This array contains the list of models that are allowed to be used
    | for reporting. Only models listed here and implementing the Reportable
    | interface will be available in the report builder.
    |
    */
    'models' => [
        // \App\Models\User::class,
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
];
