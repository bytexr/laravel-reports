<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Report' }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 10px;
            line-height: 1.4;
            color: #333;
            padding: 20px;
        }
        .header {
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #3b82f6;
        }
        .header-top {
            display: table;
            width: 100%;
            margin-bottom: 10px;
        }
        .header-left {
            display: table-cell;
            vertical-align: top;
            width: 70%;
        }
        .header-right {
            display: table-cell;
            vertical-align: top;
            text-align: right;
            width: 30%;
            color: #666;
            font-size: 9px;
        }
        .logo {
            max-height: 40px;
            margin-bottom: 10px;
        }
        .company-name {
            font-size: 14px;
            font-weight: bold;
            color: #1f2937;
            margin-bottom: 5px;
        }
        .report-title {
            font-size: 18px;
            font-weight: bold;
            color: #1f2937;
            margin-bottom: 5px;
        }
        .report-description {
            color: #666;
            font-size: 10px;
            margin-bottom: 10px;
        }
        .meta-info {
            margin-bottom: 15px;
            padding: 10px;
            background: #f8fafc;
            border-radius: 4px;
        }
        .date-range {
            margin-bottom: 8px;
            font-size: 10px;
        }
        .date-range-label {
            font-weight: bold;
            color: #374151;
        }
        .filters-section {
            margin-bottom: 10px;
        }
        .filters-label {
            font-weight: bold;
            margin-right: 10px;
            color: #374151;
        }
        .filter-badge {
            display: inline-block;
            padding: 2px 8px;
            background: #e0e7ff;
            color: #3730a3;
            border-radius: 3px;
            margin-right: 5px;
            margin-bottom: 3px;
            font-size: 9px;
        }
        .stats-section {
            margin-bottom: 15px;
        }
        .stats-grid {
            display: table;
            width: 100%;
        }
        .stat-item {
            display: table-cell;
            padding: 10px 15px;
            background: #f0f9ff;
            border-left: 3px solid #3b82f6;
            margin-right: 10px;
        }
        .stat-label {
            display: block;
            font-size: 9px;
            color: #666;
            text-transform: uppercase;
            margin-bottom: 3px;
        }
        .stat-value {
            display: block;
            font-size: 16px;
            font-weight: bold;
            color: #1f2937;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        th {
            background: #3b82f6;
            color: white;
            padding: 8px 10px;
            text-align: left;
            font-weight: bold;
            font-size: 9px;
            text-transform: uppercase;
        }
        td {
            padding: 6px 10px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 9px;
        }
        tr:nth-child(even) {
            background: #f9fafb;
        }
        .footer {
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            color: #666;
            font-size: 8px;
        }
        .limit-warning {
            margin-top: 10px;
            padding: 8px;
            background: #fef3c7;
            color: #92400e;
            border-radius: 4px;
            font-size: 9px;
        }
        .page-break {
            page-break-after: always;
        }
        @page {
            margin: 15mm;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-top">
            <div class="header-left">
                @if(!empty($logoUrl))
                    <img src="{{ $logoUrl }}" alt="Logo" class="logo">
                @endif
                @if(!empty($companyName))
                    <div class="company-name">{{ $companyName }}</div>
                @endif
                <h1 class="report-title">{{ $title ?? 'Report' }}</h1>
                @if(!empty($description))
                    <p class="report-description">{{ $description }}</p>
                @endif
            </div>
            <div class="header-right">
                <p>Generated: {{ $generatedAt instanceof \DateTimeInterface ? $generatedAt->format('F j, Y \a\t g:i A') : $generatedAt }}</p>
                <p>Total Records: {{ number_format($totalRows ?? 0) }}</p>
            </div>
        </div>
    </div>

    <div class="meta-info">
        @if(!empty($dateRange))
            <div class="date-range">
                <span class="date-range-label">Date Range:</span>
                {{ $dateRange['start'] ?? '' }} - {{ $dateRange['end'] ?? '' }}
            </div>
        @endif

        @if(!empty($filters))
            <div class="filters-section">
                <span class="filters-label">Applied Filters:</span>
                @foreach($filters as $filter)
                    @if(is_array($filter) && isset($filter['field']))
                        <span class="filter-badge">
                            {{ $filter['field'] }} {{ $filter['operator'] ?? '' }} {{ $filter['value'] ?? '' }}
                        </span>
                    @endif
                @endforeach
            </div>
        @endif

        @if($isLimited ?? false)
            <p class="limit-warning">
                Note: This report is limited to {{ number_format($maxRows ?? 1000) }} rows for PDF generation.
                Total records matching criteria: {{ number_format($totalRows ?? 0) }}
            </p>
        @endif
    </div>

    @if(!empty($stats))
        <div class="stats-section">
            <div class="stats-grid">
                @foreach($stats as $stat)
                    <div class="stat-item">
                        <span class="stat-label">{{ $stat['label'] ?? '' }}</span>
                        <span class="stat-value">{{ $stat['value'] ?? '' }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <table>
        <thead>
            <tr>
                @foreach($headers ?? [] as $header)
                    <th>{{ $header }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($rows ?? [] as $row)
                <tr>
                    @foreach($row as $cell)
                        <td>{{ $cell }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($headers ?? []) }}" style="text-align: center; padding: 20px; color: #666;">
                        No data available
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        <p>This report was automatically generated by Dynamic Reporter</p>
        <p>Page generated at {{ now()->format('Y-m-d H:i:s T') }}</p>
    </div>
</body>
</html>
