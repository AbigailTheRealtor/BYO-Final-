<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hire This Agent</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }

        html, body {
            margin: 0;
            padding: 0;
            height: 100%;
            background: transparent;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
        }

        .byo-widget {
            border: 1px solid #e0e0e0;
            border-left: 4px solid #049399;
            border-radius: 10px;
            padding: 18px 20px 16px;
            background: #fff;
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .byo-top {
            flex: 1;
        }

        .byo-label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .07em;
            color: #049399;
            margin-bottom: 6px;
        }

        .byo-name {
            font-size: 17px;
            font-weight: 700;
            color: #1a1a1a;
            line-height: 1.2;
            margin-bottom: 4px;
        }

        .byo-meta {
            font-size: 13px;
            color: #666;
            margin-top: 3px;
        }

        .byo-meta-role {
            font-size: 13px;
            color: #444;
            font-weight: 500;
            margin-top: 6px;
        }

        .byo-services {
            font-size: 12px;
            color: #049399;
            margin-top: 8px;
            font-weight: 600;
        }

        .byo-services::before {
            content: '✓ ';
        }

        .byo-cta {
            display: block;
            margin-top: 14px;
            background: #049399;
            color: #fff;
            text-align: center;
            padding: 10px 16px;
            border-radius: 7px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: background .15s;
        }

        .byo-cta:hover {
            background: #036b70;
            color: #fff;
        }

        .byo-footer {
            font-size: 10px;
            color: #bbb;
            text-align: right;
            margin-top: 10px;
            letter-spacing: .02em;
        }

        .byo-footer a {
            color: #bbb;
            text-decoration: none;
        }

        .byo-footer a:hover {
            color: #049399;
        }
    </style>
</head>
<body>
    <div class="byo-widget">
        <div class="byo-top">
            <div class="byo-label">Agent Available to Hire</div>
            <div class="byo-name">{{ $agentName }}</div>
            <div class="byo-meta-role">{{ $roleLabel }} &middot; {{ $propertyTypeLabel }}</div>
            @if (!empty($brokerage))
                <div class="byo-meta">{{ $brokerage }}</div>
            @endif
            <div class="byo-services">{{ $serviceCount }} {{ $serviceCount === 1 ? 'service' : 'services' }} included</div>
        </div>
        <div>
            <a href="{{ $hireUrl }}" target="_top" class="byo-cta">
                Hire This Agent
            </a>
            <div class="byo-footer">
                Powered by <a href="{{ url('/') }}" target="_top">BidYourOffer</a>
            </div>
        </div>
    </div>
</body>
</html>
