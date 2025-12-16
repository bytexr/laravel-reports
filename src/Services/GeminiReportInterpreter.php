<?php

declare(strict_types=1);

namespace ByteXR\DynamicReporter\Services;

use ByteXR\DynamicReporter\DTOs\FieldDefinition;
use ByteXR\DynamicReporter\DTOs\ReportConfig;
use ByteXR\DynamicReporter\DTOs\ReportSchema;
use ByteXR\DynamicReporter\Exceptions\GeminiException;
use ByteXR\DynamicReporter\Models\SavedReport;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class GeminiReportInterpreter
{
    protected string $apiKey;

    protected string $model;

    protected string $baseUrl;

    protected int $timeout;

    protected int $maxTokens;

    protected FilterFactory $filterFactory;

    public function __construct(?FilterFactory $filterFactory = null)
    {
        $this->apiKey = (string) config('dynamic-reporter.gemini.api_key', '');
        $this->model = (string) config('dynamic-reporter.gemini.model', 'gemini-1.5-flash');
        $this->baseUrl = (string) config('dynamic-reporter.gemini.base_url', 'https://generativelanguage.googleapis.com/v1beta');
        $this->timeout = (int) config('dynamic-reporter.gemini.timeout', 30);
        $this->maxTokens = (int) config('dynamic-reporter.gemini.max_tokens', 2048);
        $this->filterFactory = $filterFactory ?? new FilterFactory();
    }

    /**
     * Interpret a natural language prompt and convert it to a ReportConfig.
     *
     * @throws GeminiException
     * @throws InvalidArgumentException
     */
    public function interpret(string $userPrompt, ReportSchema $schema): ReportConfig
    {
        $this->validateApiKey();

        $systemPrompt = $this->buildSystemPrompt($schema);
        $response = $this->callGeminiApi($systemPrompt, $userPrompt);
        $parsedConfig = $this->parseResponse($response);

        return $this->validateAndBuildConfig($parsedConfig, $schema);
    }

    /**
     * Interpret and return raw array instead of DTO.
     *
     * @return array<string, mixed>
     * @throws GeminiException
     * @throws InvalidArgumentException
     */
    public function interpretToArray(string $userPrompt, ReportSchema $schema): array
    {
        return $this->interpret($userPrompt, $schema)->toArray();
    }

    /**
     * Build the system prompt with schema information.
     */
    protected function buildSystemPrompt(ReportSchema $schema): string
    {
        $schemaDescription = $this->buildSchemaDescription($schema);
        $operatorsDescription = $this->buildOperatorsDescription($schema);
        $metricsDescription = $this->buildMetricsDescription($schema);

        $metricsSection = '';
        if (! empty($metricsDescription)) {
            $metricsSection = <<<METRICS

## Available Business Metrics
Use these keys if the user's intent matches the description. These are calculated fields that provide business insights:
{$metricsDescription}
METRICS;
        }

        return <<<PROMPT
You are a report configuration assistant. Your task is to convert natural language requests into a structured JSON configuration for generating reports.

## Available Schema: {$schema->name}
{$schema->description}

## Available Fields:
{$schemaDescription}
{$metricsSection}

## Available Filter Operators by Field Type:
{$operatorsDescription}

## Output Format
You must respond with ONLY valid JSON (no markdown, no explanation) matching this exact structure:
{
    "columns": ["field_name1", "field_name2"],
    "filters": [
        {"field": "field_name", "operator": "operator_name", "value": "value", "value2": "optional_second_value"}
    ],
    "sort": [
        {"field": "field_name", "direction": "asc|desc"}
    ],
    "group_by": ["field_name"],
    "limit": null
}

## Rules:
1. Only use field names that exist in the Available Fields or Available Business Metrics lists above.
2. Only use operators that are valid for the field's type.
3. For "between" operator, include both "value" and "value2".
4. For date filters, use ISO 8601 format (YYYY-MM-DD or YYYY-MM-DD HH:MM:SS).
5. If the user doesn't specify columns, include all available fields.
6. If the user doesn't specify sorting, leave the sort array empty.
7. If the user mentions "last month", "this week", etc., calculate the appropriate date range.
8. The "limit" field should be null unless the user specifies a number of records.
9. Only include fields in "group_by" if the user explicitly asks for grouping or aggregation.
10. When the user asks for business concepts (like "margin", "LTV", "redemption rate"), use the matching metric key from Available Business Metrics.

Respond with ONLY the JSON object, nothing else.
PROMPT;
    }

    /**
     * Build a description of available business metrics for AI context.
     */
    protected function buildMetricsDescription(ReportSchema $schema): string
    {
        if (empty($schema->metrics)) {
            return '';
        }

        $lines = [];

        foreach ($schema->metrics as $metric) {
            $type = $metric->getType();
            $description = $metric->getDescription();
            $label = $metric->getLabel();

            $lines[] = "- {$metric->getName()} ({$type}): {$label}";

            if (! empty($description)) {
                $lines[] = "  Description: {$description}";
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Build a clean description of the schema fields.
     */
    protected function buildSchemaDescription(ReportSchema $schema): string
    {
        $lines = [];

        foreach ($schema->fields as $field) {
            $attributes = [];

            if ($field->isSortable) {
                $attributes[] = 'sortable';
            }

            if ($field->isFilterable) {
                $attributes[] = 'filterable';
            }

            if ($field->isRelationship()) {
                $attributes[] = "relation:{$field->relationship}";
            }

            $attributeStr = ! empty($attributes) ? ' [' . implode(', ', $attributes) . ']' : '';
            $lines[] = "- {$field->name} ({$field->type}): {$field->label}{$attributeStr}";
        }

        return implode("\n", $lines);
    }

    /**
     * Build a description of available operators for each field type.
     */
    protected function buildOperatorsDescription(ReportSchema $schema): string
    {
        $types = [];

        foreach ($schema->fields as $field) {
            if (! isset($types[$field->type])) {
                $types[$field->type] = true;
            }
        }

        $lines = [];

        foreach (array_keys($types) as $type) {
            $operators = $this->filterFactory->getOperatorLabels($type);
            $operatorList = implode(', ', array_keys($operators));
            $lines[] = "- {$type}: {$operatorList}";
        }

        return implode("\n", $lines);
    }

    /**
     * Call the Gemini API.
     *
     * @throws GeminiException
     */
    protected function callGeminiApi(string $systemPrompt, string $userPrompt): string
    {
        $url = "{$this->baseUrl}/models/{$this->model}:generateContent";

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                ])
                ->withQueryParameters([
                    'key' => $this->apiKey,
                ])
                ->post($url, [
                    'contents' => [
                        [
                            'role' => 'user',
                            'parts' => [
                                ['text' => $systemPrompt . "\n\nUser Request: " . $userPrompt],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'temperature' => 0.1,
                        'maxOutputTokens' => $this->maxTokens,
                        'responseMimeType' => 'application/json',
                    ],
                ]);

            if (! $response->successful()) {
                $error = $response->json('error.message', 'Unknown error');
                throw new GeminiException("Gemini API error: {$error}", $response->status());
            }

            $content = $response->json('candidates.0.content.parts.0.text');

            if (empty($content)) {
                throw new GeminiException('Empty response from Gemini API');
            }

            return $content;
        } catch (ConnectionException $e) {
            throw new GeminiException("Failed to connect to Gemini API: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Parse the JSON response from Gemini.
     *
     * @return array<string, mixed>
     * @throws GeminiException
     */
    protected function parseResponse(string $response): array
    {
        $response = trim($response);

        if (str_starts_with($response, '```json')) {
            $response = substr($response, 7);
        }

        if (str_starts_with($response, '```')) {
            $response = substr($response, 3);
        }

        if (str_ends_with($response, '```')) {
            $response = substr($response, 0, -3);
        }

        $response = trim($response);

        $decoded = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new GeminiException('Failed to parse Gemini response as JSON: ' . json_last_error_msg());
        }

        if (! is_array($decoded)) {
            throw new GeminiException('Gemini response is not a valid configuration object');
        }

        return $decoded;
    }

    /**
     * Validate the parsed config against the schema and build a ReportConfig.
     *
     * @param array<string, mixed> $config
     * @throws InvalidArgumentException
     */
    protected function validateAndBuildConfig(array $config, ReportSchema $schema): ReportConfig
    {
        $validFieldNames = $schema->getFieldNames();
        $validMetricNames = $schema->getMetricNames();
        $allValidNames = array_merge($validFieldNames, $validMetricNames);

        $columns = $this->validateColumns($config['columns'] ?? [], $allValidNames);
        $filters = $this->validateFilters($config['filters'] ?? [], $schema);
        $sort = $this->validateSort($config['sort'] ?? [], $schema);
        $groupBy = $this->validateGroupBy($config['group_by'] ?? [], $validFieldNames);
        $limit = isset($config['limit']) && is_numeric($config['limit']) ? (int) $config['limit'] : null;

        return new ReportConfig(
            columns: $columns,
            filters: $filters,
            sort: $sort,
            groupBy: $groupBy,
            limit: $limit,
        );
    }

    /**
     * Validate and filter columns to only include valid field names.
     * Uses fuzzy matching to correct AI hallucinations.
     *
     * @param array<int, mixed> $columns
     * @param array<int, string> $validFieldNames
     * @return array<int, string>
     */
    protected function validateColumns(array $columns, array $validFieldNames): array
    {
        if (empty($columns)) {
            return $validFieldNames;
        }

        $validatedColumns = [];

        foreach ($columns as $col) {
            if (! is_string($col)) {
                continue;
            }

            $matchedField = $this->fuzzyMatchField($col, $validFieldNames);

            if ($matchedField !== null && ! in_array($matchedField, $validatedColumns, true)) {
                $validatedColumns[] = $matchedField;
            }
        }

        return $validatedColumns;
    }

    /**
     * Fuzzy match a field name against valid field names.
     * Returns the matched field name or null if no good match found.
     *
     * @param array<int, string> $validFieldNames
     */
    protected function fuzzyMatchField(string $input, array $validFieldNames): ?string
    {
        $input = strtolower(trim($input));

        if (in_array($input, array_map('strtolower', $validFieldNames), true)) {
            foreach ($validFieldNames as $validName) {
                if (strtolower($validName) === $input) {
                    return $validName;
                }
            }
        }

        $bestMatch = null;
        $bestDistance = PHP_INT_MAX;
        $inputLength = strlen($input);

        foreach ($validFieldNames as $validName) {
            $validNameLower = strtolower($validName);
            $distance = levenshtein($input, $validNameLower);

            $threshold = max(2, (int) ceil($inputLength * 0.3));

            if ($distance < $bestDistance && $distance <= $threshold) {
                $bestDistance = $distance;
                $bestMatch = $validName;
            }

            if (str_contains($validNameLower, $input) || str_contains($input, $validNameLower)) {
                if ($distance < $bestDistance || $bestMatch === null) {
                    $bestDistance = $distance;
                    $bestMatch = $validName;
                }
            }
        }

        return $bestMatch;
    }

    /**
     * Validate and filter filters to only include valid configurations.
     * Uses fuzzy matching to correct AI hallucinations in field names.
     *
     * @param array<int, mixed> $filters
     * @return array<int, array{field: string, operator: string, value: mixed, value2?: mixed}>
     */
    protected function validateFilters(array $filters, ReportSchema $schema): array
    {
        $validFilters = [];
        $validFieldNames = $schema->getFieldNames();

        foreach ($filters as $filter) {
            if (! is_array($filter)) {
                continue;
            }

            $fieldName = $filter['field'] ?? null;

            if (! is_string($fieldName)) {
                continue;
            }

            $matchedFieldName = $this->fuzzyMatchField($fieldName, $validFieldNames);

            if ($matchedFieldName === null) {
                continue;
            }

            $field = $schema->getField($matchedFieldName);

            if ($field === null || ! $field->isFilterable) {
                continue;
            }

            $operator = $filter['operator'] ?? null;

            if (! is_string($operator) || ! $this->filterFactory->isValidOperator($field->type, $operator)) {
                continue;
            }

            $validFilter = [
                'field' => $matchedFieldName,
                'operator' => $operator,
                'value' => $filter['value'] ?? null,
            ];

            if (isset($filter['value2'])) {
                $validFilter['value2'] = $filter['value2'];
            }

            $validFilters[] = $validFilter;
        }

        return $validFilters;
    }

    /**
     * Validate and filter sort configurations.
     * Uses fuzzy matching to correct AI hallucinations in field names.
     *
     * @param array<int, mixed> $sorts
     * @return array<int, array{field: string, direction: string}>
     */
    protected function validateSort(array $sorts, ReportSchema $schema): array
    {
        $validSorts = [];
        $validFieldNames = $schema->getFieldNames();

        foreach ($sorts as $sort) {
            if (! is_array($sort)) {
                continue;
            }

            $fieldName = $sort['field'] ?? null;

            if (! is_string($fieldName)) {
                continue;
            }

            $matchedFieldName = $this->fuzzyMatchField($fieldName, $validFieldNames);

            if ($matchedFieldName === null) {
                continue;
            }

            $field = $schema->getField($matchedFieldName);

            if ($field === null || ! $field->isSortable) {
                continue;
            }

            $direction = strtolower((string) ($sort['direction'] ?? 'asc'));

            if (! in_array($direction, ['asc', 'desc'], true)) {
                $direction = 'asc';
            }

            $validSorts[] = [
                'field' => $matchedFieldName,
                'direction' => $direction,
            ];
        }

        return $validSorts;
    }

    /**
     * Validate and filter group by fields.
     *
     * @param array<int, mixed> $groupBy
     * @param array<int, string> $validFieldNames
     * @return array<int, string>
     */
    protected function validateGroupBy(array $groupBy, array $validFieldNames): array
    {
        return array_values(array_filter(
            $groupBy,
            static fn (mixed $field): bool => is_string($field) && in_array($field, $validFieldNames, true),
        ));
    }

    /**
     * Validate that the API key is configured.
     *
     * @throws GeminiException
     */
    protected function validateApiKey(): void
    {
        if (empty($this->apiKey)) {
            throw new GeminiException(
                'Gemini API key is not configured. Set GEMINI_API_KEY in your environment or config.'
            );
        }
    }

    /**
     * Set a custom API key (useful for testing).
     */
    public function setApiKey(string $apiKey): self
    {
        $this->apiKey = $apiKey;

        return $this;
    }

    /**
     * Set a custom model.
     */
    public function setModel(string $model): self
    {
        $this->model = $model;

        return $this;
    }

    /**
     * Interpret a natural language prompt and suggest chart configuration.
     *
     * @return array{chart_type: string|null, chart_config: array{x_axis: string|null, y_axis: string|null, title: string|null}}
     * @throws GeminiException
     */
    public function interpretWithChart(string $userPrompt, ReportSchema $schema): array
    {
        $this->validateApiKey();

        $systemPrompt = $this->buildChartSystemPrompt($schema);
        $response = $this->callGeminiApi($systemPrompt, $userPrompt);
        $parsedConfig = $this->parseResponse($response);

        return $this->validateChartConfig($parsedConfig, $schema);
    }

    /**
     * Build the system prompt for chart suggestions.
     */
    protected function buildChartSystemPrompt(ReportSchema $schema): string
    {
        $schemaDescription = $this->buildSchemaDescription($schema);
        $chartTypes = implode(', ', array_keys(SavedReport::CHART_TYPES));

        return <<<PROMPT
You are a data visualization assistant. Your task is to suggest the best chart type and configuration based on a natural language request and available data fields.

## Available Schema: {$schema->name}
{$schema->description}

## Available Fields:
{$schemaDescription}

## Available Chart Types:
{$chartTypes}

## Chart Type Selection Guidelines:
- **line**: Best for time-series data, trends over time, continuous data. Use when X-axis is date/datetime.
- **bar**: Best for comparing categories, discrete data, rankings. Use when X-axis is text/categorical.
- **pie**: Best for showing proportions of a whole, distribution. Use when you have a single category and value. Limited to 5-10 items.
- **area**: Similar to line but emphasizes volume/magnitude over time.
- **donut**: Similar to pie but with a hole in the center, good for percentages.

## Output Format
You must respond with ONLY valid JSON (no markdown, no explanation) matching this exact structure:
{
    "chart_type": "line|bar|pie|area|donut",
    "chart_config": {
        "x_axis": "field_name_for_x_axis",
        "y_axis": "field_name_for_y_axis",
        "title": "Suggested chart title"
    },
    "columns": ["field_name1", "field_name2"],
    "filters": [],
    "sort": [],
    "group_by": []
}

## Rules:
1. Only use field names that exist in the Available Fields list.
2. For pie/donut charts, x_axis should be a categorical field and y_axis should be numeric.
3. For line/area charts, prefer date/datetime fields for x_axis.
4. For bar charts, x_axis can be categorical or date, y_axis should be numeric.
5. If grouping by date, suggest line or area chart.
6. If grouping by category with few items (<10), suggest pie or bar chart.
7. If grouping by category with many items (>10), suggest bar chart.
8. The title should be descriptive and based on the user's request.

Respond with ONLY the JSON object, nothing else.
PROMPT;
    }

    /**
     * Validate and extract chart configuration from parsed response.
     *
     * @param array<string, mixed> $config
     * @return array{chart_type: string|null, chart_config: array{x_axis: string|null, y_axis: string|null, title: string|null}, columns: array<int, string>, filters: array<int, mixed>, sort: array<int, mixed>, group_by: array<int, string>}
     */
    protected function validateChartConfig(array $config, ReportSchema $schema): array
    {
        $validFieldNames = $schema->getFieldNames();

        $chartType = $config['chart_type'] ?? null;
        if (! is_string($chartType) || ! array_key_exists($chartType, SavedReport::CHART_TYPES)) {
            $chartType = $this->suggestChartTypeFromSchema($schema, $config['group_by'] ?? []);
        }

        $chartConfig = $config['chart_config'] ?? [];
        $xAxis = $this->fuzzyMatchField($chartConfig['x_axis'] ?? '', $validFieldNames);
        $yAxis = $this->fuzzyMatchField($chartConfig['y_axis'] ?? '', $validFieldNames);
        $title = is_string($chartConfig['title'] ?? null) ? $chartConfig['title'] : null;

        $columns = $this->validateColumns($config['columns'] ?? [], $validFieldNames);
        $filters = $this->validateFilters($config['filters'] ?? [], $schema);
        $sort = $this->validateSort($config['sort'] ?? [], $schema);
        $groupBy = $this->validateGroupBy($config['group_by'] ?? [], $validFieldNames);

        return [
            'chart_type' => $chartType,
            'chart_config' => [
                'x_axis' => $xAxis,
                'y_axis' => $yAxis,
                'title' => $title,
            ],
            'columns' => $columns,
            'filters' => $filters,
            'sort' => $sort,
            'group_by' => $groupBy,
        ];
    }

    /**
     * Suggest a chart type based on schema field types and grouping.
     *
     * @param array<int, mixed> $groupBy
     */
    public function suggestChartTypeFromSchema(ReportSchema $schema, array $groupBy = []): string
    {
        if (empty($groupBy)) {
            $dateFields = array_filter(
                $schema->fields,
                static fn ($field): bool => in_array($field->type, ['date', 'datetime'], true),
            );

            if (! empty($dateFields)) {
                return SavedReport::CHART_TYPE_LINE;
            }

            return SavedReport::CHART_TYPE_BAR;
        }

        $firstGroupField = is_string($groupBy[0] ?? null) ? $groupBy[0] : null;

        if ($firstGroupField === null) {
            return SavedReport::CHART_TYPE_BAR;
        }

        $groupField = $schema->getField($firstGroupField);

        if ($groupField === null) {
            return SavedReport::CHART_TYPE_BAR;
        }

        return match ($groupField->type) {
            'date', 'datetime' => SavedReport::CHART_TYPE_LINE,
            'boolean' => SavedReport::CHART_TYPE_PIE,
            'text' => count($groupBy) === 1 ? SavedReport::CHART_TYPE_PIE : SavedReport::CHART_TYPE_BAR,
            default => SavedReport::CHART_TYPE_BAR,
        };
    }

    /**
     * Suggest chart axes based on schema fields.
     *
     * @return array{x_axis: string|null, y_axis: string|null}
     */
    public function suggestChartAxes(ReportSchema $schema): array
    {
        $xAxis = null;
        $yAxis = null;

        foreach ($schema->fields as $field) {
            if ($xAxis === null && in_array($field->type, ['date', 'datetime', 'text'], true)) {
                $xAxis = $field->name;
            }

            if ($yAxis === null && in_array($field->type, ['number', 'money'], true)) {
                $yAxis = $field->name;
            }

            if ($xAxis !== null && $yAxis !== null) {
                break;
            }
        }

        return [
            'x_axis' => $xAxis,
            'y_axis' => $yAxis,
        ];
    }
}
