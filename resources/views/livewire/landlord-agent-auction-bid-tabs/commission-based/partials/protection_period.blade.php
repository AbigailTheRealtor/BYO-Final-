
@if ($property_type === 'Residential Property')
    <!-- Protection Period Timeframe -->
    <div class="form-group mb-4 mt-3">
        <label class="fw-bold d-flex align-items-center">
            Protection Period Timeframe (Days):
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Enter the number of days after the Listing Period ends during which the Landlord’s Broker is entitled to a fee if the property is leased to a prospect with whom the Broker—or any other Broker—communicated during the Listing Period. If requested, the Broker must provide a list of those prospects, and compensation is limited to the names on that list. This protection ends if the Landlord signs a good faith exclusive right-to-lease agreement with another Broker after the Listing Period.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </label>
        <div class="input-cover mt-2">
            <input type="number" wire:model.lazy="protection_period" class="form-control has-icon"
                data-icon="fa-solid fa-shield-alt" placeholder="Enter protection period in days (e.g., 90)">
        </div>
    </div>
@endif

@if ($property_type === 'Commercial Property')
    <!-- Protection Period Timeframe -->
    <div class="form-group mb-4 mt-3">
        <label class="fw-bold d-flex align-items-center">
            Protection Period Timeframe (Days):
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Enter the number of days after the Listing Period ends during which the Landlord agrees to pay a commission if the property is leased to a prospect with whom the Broker—or any other Broker—communicated during the Listing Period. If requested, the Broker must provide a list of such prospects, and compensation is limited to the names on that list. This protection period ends if the Landlord enters into a good faith exclusive leasing agreement with another Broker after the Listing Period.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </label>
        <div class="input-cover mt-2">
            <input type="number" wire:model.lazy="protection_period" class="form-control has-icon"
                data-icon="fa-solid fa-shield-alt" placeholder="Enter protection period in days (e.g., 90)">
        </div>
    </div>
@endif