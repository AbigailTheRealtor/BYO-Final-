@extends('layouts.admin')
@section('content')
<div class="card">
    {{-- <div class="card-header">Manage Cities</div> --}}
    <div class="card-body">
        <div class="pb-4 text-end">
            <a class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addCityModal"><i class="fa-solid fa-plus"></i> Add New City</a>
        </div>
        <table class="table table-striped table-bordered nowrap datatable">
            <thead>
                <tr>
                    <th width="50px" class="text-center">Sr.</th>
                    <th>State</th>
                    <th>City</th>
                    <th width="200px">Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($cities as $city)
                <tr>
                    <td class="text-center">{{$loop->iteration}}</td>
                    <td>{{ucfirst($city->state->name)}}</td>
                    <td>{{$city->name}}</td>
                    <td class="text-center">
                        <a class="btn btn-primary btn-sm" onclick="editCity(this);" data-action="{{route('admin.cities.update', $city->id)}}" data-name="{{$city->name}}" data-state_id="{{$city->state_id}}"><i class="fa-solid fa-pen-to-square"></i> Edit</a>
                        <a class="btn btn-danger btn-sm" onclick="deleteCity(this);" data-action="{{route('admin.cities.destroy', $city->id)}}"><i class="fa-solid fa-trash-can"></i> Delete</a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
@push('scripts')
<script>
    function editCity(f) {
        var action = $(f).data('action');
        var name = $(f).data('name');
        var state_id = $(f).data('state_id');
        $('.edit-city-form').attr('action', action);
        $('#edit_state_id').val(state_id);
        $('#edit_name').val(name);
        $('#editCityModal').modal('show');
    }

    function deleteCity(f) {
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
                        var action = $(f).data('action');
                        $('.delete-city-form').attr('action', action);
                        $('.delete-city-form').submit();
                    }
                })
    }
$(function () {
    $("#editCityModal").on("hidden.bs.modal", function () {
        // alert('hidden');
        $('.edit-city-form').trigger('reset');
        $('.edit-city-form').attr('action', '');
        // $('#edit_name').val(name);
    });
});
</script>

<form action="" method="POST" class="delete-city-form">
    @method('delete')
    @csrf
</form>

{{-- Modals --}}
<div class="modal fade" id="addCityModal" tabindex="-1" aria-labelledby="addCityModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <form action="{{route('admin.cities.store')}}" method="POST">
            @csrf
            <div class="modal-header">
            <h5 class="modal-title" id="addCityModalLabel">Add City</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>State <span class="text-danger">*</span></label>

                                <select name="state_id" id="edit_state_id" class="form-select" required>
                                    @foreach ($states as $state)
                                        <option value="{{$state->id}}">{{ucfirst($state->name)}}</option>
                                    @endforeach
                                </select>
                                @if($errors->has('state_id'))
                                    <div class="text-danger">{{$errors->first('state_id')}}</div>
                                @endif
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>City Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="name" placeholder="City Name" required >
                                @if($errors->has('name'))
                                    <div class="text-danger">{{$errors->first('name')}}</div>
                                @endif
                            </div>
                        </div>
                    </div>
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




  <div class="modal fade" id="editCityModal" tabindex="-1" aria-labelledby="editCityModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <form action="" method="POST" class="edit-city-form">
            @csrf
            @method('put')
            <div class="modal-header">
            <h5 class="modal-title" id="editCityModalLabel">Edit City</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>State <span class="text-danger">*</span></label>

                                <select name="state_id" class="form-select" required>
                                    @foreach ($states as $state)
                                        <option value="{{$state->id}}">{{ucfirst($state->name)}}</option>
                                    @endforeach
                                </select>
                                @if($errors->has('state_id'))
                                    <div class="text-danger">{{$errors->first('state_id')}}</div>
                                @endif
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>City Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="name" id="edit_name" placeholder="City Name" required >
                                @if($errors->has('name'))
                                    <div class="text-danger">{{$errors->first('name')}}</div>
                                @endif
                            </div>
                        </div>
                    </div>
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

  {{-- End Modals --}}
@endpush
