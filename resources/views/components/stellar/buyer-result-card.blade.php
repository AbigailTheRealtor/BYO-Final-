{{--
    Stellar Buyer Result Card component.

    Props:
      $card    — associative array produced by BuyerResultViewMapper::mapOne()
      $isTop   — bool; if true the explanation accordion is auto-expanded

    Compliance:
      - listing_key is present for internal use (future detail link) but NEVER rendered as visible text.
      - raw_json, agent PII, brokerage info, lockbox, showing instructions are all absent from $card.
      - This component receives only the allowlisted view-model array from BuyerResultViewMapper.
--}}
@props(['card', 'isTop' => false, 'criteriaId' => null, 'criteriaType' => 'buyer'])

@php
    $cardId      = 'result-card-'  . md5($card['listing_key']);
    $accordionId = 'accordion-'    . md5($card['listing_key']);

    $detailParams = array_filter([
        'criteria_id'   => $criteriaId,
        'criteria_type' => ($criteriaType !== 'buyer' ? $criteriaType : null),
    ]);
    $detailUrl = route('stellar.property.show', array_merge(['listingKey' => $card['listing_key']], $detailParams));

    $hasWhy      = !empty($card['why_this_matches']);
    $hasTradeoffs = !empty($card['tradeoffs']);
    $hasCaution   = !empty($card['caution_flags']);
    $hasMissing   = !empty($card['missing_data']);
    $hasExplanation = $hasWhy || $hasTradeoffs || $hasCaution || $hasMissing;

    $scoreColor = match(true) {
        $card['total_score'] >= 80 => 'success',
        $card['total_score'] >= 60 => 'primary',
        $card['total_score'] >= 40 => 'warning',
        default                    => 'secondary',
    };
@endphp

