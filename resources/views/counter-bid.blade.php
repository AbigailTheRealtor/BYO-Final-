{{-- resources/views/hire_tenant_agent/counter-bid.blade.php --}}
@extends('layouts.main')
@section('content')

    @livewire('buyer.buyer-agent-auction-bid-counter', ['pab' => $pab, 'bidId' => $bid_id, 'parent_counter_id' => $parent_counter_id])
@endsection
