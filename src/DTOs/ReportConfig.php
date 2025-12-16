<?php

declare(strict_types=1);

namespace ByteXR\DynamicReporter\DTOs;

final readonly class ReportConfig
{
    /**
     * @param array<int, string> $columns The columns/fields to include in the report
     * @param array<int, array{field: string, operator: string, value: mixed, value2?: mixed}> $filters The filters to apply
     * @param array<int, array{field: string, direction: string}> $sort The sort configuration
     * @param array<int, string> $groupBy The fields to group by
     * @param int|null $limit Maximum number of records to return
     */
    public function __construct(
        public array $columns = [],
        public array $filters = [],
        public array $sort = [],
        public array $groupBy = [],
        public ?int $limit = null,
    ) {}

    /**
     * Create a ReportConfig from an array.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            columns: $data['columns'] ?? [],
            filters: $data['filters'] ?? [],
            sort: $data['sort'] ?? [],
            groupBy: $data['group_by'] ?? [],
            limit: isset($data['limit']) ? (int) $data['limit'] : null,
        );
    }

    /**
     * Convert the config to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'columns' => $this->columns,
            'filters' => $this->filters,
            'sort' => $this->sort,
            'group_by' => $this->groupBy,
            'limit' => $this->limit,
        ];
    }
}
