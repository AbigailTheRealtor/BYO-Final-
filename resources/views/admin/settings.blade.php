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

                <hr>
                <h5 class="mb-3">Mortgage Calculator Defaults</h5>
                <div class="row g-3">
                    <div class="form-group col-md-4">
                        <label>Default Interest Rate (%)</label>
                        <input type="number" name="calc_interest_rate" class="form-control"
                               value="{{ get_setting('calc_interest_rate') ?: '7.0' }}"
                               min="0" max="30" step="0.125">
                        <small class="text-muted">e.g. 7.0</small>
                    </div>
                    <div class="form-group col-md-4">
                        <label>Default Down Payment (%)</label>
                        <input type="number" name="calc_down_payment_pct" class="form-control"
                               value="{{ get_setting('calc_down_payment_pct') ?: '10' }}"
                               min="0" max="100" step="0.5">
                        <small class="text-muted">e.g. 10</small>
                    </div>
                    <div class="form-group col-md-4">
                        <label>Default Loan Term (years)</label>
                        <input type="number" name="calc_loan_term" class="form-control"
                               value="{{ get_setting('calc_loan_term') ?: '30' }}"
                               min="1" max="50" step="1">
                        <small class="text-muted">e.g. 30</small>
                    </div>
                    <div class="form-group col-md-4">
                        <label>Default Tax Rate (% of price/year)</label>
                        <input type="number" name="calc_tax_rate" class="form-control"
                               value="{{ get_setting('calc_tax_rate') ?: '1.1' }}"
                               min="0" max="10" step="0.01">
                        <small class="text-muted">e.g. 1.1</small>
                    </div>
                    <div class="form-group col-md-4">
                        <label>Default Insurance Rate (% of price/year)</label>
                        <input type="number" name="calc_insurance_rate" class="form-control"
                               value="{{ get_setting('calc_insurance_rate') ?: '0.5' }}"
                               min="0" max="5" step="0.01">
                        <small class="text-muted">e.g. 0.5</small>
                    </div>
                    <div class="form-group col-md-4">
                        <label>Default PMI Rate (% of price/year)</label>
                        <input type="number" name="calc_pmi_rate" class="form-control"
                               value="{{ get_setting('calc_pmi_rate') ?: '0.85' }}"
                               min="0" max="5" step="0.01">
                        <small class="text-muted">e.g. 0.85 (applied when down payment &lt; 20%)</small>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="form-group col-md-6">
                        <button type="submit" class="btn btn-success"><i class="fa-solid fa-save"></i> Save</button>
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
