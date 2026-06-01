@extends('layouts.admin')
@section('content')

<div class="alert alert-warning border border-warning d-flex align-items-start gap-2 mb-4" role="alert">
    <strong>&#9888; Internal admin preview only &mdash; not public, not client-facing, not agent-facing.</strong>
</div>

@if(session('error'))
    <div class="alert alert-danger border border-danger mb-4" role="alert">
        <strong>Error:</strong> {{ session('error') }}
    </div>
@endif

@if(session('success'))
    <div class="alert alert-success border border-success mb-4" role="alert">
        {{ session('success') }}
    </div>
@endif

<div class="mb-3 d-flex align-items-center gap-2">
    <a href="{{ route('admin.dna.property.index') }}" class="btn btn-sm btn-outline-secondary">&larr; Back to Property DNA Index</a>

    @if(auth()->user() && auth()->user()->user_type === 'admin' && isset($profile) && !($hasExistingReport ?? true))
        <form method="POST" action="{{ route('admin.property-dna.marketing-reports.generate', $profile->id) }}" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-sm btn-primary"
                onclick="return confirm('Generate a marketing report for this profile? This will call the AI pipeline and create rows in the database.')">
                Generate Marketing Report
            </button>
        </form>
    @elseif(auth()->user() && auth()->user()->user_type === 'admin' && isset($profile) && ($hasExistingReport ?? false))
        <span class="text-muted small">A marketing report already exists for this profile.</span>
    @endif
</div>

<div class="card mb-3">
    <div class="card-header">
        <h5 class="mb-0">Marketing Brief Preview &mdash; Profile #{{ $profile->id }}</h5>
        <div class="text-muted small mt-1">
            {{ $profile->listing_type }} / Listing {{ $profile->listing_id }} / Version {{ $profile->version }}
        </div>
    </div>
</div>

{{-- ================================================================
     SECTION 1: property_attribute_context
     ================================================================ --}}
