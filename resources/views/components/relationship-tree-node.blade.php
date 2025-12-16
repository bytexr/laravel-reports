@props(['relationship', 'depth' => 0])

<div class="relationship-tree-node" style="margin-left: {{ $depth * 1.5 }}rem;">
    <div
        class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors cursor-pointer group"
        x-data="{ expanded: false }"
    >
        {{-- Expand/Collapse Toggle --}}
        @if(!empty($relationship['children']))
            <button
                @click="expanded = !expanded"
                class="p-1 rounded hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors"
            >
                <x-heroicon-o-chevron-right
                    class="h-4 w-4 text-gray-400 transition-transform"
                    x-bind:class="{ 'rotate-90': expanded }"
                />
            </button>
        @else
            <div class="w-6"></div>
        @endif

        {{-- Checkbox --}}
        <label class="flex items-center gap-3 flex-1 cursor-pointer">
            <input
                type="checkbox"
                wire:click="toggleRelationship('{{ $relationship['id'] }}')"
                @checked(in_array($relationship['id'], $selectedRelationships ?? []))
                class="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
            >

            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2">
                    <span class="text-sm font-medium text-gray-900 dark:text-white">
                        {{ $relationship['name'] }}
                    </span>

                    {{-- Path breadcrumb --}}
                    <span class="text-xs text-gray-400 dark:text-gray-500 truncate">
                        {{ $relationship['path'] }}
                    </span>
                </div>

                @if(!empty($relationship['description']))
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                        {{ $relationship['description'] }}
                    </p>
                @endif
            </div>
        </label>

        {{-- Warning indicator --}}
        @if(!empty($relationship['warning']))
            <div
                class="text-warning-500"
                title="{{ $relationship['warning'] }}"
            >
                <x-heroicon-o-exclamation-triangle class="h-4 w-4" />
            </div>
        @endif

        {{-- Field count badge --}}
        @if(!empty($relationship['fields']))
            <span class="px-2 py-0.5 text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400 rounded-full">
                {{ count($relationship['fields']) }} fields
            </span>
        @endif
    </div>

    {{-- Children --}}
    @if(!empty($relationship['children']))
        <div x-show="expanded" x-collapse class="mt-1">
            @foreach($relationship['children'] as $child)
                @include('dynamic-reporter::components.relationship-tree-node', ['relationship' => $child, 'depth' => $depth + 1])
            @endforeach
        </div>
    @endif

    {{-- Available Fields (when expanded) --}}
    @if(!empty($relationship['fields']))
        <div x-show="expanded" x-collapse class="mt-2 ml-9 p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-2">Available fields:</p>
            <div class="flex flex-wrap gap-2">
                @foreach($relationship['fields'] as $field)
                    <span class="inline-flex items-center px-2 py-1 text-xs bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded">
                        {{ $field['name'] }}
                        <span class="ml-1 text-gray-400">({{ $field['type'] }})</span>
                    </span>
                @endforeach
            </div>
        </div>
    @endif
</div>
