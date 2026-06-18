@extends('layouts.main')

@php
    $str = function (string $key) use ($meta): string {
        $v = $meta[$key] ?? '';
        return is_array($v) ? implode(', ', array_map(fn($e) => (string)$e, $v)) : (string) $v;
    };

    $addrParts = array_filter([
        $meta['address'] ?? null,
        !empty($meta['unit_number']) ? $meta['unit_number'] : null,
        $meta['property_city'] ?? null,
    ]);
    $addrState = trim($meta['property_state'] ?? '');
    $addrZip   = trim($meta['property_zip'] ?? $meta['zip_code'] ?? '');
    $stateZip  = trim($addrState . ($addrState && $addrZip ? ' ' : '') . $addrZip);
    if ($stateZip) $addrParts[] = $stateZip;
    $fullAddress = implode(', ', array_filter($addrParts));
@endphp

@section('content')
<div class="container py-4" style="max-width:960px;">

    <div class="mb-3 d-flex align-items-center gap-2 flex-wrap">
        <a href="{{ route('offer.listing.landlord.view', ['id' => $auction->id]) }}"
           class="btn btn-sm btn-outline-secondary">
            <i class="fa-solid fa-arrow-left me-1"></i>Back to listing
        </a>
    </div>

    <h2 class="fw-bold mb-1" style="color:#1e293b;">Qualification check submissions</h2>
    @if($fullAddress)
        <p class="text-muted mb-4"><i class="fa-solid fa-location-dot me-1" style="color:#0f766e;"></i>{{ $fullAddress }}</p>
    @endif

    @if($submissions->isEmpty())
    <div class="alert alert-secondary">
        <i class="fa-solid fa-circle-info me-2"></i>No rental qualification submissions yet for this listing.
    </div>
    @else
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead style="background:#f8fafc;">
                    <tr>
                        <th style="font-size:.78rem;text-transform:uppercase;letter-spacing:.05em;color:#64748b;padding:.75rem 1rem;">Applicant</th>
                        <th style="font-size:.78rem;text-transform:uppercase;letter-spacing:.05em;color:#64748b;">Credit Score</th>
                        <th style="font-size:.78rem;text-transform:uppercase;letter-spacing:.05em;color:#64748b;">Employment</th>
                        <th style="font-size:.78rem;text-transform:uppercase;letter-spacing:.05em;color:#64748b;">Occupants</th>
                        <th style="font-size:.78rem;text-transform:uppercase;letter-spacing:.05em;color:#64748b;">Submitted</th>
                        <th style="font-size:.78rem;text-transform:uppercase;letter-spacing:.05em;color:#64748b;"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($submissions as $sub)
                    <tr>
                        <td style="padding:.75rem 1rem;">
                            <div class="fw-semibold" style="color:#1e293b;">{{ $sub->name }}</div>
                            <div style="font-size:.82rem;color:#64748b;">{{ $sub->email }}</div>
                        </td>
                        <td>
                            @if($sub->estimated_credit_score)
                                <span style="font-size:.88rem;color:#334155;">{{ $sub->estimated_credit_score }}</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if($sub->employment_status)
                                <span style="font-size:.88rem;color:#334155;">{{ $sub->employment_status }}</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if($sub->number_of_occupants)
                                <span style="font-size:.88rem;color:#334155;">{{ $sub->number_of_occupants }}</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            <span style="font-size:.82rem;color:#64748b;">{{ $sub->created_at->format('M j, Y') }}</span>
                        </td>
                        <td>
                            <a href="{{ route('offer.listing.landlord.qualification.review', ['listing' => $auction->id, 'check' => $sub->id]) }}"
                               class="btn btn-sm btn-outline-primary">
                                Review
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">
        {{ $submissions->links() }}
    </div>
    @endif

</div>
@endsection
