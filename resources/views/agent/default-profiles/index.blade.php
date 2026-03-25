@extends('layouts.main')

@section('title', 'My Default Bid Profiles')

@section('content')
<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">My Default Bid Profiles</h2>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="alert alert-info mb-4">
        <i class="fa-solid fa-circle-info me-2"></i>
        <strong>What are Default Bid Profiles?</strong>
        When you submit a bid on any listing, you can save your Agent Overview answers (About You, Why Hire You, Marketing Strategy, etc.) as a default profile for that listing type. Next time you bid on a similar listing, the form will automatically pre-fill with your saved answers — saving you time.
    </div>

    @if($profiles->isEmpty())
        <div class="card shadow-sm">
            <div class="card-body text-center py-5">
                <i class="fa-solid fa-file-circle-question fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No default profiles saved yet</h5>
                <p class="text-muted">When you submit a bid, you'll have the option to save your answers as a default profile for future use.</p>
            </div>
        </div>
    @else
        <div class="row g-4">
            @foreach($profiles as $profile)
            <div class="col-md-6 col-lg-4">
                <div class="card shadow-sm h-100">
                    <div class="card-header d-flex justify-content-between align-items-center" style="background-color: #049399; color: white;">
                        <div>
                            <span class="badge bg-white text-dark me-1">{{ \App\Models\AgentDefaultProfile::roleLabel($profile->role_type) }}</span>
                            <span class="badge bg-light text-dark">{{ \App\Models\AgentDefaultProfile::propertyLabel($profile->property_type) }}</span>
                        </div>
                        <small class="text-white-50">{{ $profile->updated_at->diffForHumans() }}</small>
                    </div>
                    <div class="card-body">
                        @php $data = $profile->profile_data ?? []; @endphp

                        @if(!empty($data['bio']))
                        <div class="mb-2">
                            <strong class="small text-muted d-block">About Agent</strong>
                            <p class="mb-0 small">{{ \Illuminate\Support\Str::limit($data['bio'], 120) }}</p>
                        </div>
                        @endif

                        @if(!empty($data['why_hire_you']))
                        <div class="mb-2">
                            <strong class="small text-muted d-block">Why Hire You</strong>
                            <p class="mb-0 small">{{ \Illuminate\Support\Str::limit($data['why_hire_you'], 100) }}</p>
                        </div>
                        @endif

                        @if(!empty($data['marketing_plan']))
                        <div class="mb-2">
                            <strong class="small text-muted d-block">Marketing Strategy</strong>
                            <p class="mb-0 small">{{ \Illuminate\Support\Str::limit($data['marketing_plan'], 100) }}</p>
                        </div>
                        @endif

                        @if(!empty($data['year_licensed']))
                        <div class="mb-2">
                            <strong class="small text-muted d-block">Year Licensed</strong>
                            <p class="mb-0 small">{{ $data['year_licensed'] }}</p>
                        </div>
                        @endif
                    </div>
                    <div class="card-footer bg-transparent text-end">
                        <form action="{{ route('agent.default-profiles.destroy', $profile->id) }}" method="POST"
                              onsubmit="return confirm('Delete this default profile?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                <i class="fa-solid fa-trash-can me-1"></i> Delete
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
