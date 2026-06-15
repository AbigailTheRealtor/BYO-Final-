<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>AI Inbox Alert</title>
    <style>
        body { font-family: Arial, sans-serif; color: #333; margin: 0; padding: 0; background: #f4f4f4; }
        .container { max-width: 600px; margin: 30px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
        .header { background: #049399; color: #fff; padding: 24px 32px; }
        .header h1 { margin: 0; font-size: 1.4rem; }
        .header .score-badge { display: inline-block; background: #fff; color: #049399; font-weight: bold; font-size: 1.1rem; padding: 4px 14px; border-radius: 20px; margin-top: 10px; }
        .body { padding: 28px 32px; }
        .body p { margin: 0 0 14px; line-height: 1.6; }
        .detail-row { display: flex; gap: 8px; margin-bottom: 10px; }
        .detail-label { font-weight: bold; min-width: 120px; color: #555; }
        .questions { background: #f8f9fa; border-left: 4px solid #049399; padding: 14px 18px; border-radius: 4px; margin: 18px 0; }
        .questions p { margin: 4px 0; font-style: italic; color: #444; }
        .cta { text-align: center; margin-top: 24px; }
        .cta a { background: #049399; color: #fff; text-decoration: none; padding: 12px 28px; border-radius: 6px; font-weight: bold; font-size: 1rem; display: inline-block; }
        .footer { background: #f4f4f4; padding: 16px 32px; font-size: .8rem; color: #888; text-align: center; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>🔥 AI Inbox Alert: Hot Lead</h1>
        <div class="score-badge">Lead Score: {{ $score ?? 0 }} / 100</div>
    </div>
    <div class="body">
        <p>Hi {{ $agentName ?? 'Agent' }},</p>
        <p>A visitor on your Agent AI chat has reached a high-intent score. Here's a summary:</p>

        <div class="detail-row">
            <span class="detail-label">Visitor:</span>
            <span>{{ $visitorLabel ?? 'Anonymous' }}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Lead Type:</span>
            <span>{{ ucfirst(str_replace('_', ' ', $leadType ?? 'unknown')) }}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Lead Score:</span>
            <span><strong>{{ $score ?? 0 }} / 100</strong></span>
        </div>

        @if (!empty($questions))
        <div class="questions">
            <p><strong>Recent questions asked:</strong></p>
            @foreach ($questions as $q)
                <p>"{{ $q }}"</p>
            @endforeach
        </div>
        @endif

        <div class="cta">
            <a href="{{ url('/agent/ai-inbox') }}">View in AI Inbox</a>
        </div>
    </div>
    <div class="footer">
        <p>This notification was sent because a visitor's chat session reached a lead score threshold on your Bid Your Offer Agent AI. Only you can see this notification.</p>
    </div>
</div>
</body>
</html>
