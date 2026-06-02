@extends('layouts.admin')
@section('content')

<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Ask AI — Internal Pipeline Test</h5>
        <small class="text-muted">Admin-only debug interface. No database writes. Not linked from any navigation.</small>
    </div>
    <div class="card-body">

        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.ask-ai.test.run') }}">
            @csrf
            <div class="form-group row mb-3">
                <label for="listing_type" class="col-sm-2 col-form-label">Listing Type</label>
                <div class="col-sm-4">
                    <input type="text" class="form-control" id="listing_type" name="listing_type"
                           value="{{ old('listing_type') }}"
                           placeholder="e.g. seller, buyer, landlord, tenant" required>
                </div>
            </div>
            <div class="form-group row mb-3">
                <label for="listing_id" class="col-sm-2 col-form-label">Listing ID</label>
                <div class="col-sm-4">
                    <input type="number" class="form-control" id="listing_id" name="listing_id"
                           value="{{ old('listing_id') }}"
                           placeholder="Integer primary key" required>
                </div>
            </div>
            <div class="form-group row mb-3">
                <label for="question" class="col-sm-2 col-form-label">Question</label>
                <div class="col-sm-8">
                    <input type="text" class="form-control" id="question" name="question"
                           value="{{ old('question') }}"
                           placeholder="Enter the user question to test" required>
                </div>
            </div>
            <div class="form-group row mb-3">
                <label for="options" class="col-sm-2 col-form-label">Options <small class="text-muted">(JSON)</small></label>
                <div class="col-sm-8">
                    <textarea class="form-control font-monospace" id="options" name="options" rows="3"
                              placeholder='Optional JSON object, e.g. {"key":"value"}'>{{ old('options') }}</textarea>
                </div>
            </div>
            <div class="form-group row">
                <div class="col-sm-10 offset-sm-2">
                    <button type="submit" class="btn btn-primary">Run Pipeline</button>
                </div>
            </div>
        </form>

    </div>
</div>

@isset($result)

@php
    $finalResponse     = $result['final_response'] ?? [];
    $disclosures       = $finalResponse['disclosures']        ?? null;
    $sourceAttribution = $finalResponse['source_attribution'] ?? null;
@endphp

{{-- ── Status Badge ─────────────────────────────────────────────────────── --}}
<div class="mt-3 mb-3">
    @if($result['success'])
        <span class="badge badge-success" style="font-size:1rem;">success = true &nbsp;|&nbsp; status = {{ $result['status'] }}</span>
    @else
        <span class="badge badge-danger" style="font-size:1rem;">success = false &nbsp;|&nbsp; status = {{ $result['status'] }}</span>
    @endif
    @if($result['error'])
        <div class="alert alert-warning mt-2"><strong>Error:</strong> {{ $result['error'] }}</div>
    @endif
</div>

