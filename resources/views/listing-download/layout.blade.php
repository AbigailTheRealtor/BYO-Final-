<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $role }} Listing Snapshot</title>
    <style>
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 12px;
            line-height: 1.5;
            color: #333;
            margin: 0;
            padding: 20px;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .header h1 {
            font-size: 20px;
            margin: 0 0 5px 0;
        }
        .header .subtitle {
            font-size: 13px;
            color: #666;
        }
        .section {
            margin-bottom: 18px;
            page-break-inside: avoid;
        }
        .section-title {
            font-size: 14px;
            font-weight: bold;
            background: #f5f5f5;
            padding: 6px 10px;
            border-left: 4px solid #333;
            margin-bottom: 8px;
        }
        .field-row {
            padding: 3px 10px;
            display: flex;
        }
        .field-label {
            font-weight: bold;
            min-width: 200px;
            display: inline-block;
        }
        .field-value {
            display: inline;
        }
        .chip {
            display: inline-block;
            background: #e9ecef;
            border-radius: 12px;
            padding: 2px 10px;
            margin: 1px 3px;
            font-size: 11px;
        }
        .services-list {
            margin: 0;
            padding-left: 20px;
        }
        .services-list li {
            margin-bottom: 2px;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10px;
            color: #999;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
        table.fields {
            width: 100%;
            border-collapse: collapse;
        }
        table.fields td {
            padding: 4px 10px;
            vertical-align: top;
        }
        table.fields td.label {
            font-weight: bold;
            width: 200px;
            white-space: nowrap;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $role }} Listing Snapshot</h1>
        <div class="subtitle">
            @if (!empty($auction->listing_id))
                Listing ID: {{ $auction->listing_id }} |
            @endif
            Generated: {{ now()->format('M j, Y g:i A') }}
        </div>
    </div>

    @yield('content')

    <div class="footer">
        Bid Your Offer &mdash; Listing Snapshot &mdash; {{ now()->format('M j, Y') }}
    </div>
</body>
</html>