<div class="card mb-4">
    <div class="card-header bg-light">
        <h6 class="mb-0 fw-semibold">1. Property Attribute Context</h6>
        <div class="text-muted small">Phase P pass-through &mdash; attribute_context</div>
    </div>
    <div class="card-body p-0">
        @if(empty($brief['property_attribute_context']))
            <p class="text-muted p-3 mb-0"><em>No data — section is empty.</em></p>
        @else
            <pre class="p-3 mb-0" style="font-size:.82rem;white-space:pre-wrap;word-break:break-word;">{{ json_encode($brief['property_attribute_context'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
        @endif
    </div>
</div>

{{-- ================================================================
     SECTION 2: transaction_context
     ================================================================ --}}
<div class="card mb-4">
    <div class="card-header bg-light">
        <h6 class="mb-0 fw-semibold">2. Transaction Context</h6>
        <div class="text-muted small">Phase P pass-through &mdash; transaction_context</div>
    </div>
    <div class="card-body p-0">
        @if(empty($brief['transaction_context']))
            <p class="text-muted p-3 mb-0"><em>No data — section is empty.</em></p>
        @else
            <pre class="p-3 mb-0" style="font-size:.82rem;white-space:pre-wrap;word-break:break-word;">{{ json_encode($brief['transaction_context'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
        @endif
    </div>
</div>

{{-- ================================================================
     SECTION 3: quantitative_context
     ================================================================ --}}
<div class="card mb-4">
    <div class="card-header bg-light">
        <h6 class="mb-0 fw-semibold">3. Quantitative Context</h6>
        <div class="text-muted small">Phase P pass-through &mdash; quantitative_context</div>
    </div>
    <div class="card-body p-0">
        @if(empty($brief['quantitative_context']))
            <p class="text-muted p-3 mb-0"><em>No data — section is empty.</em></p>
        @else
            <pre class="p-3 mb-0" style="font-size:.82rem;white-space:pre-wrap;word-break:break-word;">{{ json_encode($brief['quantitative_context'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
        @endif
    </div>
</div>

{{-- ================================================================
     SECTION 4: marketing_asset_checklist
     ================================================================ --}}
<div class="card mb-4">
    <div class="card-header bg-light">
        <h6 class="mb-0 fw-semibold">4. Marketing Asset Checklist</h6>
        <div class="text-muted small">Derived from the presentation bucket</div>
    </div>
    <div class="card-body">
        @forelse($brief['marketing_asset_checklist'] as $item)
            <dl class="row mb-2 border-bottom pb-2">
                <dt class="col-sm-2">Tag</dt>
                <dd class="col-sm-10"><code>{{ $item['tag'] ?: '(none)' }}</code></dd>
                <dt class="col-sm-2">Entry</dt>
                <dd class="col-sm-10">{{ $item['checklist_entry'] }}</dd>
            </dl>
        @empty
            <p class="text-muted mb-0"><em>No data — section is empty.</em></p>
        @endforelse
    </div>
</div>

{{-- ================================================================
     SECTION 5: missing_information_checklist
     ================================================================ --}}
<div class="card mb-4">
    <div class="card-header bg-light">
        <h6 class="mb-0 fw-semibold">5. Missing Information Checklist</h6>
        <div class="text-muted small">Derived from empty named buckets</div>
    </div>
    <div class="card-body">
        @forelse($brief['missing_information_checklist'] as $item)
            <dl class="row mb-2 border-bottom pb-2">
                <dt class="col-sm-2">Context Group</dt>
                <dd class="col-sm-10"><code>{{ $item['context_group'] }}</code></dd>
                <dt class="col-sm-2">Bucket</dt>
                <dd class="col-sm-10"><code>{{ $item['bucket'] }}</code></dd>
                <dt class="col-sm-2">Entry</dt>
                <dd class="col-sm-10">{{ $item['checklist_entry'] }}</dd>
            </dl>
        @empty
            <p class="text-muted mb-0"><em>No data — section is empty.</em></p>
        @endforelse
    </div>
</div>

{{-- ================================================================
     SECTION 6: seller_landlord_questions
     ================================================================ --}}
<div class="card mb-4">
    <div class="card-header bg-light">
        <h6 class="mb-0 fw-semibold">6. Seller / Landlord Questions</h6>
        <div class="text-muted small">Pre-written questions for empty or sparse buckets</div>
    </div>
    <div class="card-body">
        @forelse($brief['seller_landlord_questions'] as $item)
            <dl class="row mb-2 border-bottom pb-2">
                <dt class="col-sm-2">Context Group</dt>
                <dd class="col-sm-10"><code>{{ $item['context_group'] }}</code></dd>
                <dt class="col-sm-2">Bucket</dt>
                <dd class="col-sm-10"><code>{{ $item['bucket'] }}</code></dd>
                <dt class="col-sm-2">Question</dt>
                <dd class="col-sm-10">{{ $item['question'] }}</dd>
            </dl>
        @empty
            <p class="text-muted mb-0"><em>No data — section is empty.</em></p>
        @endforelse
    </div>
</div>

{{-- ================================================================
     SECTION 7: listing_preparation_notes
     ================================================================ --}}
<div class="card mb-4">
    <div class="card-header bg-light">
        <h6 class="mb-0 fw-semibold">7. Listing Preparation Notes</h6>
        <div class="text-muted small">Derived from timing, transaction_structure, and financing buckets</div>
    </div>
    <div class="card-body">
        @forelse($brief['listing_preparation_notes'] as $item)
            <dl class="row mb-2 border-bottom pb-2">
                <dt class="col-sm-2">Bucket</dt>
                <dd class="col-sm-10"><code>{{ $item['bucket'] }}</code></dd>
                <dt class="col-sm-2">Tag</dt>
                <dd class="col-sm-10"><code>{{ $item['tag'] ?: '(none)' }}</code></dd>
                <dt class="col-sm-2">Note</dt>
                <dd class="col-sm-10">{{ $item['note'] }}</dd>
            </dl>
        @empty
            <p class="text-muted mb-0"><em>No data — section is empty.</em></p>
        @endforelse
    </div>
</div>

{{-- ================================================================
     SECTION 8: neutral_feature_summary
     ================================================================ --}}
<div class="card mb-4">
    <div class="card-header bg-light">
        <h6 class="mb-0 fw-semibold">8. Neutral Feature Summary</h6>
        <div class="text-muted small">Factual attribute and quantitative entries</div>
    </div>
    <div class="card-body">
        @forelse($brief['neutral_feature_summary'] as $item)
            <dl class="row mb-2 border-bottom pb-2">
                <dt class="col-sm-2">Source</dt>
                <dd class="col-sm-10"><code>{{ $item['source'] ?? '—' }}</code></dd>
                @if(isset($item['bucket']))
                    <dt class="col-sm-2">Bucket</dt>
                    <dd class="col-sm-10"><code>{{ $item['bucket'] }}</code></dd>
                @endif
                @if(isset($item['tag']))
                    <dt class="col-sm-2">Tag</dt>
                    <dd class="col-sm-10"><code>{{ $item['tag'] ?: '(none)' }}</code></dd>
                @endif
                @if(isset($item['trait']))
                    <dt class="col-sm-2">Trait</dt>
                    <dd class="col-sm-10"><code>{{ $item['trait'] }}</code></dd>
                @endif
                @if(isset($item['value']))
                    <dt class="col-sm-2">Value</dt>
                    <dd class="col-sm-10">{{ $item['value'] }}</dd>
                @endif
                @if(isset($item['description']))
                    <dt class="col-sm-2">Description</dt>
                    <dd class="col-sm-10">{{ $item['description'] }}</dd>
                @endif
            </dl>
        @empty
            <p class="text-muted mb-0"><em>No data — section is empty.</em></p>
        @endforelse
    </div>
</div>

{{-- ================================================================
     SECTION 9: summary
     ================================================================ --}}
<div class="card mb-4">
    <div class="card-header bg-light">
        <h6 class="mb-0 fw-semibold">9. Summary</h6>
        <div class="text-muted small">Six deterministic integer counts</div>
    </div>
    <div class="card-body">
        @if(empty($brief['summary']))
            <p class="text-muted mb-0"><em>No data — section is empty.</em></p>
        @else
            <dl class="row mb-0">
                @foreach($brief['summary'] as $key => $value)
                    <dt class="col-sm-4"><code>{{ $key }}</code></dt>
                    <dd class="col-sm-8">{{ $value }}</dd>
                @endforeach
            </dl>
        @endif
    </div>
</div>

@endsection
