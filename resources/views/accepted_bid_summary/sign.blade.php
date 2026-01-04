@extends('layouts.main')

@section('content')
<div class="container py-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>{{ $userRole === 'tenant' ? 'Tenant' : 'Agent' }}: E-Sign Acknowledgement</h2>
                <a href="{{ route('accepted-bid-summary.view', $summary->id) }}" class="btn btn-secondary">Cancel</a>
            </div>

            <div class="row">
                <div class="col-lg-8">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Review Accepted Bid Summary</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="summary-preview" style="max-height: 500px; overflow-y: auto; padding: 20px; background: #f8f9fa;">
                                {!! $html !!}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card position-sticky" style="top: 20px;">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">E-Sign Acknowledgement</h5>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info">
                                <small>
                                    <strong>Important:</strong> By signing below, you acknowledge receipt and review of this Accepted Bid Summary. This is an acknowledgement only, not a contract execution.
                                </small>
                            </div>

                            <form action="{{ route('accepted-bid-summary.sign', $summary->id) }}" method="POST">
                                @csrf
                                
                                <div class="mb-3">
                                    <label for="signature_name" class="form-label">
                                        Type Your Full Legal Name <span class="text-danger">*</span>
                                    </label>
                                    <input 
                                        type="text" 
                                        class="form-control @error('signature_name') is-invalid @enderror" 
                                        id="signature_name" 
                                        name="signature_name" 
                                        placeholder="Enter your full legal name"
                                        value="{{ old('signature_name') }}"
                                        required
                                    >
                                    @error('signature_name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Role</label>
                                    <input type="text" class="form-control" value="{{ ucfirst($userRole) }}" disabled>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Timestamp</label>
                                    <input type="text" class="form-control" value="{{ now()->format('M j, Y g:i A T') }}" disabled>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">IP Address</label>
                                    <input type="text" class="form-control" value="{{ request()->ip() }}" disabled>
                                </div>

                                <div class="form-check mb-4">
                                    <input class="form-check-input" type="checkbox" id="agree_terms" required>
                                    <label class="form-check-label" for="agree_terms">
                                        <small>I confirm that I have reviewed the Accepted Bid Summary and acknowledge its contents.</small>
                                    </label>
                                </div>

                                <button type="submit" class="btn btn-primary btn-lg w-100">
                                    {{ $userRole === 'tenant' ? 'Tenant' : 'Agent' }}: E-Sign Acknowledgement
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .summary-preview {
        font-size: 0.9em;
    }
    .summary-preview h1 {
        font-size: 1.5em;
    }
    .summary-preview h2 {
        font-size: 1.2em;
    }
</style>
@endsection
