@extends('layouts.main')

@section('title', 'Invalid Action')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 text-center">
            <div class="alert alert-danger">
                <h5 class="mb-1">Invalid Showing Action</h5>
                <p class="mb-2">{{ $message }}</p>
                <a href="{{ route('showings.manage') }}" class="btn btn-sm btn-outline-danger mt-2">Back to Showing Requests</a>
            </div>
        </div>
    </div>
</div>
@endsection
