@extends('layouts.admin')
@section('content')

<div class="card">
    <table class="table">
        <thead>
            <tr>
                <th>Sr.</th>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            @forelse($users as $user)
            @php
                if($user->user_type == "admin")
                {
                    $role = "Admin";
                } else if ($user->user_type == "seller")
                {
                    $role = "Seller";
                } else if ($user->user_type == "buyer")
                {
                    $role = "Buyer";
                } else if ($user->user_type == "agent")
                {
                    $role = "Agent";
                } else {
                    $role = "User";
                }
            @endphp
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $user->name }}</td>
                    <td>{{ $user->email }}</td>
                    <td>{{ $role }}</td>
                    <td>
                        <a href="{{ route('admin.user.approve', $user->id) }}" class="btn btn-primary">Approve</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-center">
                        <h6>No pending user request found.</h6>
                    </td>
                </tr>
            @endforelse
    </table>
</div>

@endsection
