<?php

declare(strict_types=1);

namespace ByteXR\DynamicReporter\Filament\Pages;

use ByteXR\DynamicReporter\Models\ReportVersion;
use ByteXR\DynamicReporter\Models\SavedReport;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Support\HtmlString;

class ViewReport extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Saved Reports';

    protected static ?string $title = 'Saved Reports';

    protected static ?string $slug = 'reports/saved';

    protected static string $view = 'dynamic-reporter::filament.pages.view-report';

    public ?SavedReport $selectedReport = null;

    public bool $showHistoryModal = false;

    public function mount(): void
    {
        $this->selectedReport = null;
        $this->showHistoryModal = false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(SavedReport::query()->orderByDesc('updated_at'))
            ->columns([
                TextColumn::make('name')
                    ->label('Report Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('model_class')
                    ->label('Data Source')
                    ->formatStateUsing(fn (string $state): string => class_basename($state))
                    ->sortable(),

                TextColumn::make('user.name')
                    ->label('Created By')
                    ->default('System'),

                TextColumn::make('is_active')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Active' : 'Inactive')
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),

                TextColumn::make('versions_count')
                    ->label('Versions')
                    ->counts('versions')
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                TableAction::make('history')
                    ->label('History')
                    ->icon('heroicon-o-clock')
                    ->color('gray')
                    ->action(fn (SavedReport $record) => $this->openHistoryModal($record)),

                TableAction::make('edit')
                    ->label('Edit')
                    ->icon('heroicon-o-pencil')
                    ->url(fn (SavedReport $record): string => route('filament.admin.pages.reports/create', ['report' => $record->id])),
            ])
            ->bulkActions([])
            ->emptyStateHeading('No saved reports')
            ->emptyStateDescription('Create a report to get started.')
            ->emptyStateIcon('heroicon-o-document-chart-bar');
    }

    public function openHistoryModal(SavedReport $report): void
    {
        $this->selectedReport = $report;
        $this->showHistoryModal = true;
        $this->dispatch('open-modal', id: 'history-modal');
    }

    public function closeHistoryModal(): void
    {
        $this->showHistoryModal = false;
        $this->selectedReport = null;
    }

    public function restoreVersion(int $versionId): void
    {
        if ($this->selectedReport === null) {
            Notification::make()
                ->title('Error')
                ->body('No report selected.')
                ->danger()
                ->send();

            return;
        }

        $version = ReportVersion::find($versionId);

        if ($version === null) {
            Notification::make()
                ->title('Error')
                ->body('Version not found.')
                ->danger()
                ->send();

            return;
        }

        if ($version->saved_report_id !== $this->selectedReport->id) {
            Notification::make()
                ->title('Error')
                ->body('Version does not belong to this report.')
                ->danger()
                ->send();

            return;
        }

        $restored = $this->selectedReport->restoreFromVersion($version);

        if ($restored) {
            Notification::make()
                ->title('Version Restored')
                ->body("Report restored to version {$version->version_number}.")
                ->success()
                ->send();

            $this->closeHistoryModal();
        } else {
            Notification::make()
                ->title('Restore Failed')
                ->body('Failed to restore the report version.')
                ->danger()
                ->send();
        }
    }

    public function getVersionsProperty(): array
    {
        if ($this->selectedReport === null) {
            return [];
        }

        return $this->selectedReport->versions()
            ->with('changedByUser')
            ->orderByDesc('version_number')
            ->get()
            ->toArray();
    }

    public function getHistoryContent(): HtmlString
    {
        if ($this->selectedReport === null) {
            return new HtmlString('<p class="text-gray-500">No report selected.</p>');
        }

        $versions = $this->selectedReport->versions()
            ->with('changedByUser')
            ->orderByDesc('version_number')
            ->get();

        if ($versions->isEmpty()) {
            return new HtmlString('<p class="text-gray-500">No version history available.</p>');
        }

        $html = '<div class="space-y-4">';

        foreach ($versions as $version) {
            $changedBy = $version->getChangedByName();
            $formattedDate = $version->getFormattedDate();
            $changeSummary = e($version->getChangeSummaryText());
            $versionNumber = $version->version_number;
            $versionId = $version->id;

            $html .= <<<HTML
            <div class="border rounded-lg p-4 bg-white dark:bg-gray-800">
                <div class="flex justify-between items-start">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                v{$versionNumber}
                            </span>
                            <span class="text-sm text-gray-500 dark:text-gray-400">{$formattedDate}</span>
                        </div>
                        <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">{$changeSummary}</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Changed by: {$changedBy}</p>
                    </div>
                    <button
                        type="button"
                        wire:click="restoreVersion({$versionId})"
                        wire:loading.attr="disabled"
                        class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 disabled:opacity-50"
                    >
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        Restore
                    </button>
                </div>
            </div>
            HTML;
        }

        $html .= '</div>';

        return new HtmlString($html);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label('Create Report')
                ->icon('heroicon-o-plus')
                ->url(route('filament.admin.pages.reports/create')),
        ];
    }
}
