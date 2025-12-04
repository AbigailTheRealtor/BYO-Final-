@extends('layouts.admin')
@section('content')
<div class="card">
    {{-- <div class="card-header">Manage Cities</div> --}}
    <div class="card-body">
        <div class="pb-4 text-end">
            <a class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addCityModal"><i class="fa-solid fa-plus"></i> Add New Question</a>
        </div>
        <table class="table table-striped table-bordered nowrap datatable">
            <thead>
                <tr>
                    <th width="50px" class="text-center">Sr.</th>
                    <th>Question</th>
                    <th>Answer</th>
                    <th width="200px">Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($commonBotQuestions as $commonBotQuestion)
                @php
                    $sr = $loop->iteration;
                @endphp
                <tr class="{{$sr}}">
                    <td class="text-center">{{$sr}}</td>
                    <td>
                        {{$commonBotQuestion->question}}
                        <input type="hidden" id="question_{{$sr}}" value="{{$commonBotQuestion->question}}">
                        <input type="hidden" id="answer_{{$sr}}" value="{{$commonBotQuestion->answer}}">
                    </td>
                    <td>{{$commonBotQuestion->answer}}</td>
                    <td class="text-center">
                        <a class="btn btn-primary btn-sm" onclick="editCity(this);" data-action="{{route('admin.commonBotQuestions.update', $commonBotQuestion->id)}}" data-row="{{$sr}}" ><i class="fa-solid fa-pen-to-square"></i> Edit</a>
                        <a class="btn btn-danger btn-sm" onclick="deleteCity(this);" data-action="{{route('admin.commonBotQuestions.destroy', $commonBotQuestion->id)}}"><i class="fa-solid fa-trash-can"></i> Delete</a>
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
        var sr = $(f).data('row');
        var question = $('#question_'+sr).val();
        var answer = $('#answer_'+sr).val();
        $('.edit-city-form').attr('action', action);
        $('#edit_question').val(question);
        $('#edit_answer').val(answer);
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
        <form action="{{route('admin.commonBotQuestions.store')}}" method="POST">
            @csrf
            <div class="modal-header">
            <h5 class="modal-title" id="addCityModalLabel">Add Question</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label>Question <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="question" placeholder="Question" required >
                                @if($errors->has('question'))
                                    <div class="text-danger">{{$errors->first('question')}}</div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label>Answer <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="answer" placeholder="Answer" required >
                                @if($errors->has('answer'))
                                    <div class="text-danger">{{$errors->first('answer')}}</div>
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
            <h5 class="modal-title" id="editCityModalLabel">Edit Question</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label>Question <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="question" id="edit_question" placeholder="Question" required >
                                @if($errors->has('question'))
                                    <div class="text-danger">{{$errors->first('question')}}</div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label>Answer <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="answer" id="edit_answer" placeholder="Answer" required >
                                @if($errors->has('answer'))
                                    <div class="text-danger">{{$errors->first('answer')}}</div>
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
