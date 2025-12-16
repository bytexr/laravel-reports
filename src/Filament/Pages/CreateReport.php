<?php

declare(strict_types=1);

namespace ByteXR\DynamicReporter\Filament\Pages;

use ByteXR\DynamicReporter\Contracts\Reportable;
use ByteXR\DynamicReporter\Drivers\GoogleDriveExportDriver;
use ByteXR\DynamicReporter\DTOs\FieldDefinition;
use ByteXR\DynamicReporter\DTOs\ReportSchema;
use ByteXR\DynamicReporter\Exceptions\GeminiException;
use ByteXR\DynamicReporter\Jobs\ExportReportToExternalService;
use ByteXR\DynamicReporter\Models\SavedReport;
use ByteXR\DynamicReporter\ReportableRegistry;
use ByteXR\DynamicReporter\Services\FilterFactory;
use ByteXR\DynamicReporter\Services\GeminiReportInterpreter;
use ByteXR\DynamicReporter\Services\ReportQueryBuilder;
use ByteXR\DynamicReporter\Services\StreamCsvExport;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Components\Wizard\Step;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

class CreateReport extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static ?string $navigationLabel = 'Create Report';

    protected static ?string $title = 'Create Report';

    protected static ?string $slug = 'reports/create';

    protected static string $view = 'dynamic-reporter::filament.pages.create-report';

    public ?array $data = [];

    public ?string $aiPrompt = null;

    public ?array $previewData = null;

    protected FilterFactory $filterFactory;

    public function boot(): void
    {
        $this->filterFactory = new FilterFactory();
    }

    public function mount(): void
    {
        $this->form->fill([
            'model' => null,
            'columns' => [],
            'filters' => [],
            'sorts' => [],
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                $this->getAiSection(),
                $this->getWizard(),
            ])
            ->statePath('data');
    }

    protected function getAiSection(): Section
    {
        return Section::make('AI Report Generator')
            ->description('Describe your report in natural language and let AI configure it for you.')
            ->schema([
                Textarea::make('ai_prompt')
                    ->label('Describe your report...')
                    ->placeholder('e.g., "Show me all orders from last month over $100, sorted by total descending"')
                    ->rows(3)
                    ->columnSpanFull(),
                Actions::make([
                    Action::make('generateWithAi')
                        ->label('Generate with AI')
                        ->icon('heroicon-o-sparkles')
                        ->color('primary')
                        ->action(function (Get $get, Set $set): void {
                            $this->generateWithAi($get, $set);
                        })
                        ->disabled(fn (Get $get): bool => empty($get('model')) || empty($get('ai_prompt'))),
                ]),
            ])
            ->collapsible()
            ->collapsed(false);
    }

    protected function getWizard(): Wizard
    {
        return Wizard::make([
            $this->getDataSourceStep(),
            $this->getColumnsStep(),
            $this->getFiltersStep(),
            $this->getPreviewStep(),
            $this->getScheduleStep(),
        ])
            ->skippable()
            ->persistStepInQueryString()
            ->submitAction(
                Action::make('save')
                    ->label('Save Report')
                    ->action(fn () => $this->saveReport())
            );
    }

    protected function getDataSourceStep(): Step
    {
        return Step::make('Data Source')
            ->description('Select the data source for your report')
            ->icon('heroicon-o-circle-stack')
            ->schema([
                Select::make('model')
                    ->label('Select Model')
                    ->options(fn (): array => $this->getModelOptions())
                    ->required()
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(function (Set $set): void {
                        $set('columns', []);
                        $set('filters', []);
                        $set('sorts', []);
                        $this->previewData = null;
                    })
                    ->helperText('Choose the data source for your report.'),
            ]);
    }

    protected function getColumnsStep(): Step
    {
        return Step::make('Columns')
            ->description('Select the columns to include in your report')
            ->icon('heroicon-o-view-columns')
            ->schema([
                Section::make('Native Fields')
                    ->description('Fields directly from the model')
                    ->schema([
                        CheckboxList::make('columns.native')
                            ->label('')
                            ->options(fn (Get $get): array => $this->getFieldOptionsByCategory($get('model'), 'native'))
                            ->columns(3)
                            ->gridDirection('row'),
                    ])
                    ->visible(fn (Get $get): bool => ! empty($this->getFieldOptionsByCategory($get('model'), 'native'))),

                Section::make('Computed Fields')
                    ->description('Calculated or accessor fields')
                    ->schema([
                        CheckboxList::make('columns.computed')
                            ->label('')
                            ->options(fn (Get $get): array => $this->getFieldOptionsByCategory($get('model'), 'computed'))
                            ->columns(3)
                            ->gridDirection('row'),
                    ])
                    ->visible(fn (Get $get): bool => ! empty($this->getFieldOptionsByCategory($get('model'), 'computed'))),

                Section::make('Relationship Fields')
                    ->description('Fields from related models')
                    ->schema([
                        CheckboxList::make('columns.relations')
                            ->label('')
                            ->options(fn (Get $get): array => $this->getFieldOptionsByCategory($get('model'), 'relations'))
                            ->columns(3)
                            ->gridDirection('row'),
                    ])
                    ->visible(fn (Get $get): bool => ! empty($this->getFieldOptionsByCategory($get('model'), 'relations'))),
            ]);
    }

    protected function getFiltersStep(): Step
    {
        return Step::make('Filters & Sorting')
            ->description('Configure filters and sorting options')
            ->icon('heroicon-o-funnel')
            ->schema([
                Section::make('Filters')
                    ->schema([
                        Repeater::make('filters')
                            ->schema([
                                Select::make('field')
                                    ->label('Field')
                                    ->options(fn (Get $get): array => $this->getFilterableFieldOptions($get('../../model')))
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(fn (Set $set) => $set('operator', null))
                                    ->columnSpan(1),

                                Select::make('operator')
                                    ->label('Operator')
                                    ->options(fn (Get $get): array => $this->getOperatorOptions($get('../../model'), $get('field')))
                                    ->required()
                                    ->live()
                                    ->columnSpan(1),

                                TextInput::make('value')
                                    ->label('Value')
                                    ->required(fn (Get $get): bool => $this->operatorRequiresValue($get('../../model'), $get('field'), $get('operator')))
                                    ->visible(fn (Get $get): bool => $this->operatorRequiresValue($get('../../model'), $get('field'), $get('operator')))
                                    ->columnSpan(1),

                                TextInput::make('value2')
                                    ->label('Second Value')
                                    ->required(fn (Get $get): bool => $this->operatorRequiresSecondValue($get('../../model'), $get('field'), $get('operator')))
                                    ->visible(fn (Get $get): bool => $this->operatorRequiresSecondValue($get('../../model'), $get('field'), $get('operator')))
                                    ->columnSpan(1),
                            ])
                            ->columns(4)
                            ->addActionLabel('Add Filter')
                            ->reorderable()
                            ->collapsible()
                            ->defaultItems(0),
                    ]),

                Section::make('Sorting')
                    ->schema([
                        Repeater::make('sorts')
                            ->schema([
                                Select::make('field')
                                    ->label('Field')
                                    ->options(fn (Get $get): array => $this->getSortableFieldOptions($get('../../model')))
                                    ->required()
                                    ->columnSpan(1),

                                Select::make('direction')
                                    ->label('Direction')
                                    ->options([
                                        'asc' => 'Ascending',
                                        'desc' => 'Descending',
                                    ])
                                    ->default('asc')
                                    ->required()
                                    ->columnSpan(1),
                            ])
                            ->columns(2)
                            ->addActionLabel('Add Sort')
                            ->reorderable()
                            ->collapsible()
                            ->defaultItems(0)
                            ->maxItems(3),
                    ]),
            ]);
    }

    protected function getPreviewStep(): Step
    {
        return Step::make('Preview')
            ->description('Preview your report data')
            ->icon('heroicon-o-eye')
            ->schema([
                Actions::make([
                    Action::make('generatePreview')
                        ->label('Generate Preview')
                        ->icon('heroicon-o-play')
                        ->color('primary')
                        ->action(fn () => $this->generatePreview()),

                    Action::make('exportCsv')
                        ->label('Export CSV')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('gray')
                        ->action(fn () => $this->exportReport('csv'))
                        ->disabled(fn (Get $get): bool => empty($get('model'))),

                    Action::make('exportExcel')
                        ->label('Export Excel')
                        ->icon('heroicon-o-table-cells')
                        ->color('gray')
                        ->action(fn () => $this->exportReport('xlsx'))
                        ->disabled(fn (Get $get): bool => empty($get('model'))),

                    Action::make('exportGoogleDrive')
                        ->label('Save to Google Drive')
                        ->icon('heroicon-o-cloud-arrow-up')
                        ->color('success')
                        ->action(fn () => $this->exportToGoogleDrive())
                        ->disabled(fn (Get $get): bool => empty($get('model')))
                        ->visible(fn (): bool => $this->isGoogleDriveConfigured()),
                ]),

                Placeholder::make('preview')
                    ->label('')
                    ->content(fn (): HtmlString => $this->getPreviewContent()),
            ]);
    }

    protected function isGoogleDriveConfigured(): bool
    {
        return GoogleDriveExportDriver::isConfigured();
    }

    protected function exportReport(string $format): void
    {
        $modelClass = $this->data['model'] ?? null;

        if (empty($modelClass)) {
            Notification::make()
                ->title('No Model Selected')
                ->body('Please select a model first.')
                ->warning()
                ->send();

            return;
        }

        try {
            $columns = $this->getSelectedColumns();
            $filters = $this->data['filters'] ?? [];
            $sorts = $this->data['sorts'] ?? [];

            $builder = new ReportQueryBuilder();
            $cursor = $builder
                ->forUser(auth()->user())
                ->withRequest(request())
                ->compileWithCursor($modelClass, $filters, $sorts, $columns);

            $schema = $this->getSchemaForModel($modelClass);
            $streamExport = new StreamCsvExport();

            $filePath = $format === 'xlsx'
                ? $streamExport->exportToExcel($cursor, $schema, $columns)
                : $streamExport->exportFromCursor($cursor, $schema, $columns);

            $fileName = $this->generateExportFileName($format);

            Notification::make()
                ->title('Export Complete')
                ->body("Your {$format} file has been generated.")
                ->success()
                ->send();

            return response()->download($filePath, $fileName)->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            Notification::make()
                ->title('Export Error')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function exportToGoogleDrive(): void
    {
        $modelClass = $this->data['model'] ?? null;

        if (empty($modelClass)) {
            Notification::make()
                ->title('No Model Selected')
                ->body('Please select a model first.')
                ->warning()
                ->send();

            return;
        }

        try {
            $columns = $this->getSelectedColumns();
            $filters = $this->data['filters'] ?? [];
            $sorts = $this->data['sorts'] ?? [];

            $report = SavedReport::create([
                'name' => $this->data['report_name'] ?? 'Untitled Report',
                'model_class' => $modelClass,
                'columns' => $columns,
                'filters' => $filters,
                'sort' => $sorts,
                'is_active' => false,
                'user_id' => auth()->id(),
            ]);

            ExportReportToExternalService::dispatch(
                $report,
                'csv',
                'google_drive',
                auth()->user()?->email
            );

            Notification::make()
                ->title('Export Queued')
                ->body('Your report is being exported to Google Drive. You will receive an email when complete.')
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Export Error')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function generateExportFileName(string $format): string
    {
        $reportName = $this->data['report_name'] ?? 'report';
        $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $reportName);

        return $safeName . '_' . now()->format('Y-m-d_His') . '.' . $format;
    }

    protected function getScheduleStep(): Step
    {
        return Step::make('Schedule')
            ->description('Configure scheduled delivery')
            ->icon('heroicon-o-clock')
            ->schema([
                Section::make('Report Details')
                    ->schema([
                        TextInput::make('report_name')
                            ->label('Report Name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., Weekly Sales Report'),
                    ]),

                Section::make('Schedule')
                    ->description('Configure when this report should run')
                    ->schema([
                        Toggle::make('enable_schedule')
                            ->label('Enable Scheduled Delivery')
                            ->live()
                            ->default(false),

                        Select::make('frequency_preset')
                            ->label('Frequency')
                            ->options([
                                'daily' => 'Daily (8:00 AM)',
                                'weekly' => 'Weekly (Monday 8:00 AM)',
                                'monthly' => 'Monthly (1st day 8:00 AM)',
                                'quarterly' => 'Quarterly (1st day 8:00 AM)',
                                'custom' => 'Custom (Cron Expression)',
                            ])
                            ->live()
                            ->visible(fn (Get $get): bool => (bool) $get('enable_schedule')),

                        TextInput::make('frequency')
                            ->label('Cron Expression')
                            ->placeholder('0 8 * * *')
                            ->helperText('Standard cron format: minute hour day month weekday')
                            ->visible(fn (Get $get): bool => (bool) $get('enable_schedule') && $get('frequency_preset') === 'custom'),
                    ]),

                Section::make('Email Notifications')
                    ->description('Send report via email')
                    ->schema([
                        TagsInput::make('email_recipients')
                            ->label('Email Recipients')
                            ->placeholder('Add email addresses')
                            ->splitKeys(['Tab', ',', ' '])
                            ->helperText('Press Tab or comma to add multiple emails'),
                    ])
                    ->visible(fn (Get $get): bool => (bool) $get('enable_schedule')),

                Section::make('Slack Notifications')
                    ->description('Send report notifications to Slack')
                    ->schema([
                        Toggle::make('enable_slack')
                            ->label('Notify via Slack')
                            ->live()
                            ->default(false)
                            ->helperText('Send a notification to Slack when the report is generated'),

                        TextInput::make('slack_webhook_url')
                            ->label('Slack Webhook URL')
                            ->url()
                            ->placeholder('https://hooks.slack.com/services/...')
                            ->visible(fn (Get $get): bool => (bool) $get('enable_slack'))
                            ->helperText('Enter your Slack incoming webhook URL'),
                    ])
                    ->visible(fn (): bool => $this->isSlackConfigured()),
            ]);
    }

    protected function isSlackConfigured(): bool
    {
        return SavedReport::isSlackConfigured();
    }

    protected function saveReport(): void
    {
        $modelClass = $this->data['model'] ?? null;

        if (empty($modelClass)) {
            Notification::make()
                ->title('No Model Selected')
                ->body('Please select a model first.')
                ->warning()
                ->send();

            return;
        }

        $reportName = $this->data['report_name'] ?? null;

        if (empty($reportName)) {
            Notification::make()
                ->title('Report Name Required')
                ->body('Please enter a name for your report.')
                ->warning()
                ->send();

            return;
        }

        try {
            $columns = $this->getSelectedColumns();
            $filters = $this->data['filters'] ?? [];
            $sorts = $this->data['sorts'] ?? [];

            $frequency = null;

            if (! empty($this->data['enable_schedule'])) {
                $preset = $this->data['frequency_preset'] ?? null;

                if ($preset === 'custom') {
                    $frequency = $this->data['frequency'] ?? null;
                } elseif ($preset !== null) {
                    $presets = config('dynamic-reporter.schedule_presets', []);
                    $frequency = $presets[$preset] ?? null;
                }
            }

            $report = SavedReport::create([
                'name' => $reportName,
                'model_class' => $modelClass,
                'columns' => $columns,
                'filters' => $filters,
                'sort' => $sorts,
                'frequency' => $frequency,
                'email_recipients' => $this->data['email_recipients'] ?? null,
                'slack_webhook_url' => ! empty($this->data['enable_slack'])
                    ? ($this->data['slack_webhook_url'] ?? null)
                    : null,
                'is_active' => true,
                'user_id' => auth()->id(),
            ]);

            Notification::make()
                ->title('Report Saved')
                ->body("Report \"{$reportName}\" has been saved successfully.")
                ->success()
                ->send();

            $this->redirect(static::getUrl());
        } catch (\Exception $e) {
            Notification::make()
                ->title('Save Error')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Generate report configuration using AI.
     */
    protected function generateWithAi(Get $get, Set $set): void
    {
        $modelClass = $get('model');
        $prompt = $get('ai_prompt');

        if (empty($modelClass) || empty($prompt)) {
            Notification::make()
                ->title('Missing Information')
                ->body('Please select a model and enter a description.')
                ->warning()
                ->send();

            return;
        }

        try {
            $schema = $this->getSchemaForModel($modelClass);
            $interpreter = new GeminiReportInterpreter();
            $config = $interpreter->interpret($prompt, $schema);

            $this->mapAiConfigToWizardState($config->toArray(), $set, $schema);

            Notification::make()
                ->title('Report Generated')
                ->body('AI has configured your report. Review and adjust as needed.')
                ->success()
                ->send();
        } catch (GeminiException $e) {
            Notification::make()
                ->title('AI Error')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Map AI-generated config to wizard state.
     *
     * @param array<string, mixed> $config
     */
    protected function mapAiConfigToWizardState(array $config, Set $set, ReportSchema $schema): void
    {
        $nativeColumns = [];
        $computedColumns = [];
        $relationColumns = [];

        foreach ($config['columns'] ?? [] as $columnName) {
            $field = $schema->getField($columnName);

            if ($field === null) {
                continue;
            }

            if ($field->isRelationship()) {
                $relationColumns[] = $columnName;
            } elseif (isset($field->meta['computed']) && $field->meta['computed']) {
                $computedColumns[] = $columnName;
            } else {
                $nativeColumns[] = $columnName;
            }
        }

        $set('columns.native', $nativeColumns);
        $set('columns.computed', $computedColumns);
        $set('columns.relations', $relationColumns);

        $set('filters', $config['filters'] ?? []);
        $set('sorts', $config['sort'] ?? []);
    }

    /**
     * Generate preview data.
     */
    protected function generatePreview(): void
    {
        $modelClass = $this->data['model'] ?? null;

        if (empty($modelClass)) {
            Notification::make()
                ->title('No Model Selected')
                ->body('Please select a model first.')
                ->warning()
                ->send();

            return;
        }

        try {
            $columns = $this->getSelectedColumns();
            $filters = $this->data['filters'] ?? [];
            $sorts = $this->data['sorts'] ?? [];

            $builder = new ReportQueryBuilder();
            $query = $builder
                ->forUser(auth()->user())
                ->withRequest(request())
                ->compile($modelClass, $filters, $sorts, $columns);

            $this->previewData = $query->limit(10)->get()->toArray();

            Notification::make()
                ->title('Preview Generated')
                ->body('Showing first 10 records.')
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Preview Error')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Get preview content as HTML.
     */
    protected function getPreviewContent(): HtmlString
    {
        if (empty($this->previewData)) {
            return new HtmlString('<div class="text-gray-500 text-center py-8">Click "Generate Preview" to see your report data.</div>');
        }

        if (empty($this->previewData)) {
            return new HtmlString('<div class="text-gray-500 text-center py-8">No data found matching your criteria.</div>');
        }

        $headers = array_keys($this->previewData[0] ?? []);
        $headerHtml = implode('', array_map(
            fn (string $header): string => '<th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">' . e($header) . '</th>',
            $headers
        ));

        $rowsHtml = '';

        foreach ($this->previewData as $row) {
            $cellsHtml = implode('', array_map(
                fn (mixed $value): string => '<td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900">' . e(is_array($value) ? json_encode($value) : (string) $value) . '</td>',
                array_values($row)
            ));
            $rowsHtml .= '<tr class="hover:bg-gray-50">' . $cellsHtml . '</tr>';
        }

        return new HtmlString(<<<HTML
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>{$headerHtml}</tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        {$rowsHtml}
                    </tbody>
                </table>
            </div>
        HTML);
    }

    /**
     * Get selected columns from all categories.
     *
     * @return array<int, string>
     */
    protected function getSelectedColumns(): array
    {
        $columns = [];

        foreach (['native', 'computed', 'relations'] as $category) {
            $categoryColumns = $this->data['columns'][$category] ?? [];

            if (is_array($categoryColumns)) {
                $columns = array_merge($columns, $categoryColumns);
            }
        }

        return $columns;
    }

    /**
     * Get model options for the select dropdown.
     *
     * @return array<string, string>
     */
    protected function getModelOptions(): array
    {
        return ReportableRegistry::getInstance()->getModelOptions();
    }

    /**
     * Get field options grouped by category.
     *
     * @return array<string, string>
     */
    protected function getFieldOptionsByCategory(?string $modelClass, string $category): array
    {
        if (empty($modelClass)) {
            return [];
        }

        $schema = $this->getSchemaForModel($modelClass);
        $options = [];

        foreach ($schema->fields as $field) {
            $isRelation = $field->isRelationship();
            $isComputed = isset($field->meta['computed']) && $field->meta['computed'];

            $fieldCategory = match (true) {
                $isRelation => 'relations',
                $isComputed => 'computed',
                default => 'native',
            };

            if ($fieldCategory === $category) {
                $options[$field->name] = $field->label;
            }
        }

        return $options;
    }

    /**
     * Get filterable field options.
     *
     * @return array<string, string>
     */
    protected function getFilterableFieldOptions(?string $modelClass): array
    {
        if (empty($modelClass)) {
            return [];
        }

        $schema = $this->getSchemaForModel($modelClass);
        $options = [];

        foreach ($schema->getFilterableFields() as $field) {
            $options[$field->name] = $field->label;
        }

        return $options;
    }

    /**
     * Get sortable field options.
     *
     * @return array<string, string>
     */
    protected function getSortableFieldOptions(?string $modelClass): array
    {
        if (empty($modelClass)) {
            return [];
        }

        $schema = $this->getSchemaForModel($modelClass);
        $options = [];

        foreach ($schema->getSortableFields() as $field) {
            $options[$field->name] = $field->label;
        }

        return $options;
    }

    /**
     * Get operator options for a field.
     *
     * @return array<string, string>
     */
    protected function getOperatorOptions(?string $modelClass, ?string $fieldName): array
    {
        if (empty($modelClass) || empty($fieldName)) {
            return [];
        }

        $schema = $this->getSchemaForModel($modelClass);
        $field = $schema->getField($fieldName);

        if ($field === null) {
            return [];
        }

        return $this->filterFactory->getOperatorLabels($field->type);
    }

    /**
     * Check if operator requires a value.
     */
    protected function operatorRequiresValue(?string $modelClass, ?string $fieldName, ?string $operator): bool
    {
        if (empty($modelClass) || empty($fieldName) || empty($operator)) {
            return false;
        }

        $schema = $this->getSchemaForModel($modelClass);
        $field = $schema->getField($fieldName);

        if ($field === null) {
            return false;
        }

        try {
            $operatorConfig = $this->filterFactory->getOperator($field->type, $operator);

            return $operatorConfig['requiresValue'] ?? false;
        } catch (\InvalidArgumentException) {
            return false;
        }
    }

    /**
     * Check if operator requires a second value.
     */
    protected function operatorRequiresSecondValue(?string $modelClass, ?string $fieldName, ?string $operator): bool
    {
        if (empty($modelClass) || empty($fieldName) || empty($operator)) {
            return false;
        }

        $schema = $this->getSchemaForModel($modelClass);
        $field = $schema->getField($fieldName);

        if ($field === null) {
            return false;
        }

        try {
            $operatorConfig = $this->filterFactory->getOperator($field->type, $operator);

            return $operatorConfig['requiresSecondValue'] ?? false;
        } catch (\InvalidArgumentException) {
            return false;
        }
    }

    /**
     * Get schema for a model class.
     *
     * @param class-string<Model&Reportable> $modelClass
     */
    protected function getSchemaForModel(string $modelClass): ReportSchema
    {
        return ReportableRegistry::getInstance()->getSchema($modelClass);
    }
}
