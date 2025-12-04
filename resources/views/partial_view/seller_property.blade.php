@push('styles')
    <style>
        .choices__list {
            z-index: 999;
        }

        .wizard-steps-progress {
            height: 5px;
            width: 100%;
            background-color: #CCC;
            position: absolute;
            top: 0;
            left: 0;
        }

        .steps-progress-percent {
            height: 100%;
            width: 0%;
            background-color: #11b7cf;
        }

        .wizard-step {
            display: none;
        }

        .wizard-step.active {
            display: block;
        }

        label.warning {
            color: #f00;
        }

        ::placeholder {
            color: #cacaca !important;
            opacity: 1;
            /* Firefox */
        }

        :-ms-input-placeholder {
            /* Internet Explorer 10-11 */
            color: #cacaca !important;
        }

        ::-ms-input-placeholder {
            /* Microsoft Edge */
            color: #cacaca !important;
        }

        .hide_arrow::-webkit-outer-spin-button,
        .hide_arrow::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        /* Firefox */
        .hide_arrow {
            -moz-appearance: textfield;
        }

        .input-cover {
            align-items: center;
            position: relative;
        }

        .input-cover .input-icon {
            position: absolute;
            left: 10px;
            font-size: 30px;
            color: #11b7cf;
        }

        .input-cover .form-control {
            padding-left: 50px;
        }

        .form-control {
            min-height: 50px;
        }

        .form-group {
            margin-top: 15px;
        }

        .options-container {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-start;
        }

        .option-container {
            align-items: center;
            cursor: pointer;
            display: flex;
            flex-direction: row;
            align-items: center;
            padding: 15px;
            margin: 10px;
            margin-left: 0;
            margin-bottom: 0;
        }

        .option-container.active {
            border-color: #006e9f;
            color: #006e9f;
        }

        .option-container .option-icon {
            font-size: 40px;
            color: #11b7cf;
        }

        .option-container .option-text {
            padding-left: 10px;
        }

        .text-error {
            width: 100%;
        }

        .text-error {
            border-color: rgba(var(--bs-danger-rgb), var(--bs-text-opacity)) !important;
        }

        .grid-picker1 {
            width: 100%;
            height: 0px;
            visibility: hidden;
        }

        ::-ms-browse {
            height: 50px;
        }

        ::-webkit-file-upload-button {
            height: 50px;
        }

        input[type=file]::file-selector-button {
            height: 50px;
        }
    </style>
@endpush
<div class="wizard-step vacant_land_remove">
    @php
       $bathrooms = [['target' => '', 'name' => '1'], ['target' => '', 'name' => '1.5'], ['target' => '', 'name' => '2'], ['target' => '', 'name' => '2.5'], ['target' => '', 'name' => '3'], ['target' => '', 'name' => '3.5'], ['target' => '', 'name' => '4'], ['target' => '', 'name' => '4.5'], ['target' => '', 'name' => '5'], ['target' => '', 'name' => '5.5'], ['target' => '', 'name' => '6'], ['target' => '', 'name' => '6.5'], ['target' => '', 'name' => '7'], ['target' => '', 'name' => '7.5'], ['target' => '', 'name' => '8'], ['target' => '', 'name' => '8.5'], ['target' => '', 'name' => '9'], ['target' => '', 'name' => '9.5'], ['target' => '', 'name' => '10'], ['target' => '.custom_bathrooms', 'name' => 'Other']];

    @endphp
    <div class="form-group">
        <label class="fw-bold">Bathrooms:</label>
        <select class="grid-picker1" name="bathrooms" id="bathrooms"  required>
            <option value="">Select</option>
            @foreach ($bathrooms as $item)
                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                    class="card flex-column" style="width:calc(33.3% - 10px);"
                    data-icon='<i class="fa-solid fa-bath"></i>'>
                    {{ $item['name'] }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="form-group custom_bathrooms d-none">
        <label class="fw-bold">Bathrooms:</label>
        <input type="text" name="custom_bathrooms" id="custom_bathrooms"
            class="form-control has-icon1" data-icon="fa-solid fa-bath" required>
    </div>
</div>
{{-- 3 Jul 2023 For Business Opportunity --}}
                  {{-- 28 July 2023 --}}
        {{-- 3 Jul 2023 For Business Opportunity --}}
        <div class="wizard-step vacant_land_remove">
            @php
                $prop_conditions = [['name' => 'Not Updated: Requires a complete update.', 'target' => ''], ['name' => 'Tear Down: Requires complete demolition and reconstruction.', 'target' => ''], ['name' => 'Semi-updated, needs minor updates: Partially updated, but requires additional minor updates.', 'target' => ''], ['name' => 'Move-in Ready, Completely Updated: Fully updated and ready for occupancy.', 'target' => '']];
            @endphp
            <div class="form-group">
                <label class="fw-bold">Property condition:</label>
                <select class="grid-picker1" name="prop_condition" id="prop_condition"
                    style="justify-content: flex-start;" required>
                    <option value="">Select</option>
                    @foreach ($prop_conditions as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                            class="card flex-row" style="width:calc(50% - 10px);">
                            {{ $item['name'] }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label class="fw-bold">List of Required Rquired Repairs</label>
                <textarea name="known_repairs" id="known_repairs" class="form-control" cols="30" rows="5"></textarea>
            </div>


        </div>
