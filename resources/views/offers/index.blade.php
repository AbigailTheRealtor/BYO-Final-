@extends('layouts.main')

@section('content')
<div class="container py-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>My Offers</h2>
            </div>

            @if($offers->isEmpty())
                <div class="alert alert-info">You have no offers yet.</div>
            @else
                <div class="card">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Offer ID</th>
                                        <th>Status</th>
                                        <th>Role</th>
                                        <th>Parent Offer ID</th>
                                        <th>Created At</th>
                                        <th>Expires At</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($offers as $offer)
                                        <tr>
                                            <td>{{ $offer->id }}</td>
                                            <td>
                                                @php
                                                    $statusColors = [
                                                        'draft'     => 'secondary',
                                                        'submitted' => 'primary',
                                                        'countered' => 'warning',
                                                        'accepted'  => 'success',
                                                        'rejected'  => 'danger',
                                                        'withdrawn' => 'dark',
                                                        'expired'   => 'light',
                                                    ];
                                                    $color = $statusColors[$offer->status] ?? 'secondary';
                                                @endphp
                                                <span class="badge badge-{{ $color }}">{{ ucfirst($offer->status) }}</span>
                                            </td>
                                            <td>{{ $offer->role }}</td>
                                            <td>{{ $offer->parent_offer_id ?? '—' }}</td>
                                            <td>{{ $offer->created_at ? $offer->created_at->format('M j, Y g:i A') : '—' }}</td>
                                            <td>{{ $offer->expires_at ? $offer->expires_at->format('M j, Y g:i A') : '—' }}</td>
                                            <td>
                                                <a href="{{ route('offers.show', $offer) }}" class="btn btn-sm btn-outline-primary">View Offer</a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
