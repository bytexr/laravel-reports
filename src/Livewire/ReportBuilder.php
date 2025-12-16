<?php

declare(strict_types=1);

namespace ByteXR\DynamicReporter\Livewire;

use ByteXR\DynamicReporter\DTOs\ReportSchema;
use ByteXR\DynamicReporter\ReportableRegistry;
use ByteXR\DynamicReporter\Services\FilterFactory;
use ByteXR\DynamicReporter\Services\RelationshipTreeBuilder;
use ByteXR\DynamicReporter\Services\ReportQueryBuilder;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Standalone Livewire component for building reports.
 *
 * This component can be used independently of Filament Panels,
 * making it suitable for embedding in any Laravel application.
 */
class ReportBuilder extends Component
{
    /**
     * Current wizard step.
     */
    public int $currentStep = 1;

    /**
     * Total number of wizard steps.
     */
    public int $totalSteps = 5;

    /**
     * Selected model class.
     */
    public ?string $selectedModel = null;

    /**
     * Selected columns/fields.
     *
     * @var array<string, array<int, string>>
     */
    public array $selectedColumns = [
        'native' => [],
        'computed' => [],
        'relations' => [],
        'metrics' => [],
    ];

    /**
     * Selected relationship paths.
     *
     * @var array<int, string>
     */
    public array $selectedRelationships = [];

    /**
     * Configured filters.
     *
     * @var array<int, array{field: string, operator: string, value: mixed, value2?: mixed}>
     */
    public array $filters = [];

    /**
     * Configured sorts.
     *
     * @var array<int, array{field: string, direction: string}>
     */
    public array $sorts = [];

    /**
     * Report name.
     */
    public string $reportName = '';

    /**
     * Report description.
     */
    public string $reportDescription = '';

    /**
     * Preview data.
     *
     * @var array<int, array<string, mixed>>|null
     */
    public ?array $previewData = null;

    /**
     * Preview error message.
     */
    public ?string $previewError = null;

    /**
     * Whether the report is being saved.
     */
    public bool $isSaving = false;

    /**
     * Validation messages.
     *
     * @var array<string, string>
     */
    public array $validationMessages = [];

    protected FilterFactory $filterFactory;

    protected RelationshipTreeBuilder $treeBuilder;

    public function boot(): void
    {
        $this->filterFactory = new FilterFactory();
        $this->treeBuilder = new RelationshipTreeBuilder();
    }

    public function mount(?string $modelClass = null): void
    {
        if ($modelClass !== null) {
            $this->selectedModel = $modelClass;
        }
    }

