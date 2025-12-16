<x-filament-widgets::widget>
    @if($this->hasChart())
        <x-filament::section>
            <div
                x-data="{
                    chart: null,
                    init() {
                        this.renderChart();
                    },
                    renderChart() {
                        const chartData = @js($this->getChartData());
                        const chartOptions = @js($this->getChartOptions());
                        const chartType = @js($this->getChartType());

                        if (!chartData.labels || chartData.labels.length === 0) {
                            return;
                        }

                        const options = {
                            ...chartOptions,
                            series: this.formatSeries(chartData, chartType),
                            labels: chartData.labels,
                        };

                        if (this.chart) {
                            this.chart.destroy();
                        }

                        this.chart = new ApexCharts(this.$refs.chart, options);
                        this.chart.render();
                    },
                    formatSeries(data, type) {
                        if (['pie', 'donut'].includes(type)) {
                            return data.datasets[0]?.data || [];
                        }
                        return data.datasets.map(dataset => ({
                            name: dataset.name,
                            data: dataset.data
                        }));
                    }
                }"
                x-init="init()"
                wire:key="report-chart-{{ $this->report?->id }}"
            >
                <div x-ref="chart" class="w-full" style="min-height: 350px;"></div>
            </div>
        </x-filament::section>
    @else
        <x-filament::section>
            <div class="text-center text-gray-500 py-8">
                No chart configured for this report.
            </div>
        </x-filament::section>
    @endif
</x-filament-widgets::widget>
