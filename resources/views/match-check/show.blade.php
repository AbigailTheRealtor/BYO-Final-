@extends('layouts.main')

{{--
  Match Check — lookup form (Phase 4 · git-C14).
  First consumer surface. Reachable only when config('mls_match_check.enabled') is ON
  (the CheckMatchCheckEnabled middleware 404s the whole group otherwise) and the user is
  authenticated. v1 exposes two identifier modes only: MLS # and address (owner §7.2).
--}}

@section('content')
<div class="container py-4" style="max-width: 720px;">
    <h1 class="h3 mb-2">Match Check</h1>
    <p class="text-muted mb-4">
        Enter a listing's MLS&nbsp;# or address to see how it matches your saved search criteria.
    </p>

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('match-check.lookup') }}" class="card">
        @csrf
        <div class="card-body">
            <div class="form-group mb-3">
                <label class="d-block font-weight-bold mb-2">Look up by</label>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="mode" id="mode-mls"
                           value="mls" {{ old('mode', 'mls') === 'mls' ? 'checked' : '' }}>
                    <label class="form-check-label" for="mode-mls">MLS&nbsp;#</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="mode" id="mode-address"
                           value="address" {{ old('mode') === 'address' ? 'checked' : '' }}>
                    <label class="form-check-label" for="mode-address">Address</label>
                </div>
            </div>

            <div class="form-group mb-3">
                <label for="mls_number">MLS&nbsp;#</label>
                <input type="text" class="form-control" id="mls_number" name="mls_number"
                       value="{{ old('mls_number') }}" maxlength="64" placeholder="e.g. A4567890" autocomplete="off">
            </div>

            <div class="form-group mb-0">
                <label for="address">Property address</label>
                <input type="text" class="form-control" id="address" name="address"
                       value="{{ old('address') }}" maxlength="255" placeholder="e.g. 123 Ocean Dr, Sarasota, FL" autocomplete="off">
            </div>
        </div>
        <div class="card-footer text-right">
            <button type="submit" class="btn btn-primary">Check match</button>
        </div>
    </form>
</div>
@endsection