    /**
     * Get available model options.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function modelOptions(): array
    {
        return ReportableRegistry::getInstance()->getModelOptions();
    }

    /**
     * Get the current schema.
     */
    #[Computed]
    public function schema(): ?ReportSchema
    {
        if ($this->selectedModel === null) {
            return null;
        }

        try {
            return ReportableRegistry::getInstance()->getSchema($this->selectedModel);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Get relationship tree for the selected model.
     *
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function relationshipTree(): array
    {
        if ($this->selectedModel === null) {
            return [];
        }

        return $this->treeBuilder->buildUserFriendlyTree($this->selectedModel);
    }

    /**
     * Get field options grouped by category.
     *
     * @return array<string, array<string, string>>
     */
    #[Computed]
    public function fieldOptions(): array
    {
        $schema = $this->schema;

        if ($schema === null) {
            return [];
        }

        $options = [
            'native' => [],
            'computed' => [],
            'relations' => [],
        ];

        foreach ($schema->fields as $field) {
            $isRelation = $field->isRelationship();
            $isComputed = isset($field->meta['computed']) && $field->meta['computed'];

            $category = match (true) {
                $isRelation => 'relations',
                $isComputed => 'computed',
                default => 'native',
            };

            $options[$category][$field->name] = $field->label;
        }

        return $options;
    }

    /**
     * Get metric options.
     *
     * @return array<string, array{label: string, description: string}>
     */
    #[Computed]
    public function metricOptions(): array
    {
        $schema = $this->schema;

        if ($schema === null) {
            return [];
        }

        $options = [];

        foreach ($schema->metrics as $metric) {
            $options[$metric->getName()] = [
                'label' => $metric->getLabel(),
                'description' => $metric->getDescription(),
            ];
        }

        return $options;
    }

    /**
     * Get filterable field options.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function filterableFields(): array
    {
        $schema = $this->schema;

        if ($schema === null) {
            return [];
        }

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
    #[Computed]
    public function sortableFields(): array
    {
        $schema = $this->schema;

        if ($schema === null) {
            return [];
        }

        $options = [];

        foreach ($schema->getSortableFields() as $field) {
            $options[$field->name] = $field->label;
        }

        return $options;
    }

    /**
     * Get wizard step labels.
     *
     * @return array<int, array{title: string, description: string, icon: string}>
     */
    #[Computed]
    public function steps(): array
    {
        return [
            1 => [
                'title' => 'Choose Data',
                'description' => 'Select what information you want to see',
                'icon' => 'heroicon-o-circle-stack',
            ],
            2 => [
                'title' => 'Include Related Data',
                'description' => 'Add information from connected records',
                'icon' => 'heroicon-o-link',
            ],
            3 => [
                'title' => 'Filter Results',
                'description' => 'Narrow down your data',
                'icon' => 'heroicon-o-funnel',
            ],
            4 => [
                'title' => 'Sort & Arrange',
                'description' => 'Organize how your data appears',
                'icon' => 'heroicon-o-arrows-up-down',
            ],
            5 => [
                'title' => 'Preview & Save',
                'description' => 'Review and save your report',
                'icon' => 'heroicon-o-eye',
            ],
        ];
    }

    /**
     * Handle model selection change.
     */
    public function updatedSelectedModel(): void
    {
        $this->selectedColumns = [
            'native' => [],
            'computed' => [],
            'relations' => [],
            'metrics' => [],
        ];
        $this->selectedRelationships = [];
        $this->filters = [];
        $this->sorts = [];
        $this->previewData = null;
        $this->previewError = null;
        $this->validationMessages = [];
    }

    /**
     * Go to the next step.
     */
    public function nextStep(): void
    {
        if ($this->validateCurrentStep()) {
            $this->currentStep = min($this->currentStep + 1, $this->totalSteps);
        }
    }

    /**
     * Go to the previous step.
     */
    public function previousStep(): void
    {
        $this->currentStep = max($this->currentStep - 1, 1);
    }

    /**
     * Go to a specific step.
     */
    public function goToStep(int $step): void
    {
        if ($step >= 1 && $step <= $this->totalSteps) {
            $this->currentStep = $step;
        }
    }

    /**
     * Validate the current step.
     */
    protected function validateCurrentStep(): bool
    {
        $this->validationMessages = [];

        return match ($this->currentStep) {
            1 => $this->validateDataStep(),
            2 => true,
            3 => true,
            4 => true,
            5 => $this->validateSaveStep(),
            default => true,
        };
    }

    /**
     * Validate the data selection step.
     */
    protected function validateDataStep(): bool
    {
        if ($this->selectedModel === null) {
            $this->validationMessages['model'] = 'Please select a data source to continue.';

            return false;
        }

        $hasColumns = ! empty($this->selectedColumns['native'])
            || ! empty($this->selectedColumns['computed'])
            || ! empty($this->selectedColumns['relations'])
            || ! empty($this->selectedColumns['metrics']);

        if (! $hasColumns) {
            $this->validationMessages['columns'] = 'Please select at least one field to include in your report.';

            return false;
        }

        return true;
    }

    /**
     * Validate the save step.
     */
    protected function validateSaveStep(): bool
    {
        if (empty(trim($this->reportName))) {
            $this->validationMessages['name'] = 'Please enter a name for your report.';

            return false;
        }

        return true;
    }

    /**
     * Add a new filter.
     */
    public function addFilter(): void
    {
        $this->filters[] = [
            'field' => '',
            'operator' => '',
            'value' => '',
            'value2' => '',
        ];
    }

    /**
     * Remove a filter.
     */
    public function removeFilter(int $index): void
    {
        unset($this->filters[$index]);
        $this->filters = array_values($this->filters);
    }

    /**
     * Add a new sort.
     */
    public function addSort(): void
    {
        $this->sorts[] = [
            'field' => '',
            'direction' => 'asc',
        ];
    }

    /**
     * Remove a sort.
     */
    public function removeSort(int $index): void
    {
        unset($this->sorts[$index]);
        $this->sorts = array_values($this->sorts);
    }

    /**
     * Get operator options for a field.
     *
     * @return array<string, string>
     */
    public function getOperatorOptions(string $fieldName): array
    {
        $schema = $this->schema;

        if ($schema === null) {
            return [];
        }

        $field = $schema->getField($fieldName);

        if ($field === null) {
            return [];
        }

        return $this->filterFactory->getOperatorLabels($field->type);
    }

    /**
     * Toggle a relationship selection.
     */
    public function toggleRelationship(string $path): void
    {
        if (in_array($path, $this->selectedRelationships, true)) {
            $this->selectedRelationships = array_values(
                array_filter(
                    $this->selectedRelationships,
                    fn (string $p): bool => $p !== $path
                )
            );
        } else {
            $this->selectedRelationships[] = $path;
        }
    }

    /**
     * Generate preview data.
     */
    public function generatePreview(): void
    {
        $this->previewData = null;
        $this->previewError = null;

        if ($this->selectedModel === null) {
            $this->previewError = 'Please select a data source first.';

            return;
        }

        try {
            $columns = $this->getSelectedColumns();

            if (empty($columns)) {
                $this->previewError = 'Please select at least one field to preview.';

                return;
            }

            $builder = new ReportQueryBuilder();
            $query = $builder
                ->forUser(auth()->user())
                ->withRequest(request())
                ->compile($this->selectedModel, $this->filters, $this->sorts, $columns);

            $this->previewData = $query->limit(10)->get()->toArray();

            if (empty($this->previewData)) {
                $this->previewError = 'No data found matching your criteria. Try adjusting your filters.';
            }
        } catch (\Throwable $e) {
            $this->previewError = 'Unable to generate preview: ' . $e->getMessage();
        }
    }

    /**
     * Get all selected columns.
     *
     * @return array<int, string>
     */
    protected function getSelectedColumns(): array
    {
        $columns = [];

        foreach (['native', 'computed', 'relations', 'metrics'] as $category) {
            if (! empty($this->selectedColumns[$category])) {
                $columns = array_merge($columns, $this->selectedColumns[$category]);
            }
        }

        return $columns;
    }

    /**
     * Save the report.
     */
    public function saveReport(): void
    {
        if (! $this->validateSaveStep()) {
            return;
        }

        $this->isSaving = true;

        try {
            $savedReportClass = \ByteXR\DynamicReporter\DynamicReporterServiceProvider::getSavedReportModel();

            $report = $savedReportClass::create([
                'name' => $this->reportName,
                'description' => $this->reportDescription,
                'model_class' => $this->selectedModel,
                'columns' => $this->getSelectedColumns(),
                'filters' => $this->filters,
                'sort' => $this->sorts,
                'is_active' => true,
                'user_id' => auth()->id(),
            ]);

            $this->dispatch('report-saved', reportId: $report->id);
            $this->dispatch('notify', type: 'success', message: 'Report saved successfully!');
        } catch (\Throwable $e) {
            $this->dispatch('notify', type: 'error', message: 'Failed to save report: ' . $e->getMessage());
        } finally {
            $this->isSaving = false;
        }
    }

    /**
     * Reset the builder to initial state.
     */
    public function resetBuilder(): void
    {
        $this->currentStep = 1;
        $this->selectedModel = null;
        $this->selectedColumns = [
            'native' => [],
            'computed' => [],
            'relations' => [],
            'metrics' => [],
        ];
        $this->selectedRelationships = [];
        $this->filters = [];
        $this->sorts = [];
        $this->reportName = '';
        $this->reportDescription = '';
        $this->previewData = null;
        $this->previewError = null;
        $this->validationMessages = [];
    }

    public function render(): View
    {
        return view('dynamic-reporter::livewire.report-builder');
    }
}