{{-- ── Result Panels ────────────────────────────────────────────────────── --}}
<div class="accordion" id="askAiResultAccordion">

    {{-- Panel: classification --}}
    <div class="card">
        <div class="card-header" id="heading-classification">
            <h2 class="mb-0">
                <button class="btn btn-link btn-block text-left" type="button"
                        data-toggle="collapse" data-target="#panel-classification">
                    classification
                </button>
            </h2>
        </div>
        <div id="panel-classification" class="collapse" data-parent="#askAiResultAccordion">
            <div class="card-body">
                <pre class="bg-light p-3 rounded">{{ json_encode($result['classification'], JSON_PRETTY_PRINT) }}</pre>
            </div>
        </div>
    </div>

    {{-- Panel: context --}}
    <div class="card">
        <div class="card-header" id="heading-context">
            <h2 class="mb-0">
                <button class="btn btn-link btn-block text-left" type="button"
                        data-toggle="collapse" data-target="#panel-context">
                    context
                </button>
            </h2>
        </div>
        <div id="panel-context" class="collapse" data-parent="#askAiResultAccordion">
            <div class="card-body">
                <pre class="bg-light p-3 rounded">{{ json_encode($result['context'], JSON_PRETTY_PRINT) }}</pre>
            </div>
        </div>
    </div>

    {{-- Panel: contract --}}
    <div class="card">
        <div class="card-header" id="heading-contract">
            <h2 class="mb-0">
                <button class="btn btn-link btn-block text-left" type="button"
                        data-toggle="collapse" data-target="#panel-contract">
                    contract
                </button>
            </h2>
        </div>
        <div id="panel-contract" class="collapse" data-parent="#askAiResultAccordion">
            <div class="card-body">
                <pre class="bg-light p-3 rounded">{{ json_encode($result['contract'], JSON_PRETTY_PRINT) }}</pre>
            </div>
        </div>
    </div>

    {{-- Panel: prompt_package --}}
    <div class="card">
        <div class="card-header" id="heading-prompt_package">
            <h2 class="mb-0">
                <button class="btn btn-link btn-block text-left" type="button"
                        data-toggle="collapse" data-target="#panel-prompt_package">
                    prompt_package
                </button>
            </h2>
        </div>
        <div id="panel-prompt_package" class="collapse" data-parent="#askAiResultAccordion">
            <div class="card-body">
                <pre class="bg-light p-3 rounded">{{ json_encode($result['prompt_package'], JSON_PRETTY_PRINT) }}</pre>
            </div>
        </div>
    </div>

    {{-- Panel: adapter_result --}}
    <div class="card">
        <div class="card-header" id="heading-adapter_result">
            <h2 class="mb-0">
                <button class="btn btn-link btn-block text-left" type="button"
                        data-toggle="collapse" data-target="#panel-adapter_result">
                    adapter_result
                </button>
            </h2>
        </div>
        <div id="panel-adapter_result" class="collapse" data-parent="#askAiResultAccordion">
            <div class="card-body">
                <pre class="bg-light p-3 rounded">{{ json_encode($result['adapter_result'], JSON_PRETTY_PRINT) }}</pre>
            </div>
        </div>
    </div>

    {{-- Panel: final_response (full object + nested sub-panels for disclosures & source_attribution) --}}
    <div class="card">
        <div class="card-header" id="heading-final_response">
            <h2 class="mb-0">
                <button class="btn btn-link btn-block text-left" type="button"
                        data-toggle="collapse" data-target="#panel-final_response">
                    final_response
                </button>
            </h2>
        </div>
        <div id="panel-final_response" class="collapse" data-parent="#askAiResultAccordion">
            <div class="card-body">
                <pre class="bg-light p-3 rounded">{{ json_encode($result['final_response'], JSON_PRETTY_PRINT) }}</pre>

                @if($disclosures !== null || $sourceAttribution !== null)
                    <div class="accordion mt-3" id="finalSubAccordion">

                        @if($disclosures !== null)
                        <div class="card border-info">
                            <div class="card-header bg-info text-white" id="heading-fr-disclosures">
                                <h2 class="mb-0">
                                    <button class="btn btn-link btn-block text-left text-white" type="button"
                                            data-toggle="collapse" data-target="#panel-fr-disclosures">
                                        final_response → disclosures
                                    </button>
                                </h2>
                            </div>
                            <div id="panel-fr-disclosures" class="collapse" data-parent="#finalSubAccordion">
                                <div class="card-body">
                                    <pre class="bg-light p-3 rounded">{{ json_encode($disclosures, JSON_PRETTY_PRINT) }}</pre>
                                </div>
                            </div>
                        </div>
                        @endif

                        @if($sourceAttribution !== null)
                        <div class="card border-info">
                            <div class="card-header bg-info text-white" id="heading-fr-source_attribution">
                                <h2 class="mb-0">
                                    <button class="btn btn-link btn-block text-left text-white" type="button"
                                            data-toggle="collapse" data-target="#panel-fr-source_attribution">
                                        final_response → source_attribution
                                    </button>
                                </h2>
                            </div>
                            <div id="panel-fr-source_attribution" class="collapse" data-parent="#finalSubAccordion">
                                <div class="card-body">
                                    <pre class="bg-light p-3 rounded">{{ json_encode($sourceAttribution, JSON_PRETTY_PRINT) }}</pre>
                                </div>
                            </div>
                        </div>
                        @endif

                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Panel: disclosures (top-level, extracted from final_response) --}}
    <div class="card">
        <div class="card-header" id="heading-disclosures">
            <h2 class="mb-0">
                <button class="btn btn-link btn-block text-left" type="button"
                        data-toggle="collapse" data-target="#panel-disclosures">
                    disclosures
                    @if($disclosures === null)<small class="text-muted ml-2">(null)</small>@endif
                </button>
            </h2>
        </div>
        <div id="panel-disclosures" class="collapse" data-parent="#askAiResultAccordion">
            <div class="card-body">
                <pre class="bg-light p-3 rounded">{{ json_encode($disclosures, JSON_PRETTY_PRINT) }}</pre>
            </div>
        </div>
    </div>

    {{-- Panel: source_attribution (top-level, extracted from final_response) --}}
    <div class="card">
        <div class="card-header" id="heading-source_attribution">
            <h2 class="mb-0">
                <button class="btn btn-link btn-block text-left" type="button"
                        data-toggle="collapse" data-target="#panel-source_attribution">
                    source_attribution
                    @if($sourceAttribution === null)<small class="text-muted ml-2">(null)</small>@endif
                </button>
            </h2>
        </div>
        <div id="panel-source_attribution" class="collapse" data-parent="#askAiResultAccordion">
            <div class="card-body">
                <pre class="bg-light p-3 rounded">{{ json_encode($sourceAttribution, JSON_PRETTY_PRINT) }}</pre>
            </div>
        </div>
    </div>

    {{-- Panel: error --}}
    <div class="card">
        <div class="card-header" id="heading-error">
            <h2 class="mb-0">
                <button class="btn btn-link btn-block text-left" type="button"
                        data-toggle="collapse" data-target="#panel-error">
                    error
                </button>
            </h2>
        </div>
        <div id="panel-error" class="collapse" data-parent="#askAiResultAccordion">
            <div class="card-body">
                <pre class="bg-light p-3 rounded">{{ json_encode($result['error'], JSON_PRETTY_PRINT) }}</pre>
            </div>
        </div>
    </div>

    {{-- Panel: success --}}
    <div class="card">
        <div class="card-header" id="heading-success">
            <h2 class="mb-0">
                <button class="btn btn-link btn-block text-left" type="button"
                        data-toggle="collapse" data-target="#panel-success">
                    success
                </button>
            </h2>
        </div>
        <div id="panel-success" class="collapse" data-parent="#askAiResultAccordion">
            <div class="card-body">
                <pre class="bg-light p-3 rounded">{{ json_encode($result['success'], JSON_PRETTY_PRINT) }}</pre>
            </div>
        </div>
    </div>

    {{-- Panel: status --}}
    <div class="card">
        <div class="card-header" id="heading-status">
            <h2 class="mb-0">
                <button class="btn btn-link btn-block text-left" type="button"
                        data-toggle="collapse" data-target="#panel-status">
                    status
                </button>
            </h2>
        </div>
        <div id="panel-status" class="collapse" data-parent="#askAiResultAccordion">
            <div class="card-body">
                <pre class="bg-light p-3 rounded">{{ json_encode($result['status'], JSON_PRETTY_PRINT) }}</pre>
            </div>
        </div>
    </div>

</div>
@endisset

@endsection
