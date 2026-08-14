{{--
    MLS Quick Import — the shortened Seller/Landlord creation path.

    Shared by SellerMlsQuickImport and LandlordMlsQuickImport. Everything that
    differs between the two arrives as data ($role, $schema, $priceField), so
    there is one template rather than two that drift.

    THE UI PRINCIPLE THIS TEMPLATE EXISTS TO HOLD
    ---------------------------------------------
    The user must never be shown a giant property form pre-filled with MLS
    values. Imported property facts appear ONCE, as a confirmation card and then
    again on the review screen — as read-only summaries, never as a hundred
    editable inputs to click through. The only things this page asks for are the
    transaction questions MLS does not answer.
--}}
<div class="container py-4" style="max-width: 960px;">

    {{-- ── Progress ─────────────────────────────────────────────────────── --}}
    @php
        $steps = [
            \App\Http\Livewire\OfferListing\QuickImport\MlsQuickImportComponent::STEP_LOOKUP  => 'MLS #',
            \App\Http\Livewire\OfferListing\QuickImport\MlsQuickImportComponent::STEP_CONFIRM => 'Confirm Property',
            \App\Http\Livewire\OfferListing\QuickImport\MlsQuickImportComponent::STEP_METHOD  => 'Listing Method',
            \App\Http\Livewire\OfferListing\QuickImport\MlsQuickImportComponent::STEP_TERMS   => 'Your Terms',
            \App\Http\Livewire\OfferListing\QuickImport\MlsQuickImportComponent::STEP_REVIEW  => 'Review',
        ];
        $stepKeys  = array_keys($steps);
        $stepIndex = array_search($step, $stepKeys, true) ?: 0;
    @endphp

    <ol class="list-unstyled d-flex flex-wrap gap-2 mb-4 small">
        @foreach($steps as $key => $label)
            @php $i = array_search($key, $stepKeys, true); @endphp
            <li class="px-3 py-1 rounded-pill {{ $i === $stepIndex ? 'bg-primary text-white' : ($i < $stepIndex ? 'bg-success-subtle text-success-emphasis' : 'bg-light text-muted') }}">
                {{ $i + 1 }}. {{ $label }}
            </li>
        @endforeach
    </ol>

    @if($errorMessage)
        <div class="alert alert-danger py-2">
            <i class="fas fa-exclamation-circle me-1"></i>{{ $errorMessage }}
        </div>
    @endif

    {{-- ── Step 1: MLS number ───────────────────────────────────────────── --}}
    @if($step === 'lookup')
        <div class="card shadow-sm">
            <div class="card-body">
                <h4 class="fw-semibold mb-2">Import your listing from the MLS</h4>
                <p class="text-muted">
                    Enter your MLS number and we'll build the property portion of your listing for you.
                    You only tell BidYourOffer how you want the transaction handled.
                </p>

                <div class="mb-3">
                    <label for="quick-mls-number" class="form-label fw-semibold">MLS #</label>
                    <input type="text" id="quick-mls-number" class="form-control form-control-lg"
                           placeholder="e.g. A4567890" wire:model.defer="mlsNumber"
                           wire:keydown.enter="findListing">
                </div>

                <button type="button" class="btn btn-primary btn-lg"
                        wire:click="findListing" wire:loading.attr="disabled" wire:target="findListing">
                    <span wire:loading.remove wire:target="findListing"><i class="fas fa-search me-1"></i>Find My Listing</span>
                    <span wire:loading wire:target="findListing"><span class="spinner-border spinner-border-sm me-1"></span>Searching…</span>
                </button>

                <hr class="my-4">
                <p class="small text-muted mb-0">
                    Don't have an MLS number?
                    <a href="{{ route($role === 'seller' ? 'offer.listing.seller' : 'offer.listing.landlord') }}">
                        Create your listing manually
                    </a>.
                </p>
            </div>
        </div>
    @endif

    {{-- ── Step 2: confirm the property ─────────────────────────────────── --}}
    @if($step === 'confirm')
        <div class="card shadow-sm">
            <div class="card-body">
                <h4 class="fw-semibold mb-3">Is this your property?</h4>

                <div class="row g-3 align-items-center">
                    <div class="col-12">
                        <div class="fs-5 fw-semibold">{{ $headline['address'] ?? 'Address unavailable' }}</div>
                        <div class="text-muted">
                            {{ collect([$headline['city'] ?? null, $headline['state'] ?? null, $headline['postal_code'] ?? null])->filter()->implode(', ') }}
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="d-flex flex-wrap gap-3 mt-2">
                            @if(!empty($headline['price']))
                                <div class="fs-4 fw-bold">${{ number_format((float) $headline['price']) }}</div>
                            @endif
                            @if(!empty($headline['bedrooms']))
                                <div class="align-self-center">{{ $headline['bedrooms'] }} Beds</div>
                            @endif
                            @if(!empty($headline['bathrooms']))
                                <div class="align-self-center">{{ $headline['bathrooms'] }} Baths</div>
                            @endif
                            @if(!empty($headline['living_area']))
                                <div class="align-self-center">{{ number_format((float) $headline['living_area']) }} Sq Ft</div>
                            @endif
                            @if(!empty($headline['year_built']))
                                <div class="align-self-center text-muted">Built {{ $headline['year_built'] }}</div>
                            @endif
                        </div>
                    </div>

                    @if($photoCount > 0)
                        <div class="col-12">
                            <span class="badge bg-success-subtle text-success-emphasis">
                                <i class="fas fa-images me-1"></i>{{ $photoCount }} MLS {{ \Illuminate\Support\Str::plural('photo', $photoCount) }} will be imported
                            </span>
                        </div>
                    @endif
                </div>

                <div class="alert alert-warning py-2 mt-4 mb-0 small">
                    Imported MLS data is provided for convenience and should be reviewed for accuracy
                    before publishing. You'll see the full listing before anything goes live.
                </div>
            </div>
            <div class="card-footer bg-white d-flex gap-2">
                <button type="button" class="btn btn-primary" wire:click="acceptProperty"
                        wire:loading.attr="disabled" wire:target="acceptProperty">
                    <span wire:loading.remove wire:target="acceptProperty">Yes, this is my property</span>
                    <span wire:loading wire:target="acceptProperty"><span class="spinner-border spinner-border-sm me-1"></span>Importing…</span>
                </button>
                <button type="button" class="btn btn-outline-secondary" wire:click="backToLookup">
                    No, try a different MLS #
                </button>
            </div>
        </div>
    @endif

    {{-- ── Step 3: listing method ───────────────────────────────────────── --}}
    @if($step === 'method')
        <div class="card shadow-sm">
            <div class="card-body">
                <h4 class="fw-semibold mb-1">How would you like to {{ $role === 'seller' ? 'sell' : 'lease' }}?</h4>
                <p class="text-muted">Your property details are already imported. This is the first BidYourOffer question.</p>

                <div class="row g-3 mt-1">
                    @foreach($methods as $method)
                        <div class="col-md-6">
                            <button type="button"
                                    class="btn w-100 text-start p-3 h-100 {{ $auction_type === $method ? 'btn-primary' : 'btn-outline-secondary' }}"
                                    wire:click="chooseMethod('{{ $method }}')">
                                <span class="fw-semibold d-block">{{ $method }}</span>
                                <span class="small d-block mt-1 {{ $auction_type === $method ? 'text-white-50' : 'text-muted' }}">
                                    @if($method === 'Traditional')
                                        Offers come in as they arrive and you respond to each one.
                                    @else
                                        Offers are collected over a set window that you control.
                                    @endif
                                </span>
                            </button>
                        </div>
                    @endforeach
                </div>

                @if($auction_type === 'Bidding Period')
                    <div class="mt-4">
                        <label for="quick-auction-time" class="form-label fw-semibold">Bidding Period Length</label>
                        <select id="quick-auction-time" class="form-select" wire:model="auction_time">
                            <option value="">Select</option>
                            @foreach(['3 Days', '5 Days', '7 Days', '10 Days', '14 Days', '30 Days'] as $window)
                                <option value="{{ $window }}">{{ $window }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
            </div>
            <div class="card-footer bg-white d-flex gap-2">
                <button type="button" class="btn btn-primary" wire:click="continueToTerms">Continue</button>
            </div>
        </div>
    @endif

    {{-- ── Step 4: transaction terms ────────────────────────────────────── --}}
    @if($step === 'terms')
        @php
            $sections = [];
            foreach ($schema as $field => $spec) {
                $sections[$spec['section']][$field] = $spec;
            }
        @endphp

        <div class="card shadow-sm">
            <div class="card-body">
                <h4 class="fw-semibold mb-1">Your {{ $role === 'seller' ? 'sale' : 'lease' }} terms</h4>
                <p class="text-muted">
                    These are the questions the MLS doesn't answer — how you want BidYourOffer to handle
                    the transaction.
                </p>

                @foreach($sections as $sectionName => $fields)
                    <fieldset class="mb-4">
                        <legend class="fs-6 fw-semibold text-uppercase text-muted">{{ $sectionName }}</legend>

                        @foreach($fields as $field => $spec)
                            @php
                                /* Conditional visibility: "field:Value" shows only when that answer is
                                   selected; "field:not:Value" shows unless it is. Evaluated in the view
                                   because it is purely presentational — the server still validates and
                                   persists from the schema regardless of what was rendered. */
                                $show = true;
                                if (!empty($spec['when'])) {
                                    $parts = explode(':', $spec['when']);
                                    $dep   = $parts[0];
                                    if (($parts[1] ?? '') === 'not') {
                                        $show = ($terms[$dep] ?? '') !== ($parts[2] ?? '');
                                    } else {
                                        $needle = $parts[1] ?? '';
                                        $show = in_array($needle, (array) ($multiTerms[$dep] ?? []), true)
                                             || ($terms[$dep] ?? '') === $needle;
                                    }
                                }
                            @endphp

                            @if($show)
                                <div class="mb-3">
                                    <label class="form-label fw-semibold" for="qt-{{ $field }}">
                                        {{ $spec['label'] }}
                                        @if(!empty($spec['required']))<span class="text-danger">*</span>@endif
                                    </label>

                                    @if($spec['type'] === 'multiselect')
                                        <div class="d-flex flex-wrap gap-2">
                                            @foreach($spec['options'] as $option)
                                                <label class="border rounded px-3 py-1 small {{ in_array($option, (array) ($multiTerms[$field] ?? []), true) ? 'border-primary bg-primary-subtle' : '' }}">
                                                    <input type="checkbox" class="form-check-input me-1"
                                                           value="{{ $option }}"
                                                           wire:model="multiTerms.{{ $field }}">
                                                    {{ $option }}
                                                </label>
                                            @endforeach
                                        </div>
                                    @elseif($spec['type'] === 'select')
                                        <select id="qt-{{ $field }}" class="form-select" wire:model="terms.{{ $field }}">
                                            <option value="">Select</option>
                                            @foreach($spec['options'] as $option)
                                                <option value="{{ $option }}">{{ $option }}</option>
                                            @endforeach
                                        </select>
                                    @elseif($spec['type'] === 'textarea')
                                        <textarea id="qt-{{ $field }}" class="form-control" rows="3"
                                                  wire:model.defer="terms.{{ $field }}"></textarea>
                                    @else
                                        <input id="qt-{{ $field }}"
                                               type="{{ $spec['type'] === 'date' ? 'date' : 'text' }}"
                                               class="form-control"
                                               wire:model.defer="terms.{{ $field }}">
                                    @endif

                                    @if(!empty($spec['help']))
                                        <div class="form-text">{{ $spec['help'] }}</div>
                                    @endif
                                </div>
                            @endif
                        @endforeach
                    </fieldset>
                @endforeach
            </div>
            <div class="card-footer bg-white d-flex gap-2">
                <button type="button" class="btn btn-outline-secondary" wire:click="backToMethod">Back</button>
                <button type="button" class="btn btn-primary" wire:click="continueToReview">Review My Listing</button>
            </div>
        </div>
    @endif

    {{-- ── Step 5: review before publish ────────────────────────────────── --}}
    @if($step === 'review')
        <div class="card shadow-sm mb-3">
            <div class="card-body">
                <h4 class="fw-semibold mb-1">Review your listing</h4>
                <p class="text-muted">This is how your listing will appear. Nothing is public until you publish.</p>

                {{-- Photo gallery --}}
                @if(count($gallery) > 0)
                    <div class="row g-2 mt-3">
                        {{-- Each photo arrives already resolved by ListingGalleryView — the same
                             resolver the published Seller and Landlord pages use. That is the point:
                             the review screen and the published page must not be able to disagree
                             about a photograph, and they can only be guaranteed not to if there is
                             one rule rather than one per view. MLS media is referenced at the
                             provider's own URL; nothing was ever copied to our storage. --}}
                        @foreach($gallery as $photo)
                            <div class="col-6 col-md-3">
                                <div class="position-relative border rounded overflow-hidden {{ $photo->isCover ? 'border-primary border-2' : '' }}">
                                    <img src="{{ $photo->url }}" alt="{{ $photo->caption ?? 'Listing photo' }}"
                                         class="w-100" style="aspect-ratio: 4/3; object-fit: cover;" loading="lazy">

                                    @if($photo->isCover)
                                        <span class="badge bg-primary position-absolute top-0 start-0 m-1">Cover</span>
                                    @else
                                        <button type="button"
                                                class="btn btn-sm btn-light position-absolute bottom-0 start-0 m-1"
                                                wire:click="setCoverPhoto(@js($photo->key))">
                                            Make cover
                                        </button>
                                    @endif

                                    @if($photo->isMls)
                                        <span class="badge bg-dark-subtle text-dark-emphasis position-absolute top-0 end-0 m-1"
                                              title="Supplied by the MLS">MLS</span>
                                    @endif
                                </div>
                                @if($photo->caption)
                                    <div class="small text-muted text-truncate mt-1">{{ $photo->caption }}</div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                    <p class="small text-muted mt-2 mb-0">
                        {{ count($gallery) }} {{ \Illuminate\Support\Str::plural('photo', count($gallery)) }}.
                        MLS photos are supplied by the MLS and shown from their source.
                    </p>
                @else
                    <div class="alert alert-light border mt-3 mb-0 small">
                        No photos are attached to this listing yet. You can add your own after publishing.
                    </div>
                @endif
            </div>
        </div>

        {{-- Property summary --}}
        <div class="card shadow-sm mb-3">
            <div class="card-body">
                <h5 class="fw-semibold">Property</h5>
                <div class="fs-5">{{ $headline['address'] ?? '' }}</div>
                <div class="text-muted mb-2">
                    {{ collect([$headline['city'] ?? null, $headline['state'] ?? null, $headline['postal_code'] ?? null])->filter()->implode(', ') }}
                </div>
                <div class="d-flex flex-wrap gap-3">
                    @foreach(['bedrooms' => 'Beds', 'bathrooms' => 'Baths', 'living_area' => 'Sq Ft', 'year_built' => 'Built'] as $k => $label)
                        @if(!empty($headline[$k]))
                            <div><strong>{{ $headline[$k] }}</strong> <span class="text-muted">{{ $label }}</span></div>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Additional permitted MLS details (Layer C) --}}
        @if(!empty($mlsDetails))
            <div class="card shadow-sm mb-3">
                <div class="card-body">
                    <h5 class="fw-semibold mb-3">Property Details</h5>
                    @foreach($mlsDetails as $section => $rows)
                        <div class="mb-3">
                            <div class="text-uppercase small fw-semibold text-muted mb-1">{{ $section }}</div>
                            <dl class="row mb-0 small">
                                @foreach($rows as $row)
                                    <dt class="col-sm-4 fw-normal text-muted">{{ $row['label'] }}</dt>
                                    <dd class="col-sm-8">{{ $row['value'] }}</dd>
                                @endforeach
                            </dl>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- BidYourOffer terms --}}
        <div class="card shadow-sm mb-3">
            <div class="card-body">
                <h5 class="fw-semibold mb-3">Your Terms</h5>

                <dl class="row small mb-0">
                    <dt class="col-sm-4 fw-normal text-muted">Listing Method</dt>
                    <dd class="col-sm-8">
                        {{ $auction_type }}@if($auction_type === 'Bidding Period' && $auction_time) — {{ $auction_time }}@endif
                    </dd>

                    @foreach($schema as $field => $spec)
                        @php
                            $value = $spec['type'] === 'multiselect'
                                ? implode(', ', (array) ($multiTerms[$field] ?? []))
                                : trim((string) ($terms[$field] ?? ''));
                        @endphp
                        @if($value !== '')
                            <dt class="col-sm-4 fw-normal text-muted">{{ $spec['label'] }}</dt>
                            <dd class="col-sm-8">
                                @if(in_array($spec['type'], ['money'], true))
                                    ${{ number_format((float) str_replace([',', '$'], '', $value)) }}
                                @else
                                    {{ $value }}
                                @endif
                            </dd>
                        @endif
                    @endforeach
                </dl>
            </div>
        </div>

        <div class="d-flex gap-2 mb-5">
            <button type="button" class="btn btn-outline-secondary" wire:click="backToTerms">Back to terms</button>
            <button type="button" class="btn btn-success btn-lg" wire:click="publish"
                    wire:loading.attr="disabled" wire:target="publish">
                <span wire:loading.remove wire:target="publish">Publish Listing</span>
                <span wire:loading wire:target="publish"><span class="spinner-border spinner-border-sm me-1"></span>Publishing…</span>
            </button>
        </div>
    @endif

</div>
