{{-- resources/views/hire_landlord_agent/counter-bid.blade.php --}}
@extends('layouts.main')
@section('content')
    @livewire('landlord.landlord-agent-auction-counter-term', ['pab' => $pab, 'bidId' => $bid_id, 'parent_counter_id' => $parent_counter_id])
@endsection
