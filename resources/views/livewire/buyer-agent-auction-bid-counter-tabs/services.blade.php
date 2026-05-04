<h3>Services the Buyer Requests from Their Agent</h3>
<div class="alert alert-info bg-light-info border-info mb-4">
    <div class="d-flex align-items-center">
        <div>
            <strong>🤝 Below are the services included in the Agent's offer. Uncheck any you wish to remove from the counter. The final scope and compensation will be outlined in the signed agreement.</strong>
        </div>
    </div>
</div>

@if (!empty($groupedServices))
@foreach ($groupedServices as $categoryName => $categoryServices)
<div class="service-section mb-4">
    <h5 class="section-header bg-info text-white p-2 mb-3">{{ $categoryName }}</h5>
    <div class="service-options">
        @foreach ($categoryServices as $service)
            <div class="form-check service-item" wire:key="counter-svc-{{ Str::slug($service) }}">
                <input class="form-check-input" type="checkbox" wire:model="services"
                    value="{{ $service }}" id="counter-service-{{ Str::slug($service) }}">
                <label class="form-check-label" for="counter-service-{{ Str::slug($service) }}">
                    {{ $service }}
                </label>
            </div>
        @endforeach
    </div>
</div>
@endforeach
@else
<div class="alert alert-secondary mb-4">
    <strong>No services were included in the Agent's offer.</strong>
</div>
@endif

<!-- Additional Services Section -->
<div class="service-section mb-4">
    <h5 class="section-header bg-info text-white p-2 mb-3">✍️ Additional Services</h5>

    <div class="service-options">
        <div class="form-check service-item mb-3">
            <input
                id="other-services-checkbox"
                type="checkbox"
                class="form-check-input"
                wire:model="other_services_enabled"
            >
            <label class="form-check-label" for="other-services-checkbox">
                Other – Specify additional services as needed.
            </label>
        </div>

        @if ($other_services_enabled)
            <div id="other-services-fieldset">
                @foreach ($other_services as $i => $value)
                    <div class="mb-3 service-entry" wire:key="other_service_{{ $i }}">
                        <label class="form-label" for="other-services-input-{{ $i }}">
                            Specify any additional services requested
                        </label>

                        <input
                            id="other-services-input-{{ $i }}"
                            type="text"
                            class="form-control mb-2 @error("other_services.$i") is-invalid @enderror"
                            placeholder="Specify any additional services requested"
                            wire:model="other_services.{{ $i }}"
                        >

                        @error("other_services.$i")
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror

                        <button
                            type="button"
                            class="btn btn-danger btn-sm remove-service"
                            wire:click="removeService({{ $i }})"
                        >❌ Remove</button>
                    </div>
                @endforeach
            </div>

            <button
                type="button"
                class="btn btn-primary btn-sm"
                id="add-service-btn"
                wire:click="addServiceField"
            >➕ Add Another Service</button>
        @endif
    </div>
</div>
<div class="alert alert-warning mt-3 p-2 small">
    <strong>⚖️ Note:</strong> All services described above are provided within the scope of real estate brokerage duties as defined by state law. Services are administrative, advisory, or informational in nature and do not include legal, tax, accounting, or financial advice. Analyses are not formal appraisals or valuations. The Agent does not handle client funds outside of escrow or trust accounts as permitted by law. All information is relayed as provided by third parties (e.g., Seller, Lender, Title, Escrow, Attorney, CPA, or other licensed professionals). The Buyer and/or their Attorney, CPA, or other licensed professional remain solely responsible for reviewing, confirming, and approving the accuracy, completeness, and compliance of all documents, disclosures, financial statements, contracts, and transfers. Additional services must remain within the scope of brokerage law and fiduciary duties as outlined in the signed brokerage agreement.
</div>
