@extends('layouts.main')
@push('styles')
@endpush
@section('content')
    <div class="mainDashboard">
        <div class="container">
            @include('layouts.partials.dashboard_user_section')
            <div class="dashboardContentDetails mt-3">
                <div class="card">
                    <div class="row">
                        @include('layouts.partials.sidenav')
                        <div class="rightCol col-sm-12 col-md-8 col-lg-8">
                            <div class="container mt-5 myAuctions">
                                <h1>My Agents</h1>

                                <table class="table table-striped table-bordered">
                                    @inject('carbon', 'Carbon\Carbon')
                                    <thead>
                                        <tr>
                                            <th class="text-center">#</th>
                                            <th>Name</th>
                                            <th class="text-center">MLS ID</th>
                                            <th class="text-center">Total Auctions</th>
                                            <th class="text-center">Hiring Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {{-- @dd('ok'); --}}
                                        {{-- @dd(auth()->user()->agents);
                                        @dd(auth()->user()->agents); --}}
                                        @forelse (auth()->user()->agents as $agent)
                                            <tr>
                                                <td class="text-center">{{ $loop->iteration }}</td>
                                                <td>{{ $agent->agent->name }}</td>
                                                <td class="text-center">{{ $agent->agent->mls_id }}</td>
                                                <td class="text-center">
                                                    {{-- @dd(Auth::user()) --}}
                                                    {{ $agent->seller_properties->where('user_id', $agent->agent_id)->count() }}
                                                    {{-- {{ optional(auth()->user()->seller_properties)->where('user_id', $agent->agent_id)->count() }} --}}

                                                </td>
                                                <td class="text-center">
                                                    {{ $carbon::parse($agent->created_at)->format('F d, Y') }}</td>
                                            </tr>

                                        @empty
                                            <tr>
                                                <td colspan="5" class="text-center">
                                                    <h4 class="text-secondary">Record not found!</h4>
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>

                                </table>



                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
@push('scripts')
@endpush
