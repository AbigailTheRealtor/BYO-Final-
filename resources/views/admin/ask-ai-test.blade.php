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

{{-- ── 7-Column Per-Stage Trace Summary ────────────────────────────────── --}}
@isset($traceColumns)
<div class="card mb-3">
    <div class="card-header bg-secondary text-white py-2">
        <strong>Pipeline Trace — Per-Stage Summary</strong>
        <small class="ml-2">(7 columns; expand accordion panels below for full data)</small>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-bordered table-striped mb-0 font-monospace" style="font-size:0.82rem;">
                <thead class="thead-dark">
                    <tr>
                        <th title="Question type assigned by AskAiQuestionClassifierService">classifier_result</th>
                        <th title="Specific listing.* or faq_answers.* key resolved for this question">normalized_field_key</th>
                        <th title="Status returned by AskAiContextBuilderService (assembled / partial / not_found / failed)">context_status</th>
                        <th title="Status returned by AskAiResponseContractService (contract_ready / insufficient_context / refusal_required / unsupported)">contract_status</th>
                        <th title="Status returned by AskAiPromptBuilderService (prompt_ready / blocked / insufficient_context / unsupported / failed)">prompt_package_status</th>
                        <th title="Status returned by AskAiOpenAiAdapterService (generated / blocked / failed)">adapter_status</th>
                        <th title="Final pipeline status (ready / insufficient_context / blocked / unsupported / failed)">final_status</th>
                        <th title="Error message if any stage failed">error</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        {{-- classifier_result --}}
                        <td>
                            @php $cr = $traceColumns['classifier_result'] ?? 'n/a'; @endphp
                            @if($cr === 'listing_facts')
                                <span class="badge badge-success">{{ $cr }}</span>
                            @elseif($cr === 'unsupported')
                                <span class="badge badge-danger">{{ $cr }}</span>
                            @else
                                <span class="badge badge-info">{{ $cr }}</span>
                            @endif
                        </td>
                        {{-- normalized_field_key --}}
                        <td>
                            @if(!empty($traceColumns['normalized_field_key']))
                                <span class="text-primary">{{ $traceColumns['normalized_field_key'] }}</span>
                            @else
                                <span class="text-muted">null (full context)</span>
                            @endif
                        </td>
                        {{-- context_status --}}
                        <td>
                            @php $cs = $traceColumns['context_status'] ?? 'n/a'; @endphp
                            @if(in_array($cs, ['assembled', 'partial']))
                                <span class="badge badge-success">{{ $cs }}</span>
                            @elseif($cs === 'n/a')
                                <span class="text-muted">—</span>
                            @else
                                <span class="badge badge-warning">{{ $cs }}</span>
                            @endif
                        </td>
                        {{-- contract_status --}}
                        <td>
                            @php $ks = $traceColumns['contract_status'] ?? 'n/a'; @endphp
                            @if($ks === 'contract_ready')
                                <span class="badge badge-success">{{ $ks }}</span>
                            @elseif($ks === 'n/a')
                                <span class="text-muted">—</span>
                            @else
                                <span class="badge badge-warning">{{ $ks }}</span>
                            @endif
                        </td>
                        {{-- prompt_package_status --}}
                        <td>
                            @php $ps = $traceColumns['prompt_package_status'] ?? 'n/a'; @endphp
                            @if($ps === 'prompt_ready')
                                <span class="badge badge-success">{{ $ps }}</span>
                            @elseif($ps === 'n/a')
                                <span class="text-muted">—</span>
                            @else
                                <span class="badge badge-warning">{{ $ps }}</span>
                            @endif
                        </td>
                        {{-- adapter_status --}}
                        <td>
                            @php $as = $traceColumns['adapter_status'] ?? 'n/a'; @endphp
                            @if($as === 'generated')
                                <span class="badge badge-success">{{ $as }}</span>
                            @elseif(in_array($as, ['n/a', 'skipped']))
                                <span class="text-muted">{{ $as }}</span>
                            @elseif($as === 'blocked')
                                <span class="badge badge-warning">{{ $as }}</span>
                            @else
                                <span class="badge badge-danger">{{ $as }}</span>
                            @endif
                        </td>
                        {{-- final_status --}}
                        <td>
                            @php $fs = $traceColumns['final_status'] ?? 'n/a'; @endphp
                            @if($fs === 'ready')
                                <span class="badge badge-success">{{ $fs }}</span>
                            @elseif($fs === 'insufficient_context')
                                <span class="badge badge-warning">{{ $fs }}</span>
                            @elseif($fs === 'failed')
                                <span class="badge badge-danger">{{ $fs }}</span>
                            @else
                                <span class="badge badge-secondary">{{ $fs }}</span>
                            @endif
                        </td>
                        {{-- error --}}
                        <td>
                            @if(!empty($traceColumns['error']))
                                <span class="text-danger" title="{{ $traceColumns['error'] }}">
                                    {{ \Illuminate\Support\Str::limit($traceColumns['error'], 60) }}
                                </span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endisset

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
