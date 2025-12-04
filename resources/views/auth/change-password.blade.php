@extends('layouts.main')
@push('styles')
@endpush
@section('content')
    {{-- @if ($errors->any())
<h4>{{$errors->first()}}</h4>
@endif --}}
<div class="container p-4">
    {{-- <h4>Change Password</h4> --}}

    <div class="card">
        <div class="card-header"><h4>Change Password</h4></div>
        <div class="card-body">
            <form action="{{route('password.change')}}" method="POST" >
                @csrf
                <div class="row">
                    <div class="col-md-4">
                        <label>Old Password:</label>
                        <input type="password" name="old_pass" class="form-control">
                        @if ($errors->has('old_pass'))
                            <div class="small text-danger">{{ $errors->first('old_pass') }}</div>
                        @endif
                    </div>
                    <div class="col-md-4">
                        <label>New Password:</label>
                        <input type="password" name="password" class="form-control">
                        @if ($errors->has('password'))
                            <div class="small text-danger">{{ $errors->first('password') }}</div>
                        @endif
                    </div>
                    <div class="col-md-4">
                        <label>Confirm Password:</label>
                        <input type="password" name="confirm_password" class="form-control">
                        @if ($errors->has('confirm_password'))
                            <div class="small text-danger">{{ $errors->first('confirm_password') }}</div>
                        @endif
                    </div>
                </div>
                <div class="text-end pt-4">
                    <button class="btn btn-success">Change Password</button>
                </div>
            </form>
        </div>
    </div>

</div>

@endsection
@push('scripts')

@endpush
