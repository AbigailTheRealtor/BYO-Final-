@extends('layouts.main')
@section('content')
    @livewire('buyer.buyer-agent-auction-counter-term', ['pab' => $pab, 'bidId' => $bid_id, 'parent_counter_id' => $parent_counter_id])
@endsection
