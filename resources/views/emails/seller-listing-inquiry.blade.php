<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $inquiry->type === 'showing' ? 'Showing Request' : 'New Question' }}: {{ $listingTitle }}</title>
    <style>
        body { font-family: Arial, sans-serif; color: #1e293b; background: #f8fafc; margin: 0; padding: 0; }
        .wrap { max-width: 600px; margin: 30px auto; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; }
        .header { background: #1e293b; color: #fff; padding: 24px 28px; }
        .header h2 { margin: 0; font-size: 1.1rem; }
        .header p { margin: 6px 0 0; font-size: 0.85rem; color: #94a3b8; }
        .body { padding: 24px 28px; }
        .row { margin-bottom: 14px; }
        .label { font-size: 0.78rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 3px; }
        .value { font-size: 0.95rem; color: #1e293b; }
        .section-title { font-size: 0.85rem; font-weight: 700; color: #2563eb; margin: 20px 0 10px; padding-bottom: 6px; border-bottom: 1px solid #e2e8f0; }
        .box { background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 6px; padding: 14px 16px; font-size: 0.92rem; color: #334155; white-space: pre-wrap; }
        .footer { background: #f8fafc; padding: 16px 28px; font-size: 0.78rem; color: #94a3b8; border-top: 1px solid #e2e8f0; }
        a { color: #2563eb; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="header">
        <h2>{{ $inquiry->type === 'showing' ? '📅 Showing Request' : '❓ New Question' }}</h2>
        <p>Received for: <strong>{{ $listingTitle }}</strong></p>
    </div>
    <div class="body">

        <div class="section-title">Contact Information</div>

        <div class="row">
            <div class="label">Name</div>
            <div class="value">{{ $inquiry->name }}</div>
        </div>
        <div class="row">
            <div class="label">Email</div>
            <div class="value"><a href="mailto:{{ $inquiry->email }}">{{ $inquiry->email }}</a></div>
        </div>
        @if($inquiry->phone)
        <div class="row">
            <div class="label">Phone</div>
            <div class="value">{{ $inquiry->phone }}</div>
        </div>
        @endif

        @if($inquiry->type === 'showing')
        <div class="section-title">Showing Details</div>
        @if($inquiry->preferred_date)
        <div class="row">
            <div class="label">Preferred Date</div>
            <div class="value">{{ \Carbon\Carbon::parse($inquiry->preferred_date)->format('F j, Y') }}</div>
        </div>
        @endif
        @if($inquiry->preferred_time)
        <div class="row">
            <div class="label">Preferred Time</div>
            <div class="value">{{ $inquiry->preferred_time }}</div>
        </div>
        @endif
        @if($inquiry->message)
        <div class="row">
            <div class="label">Message</div>
            <div class="box">{{ $inquiry->message }}</div>
        </div>
        @endif
        @else
        <div class="section-title">Question</div>
        @if($inquiry->question)
        <div class="row">
            <div class="box">{{ $inquiry->question }}</div>
        </div>
        @endif
        @endif

        <div class="section-title">Listing</div>
        <div class="row">
            <div class="label">View Listing</div>
            <div class="value"><a href="{{ $listingUrl }}">{{ $listingUrl }}</a></div>
        </div>
        <div class="row">
            <div class="label">Submitted</div>
            <div class="value">{{ $inquiry->created_at ? $inquiry->created_at->format('F j, Y \a\t g:i A') : now()->format('F j, Y \a\t g:i A') }}</div>
        </div>

    </div>
    <div class="footer">
        This inquiry was submitted through the Bid Your Offer public listing page. Reply directly to the inquirer at {{ $inquiry->email }}.
    </div>
</div>
</body>
</html>
