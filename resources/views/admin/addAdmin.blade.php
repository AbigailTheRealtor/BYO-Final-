@extends('layouts.admin')
@push('styles')
@endpush
@section('content')
    <div class="card p-4">
        <div class="p-4 text-end">
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addAdminModal">Add Admin</button>
        </div>
        <table class="table">
            <thead>
                <tr>
                    <th>Sr.</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($admin_users as $user)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $user->name }}</td>
                        <td>{{ $user->email }}</td>
                        <td>{!! $user->is_approved
                            ? "<span class='badge bg-success'>Active</span>"
                            : "<span class='badge bg-danger'>Inactive</span>" !!}</td>
                        <td>
                            @if(!$user->is_super)
                                @if (!$user->is_approved)
                                    <a href="{{ route('admin.user.active', $user->id) }}"
                                        class="btn btn-primary btn-xs">Active</a>
                                @else
                                    <a href="{{ route('admin.user.inactive', $user->id) }}"
                                        class="btn btn-secondary btn-xs">Inactive</a>
                                @endif
                                <a href="#" class="btn btn-edit btn-success btn-xs" data-user="{{json_encode($user)}}">Edit</a>
                                <a href="{{ route('admin.user.delete', $user->id) }}" class="btn btn-delete btn-danger btn-xs">Delete</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center">
                            <h6>No admin users found.</h6>
                        </td>
                    </tr>
                @endforelse
        </table>
    </div>




@endsection
@push('scripts')

    <script>
        $(function() {
            $('.btn-delete').on('click', function() {
                event.preventDefault();
                Swal.fire({
                    title: 'Are you sure you want to delete this?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Yes',
                    cancelButtonText: `No`,
                    customClass: {
                        actions: 'my-actions',
                        cancelButton: 'btn btn-danger',
                        confirmButton: 'btn btn-primary',
                        // denyButton: 'order-3',
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        var uri = $(this).attr('href');
                        // alert(uri);
                        // Swal.fire('Saved!', '', 'success')
                        window.location.href = uri;
                    }
                })
            });

            $('.btn-edit').on('click', function() {
                event.preventDefault();
                var user = $(this).data('user');
                // console.log("user", user);
                $('#edit_id').val(user.id);
                $('#edit_first_name').val(user.first_name);
                $('#edit_last_name').val(user.last_name);
                $('#edit_user_name').val(user.user_name);
                $('#edit_email').val(user.email);
                $('#edit_status').val(user.is_approved);
                $('#editAdminModal').modal('show');
            });
        });
    </script>

    <!-- Modal -->
<div class="modal fade" id="addAdminModal" tabindex="-1" aria-labelledby="addAdminModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <form action="{{route('admin.addAdmin')}}" method="POST">
            @csrf
            <div class="modal-header">
            <h5 class="modal-title" id="addAdminModalLabel">Add Admin</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="first_name">First Name</label>
                                <span class="text-danger">*</span>
                                <input type="text" class="form-control" id="first_name" name="first_name" placeholder="First Name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="last_name">Last Name</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" placeholder="Last Name" >
                            </div>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label for="user_name">Username</label>
                    <span class="text-danger">*</span>
                    <input type="text" class="form-control" id="user_name" name="user_name" placeholder="Username" required>
                </div>
                <div class="form-group">
                    <label for="email">Email</label>
                    <span class="text-danger">*</span>
                    <input type="email" class="form-control" id="email" name="email" placeholder="Email" required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <span class="text-danger">*</span>
                    <input type="password" class="form-control" id="password" name="password" placeholder="Password" required />
                </div>
                <div class="form-group">
                    <label for="status">Status</label>
                    <select class="form-control" id="status" name="status">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
      </div>
    </div>
  </div>




  <div class="modal fade" id="editAdminModal" tabindex="-1" aria-labelledby="editAdminModal" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <form action="{{route('admin.updateAdmin')}}" method="POST">
            @csrf
            <input type="hidden" name="id" id="edit_id">
            <div class="modal-header">
            <h5 class="modal-title" id="editAdminModal">Edit Admin</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_first_name">First Name</label>
                                <span class="text-danger">*</span>
                                <input type="text" class="form-control" id="edit_first_name" name="first_name" placeholder="First Name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_last_name">Last Name</label>
                                <input type="text" class="form-control" id="edit_last_name" name="last_name" placeholder="Last Name" >
                            </div>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label for="edit_user_name">Username</label>
                    <span class="text-danger">*</span>
                    <input type="text" class="form-control" id="edit_user_name" name="user_name" placeholder="Username" required>
                </div>
                <div class="form-group">
                    <label for="edit_email">Email</label>
                    <span class="text-danger">*</span>
                    <input type="email" class="form-control" id="edit_email" name="email" placeholder="Email" required>
                </div>
                <div class="form-group">
                    <label for="edit_password">Password</label>
                    <input type="password" class="form-control" id="edit_password" name="password" placeholder="Password" />
                </div>
                <div class="form-group">
                    <label for="edit_status">Status</label>
                    <select class="form-control" id="edit_status" name="status">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
      </div>
    </div>
  </div>
@endpush
