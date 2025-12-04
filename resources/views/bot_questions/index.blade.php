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
                        <div class="rightCol col-sm-12 col-md-9 col-lg-9">
                            <div class="container mt-5 myAuctions">
                                <div class="d-flex justify-content-between">
                                    <h1>{{ $title }}</h1>
                                    <div><button type="button" class="btn btn-success" data-bs-toggle="modal"
                                            data-bs-target="#addModal"> <i class="fa-solid fa-plus"></i> Add new
                                            question</button></div>
                                </div>

                                <table class="table table-bordered data-table">
                                    <thead>
                                        <tr>
                                            <th colspan="4" class="bg-secondary text-white">{{ $table_title }}</th>
                                        </tr>
                                        <tr>
                                            <th class="text-center" width="50">#</th>
                                            <th>Question</th>
                                            <th>Answer</th>
                                            <th class="text-center" width="100">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($auction->bot_questions->sortByDesc('id') as $row)
                                            <tr class="row-{{ $row->id }}">
                                                <td class="text-center">{{ $loop->iteration }}</td>
                                                <td class="question-{{ $row->id }}">{{ $row->question }}</td>
                                                <td class="answer-{{ $row->id }}">{{ $row->answer }}</td>
                                                <td class="text-center">
                                                    <div class="dropdown">
                                                        <button class="btn btn-secondary dropdown-toggle btn-sm"
                                                            type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                            Action
                                                        </button>
                                                        <ul class="dropdown-menu">
                                                            <li>
                                                                <a href="#" class="dropdown-item btn-edit"
                                                                    data-id="{{ $row->id }}"
                                                                    data-action="{{ route('bot.question.update', $row->id) }}">
                                                                    <i class="fa-solid fa-pencil"
                                                                        style="font-size:14px;"></i>
                                                                    <span style="font-size:14px;">Edit</span>
                                                                </a>
                                                            </li>
                                                            <li>
                                                                <a href="#" class="dropdown-item btn-delete"
                                                                    data-id="{{ $row->id }}"
                                                                    data-action="{{ route('bot.question.delete', $row->id) }}">
                                                                    <i class="fa-solid fa-trash"
                                                                        style="font-size:14px;"></i>
                                                                    <span style="font-size:14px;">Delete</span>
                                                                </a>
                                                            </li>
                                                        </ul>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
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
    <!-- Start Add Modal -->
    <div class="modal fade" id="addModal" tabindex="-1" aria-labelledby="addModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form action="" method="POST">
                    @csrf
                    @method('post')
                    <input type="hidden" name="auction_type" value="{{ $type }}">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addModalLabel">Add Question</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">

                        <div class="form-group mt-3">
                            <label>Question:</label>
                            <input type="text" name="question" id="question" class="form-control">
                        </div>
                        <div class="form-group mt-3">
                            <label>Answer:</label>
                            <input type="text" name="answer" id="answer" class="form-control">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Submit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- End Add Modal -->

    <!-- Start Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form action="" method="POST" class="edit-form">
                    @csrf
                    @method('put')
                    <div class="modal-header">
                        <h5 class="modal-title" id="editModalLabel">Update Question</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group mt-3">
                            <label>Question:</label>
                            <input type="text" name="question" id="edit_question" class="form-control">
                        </div>
                        <div class="form-group mt-3">
                            <label>Answer:</label>
                            <input type="text" name="answer" id="edit_answer" class="form-control">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Submit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- End Edit Modal -->

    <script>
        $(function() {
            $('.btn-edit').click(function(e) {
                e.preventDefault();
                var id = $(this).data('id');
                var action = $(this).data('action');
                var q = $('.question-' + id).text();
                var ans = $('.answer-' + id).text();
                $('#edit_question').val(q);
                $('#edit_answer').val(ans);
                $('.edit-form').attr('action', action);
                $('#editModal').modal('show');
            });

            $('.btn-delete').click(function(e) {
                e.preventDefault();
                var action = $(this).data('action');
                var conf = confirm('Are you sure you want to delete this?');
                if (conf) {
                    window.location = action;
                }
            });
        });
    </script>
@endpush
