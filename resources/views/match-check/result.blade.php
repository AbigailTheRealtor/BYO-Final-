@extends('layouts.main')

{{--
  Match Check — result page (Phase 4 · git-C14).
  Thin wrapper: extends the app layout and delegates every status branch to the
  layout-free _result_body partial (the same partial the render tests exercise directly).
  Renders from the data-only, F7-safe MatchCheckAnalysis only.
--}}

@section('content')
<div class="container py-4" style="max-width: 720px;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Match Check</h1>
        <a href="{{ route('match-check.show') }}" class="btn btn-outline-secondary btn-sm">Check another</a>
    </div>

    @include('match-check.partials._result_body', ['analysis' => $analysis])
</div>
@endsection
