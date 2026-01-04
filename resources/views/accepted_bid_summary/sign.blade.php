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

                            <form action="{{ route('accepted-bid-summary.sign', $summary->id) }}" method="POST" id="signForm">
                                @csrf
                                <input type="hidden" name="timezone" id="timezone" value="UTC">
                                
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
                                    <input type="text" class="form-control" id="localTimestamp" value="Loading..." disabled>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Your Timezone</label>
                                    <input type="text" class="form-control" id="timezoneDisplay" value="Detecting..." disabled>
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

                                <button type="submit" class="btn btn-primary btn-lg w-100" style="background-color: #0d6efd; border-color: #0d6efd; color: #ffffff;">
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    try {
        var tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
        document.getElementById('timezone').value = tz || 'UTC';
        
        var abbr = getTimezoneAbbr(tz);
        document.getElementById('timezoneDisplay').value = abbr + ' (' + tz + ')';
        
        var now = new Date();
        var options = { 
            year: 'numeric', 
            month: 'short', 
            day: 'numeric', 
            hour: 'numeric', 
            minute: '2-digit',
            hour12: true
        };
        var localTimeStr = now.toLocaleString('en-US', options);
        document.getElementById('localTimestamp').value = localTimeStr + ' (' + abbr + ')';
    } catch (e) {
        document.getElementById('timezone').value = 'UTC';
        document.getElementById('timezoneDisplay').value = 'UTC (Could not detect)';
        document.getElementById('localTimestamp').value = new Date().toISOString();
    }
    
    function getTimezoneAbbr(tz) {
        var abbrs = {
            'America/New_York': 'ET',
            'America/Chicago': 'CT',
            'America/Denver': 'MT',
            'America/Los_Angeles': 'PT',
            'America/Phoenix': 'MST',
            'America/Anchorage': 'AKT',
            'Pacific/Honolulu': 'HST'
        };
        return abbrs[tz] || tz.split('/').pop().replace(/_/g, ' ');
    }
});
</script>
@endsection
