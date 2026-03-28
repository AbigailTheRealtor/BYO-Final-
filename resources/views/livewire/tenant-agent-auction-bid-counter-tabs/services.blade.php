<h3>Services the Tenant Requests from Their Agent</h3>
<div class="alert alert-info bg-light-info border-info mb-4">
    <div class="d-flex align-items-center">
        <div>
            <strong>&#x1F91D; Review and adjust the services the Tenant would like the Agent to provide throughout the leasing process. Services are offered under a commission-based, full-service agreement, with the brokerage relationship type determined in accordance with state law. Selections here are for guidance only; the signed brokerage agreement governs the final scope of representation and compensation.
            </strong>
        </div>
    </div>
</div>

@foreach ($servicesConfig as $category)
<div class="service-section mb-4">
    <h5 class="section-header bg-info text-white p-2 mb-3">{{ $category['title'] }}</h5>
    <div class="service-options">
        @foreach ($category['services'] as $svcIndex => $service)
            <div class="form-check service-item">
                <input class="form-check-input" type="checkbox" wire:model="services"
                    value="{{ $service }}"
                    id="{{ $category['prefix'] }}-{{ $loop->parent->index }}-{{ $svcIndex }}">
                <label class="form-check-label" for="{{ $category['prefix'] }}-{{ $loop->parent->index }}-{{ $svcIndex }}">
                    {{ $service }}
                </label>
            </div>
        @endforeach
    </div>
</div>
@endforeach

<!-- Additional Services Section -->
<div class="service-section mb-4">
    <h5 class="section-header bg-info text-white p-2 mb-3">&#x270D;&#xFE0F; Additional Services</h5>

    <div class="service-options">
        <div class="form-check service-item mb-3">
            <input
                id="other-services-checkbox"
                type="checkbox"
                class="form-check-input"
                wire:model="other_services_enabled"
            >
            <label class="form-check-label" for="other-services-checkbox">
                Other &#8211; Specify additional services as needed
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
                        >&#x274C; Remove</button>
                    </div>
                @endforeach
            </div>

            <button
                type="button"
                class="btn btn-primary btn-sm"
                id="add-service-btn"
                wire:click="addServiceField"
            >&#x2795; Add Another Service</button>
        @endif
    </div>
</div>
<div class="alert alert-warning mt-3 p-2 small">
    <strong>&#x2696;&#xFE0F; Note:</strong> All services described above are provided within the scope of real estate brokerage duties as defined by state law. Services are administrative, advisory, or informational in nature and do not include legal, tax, accounting, or financial advice. Analyses are not formal appraisals or valuations. The Agent does not handle client funds outside of escrow or trust accounts as permitted by law. All information is relayed as provided by third parties (e.g., Landlord, Property Manager, Lender, Title, Escrow, Attorney, CPA, or other licensed professionals). The Tenant and/or their Attorney, CPA, or other licensed professional remain solely responsible for reviewing, confirming, and approving the accuracy, completeness, and compliance of all documents, disclosures, financial statements, lease agreements, and transfers. Additional services must remain within the scope of brokerage law and fiduciary duties as outlined in the signed brokerage agreement.
</div>
