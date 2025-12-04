@extends('layouts.admin')
@push('styles')

@endpush
@section('content')

<div class="card">
    <ul class="nav nav-tabs border-tab" id="top-tab" role="tablist">
        <li class="nav-item"><a href="{{route('admin.landlord.agent.auctions')}}?type=0" class="nav-link {{$type=="0"?"active":""}}"><i class="icofont icofont-close"></i>Pending Approval</a></li>
        <li class="nav-item"><a href="{{route('admin.landlord.agent.auctions')}}?type=1" class="nav-link {{$type=="1"?"active":""}}"><i class="icofont icofont-check"></i>Approved</a></li>
        <li class="nav-item"><a href="{{route('admin.landlord.agent.auctions')}}?type=2" class="nav-link {{$type=="2"?"active":""}}"><i class="icofont icofont-checked"></i>Sold</a></li>
    </ul>
    <table class="table">
        <thead>
            <th>#</th>
            <th>User</th>
            <th>Address</th>
            <th>Auction Type</th>
            <th>List Date</th>
            @if($type == 2)
            <th>Sold Date</th>
            @endif
            @if($type != 1 && $type != 2)
            <th>Action</th>
            @endif
        </thead>
        <tbody>
            @inject('carbon', 'Carbon\Carbon')
            @inject('propertyType', 'App\Models\PropertyType')
            @foreach($auctions as $auction)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{$auction->user->name}}</td>
                    <td>{{ $auction->address }}</td>
                    <td>{{ $auction->auction_type }}</td>
                    <td>
                        @if($auction->created_at)
                        {{ Carbon\Carbon::parse($auction->created_at)->format('M j, Y') }}
                        @else
                        N/A
                        @endif
                    </td>
                    @if($type == 2)
                    <td>
                        @if($auction->sold_date)
                        {{ Carbon\Carbon::parse($auction->sold_date)->format('M j, Y') }}
                        @else
                        N/A
                        @endif
                    </td>
                    @endif
                    @if($type != 1 && $type != 2)
                    <td>
                        <a href="{{route('admin.landlord.agent.auction.approve',$auction->id)}}" class="btn btn-xs btn-primary">Approve</a>
                    </td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

@endsection
