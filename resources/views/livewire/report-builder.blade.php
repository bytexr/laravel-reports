<div class="dynamic-reporter-builder">
    {{-- Wizard Progress --}}
    <div class="mb-8">
        <nav aria-label="Progress">
            <ol role="list" class="flex items-center justify-between">
                @foreach($this->steps as $stepNumber => $step)
                    <li class="relative {{ $stepNumber < $this->totalSteps ? 'pr-8 sm:pr-20' : '' }} {{ $stepNumber > 1 ? 'pl-8 sm:pl-20' : '' }}">
                        {{-- Connector line --}}
                        @if($stepNumber < $this->totalSteps)
                            <div class="absolute inset-0 flex items-center" aria-hidden="true">
                                <div class="h-0.5 w-full {{ $stepNumber < $currentStep ? 'bg-primary-600' : 'bg-gray-200 dark:bg-gray-700' }}"></div>
                            </div>
                        @endif

                        {{-- Step indicator --}}
                        <button
                            wire:click="goToStep({{ $stepNumber }})"
                            class="relative flex h-10 w-10 items-center justify-center rounded-full transition-colors
                                {{ $stepNumber < $currentStep ? 'bg-primary-600 hover:bg-primary-700' : '' }}
                                {{ $stepNumber === $currentStep ? 'border-2 border-primary-600 bg-white dark:bg-gray-900' : '' }}
                                {{ $stepNumber > $currentStep ? 'border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 hover:border-gray-400' : '' }}"
                            aria-current="{{ $stepNumber === $currentStep ? 'step' : 'false' }}"
                        >
                            @if($stepNumber < $currentStep)
                                <x-heroicon-s-check class="h-5 w-5 text-white" />
                            @else
                                <span class="{{ $stepNumber === $currentStep ? 'text-primary-600' : 'text-gray-500 dark:text-gray-400' }}">
                                    {{ $stepNumber }}
                                </span>
                            @endif
                        </button>

                        {{-- Step label (visible on larger screens) --}}
                        <div class="absolute -bottom-8 left-1/2 -translate-x-1/2 whitespace-nowrap hidden sm:block">
                            <span class="text-xs font-medium {{ $stepNumber === $currentStep ? 'text-primary-600' : 'text-gray-500 dark:text-gray-400' }}">
                                {{ $step['title'] }}
                            </span>
                        </div>
                    </li>
                @endforeach
            </ol>
        </nav>
    </div>

    {{-- Current Step Content --}}
    <div class="mt-12 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
        {{-- Step Header --}}
        <div class="mb-6 pb-4 border-b border-gray-200 dark:border-gray-700">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
                {{ $this->steps[$currentStep]['title'] }}
            </h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ $this->steps[$currentStep]['description'] }}
            </p>
        </div>

        {{-- Validation Messages --}}
        @if(!empty($validationMessages))
            <div class="mb-6 rounded-lg bg-danger-50 dark:bg-danger-900/20 p-4">
                <div class="flex">
                    <x-heroicon-s-exclamation-triangle class="h-5 w-5 text-danger-400" />
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-danger-800 dark:text-danger-200">
                            Please fix the following:
                        </h3>
                        <ul class="mt-2 text-sm text-danger-700 dark:text-danger-300 list-disc list-inside">
                            @foreach($validationMessages as $message)
                                <li>{{ $message }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        @endif

        {{-- Step 1: Choose Data --}}
        @if($currentStep === 1)
            <div class="space-y-6">
                {{-- Model Selection --}}
                <div>
                    <label for="model-select" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        What would you like to report on?
                    </label>
                    <select
                        id="model-select"
                        wire:model.live="selectedModel"
                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                    >
                        <option value="">Select a data source...</option>
                        @foreach($this->modelOptions as $class => $label)
                            <option value="{{ $class }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Field Selection --}}
                @if($selectedModel)
                    <div class="space-y-4">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                            Choose the information to include
                        </h3>

                        {{-- Native Fields --}}
                        @if(!empty($this->fieldOptions['native']))
                            <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                                <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
                                    Basic Information
                                </h4>
                                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                                    @foreach($this->fieldOptions['native'] as $name => $label)
                                        <label class="flex items-center space-x-2 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                wire:model.live="selectedColumns.native"
                                                value="{{ $name }}"
                                                class="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                                            >
                                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ $label }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        {{-- Computed Fields --}}
                        @if(!empty($this->fieldOptions['computed']))
                            <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                                <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
                                    Calculated Fields
                                </h4>
                                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                                    @foreach($this->fieldOptions['computed'] as $name => $label)
                                        <label class="flex items-center space-x-2 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                wire:model.live="selectedColumns.computed"
                                                value="{{ $name }}"
                                                class="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                                            >
                                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ $label }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        {{-- Metrics --}}
                        @if(!empty($this->metricOptions))
                            <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                                <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
                                    Business Metrics
                                </h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    @foreach($this->metricOptions as $name => $metric)
                                        <label class="flex items-start space-x-2 cursor-pointer p-2 rounded hover:bg-gray-50 dark:hover:bg-gray-700">
                                            <input
                                                type="checkbox"
                                                wire:model.live="selectedColumns.metrics"
                                                value="{{ $name }}"
                                                class="mt-1 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                                            >
                                            <div>
                                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $metric['label'] }}</span>
                                                @if($metric['description'])
                                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $metric['description'] }}</p>
                                                @endif
                                            </div>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        @endif

        {{-- Step 2: Include Related Data --}}
        @if($currentStep === 2)
            <div class="space-y-6">
                @if(empty($this->relationshipTree))
                    <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                        <x-heroicon-o-link class="mx-auto h-12 w-12 text-gray-400" />
                        <p class="mt-2">No related data available for this data source.</p>
                        <p class="text-sm">You can skip this step and continue.</p>
                    </div>
                @else
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                        Expand the tree below to include information from related records.
                        For example, if you're reporting on Orders, you can include Customer details.
                    </p>

                    <div class="space-y-2">
                        @foreach($this->relationshipTree as $relationship)
                            @include('dynamic-reporter::components.relationship-tree-node', ['relationship' => $relationship, 'depth' => 0])
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

        {{-- Step 3: Filter Results --}}
        @if($currentStep === 3)
            <div class="space-y-6">
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Add conditions to narrow down your results. For example: "Status is Completed" or "Amount is greater than 100".
                </p>

                <div class="space-y-4">
                    @foreach($filters as $index => $filter)
                        <div class="flex items-start gap-3 p-4 rounded-lg bg-gray-50 dark:bg-gray-700/50">
                            <div class="flex-1 grid grid-cols-1 md:grid-cols-4 gap-3">
                                <select
                                    wire:model.live="filters.{{ $index }}.field"
                                    class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm"
                                >
                                    <option value="">Select field...</option>
                                    @foreach($this->filterableFields as $name => $label)
                                        <option value="{{ $name }}">{{ $label }}</option>
                                    @endforeach
                                </select>

                                @if($filter['field'])
                                    <select
                                        wire:model.live="filters.{{ $index }}.operator"
                                        class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm"
                                    >
                                        <option value="">Select condition...</option>
                                        @foreach($this->getOperatorOptions($filter['field']) as $op => $label)
                                            <option value="{{ $op }}">{{ $label }}</option>
                                        @endforeach
                                    </select>

                                    <input
                                        type="text"
                                        wire:model.live="filters.{{ $index }}.value"
                                        placeholder="Enter value..."
                                        class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm"
                                    >
                                @endif
                            </div>

                            <button
                                wire:click="removeFilter({{ $index }})"
                                class="p-2 text-gray-400 hover:text-danger-500 transition-colors"
                                title="Remove filter"
                            >
                                <x-heroicon-o-x-mark class="h-5 w-5" />
                            </button>
                        </div>
                    @endforeach
                </div>

                <button
                    wire:click="addFilter"
                    class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-primary-600 hover:text-primary-700 transition-colors"
                >
                    <x-heroicon-o-plus class="h-4 w-4" />
                    Add a filter
                </button>

                @if(empty($filters))
                    <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                        <x-heroicon-o-funnel class="mx-auto h-12 w-12 text-gray-400" />
                        <p class="mt-2">No filters added yet.</p>
                        <p class="text-sm">Click "Add a filter" to narrow down your results, or skip this step to include all data.</p>
                    </div>
                @endif
            </div>
        @endif

        {{-- Step 4: Sort & Arrange --}}
        @if($currentStep === 4)
            <div class="space-y-6">
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Choose how to order your results. You can sort by multiple fields.
                </p>

                <div class="space-y-4">
                    @foreach($sorts as $index => $sort)
                        <div class="flex items-center gap-3 p-4 rounded-lg bg-gray-50 dark:bg-gray-700/50">
                            <div class="flex-1 grid grid-cols-2 gap-3">
                                <select
                                    wire:model.live="sorts.{{ $index }}.field"
                                    class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm"
                                >
                                    <option value="">Select field...</option>
                                    @foreach($this->sortableFields as $name => $label)
                                        <option value="{{ $name }}">{{ $label }}</option>
                                    @endforeach
                                </select>

                                <select
                                    wire:model.live="sorts.{{ $index }}.direction"
                                    class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm"
                                >
                                    <option value="asc">Smallest to largest (A-Z)</option>
                                    <option value="desc">Largest to smallest (Z-A)</option>
                                </select>
                            </div>

                            <button
                                wire:click="removeSort({{ $index }})"
                                class="p-2 text-gray-400 hover:text-danger-500 transition-colors"
                                title="Remove sort"
                            >
                                <x-heroicon-o-x-mark class="h-5 w-5" />
                            </button>
                        </div>
                    @endforeach
                </div>

                <button
                    wire:click="addSort"
                    class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-primary-600 hover:text-primary-700 transition-colors"
                >
                    <x-heroicon-o-plus class="h-4 w-4" />
                    Add sorting
                </button>

                @if(empty($sorts))
                    <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                        <x-heroicon-o-arrows-up-down class="mx-auto h-12 w-12 text-gray-400" />
                        <p class="mt-2">No sorting configured.</p>
                        <p class="text-sm">Click "Add sorting" to order your results, or skip this step for default ordering.</p>
                    </div>
                @endif
            </div>
        @endif

        {{-- Step 5: Preview & Save --}}
        @if($currentStep === 5)
            <div class="space-y-6">
                {{-- Report Details --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="report-name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Report Name <span class="text-danger-500">*</span>
                        </label>
                        <input
                            type="text"
                            id="report-name"
                            wire:model.live="reportName"
                            placeholder="e.g., Monthly Sales Report"
                            class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700"
                        >
                    </div>

                    <div>
                        <label for="report-description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Description (optional)
                        </label>
                        <input
                            type="text"
                            id="report-description"
                            wire:model.live="reportDescription"
                            placeholder="Brief description of this report"
                            class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700"
                        >
                    </div>
                </div>

                {{-- Preview --}}
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">Preview</h3>
                        <button
                            wire:click="generatePreview"
                            wire:loading.attr="disabled"
                            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg transition-colors disabled:opacity-50"
                        >
                            <x-heroicon-o-play class="h-4 w-4" wire:loading.class="animate-spin" wire:target="generatePreview" />
                            <span wire:loading.remove wire:target="generatePreview">Generate Preview</span>
                            <span wire:loading wire:target="generatePreview">Loading...</span>
                        </button>
                    </div>

                    @if($previewError)
                        <div class="rounded-lg bg-warning-50 dark:bg-warning-900/20 p-4 text-warning-800 dark:text-warning-200">
                            <div class="flex items-center gap-2">
                                <x-heroicon-o-exclamation-triangle class="h-5 w-5" />
                                <span>{{ $previewError }}</span>
                            </div>
                        </div>
                    @elseif($previewData)
                        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-800">
                                    <tr>
                                        @foreach(array_keys($previewData[0] ?? []) as $header)
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                                {{ str_replace('_', ' ', $header) }}
                                            </th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($previewData as $row)
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                                            @foreach($row as $value)
                                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                                    {{ is_array($value) ? json_encode($value) : $value }}
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                            Showing first {{ count($previewData) }} records
                        </p>
                    @else
                        <div class="text-center py-8 rounded-lg border-2 border-dashed border-gray-300 dark:border-gray-600">
                            <x-heroicon-o-table-cells class="mx-auto h-12 w-12 text-gray-400" />
                            <p class="mt-2 text-gray-500 dark:text-gray-400">Click "Generate Preview" to see your report data</p>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        {{-- Navigation Buttons --}}
        <div class="mt-8 pt-6 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between">
            <button
                wire:click="previousStep"
                @if($currentStep === 1) disabled @endif
                class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            >
                <x-heroicon-o-arrow-left class="h-4 w-4" />
                Back
            </button>

            <div class="flex items-center gap-3">
                @if($currentStep === $totalSteps)
                    <button
                        wire:click="saveReport"
                        wire:loading.attr="disabled"
                        class="inline-flex items-center gap-2 px-6 py-2 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg transition-colors disabled:opacity-50"
                    >
                        <span wire:loading.remove wire:target="saveReport">Save Report</span>
                        <span wire:loading wire:target="saveReport">Saving...</span>
                    </button>
                @else
                    <button
                        wire:click="nextStep"
                        class="inline-flex items-center gap-2 px-6 py-2 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg transition-colors"
                    >
                        Continue
                        <x-heroicon-o-arrow-right class="h-4 w-4" />
                    </button>
                @endif
            </div>
        </div>
    </div>
</div>
