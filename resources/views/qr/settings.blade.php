@extends('layouts.main')
@push('styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/bbbootstrap/libraries@main/choices.min.css">
@endpush
@section('content')
    <div class="mainDashboard">
        <form class="settings-for" method="POST" action="{{ route('agent.qr.settings') }}" enctype="multipart/form-data">
            @csrf
            <div class="container">

                @include('layouts.partials.dashboard_user_section')

                <div class="dashboardContentDetails mt-3 mb-5">
                    <div class="card">
                        <div class="row">
                            @include('layouts.partials.sidenav')
                            <div class="rightCol col-sm-12 col-md-9 col-lg-9">
                                <div class="container mt-5 mySettings">
                                    <h1>QR Code Settings</h1>
                                    <hr>
                                    <div class="form-group">
                                        <label>QR Code</label>
                                        <div class="qr-code" style="width: 200px; min-height:200px;">
                                            @php
                                            $id = Auth::id();
                                            $user = Auth::user();
                                            $short_id = Auth::user()->short_id;
                                            $uri_qr = route('short.uri', $short_id);
                                            $uri = $user->get->qr ?? route('author', $id);
                                            @endphp
                                            {{qr_code($uri_qr, 150)}}
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label>URL:</label>
                                        <input type="url" name="uri" id="uri" class="form-control" value="{{$uri}}" required>
                                    </div>
                                    <div class="form-group mt-4">
                                        <button type="submit" class="btn btn-success">Submit</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection
@push('scripts')
    <script></script>
@endpush