<div class="card h-100 shadow-sm border-0" id="{{ $cardId }}">

    {{-- ====================================================================
         Hero photo from Bridge CDN (or placeholder if none available)
    ==================================================================== --}}
    @if(!empty($card['hero_photo_url']))
        <a href="{{ $detailUrl }}" tabindex="-1" aria-hidden="true">
            <img src="{{ $card['hero_photo_url'] }}"
                 alt="{{ $card['address'] ?? 'Property photo' }}"
                 class="card-img-top"
                 style="height:180px;object-fit:cover;border-radius:calc(.375rem - 1px) calc(.375rem - 1px) 0 0;"
                 loading="lazy">
        </a>
    @else
        <div class="card-img-top d-flex align-items-center justify-content-center bg-light text-muted"
             style="height:180px;border-radius:calc(.375rem - 1px) calc(.375rem - 1px) 0 0;">
            <div class="text-center">
                <i class="fas fa-house fa-3x mb-2 opacity-25"></i>
                <div style="font-size:.75rem;">No photo available</div>
            </div>
        </div>
    @endif

    <div class="card-body pb-2">

        {{-- ====================================================================
             Score badge + category bar
        ==================================================================== --}}
        <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-1">
            <span class="badge bg-{{ $scoreColor }} fs-6 px-3 py-2" data-testid="score-badge">
                <i class="fas fa-star me-1"></i>{{ $card['score_display'] }}
            </span>
            @if($card['price_display'])
                <span class="fw-bold text-dark fs-5">{{ $card['price_display'] }}</span>
            @else
                <span class="text-muted fst-italic">Price not listed</span>
            @endif
        </div>

        {{-- Category bar --}}
        <div class="mb-3" data-testid="category-bar">
            <div class="d-flex gap-1 align-items-end" style="height:28px;">
                @foreach($card['category_bars'] as $bar)
                    @php
                        $barColor = match($bar['key']) {
                            'location'      => '#4f86c6',
                            'price'         => '#5cb85c',
                            'size'          => '#9b59b6',
                            'property_type' => '#e67e22',
                            'amenities'     => '#1abc9c',
                            'financial'     => '#e74c3c',
                            'lifestyle'     => '#f39c12',
                            default         => '#adb5bd',
                        };
                        $heightPx = max(4, (int) round($bar['pct'] * 24 / 100));
                    @endphp
                    <div class="d-flex flex-column align-items-center flex-fill" style="min-width:0;">
                        <div title="{{ $bar['label'] }}: {{ $bar['score'] }} / {{ $bar['max'] }} pts"
                             style="width:100%;height:{{ $heightPx }}px;background:{{ $barColor }};border-radius:3px 3px 0 0;"></div>
                    </div>
                @endforeach
            </div>
            {{-- Category labels (hidden on xs, shown on sm+) --}}
            <div class="d-none d-sm-flex gap-1 mt-1">
                @foreach($card['category_bars'] as $bar)
                    <div class="flex-fill text-center text-muted" style="font-size:.6rem;min-width:0;overflow:hidden;white-space:nowrap;">
                        {{ $bar['label'] }}
                    </div>
                @endforeach
            </div>
        </div>

        {{-- ====================================================================
             Address / Location / Property details
        ==================================================================== --}}
        <div class="mb-2">
            @if($card['address'])
                <div class="fw-semibold text-dark lh-sm" style="font-size:.95rem;">
                    {{ $card['address'] }}
                </div>
            @else
                <div class="text-muted fst-italic" style="font-size:.9rem;">
                    Address not available &mdash; contact listing agent.
                </div>
            @endif
            @if($card['city_state_zip'])
                <div class="text-muted" style="font-size:.85rem;">{{ $card['city_state_zip'] }}</div>
            @endif
        </div>

        {{-- Beds / Baths / Sqft --}}
        <div class="d-flex gap-3 text-muted mb-2" style="font-size:.85rem;">
            <span>
                <i class="fas fa-bed me-1"></i>
                {{ $card['beds'] !== null ? $card['beds'] . ' bed' : '&mdash;' }}
            </span>
            <span>
                <i class="fas fa-bath me-1"></i>
                {{ $card['baths'] !== null ? $card['baths'] . ' bath' : '&mdash;' }}
            </span>
            <span>
                <i class="fas fa-vector-square me-1"></i>
                {{ $card['sqft'] !== null ? $card['sqft'] . ' sqft' : '&mdash;' }}
            </span>
        </div>

        {{-- Property type / subtype --}}
        @if($card['property_type'])
            <div class="mb-2" style="font-size:.82rem;">
                <span class="badge bg-light text-secondary border">{{ $card['property_type'] }}</span>
                @if($card['property_sub_type'])
                    <span class="badge bg-light text-secondary border ms-1">{{ $card['property_sub_type'] }}</span>
                @endif
            </div>
        @endif

        {{-- ====================================================================
             Match explanation accordion
        ==================================================================== --}}
        @if($hasExplanation)
            <div class="accordion accordion-flush border-top pt-2 mt-2" id="{{ $accordionId }}" data-testid="explanation-accordion">

                {{-- Panel 1: Why this matches --}}
                @if($hasWhy)
                    <div class="accordion-item border-0">
                        <h2 class="accordion-header">
                            <button class="accordion-button @if(!($isTop && $hasWhy)) collapsed @endif px-0 py-2 bg-transparent shadow-none"
                                    type="button"
                                    onclick="window.sbToggle(this, '{{ $accordionId }}-why')"
                                    aria-expanded="{{ ($isTop && $hasWhy) ? 'true' : 'false' }}"
                                    style="font-size:.82rem;color:#198754;">
                                <i class="fas fa-circle-check me-2"></i>Why this matches
                            </button>
                        </h2>
                        <div id="{{ $accordionId }}-why"
                             class="accordion-collapse collapse @if($isTop && $hasWhy) show @endif">
                            <div class="accordion-body px-0 pt-1 pb-2">
                                <ul class="list-unstyled mb-0" style="font-size:.82rem;">
                                    @foreach($card['why_this_matches'] as $reason)
                                        <li class="d-flex align-items-start gap-2 mb-1">
                                            <i class="fas fa-check text-success mt-1 flex-shrink-0" style="font-size:.75rem;"></i>
                                            <span>
                                                {{ $reason['label'] }}
                                                <span class="text-muted">(+{{ $reason['score_contribution'] }} pts)</span>
                                            </span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Panel 2: Tradeoffs --}}
                @if($hasTradeoffs)
                    <div class="accordion-item border-0">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed px-0 py-2 bg-transparent shadow-none"
                                    type="button"
                                    onclick="window.sbToggle(this, '{{ $accordionId }}-tradeoffs')"
                                    aria-expanded="false"
                                    style="font-size:.82rem;color:#fd7e14;">
                                <i class="fas fa-circle-half-stroke me-2"></i>Tradeoffs
                            </button>
                        </h2>
                        <div id="{{ $accordionId }}-tradeoffs"
                             class="accordion-collapse collapse">
                            <div class="accordion-body px-0 pt-1 pb-2">
                                <ul class="list-unstyled mb-0" style="font-size:.82rem;">
                                    @foreach($card['tradeoffs'] as $tradeoff)
                                        <li class="d-flex align-items-start gap-2 mb-1">
                                            <i class="fas fa-arrow-right-arrow-left text-warning mt-1 flex-shrink-0" style="font-size:.75rem;"></i>
                                            <span>{{ $tradeoff['label'] }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Panel 3: Things to know (caution flags) --}}
                @if($hasCaution)
                    <div class="accordion-item border-0">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed px-0 py-2 bg-transparent shadow-none"
                                    type="button"
                                    onclick="window.sbToggle(this, '{{ $accordionId }}-caution')"
                                    aria-expanded="false"
                                    style="font-size:.82rem;color:#0d6efd;">
                                <i class="fas fa-circle-info me-2"></i>Things to know
                            </button>
                        </h2>
                        <div id="{{ $accordionId }}-caution"
                             class="accordion-collapse collapse">
                            <div class="accordion-body px-0 pt-1 pb-2">
                                <ul class="list-unstyled mb-0" style="font-size:.82rem;">
                                    @foreach($card['caution_flags'] as $flag)
                                        @php
                                            $flagIcon = match($flag['severity']) {
                                                'warning' => 'fas fa-triangle-exclamation text-warning',
                                                'critical' => 'fas fa-ban text-danger',
                                                default   => 'fas fa-circle-info text-info',
                                            };
                                        @endphp
                                        <li class="d-flex align-items-start gap-2 mb-1">
                                            <i class="{{ $flagIcon }} mt-1 flex-shrink-0" style="font-size:.75rem;"></i>
                                            <span>{{ $flag['label'] }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Panel 4: Missing listing data --}}
                @if($hasMissing)
                    <div class="accordion-item border-0">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed px-0 py-2 bg-transparent shadow-none"
                                    type="button"
                                    onclick="window.sbToggle(this, '{{ $accordionId }}-missing')"
                                    aria-expanded="false"
                                    style="font-size:.82rem;color:#6c757d;">
                                <i class="fas fa-circle-question me-2"></i>Missing listing data
                            </button>
                        </h2>
                        <div id="{{ $accordionId }}-missing"
                             class="accordion-collapse collapse">
                            <div class="accordion-body px-0 pt-1 pb-2">
                                <ul class="list-unstyled mb-0" style="font-size:.82rem;">
                                    @foreach($card['missing_data'] as $item)
                                        <li class="d-flex align-items-start gap-2 mb-1">
                                            <i class="fas fa-dash text-muted mt-1 flex-shrink-0" style="font-size:.75rem;"></i>
                                            <span class="text-muted">{{ $item['label'] }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                @endif

            </div>
        @endif

    </div>{{-- /card-body --}}

    {{-- ====================================================================
         CTA buttons
    ==================================================================== --}}
    <div class="card-footer bg-transparent border-top-0 pt-0 pb-3 px-3">
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ $detailUrl }}"
               class="btn btn-primary btn-sm"
               data-testid="view-details-btn">
                <i class="fas fa-eye me-1"></i>View Details
            </a>
            <button class="btn btn-outline-secondary btn-sm" disabled title="Save feature coming soon">
                <i class="far fa-bookmark me-1"></i>Save
            </button>
            <button class="btn btn-outline-secondary btn-sm" disabled title="Request Showing feature coming soon">
                <i class="fas fa-calendar-check me-1"></i>Request Showing
            </button>
            <button class="btn btn-outline-secondary btn-sm" disabled title="Ask a Question feature coming soon">
                <i class="fas fa-comment-dots me-1"></i>Ask a Question
            </button>
        </div>
    </div>

</div>{{-- /card --}}
