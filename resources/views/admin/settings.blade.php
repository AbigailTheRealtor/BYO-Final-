@extends('layouts.admin')
@section('content')
    <form action="{{ route('admin.settings') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="card">
            <div class="card-body">
                <div class="row">
                    <div class="form-group col-md-6">
                        <label>Site Title:</label>
                        <input type="text" name="title" class="form-control" value="{{ get_setting('title') }}" required>
                    </div>
                </div>
                <div class="row">
                    <div class="form-group col-md-6">
                        <label>Site Logo:</label>
                        <input type="file" name="logo" class="form-control" accept="image/*" onchange="previewImage(event, 'logo-previewImage');">
                    </div>
                    <div class="col-md-6">
                        <div class="logo-img"
                            style="width: 200px; height: 200px; border: 1px solid #e0e0e0; border-radius: 5px;">
                            <img src="{{ asset(get_setting('logo')) }}" class="logo-previewImage"
                                style="width: 100%; height: 100%; object-fit: contain;" alt="">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="form-group col-md-6">
                        <label>Favicon:</label>
                        <input type="file" name="favicon" class="form-control" accept="image/*" onchange="previewImage(event, 'favicon-previewImage');">
                    </div>
                    <div class="col-md-6 pt-4">
                        <div class="favicon-img"
                            style="width: 100px; height: 100px; border: 1px solid #e0e0e0; border-radius: 5px;">
                            <img src="{{ asset(get_setting('favicon')) }}" class="favicon-previewImage"
                                style="width: 100%; height: 100%; object-fit: contain;" alt="">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="form-group col-md-6">
                        <label>Footer Text:</label>
                        <input type="text" name="footer_text" placeholder="© 2022 Bid Your Offer All rights reserved."
                            value="{{ get_setting('footer_text') }}" class="form-control">
                    </div>
                </div>

                <div class="row">
                    <div class="form-group col-md-6">
                        <button type="submit" class="btn btn-success"><i class="fa fa-save"></i> Save</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection
@push('scripts')
    <script>
        const previewImage = (event, cls) => {
            const imageFiles = event.target.files;
            const imageFilesLength = imageFiles.length;
            if (imageFilesLength > 0) {
                const imageSrc = URL.createObjectURL(imageFiles[0]);
                $(`.${cls}`).attr('src', imageSrc);
            }
        };
    </script>
@endpush
