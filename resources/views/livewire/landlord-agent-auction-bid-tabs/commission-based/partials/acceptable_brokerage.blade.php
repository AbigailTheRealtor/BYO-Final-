
<!-- Acceptable Brokerage Relationship -->
<div class="form-group mb-4">
    <label class="fw-bold d-flex align-items-center">
        Acceptable Brokerage Relationship:
        <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
            title="Select the type of legal relationship the Landlord wishes to establish with the Broker. This determines the level of representation the Broker will provide. Real estate laws vary by state, and Brokers may offer different types of agency relationships. Both the Broker and Landlord must comply with all applicable local, state, and federal laws.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>

    <div class="input-cover mt-2">
        <select wire:model.lazy="brokerage_relationship" class="form-control has-icon"
            data-icon="fa-solid fa-handshake">
            <option value="">Select</option>
            <option value="Transaction Broker Representation">Transaction Broker Representation</option>
            <option value="Single Agent Representation">Single Agent Representation</option>
            <option value="Dual Agency Representation">Dual Agency Representation</option>
            <option value="No Brokerage Relationship">No Brokerage Relationship</option>
        </select>
    </div>

    @if ($brokerage_relationship)
        <div class="mt-3 p-3 bg-light rounded">

            @if ($brokerage_relationship === 'Transaction Broker Representation')
                <h6 class="fw-bold">• Transaction Broker Representation:</h6>
                <ul class="mb-2 ps-3">
                    <li>Default in Florida unless otherwise specified.</li>
                    <li>The Broker provides limited representation to both parties without full fiduciary duties.</li>
                    <li>Must act honestly, fairly, and with skill, care, and diligence.</li>
                    <li>Not permitted in Texas, Alaska, Vermont, Kansas, or Colorado.</li>
                </ul>
            @elseif ($brokerage_relationship === 'Single Agent Representation')
                <h6 class="fw-bold">• Single Agent Representation:</h6>
                <ul class="mb-2 ps-3">
                    <li>The Broker acts as a fiduciary, providing the highest level of loyalty, confidentiality,
                        obedience, and full disclosure.</li>
                    <li>The Broker must always act in the Landlord’s best interest.</li>
                    <li>Requires written consent from both the Landlord and the Tenant.</li>
                    <li>Requires a Single Agent Notice signed by the Landlord.</li>
                </ul>
            @elseif ($brokerage_relationship === 'Dual Agency Representation')
                <h6 class="fw-bold">• Dual Agency Representation:</h6>
                <ul class="mb-2 ps-3">
                    <li>The Broker represents both the Landlord and the Tenant in the same transaction.</li>
                    <li>Must remain neutral and may not disclose confidential information from either party.</li>
                    <li>Requires written consent from both the Landlord and the Tenant.</li>
                    <li>Not permitted in Alaska, Colorado, Florida, Kansas, Maryland, Oklahoma, Texas, Vermont, and
                        Wyoming.</li>
                </ul>
            @elseif ($brokerage_relationship === 'No Brokerage Relationship')
                <h6 class="fw-bold">• No Brokerage Relationship:</h6>
                <ul class="mb-2 ps-3">
                    <li>The Broker does not represent the Landlord and has no fiduciary duties.</li>
                    <li>Still required to act honestly and disclose all known facts that materially affect the
                        property’s value.</li>
                    <li>The Landlord is responsible for their own due diligence and negotiations.</li>
                </ul>
            @endif

            <div class="alert alert-warning mt-3 p-2 small">
                <strong>⚠️ Legal Notice:</strong> Certain brokerage relationships are not permitted in all states. If
                your selection is not allowed, the Broker will establish a permitted legal alternative. Real estate laws
                change frequently. Both the Broker and Landlord are responsible for complying with all current local,
                state, and federal laws.
            </div>
        </div>
    @endif
</div>