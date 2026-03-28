@extends('layouts.main')
@section('content')
    @livewire('seller.seller-agent-auction-counter-term', ['pab' => $pab, 'bidId' => $bid_id])
@endsection
