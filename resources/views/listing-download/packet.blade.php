<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $role }} Listing Packet</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color: #222; }
        h1 { font-size: 18px; margin: 0 0 4px; color: #111; }
        .subtitle { font-size: 12px; color: #555; margin-bottom: 12px; }
        h2 { font-size: 13px; margin: 14px 0 6px; border-bottom: 1px solid #ccc; padding-bottom: 3px; color: #333; }
        .row { margin: 4px 0; page-break-inside: avoid; }
        .label { font-weight: bold; display: inline-block; width: 220px; vertical-align: top; color: #444; }
        .val { display: inline-block; width: calc(100% - 230px); vertical-align: top; }
        ul { margin: 0; padding-left: 16px; }
        li { margin: 1px 0; }
    </style>
</head>
<body>
    <h1>{{ $role }} Listing #{{ $listingId }}</h1>
    <div class="subtitle">Generated: {{ now()->format('m/d/Y g:i A') }}</div>

    @foreach($packet as $section)
        <h2>{{ $section['title'] }}</h2>

        @foreach($section['rows'] as $r)
            <div class="row">
                <span class="label">{{ $r['label'] }}:</span>
                <span class="val">
                    @if(count($r['items']) === 1)
                        {{ $r['items'][0] }}
                    @else
                        <ul>
                            @foreach($r['items'] as $it)
                                <li>{{ $it }}</li>
                            @endforeach
                        </ul>
                    @endif
                </span>
            </div>
        @endforeach
    @endforeach
</body>
</html>
