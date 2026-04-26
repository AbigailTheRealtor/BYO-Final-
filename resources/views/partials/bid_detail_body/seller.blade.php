
                                        {{-- ========== MATCH SCORE PANEL ========== --}}
                                        @if ($hasAnyBaseline)
                                        <div class="match-score-panel mb-4 p-3" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 10px; border: 1px solid #dee2e6;">
                                            @if ($showDualScore && $originalScore && $latestCounterScore)
                                            {{-- DUAL SCORE: Original Match + Latest Counter Match --}}
                                            <h6 class="mb-2" style="color: #1a3a5c; font-weight: 600;">
                                                <i class="fa fa-chart-pie me-2"></i>Match Summary
                                            </h6>
                                            <p class="small text-muted mb-3">
                                                <i class="fa fa-info-circle me-1"></i>
                                                <strong>Original Match</strong> compares this bid to the Seller's original listing request.<br>
                                                <strong>Counter Match</strong> compares this bid to the Seller's most recent counteroffer.<br>
                                                Added services or terms do not increase either score.
                                            </p>
                                            <div class="row g-3">
                                                {{-- Original Match column --}}
                                                @php $omColor = $getScoreColor($originalScore['overall_percent']); @endphp
                                                <div class="col-md-6">
                                                    <div class="p-3 bg-white rounded" style="border: 1px solid #dee2e6; border-top: 3px solid #6c757d;">
                                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                                            <span class="small fw-semibold" style="color: #6c757d;">Original Match</span>
                                                            <span class="badge" style="background: {{ $omColor }}; font-size: 1rem; padding: 5px 12px; color: white;">{{ $originalScore['overall_percent'] }}%</span>
                                                        </div>
                                                        <div class="small text-muted mb-2">vs. Seller's Original Request</div>
                                                        <div class="d-flex justify-content-between small">
                                                            <div>
                                                                <div class="fw-semibold" style="color: {{ $getScoreColor($originalScore['services_match_percent']) }};">Services {{ $originalScore['services_match_percent'] }}%</div>
                                                                <div class="text-muted">{{ $originalScore['services_baseline_total'] > 0 ? $originalScore['services_matched_count'].'/'.$originalScore['services_baseline_total'] : 'No services requested' }}</div>
                                                            </div>
                                                            <div>
                                                                <div class="fw-semibold" style="color: {{ $getScoreColor($originalScore['terms_match_percent']) }};">Terms {{ $originalScore['terms_match_percent'] }}%</div>
                                                                <div class="text-muted">{{ $originalScore['terms_baseline_total'] > 0 ? $originalScore['terms_matched_count'].'/'.$originalScore['terms_baseline_total'] : 'No terms provided' }}</div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                {{-- Counter Match column --}}
                                                @php $cmColor = $getScoreColor($latestCounterScore['overall_percent']); @endphp
                                                <div class="col-md-6">
                                                    <div class="p-3 rounded" style="background: #f0f9ff; border: 1px solid #bde0fe; border-top: 3px solid {{ $cmColor }};">
                                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                                            <span class="small fw-semibold" style="color: #1a3a5c;">Counter Match</span>
                                                            <span class="badge" style="background: {{ $cmColor }}; font-size: 1rem; padding: 5px 12px; color: white;">{{ $latestCounterScore['overall_percent'] }}%</span>
                                                        </div>
                                                        <div class="small text-muted mb-2">vs. Your Latest Counter</div>
                                                        <div class="d-flex justify-content-between small">
                                                            <div>
                                                                <div class="fw-semibold" style="color: {{ $getScoreColor($latestCounterScore['services_match_percent']) }};">Services {{ $latestCounterScore['services_match_percent'] }}%</div>
                                                                <div class="text-muted">{{ $latestCounterScore['services_baseline_total'] > 0 ? $latestCounterScore['services_matched_count'].'/'.$latestCounterScore['services_baseline_total'] : 'No services requested' }}</div>
                                                                @if($latestCounterScore['services_baseline_total'] > 0 && $latestCounterScore['services_extra_count'] > 0)<div style="color: #6c757d;">+{{ $latestCounterScore['services_extra_count'] }} added</div>@endif
                                                                @if($latestCounterScore['services_baseline_total'] > 0 && $latestCounterScore['services_missing_count'] > 0)<div style="color: #dc3545;">{{ $latestCounterScore['services_missing_count'] }} missing</div>@endif
                                                            </div>
                                                            <div>
                                                                <div class="fw-semibold" style="color: {{ $getScoreColor($latestCounterScore['terms_match_percent']) }};">Terms {{ $latestCounterScore['terms_match_percent'] }}%</div>
                                                                <div class="text-muted">{{ $latestCounterScore['terms_baseline_total'] > 0 ? $latestCounterScore['terms_matched_count'].'/'.$latestCounterScore['terms_baseline_total'] : 'No terms provided' }}</div>
                                                                @if($latestCounterScore['terms_changed_count'] > 0)<div style="color: #dc3545;">{{ $latestCounterScore['terms_changed_count'] }} changed</div>@endif
                                                                @if($latestCounterScore['terms_added_count'] > 0)<div style="color: #6c757d;">+{{ $latestCounterScore['terms_added_count'] }} added</div>@endif
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            @else
                                            {{-- SINGLE SCORE --}}
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <h6 class="mb-0" style="color: #1a3a5c; font-weight: 600;">
                                                    <i class="fa fa-chart-pie me-2"></i>Match Score
                                                </h6>
                                                <span class="badge" style="background: {{ $getScoreColor($totalScore) }}; font-size: 1.1rem; padding: 8px 16px;">
                                                    {{ $totalScore }}% Match
                                                </span>
                                            </div>
                                            <p class="small text-muted mb-3">
                                                <i class="fa fa-info-circle me-1"></i>Match Score compares this bid only to the Seller's original request. Added services or added terms are shown for transparency but do not increase the score.<br>
                                                Comparing to: <strong>{{ $baselineLabel }}</strong>
                                            </p>
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <div class="p-2 bg-white rounded" style="border-left: 4px solid {{ $getScoreColor($servicesScore) }};">
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <span class="small fw-semibold">Services Match</span>
                                                            <span class="badge" style="background: {{ $getScoreColor($servicesScore) }};">{{ $servicesScore }}%</span>
                                                        </div>
                                                        <div class="small text-muted mt-1">
                                                            {{ $servicesTotal > 0 ? 'Matched Original: '.$servicesMatched.'/'.$servicesTotal : 'No services requested' }}
                                                        </div>
                                                        @if ($servicesExtraCount > 0)
                                                        <div class="small mt-1 d-flex align-items-center flex-wrap" style="gap: 3px 5px;" title="Extra services were included by the Agent beyond the Seller&#39;s original request. These do not increase the match score but may provide additional value.">
                                                            <span>&#11088;</span>
                                                            <span style="font-weight: 500; color: #856404;">Extra Value Added: {{ $servicesExtraCount }} {{ $servicesExtraCount === 1 ? 'Service' : 'Services' }}</span>
                                                        </div>
                                                        @endif
                                                        @if ($servicesMissingCount > 0)
                                                        <div class="small mt-1" style="color: #dc3545;">Missing from Original: {{ $servicesMissingCount }}</div>
                                                        @endif
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="p-2 bg-white rounded" style="border-left: 4px solid {{ $getScoreColor($brokerScore) }};">
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <span class="small fw-semibold">Terms Match</span>
                                                            <span class="badge" style="background: {{ $getScoreColor($brokerScore) }};">{{ $brokerScore }}%</span>
                                                        </div>
                                                        <div class="small text-muted mt-1">
                                                            {{ $brokerTotal > 0 ? 'Matched Original: '.$brokerMatched.'/'.$brokerTotal : 'No terms provided' }}
                                                        </div>
                                                        @if ($brokerTotal > 0 && ($scoreResult['terms_changed_count'] ?? 0) > 0)
                                                        <div class="small mt-1" style="color: #dc3545;">Changed from Baseline: {{ $scoreResult['terms_changed_count'] }}</div>
                                                        @endif
                                                        @if (($scoreResult['terms_added_count'] ?? 0) > 0)
                                                        <div class="small mt-1" style="color: #6c757d;">Added by Agent: {{ $scoreResult['terms_added_count'] }}</div>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                            @endif
                                        </div>
                                        @else
                                        <div class="text-muted text-center py-3 mb-4" style="font-size: 0.92rem; background: #f8f9fa; border-radius: 8px; border: 1px solid #dee2e6; padding: 16px;">
                                            <i class="fa fa-info-circle me-1"></i>No match data available for this listing.
                                        </div>
                                        @endif
                                        {{-- ========== END MATCH SCORE PANEL ========== --}}

                                        <!-- 1. Agent Overview & Qualifications -->
                                        @if ($isListingOwner || $isBidOwner)
                                        <div class="mb-5">
                                            <h6 class="section-header">
                                                <i class="fa fa-user-tie me-2"></i>Agent Overview &amp; Qualifications
                                            </h6>

                                            @if (data_get($bid, 'get.bio'))
                                            <div class="mb-3">
                                                <div class="fw-semibold" style="color: #049399;">About Agent:</div>
                                                <div class="text-muted">{{ data_get($bid, 'get.bio') }}</div>
                                            </div>
                                            @endif

                                            @if (data_get($bid, 'get.why_hire_you'))
                                            <div class="mb-3">
                                                <div class="fw-semibold" style="color: #049399;">Why Hire This Agent:</div>
                                                <div class="text-muted">{{ data_get($bid, 'get.why_hire_you') }}</div>
                                            </div>
                                            @endif

                                            @if (data_get($bid, 'get.what_sets_you_apart'))
                                            <div class="mb-3">
                                                <div class="fw-semibold" style="color: #049399;">What Sets This Agent Apart:</div>
                                                <div class="text-muted">{{ data_get($bid, 'get.what_sets_you_apart') }}</div>
                                            </div>
                                            @endif

                                            @if (data_get($bid, 'get.marketing_plan'))
                                            <div class="mb-3">
                                                <div class="fw-semibold" style="color: #049399;">Marketing Strategy:</div>
                                                <div class="text-muted">{{ data_get($bid, 'get.marketing_plan') }}</div>
                                            </div>
                                            @endif

                                            @php
                                                $sellerReviewLinks = data_get($bid, 'get.reviews_links', []);
                                                $hasAnySellerReviewUrl = !empty(array_filter((array) $sellerReviewLinks, fn($rl) => !empty(is_object($rl) ? $rl->url : ($rl['url'] ?? ''))));
                                            @endphp
                                            @if ($hasAnySellerReviewUrl)
                                            <div class="mb-3">
                                                <div class="fw-semibold" style="color: #049399;">Review Links:</div>
                                                <div>
                                                    @foreach ($sellerReviewLinks as $reviewLink)
                                                    @php $rlUrlVal = is_object($reviewLink) ? $reviewLink->url : ($reviewLink['url'] ?? ''); @endphp
                                                    @if (!empty($rlUrlVal))
                                                    <div class="mb-1">
                                                        @php
                                                            $rlFinal = $rlUrlVal;
                                                            if (!str_starts_with($rlFinal, 'http://') && !str_starts_with($rlFinal, 'https://')) {
                                                                $rlFinal = 'https://' . $rlFinal;
                                                            }
                                                            $rlText = is_object($reviewLink) ? ($reviewLink->text ?? '') : ($reviewLink['text'] ?? '');
                                                        @endphp
                                                        <a href="{{ $rlFinal }}" target="_blank" class="text-primary text-decoration-none">
                                                            <i class="fa fa-external-link-alt me-1"></i>
                                                            {{ !empty($rlText) ? $rlText : $rlUrlVal }}
                                                        </a>
                                                    </div>
                                                    @endif
                                                    @endforeach
                                                </div>
                                            </div>
                                            @endif

                                            @if (data_get($bid, 'get.website_link'))
                                            <div class="mb-3">
                                                <div class="fw-semibold" style="color: #049399;">Website Link:</div>
                                                <div>
                                                    @php
                                                        $wLink = data_get($bid, 'get.website_link');
                                                        if (!empty($wLink) && !str_starts_with($wLink, 'http://') && !str_starts_with($wLink, 'https://')) {
                                                            $wLink = 'https://' . $wLink;
                                                        }
                                                    @endphp
                                                    <a href="{{ $wLink }}" target="_blank" rel="noopener noreferrer" class="text-primary text-decoration-none">
                                                        <i class="fa fa-globe me-1"></i> Visit Website
                                                    </a>
                                                </div>
                                            </div>
                                            @endif

                                            @if (data_get($bid, 'get.social_media'))
                                            <div class="mb-3">
                                                <div class="fw-semibold" style="color: #049399;">Social Media Platforms:</div>
                                                <div>
                                                    @foreach (data_get($bid, 'get.social_media') as $social)
                                                    @php $socialArray = (array) $social; @endphp
                                                    @if (!empty($socialArray['platform']) && !empty($socialArray['url']))
                                                    <div class="mb-1">
                                                        @php
                                                            $socialUrl = $socialArray['url'];
                                                            if (!str_starts_with($socialUrl, 'http://') && !str_starts_with($socialUrl, 'https://')) {
                                                                $socialUrl = 'https://' . $socialUrl;
                                                            }
                                                        @endphp
                                                        <a href="{{ $socialUrl }}" target="_blank" class="text-primary text-decoration-none">
                                                            <i class="fab fa-{{ strtolower($socialArray['platform']) }} me-1"></i>
                                                            {{ !empty($socialArray['text']) ? $socialArray['text'] : $socialArray['platform'] }}
                                                        </a>
                                                    </div>
                                                    @endif
                                                    @endforeach
                                                </div>
                                            </div>
                                            @endif

                                            @if (data_get($bid, 'get.year_licensed'))
                                            <div class="mb-3">
                                                <div class="fw-semibold" style="color: #049399;">Licensed Year:</div>
                                                <div class="text-muted">{{ data_get($bid, 'get.year_licensed') }}</div>
                                            </div>
                                            @endif

                                        </div>
                                        @endif

                                        <!-- 2. Broker Compensation & Agency Agreement Terms -->
                                        @php
                                            $bidBrokerHasAny = data_get($bid, 'get.commission_structure') ||
                                                data_get($bid, 'get.purchase_fee_type') ||
                                                data_get($bid, 'get.nominal') ||
                                                data_get($bid, 'get.commission_structure_type') ||
                                                data_get($bid, 'get.interested_purchase_fee_type') ||
                                                data_get($bid, 'get.interested_lease_option_agreement') ||
                                                data_get($bid, 'get.protection_period') ||
                                                data_get($bid, 'get.early_termination_fee_option') ||
                                                data_get($bid, 'get.retainer_fee_option') ||
                                                data_get($bid, 'get.retained_deposits') ||
                                                data_get($bid, 'get.agency_agreement_timeframe') ||
                                                data_get($bid, 'get.brokerage_relationship');
                                        @endphp
                                        @if ($bidBrokerHasAny)
                                        <div class="mb-5">
                                            <h6 class="section-header">
                                                <i class="fa fa-handshake me-2"></i>Broker Compensation &amp; Agency Agreement Terms
                                            </h6>

                                            <!-- A) Seller's Broker Compensation -->
                                            @php
                                                $bidCommStruct = data_get($bid, 'get.commission_structure');
                                                $bidPurchaseFeeType = data_get($bid, 'get.purchase_fee_type');
                                                $bidNominal = data_get($bid, 'get.nominal');
                                                $bidCommStructType = data_get($bid, 'get.commission_structure_type');
                                                // Build Buyer's Broker Commission Fee display
                                                $bidBuyerBrokerFee = null;
                                                if ($bidCommStructType === 'Flat Fee' && data_get($bid, 'get.commission_structure_type_fee_flat')) {
                                                    $bidBuyerBrokerFee = $fmtMoney(data_get($bid, 'get.commission_structure_type_fee_flat'));
                                                } elseif ($bidCommStructType === 'Percentage of the Total Purchase Price' && data_get($bid, 'get.commission_structure_type_fee_percentage')) {
                                                    $bidBuyerBrokerFee = ($fmtPercent(data_get($bid, 'get.commission_structure_type_fee_percentage')) ?? '-') . ' of Total Purchase Price';
                                                } elseif ($bidCommStructType === 'Flat Fee + Percentage' && (data_get($bid, 'get.commission_structure_type_fee_flat_combo') || data_get($bid, 'get.commission_structure_type_fee_percentage_combo'))) {
                                                    $bbfParts = [];
                                                    if (data_get($bid, 'get.commission_structure_type_fee_percentage_combo')) $bbfParts[] = ($fmtPercent(data_get($bid, 'get.commission_structure_type_fee_percentage_combo')) ?? '') . ' of Total Purchase Price';
                                                    if (data_get($bid, 'get.commission_structure_type_fee_flat_combo')) $bbfParts[] = $fmtMoney(data_get($bid, 'get.commission_structure_type_fee_flat_combo'));
                                                    $bidBuyerBrokerFee = implode(' + ', array_filter($bbfParts));
                                                } elseif (strtolower($bidCommStructType ?? '') === 'other' && data_get($bid, 'get.commission_structure_type_fee_other')) {
                                                    $bidBuyerBrokerFee = data_get($bid, 'get.commission_structure_type_fee_other');
                                                } elseif ($bidCommStructType) {
                                                    $bidBuyerBrokerFee = $bidCommStructType;
                                                }
                                                $showSellerBrokerComp = $bidCommStruct || $bidPurchaseFeeType || $bidNominal || $bidCommStructType;
                                            @endphp
                                            @if ($showSellerBrokerComp)
                                            <div class="mb-4">
                                                <h6 class="mb-2" style="color: #049399; font-weight: 600;">A) Broker Compensation</h6>
                                                <ul class="list-unstyled ps-3 mb-0">
                                                    @if ($bidPurchaseFeeType)
                                                    <li class="mb-1" style="{{ isset($brokerMismatches['purchase_fee_type']) ? $mismatchStyle : '' }}"><span class="fw-semibold">Seller's Broker Purchase Fee:</span> {{ $sellerPurchaseFeeDisplay }}{!! isset($brokerMismatches['purchase_fee_type']) ? $mismatchBadge : '' !!}</li>
                                                    @endif
                                                    @if ($bidNominal)
                                                    <li class="mb-1" style="{{ isset($brokerMismatches['nominal']) ? $mismatchStyle : '' }}"><span class="fw-semibold">Nominal Consideration Fee:</span> {{ $fmtMoney($bidNominal) ?? '-' }}{!! isset($brokerMismatches['nominal']) ? $mismatchBadge : '' !!}</li>
                                                    @endif
                                                    @if ($bidCommStruct)
                                                    <li class="mb-1" style="{{ isset($brokerMismatches['commission_structure']) ? $mismatchStyle : '' }}"><span class="fw-semibold">Buyer's Broker Commission Structure:</span> {{ $bidCommStruct }}{!! isset($brokerMismatches['commission_structure']) ? $mismatchBadge : '' !!}</li>
                                                    @endif
                                                    @if ($bidBuyerBrokerFee)
                                                    <li class="mb-1" style="{{ isset($brokerMismatches['commission_structure_type']) ? $mismatchStyle : '' }}"><span class="fw-semibold">Buyer's Broker Commission Fee:</span> {{ $bidBuyerBrokerFee }}{!! isset($brokerMismatches['commission_structure_type']) ? $mismatchBadge : '' !!}</li>
                                                    @endif
                                                </ul>
                                            </div>
                                            @endif

                                            <!-- B) Lease Terms -->
                                            @php
                                                $bidInterestedLease = data_get($bid, 'get.interested_purchase_fee_type');
                                                $showLeaseTerms = strtolower(trim($bidInterestedLease ?? '')) === 'yes';
                                                $bidLeasingFeeType = data_get($bid, 'get.seller_leasing_fee_type');
                                                // Build leasing fee amount display (mirrors listing view logic)
                                                $bidLeasingFeeAmt = null;
                                                if ($bidLeasingFeeType === 'Flat Fee' && data_get($bid, 'get.seller_leasing_gross_purchase_fee_flat_amount')) {
                                                    $bidLeasingFeeAmt = $fmtMoney(data_get($bid, 'get.seller_leasing_gross_purchase_fee_flat_amount'));
                                                } elseif ($bidLeasingFeeType === 'Percentage of the Gross Lease Value' && data_get($bid, 'get.seller_leasing_gross')) {
                                                    $bidLeasingFeeAmt = ($fmtPercent(data_get($bid, 'get.seller_leasing_gross')) ?? '-') . ' of the Gross Lease Value';
                                                } elseif ($bidLeasingFeeType === 'Percentage of the Rent Due Each Rental Period' && data_get($bid, 'get.seller_leasing_gross_rental')) {
                                                    $bidLeasingFeeAmt = ($fmtPercent(data_get($bid, 'get.seller_leasing_gross_rental')) ?? '-') . ' of the Rent Due Each Rental Period';
                                                } elseif ($bidLeasingFeeType === "Percentage of the First Month's Rent" && data_get($bid, 'get.seller_leasing_gross_month_rent')) {
                                                    $bidLeasingFeeAmt = ($fmtPercent(data_get($bid, 'get.seller_leasing_gross_month_rent')) ?? '-') . " of the First Month's Rent";
                                                } elseif ($bidLeasingFeeType === "Percentage of Month's Rent" && data_get($bid, 'get.seller_leasing_gross_month_rent')) {
                                                    $bidLeasingFeeAmt = ($fmtPercent(data_get($bid, 'get.seller_leasing_gross_month_rent')) ?? '-') . " of Month's Rent";
                                                    $bidLeasingMonths = data_get($bid, 'get.seller_leasing_gross_no_of_months');
                                                    if (!empty($bidLeasingMonths) && $bidLeasingMonths != 'null') {
                                                        $bidLeasingFeeAmt .= ' x ' . intval($bidLeasingMonths) . ' Months';
                                                    }
                                                } elseif ($bidLeasingFeeType === 'Percentage of Net Aggregate Rent' && (data_get($bid, 'get.seller_leasing_gross_other') ?: data_get($bid, 'get.seller_leasing_gross'))) {
                                                    $netAggVal = data_get($bid, 'get.seller_leasing_gross_other') ?: data_get($bid, 'get.seller_leasing_gross');
                                                    $bidLeasingFeeAmt = ($fmtPercent($netAggVal) ?? '-') . ' of Net Aggregate Rent';
                                                } elseif ($bidLeasingFeeType === 'Percentage of Gross Rent' && (data_get($bid, 'get.seller_leasing_gross_percentage') || data_get($bid, 'get.seller_leasing_gross_ross_percentage_rent'))) {
                                                    $grossRentVal = data_get($bid, 'get.seller_leasing_gross_percentage') ?? data_get($bid, 'get.seller_leasing_gross_ross_percentage_rent');
                                                    $bidLeasingFeeAmt = ($fmtPercent($grossRentVal) ?? '-') . ' of Gross Rent';
                                                } elseif ($bidLeasingFeeType === 'Flat Fee + Percentage of the Gross Lease Value' && (data_get($bid, 'get.seller_leasing_gross_flat_combo') || data_get($bid, 'get.seller_leasing_gross_percentage_combo'))) {
                                                    $lfParts = [];
                                                    if (data_get($bid, 'get.seller_leasing_gross_flat_combo')) $lfParts[] = $fmtMoney(data_get($bid, 'get.seller_leasing_gross_flat_combo'));
                                                    if (data_get($bid, 'get.seller_leasing_gross_percentage_combo')) $lfParts[] = ($fmtPercent(data_get($bid, 'get.seller_leasing_gross_percentage_combo')) ?? '') . ' of Gross Lease Value';
                                                    $bidLeasingFeeAmt = implode(' + ', array_filter($lfParts));
                                                } elseif ($bidLeasingFeeType === 'Flat Fee + Percentage of the Net Aggregate Rent' && (data_get($bid, 'get.seller_leasing_gross_flat_net_combo') || data_get($bid, 'get.seller_leasing_gross_percentage_net_combo'))) {
                                                    $lfParts = [];
                                                    if (data_get($bid, 'get.seller_leasing_gross_flat_net_combo')) $lfParts[] = $fmtMoney(data_get($bid, 'get.seller_leasing_gross_flat_net_combo'));
                                                    if (data_get($bid, 'get.seller_leasing_gross_percentage_net_combo')) $lfParts[] = ($fmtPercent(data_get($bid, 'get.seller_leasing_gross_percentage_net_combo')) ?? '') . ' of Net Aggregate Rent';
                                                    $bidLeasingFeeAmt = implode(' + ', array_filter($lfParts));
                                                } elseif (strtolower($bidLeasingFeeType ?? '') === 'other' && data_get($bid, 'get.seller_leasing_gross_purchase_fee_other')) {
                                                    $bidLeasingFeeAmt = data_get($bid, 'get.seller_leasing_gross_purchase_fee_other');
                                                } elseif ($bidLeasingFeeType) {
                                                    $bidLeasingFeeAmt = $bidLeasingFeeType;
                                                }
                                            @endphp
                                            @if ($bidInterestedLease)
                                            <div class="mb-4">
                                                <h6 class="mb-2" style="color: #049399; font-weight: 600;">B) Lease Terms</h6>
                                                <ul class="list-unstyled ps-3 mb-0">
                                                    <li class="mb-1" style="{{ isset($brokerMismatches['interested_purchase_fee_type']) ? $mismatchStyle : '' }}"><span class="fw-semibold">Interested in Offering a Lease Agreement:</span> {{ $bidInterestedLease }}{!! isset($brokerMismatches['interested_purchase_fee_type']) ? $mismatchBadge : '' !!}</li>
                                                    @if ($showLeaseTerms && $bidLeasingFeeAmt)
                                                    <li class="mb-1" style="{{ isset($brokerMismatches['seller_leasing_fee_type']) ? $mismatchStyle : '' }}"><span class="fw-semibold">Seller's Broker Leasing Fee:</span> {{ $bidLeasingFeeAmt }}{!! isset($brokerMismatches['seller_leasing_fee_type']) ? $mismatchBadge : '' !!}</li>
                                                    @endif
                                                </ul>
                                            </div>
                                            @endif

                                            <!-- C) Lease-Option Terms -->
                                            @php
                                                $bidLeaseOption = data_get($bid, 'get.interested_lease_option_agreement');
                                                $showLeaseOption = strtolower(trim($bidLeaseOption ?? '')) === 'yes';
                                                $bidLeaseType = data_get($bid, 'get.lease_type');
                                                $bidLeaseValue = data_get($bid, 'get.lease_value');
                                                $bidPurchaseType = data_get($bid, 'get.purchase_type');
                                                $bidPurchaseValue = data_get($bid, 'get.purchase_value');
                                                // Format lease-option creation fee (mirrors listing view: % of Total Purchase Price)
                                                $bidLeaseOptionFee = null;
                                                if ($bidLeaseValue) {
                                                    if (in_array($bidLeaseType, ['%', 'percent']) || str_contains((string)($bidLeaseValue ?? ''), '%')) {
                                                        $bidLeaseOptionFee = str_replace('%', '', $bidLeaseValue) . '% of Total Purchase Price';
                                                    } else {
                                                        $bidLeaseOptionFee = $fmtMoney($bidLeaseValue);
                                                    }
                                                }
                                                // Format purchase option exercise fee
                                                $bidPurchaseOptFee = null;
                                                if ($bidPurchaseValue) {
                                                    if (in_array($bidPurchaseType, ['%', 'percent']) || str_contains((string)($bidPurchaseValue ?? ''), '%')) {
                                                        $bidPurchaseOptFee = str_replace('%', '', $bidPurchaseValue) . '% of Total Purchase Price';
                                                    } else {
                                                        $bidPurchaseOptFee = $fmtMoney($bidPurchaseValue);
                                                    }
                                                }
                                            @endphp
                                            @if ($bidLeaseOption)
                                            <div class="mb-4">
                                                <h6 class="mb-2" style="color: #049399; font-weight: 600;">C) Lease-Option Terms</h6>
                                                <ul class="list-unstyled ps-3 mb-0">
                                                    <li class="mb-1" style="{{ isset($brokerMismatches['interested_lease_option_agreement']) ? $mismatchStyle : '' }}"><span class="fw-semibold">Interested in Lease-Option Agreement:</span> {{ $bidLeaseOption }}{!! isset($brokerMismatches['interested_lease_option_agreement']) ? $mismatchBadge : '' !!}</li>
                                                    @if ($showLeaseOption && $bidLeaseOptionFee)
                                                    <li class="mb-1" style="{{ (isset($brokerMismatches['lease_type']) || isset($brokerMismatches['lease_value'])) ? $mismatchStyle : '' }}"><span class="fw-semibold">Compensation for Creating Lease-Option Agreement:</span> {{ $bidLeaseOptionFee }}{!! (isset($brokerMismatches['lease_type']) || isset($brokerMismatches['lease_value'])) ? $mismatchBadge : '' !!}</li>
                                                    @elseif ($showLeaseOption && (isset($brokerMismatches['lease_type']) || isset($brokerMismatches['lease_value'])))
                                                    <li class="mb-1" style="{{ $mismatchStyle }}"><span class="fw-semibold">Compensation for Creating Lease-Option Agreement:</span> —{!! $mismatchBadge !!}</li>
                                                    @endif
                                                    @if ($showLeaseOption && $bidPurchaseOptFee)
                                                    <li class="mb-1" style="{{ (isset($brokerMismatches['purchase_type']) || isset($brokerMismatches['purchase_value'])) ? $mismatchStyle : '' }}"><span class="fw-semibold">Compensation if Purchase Option is Exercised:</span> {{ $bidPurchaseOptFee }}{!! (isset($brokerMismatches['purchase_type']) || isset($brokerMismatches['purchase_value'])) ? $mismatchBadge : '' !!}</li>
                                                    @elseif ($showLeaseOption && (isset($brokerMismatches['purchase_type']) || isset($brokerMismatches['purchase_value'])))
                                                    <li class="mb-1" style="{{ $mismatchStyle }}"><span class="fw-semibold">Compensation if Purchase Option is Exercised:</span> —{!! $mismatchBadge !!}</li>
                                                    @endif
                                                </ul>
                                            </div>
                                            @endif

                                            <!-- D) Legal Terms -->
                                            @php
                                                $bidEarlyTermOpt = data_get($bid, 'get.early_termination_fee_option');
                                                $bidEarlyTermAmt = data_get($bid, 'get.early_termination_fee_amount');
                                                $bidRetainedDep  = data_get($bid, 'get.retained_deposits');
                                                $bidRetainerOpt  = data_get($bid, 'get.retainer_fee_option');
                                                $bidRetainerAmt  = data_get($bid, 'get.retainer_fee_amount');
                                                $bidRetainerApp  = data_get($bid, 'get.retainer_fee_application');
                                                $bidProtPeriod   = data_get($bid, 'get.protection_period');
                                                $bidAgencyTf     = data_get($bid, 'get.agency_agreement_timeframe');
                                                $bidAgencyCus    = data_get($bid, 'get.agency_agreement_custom');
                                                $bidAgencyDsp    = strtolower(trim($bidAgencyTf ?? '')) === 'other' ? ($bidAgencyCus ?: 'Other') : ($bidAgencyTf ?: '');
                                                $showLegalTerms  = $bidEarlyTermOpt || $bidRetainedDep || $bidRetainerOpt || $bidProtPeriod || $bidAgencyTf;
                                            @endphp
                                            @if ($showLegalTerms)
                                            <div class="mb-4">
                                                <h6 class="mb-2" style="color: #049399; font-weight: 600;">D) Legal Terms</h6>
                                                <ul class="list-unstyled ps-3 mb-0">
                                                    @if ($bidEarlyTermOpt)
                                                    <li class="mb-1" style="{{ isset($brokerMismatches['early_termination_fee_option']) ? $mismatchStyle : '' }}"><span class="fw-semibold">Early Termination Fee:</span> {{ ucfirst($bidEarlyTermOpt) }}{!! isset($brokerMismatches['early_termination_fee_option']) ? $mismatchBadge : '' !!}</li>
                                                    @if (strtolower($bidEarlyTermOpt) === 'yes' && $bidEarlyTermAmt)
                                                    <li class="mb-1" style="{{ isset($brokerMismatches['early_termination_fee_amount']) ? $mismatchStyle : '' }}"><span class="fw-semibold">Termination Fee Amount:</span> {{ $fmtMoney($bidEarlyTermAmt) }}{!! isset($brokerMismatches['early_termination_fee_amount']) ? $mismatchBadge : '' !!}</li>
                                                    @elseif (strtolower($bidEarlyTermOpt) === 'yes' && isset($brokerMismatches['early_termination_fee_amount']))
                                                    <li class="mb-1" style="{{ $mismatchStyle }}"><span class="fw-semibold">Termination Fee Amount:</span> —{!! $mismatchBadge !!}</li>
                                                    @endif
                                                    @endif
                                                    @if ($bidRetainerOpt)
                                                    <li class="mb-1" style="{{ isset($brokerMismatches['retainer_fee_option']) ? $mismatchStyle : '' }}"><span class="fw-semibold">Retainer Fee:</span> {{ ucfirst($bidRetainerOpt) }}{!! isset($brokerMismatches['retainer_fee_option']) ? $mismatchBadge : '' !!}</li>
                                                        @if (strtolower($bidRetainerOpt) === 'yes' && $bidRetainerAmt)
                                                        <li class="mb-1" style="{{ isset($brokerMismatches['retainer_fee_amount']) ? $mismatchStyle : '' }}"><span class="fw-semibold">Retainer Fee Amount:</span> {{ $fmtMoney($bidRetainerAmt) }}{!! isset($brokerMismatches['retainer_fee_amount']) ? $mismatchBadge : '' !!}</li>
                                                        @elseif (isset($brokerMismatches['retainer_fee_amount']))
                                                        <li class="mb-1" style="{{ $mismatchStyle }}"><span class="fw-semibold">Retainer Fee Amount:</span> —{!! $mismatchBadge !!}</li>
                                                        @endif
                                                        @if (strtolower($bidRetainerOpt) === 'yes' && $bidRetainerApp)
                                                        <li class="mb-1" style="{{ isset($brokerMismatches['retainer_fee_application']) ? $mismatchStyle : '' }}"><span class="fw-semibold">Retainer Fee Application:</span> {{ $bidRetainerApp }}{!! isset($brokerMismatches['retainer_fee_application']) ? $mismatchBadge : '' !!}</li>
                                                        @elseif (isset($brokerMismatches['retainer_fee_application']))
                                                        <li class="mb-1" style="{{ $mismatchStyle }}"><span class="fw-semibold">Retainer Fee Application:</span> —{!! $mismatchBadge !!}</li>
                                                        @endif
                                                    @endif
                                                    @if ($bidRetainedDep)
                                                    <li class="mb-1" style="{{ isset($brokerMismatches['retained_deposits']) ? $mismatchStyle : '' }}"><span class="fw-semibold">Seller's Broker's Share of Retained Deposits:</span> {{ $fmtPercent($bidRetainedDep) }}{!! isset($brokerMismatches['retained_deposits']) ? $mismatchBadge : '' !!}</li>
                                                    @endif
                                                    @if ($bidProtPeriod)
                                                    <li class="mb-1" style="{{ isset($brokerMismatches['protection_period']) ? $mismatchStyle : '' }}"><span class="fw-semibold">Protection Period Timeframe:</span> {{ $bidProtPeriod }} days{!! isset($brokerMismatches['protection_period']) ? $mismatchBadge : '' !!}</li>
                                                    @endif
                                                    @if ($bidAgencyTf)
                                                    <li class="mb-1" style="{{ isset($brokerMismatches['agency_agreement_timeframe']) ? $mismatchStyle : '' }}"><span class="fw-semibold">Seller Agency Agreement Timeframe:</span> {{ $bidAgencyDsp }}{!! isset($brokerMismatches['agency_agreement_timeframe']) ? $mismatchBadge : '' !!}</li>
                                                    @endif
                                                </ul>
                                            </div>
                                            @endif

                                            <!-- E) Brokerage Relationship -->
                                            @if (data_get($bid, 'get.brokerage_relationship'))
                                            <div class="mb-4">
                                                <h6 class="mb-2" style="color: #049399; font-weight: 600;">E) Brokerage Relationship</h6>
                                                <ul class="list-unstyled ps-3 mb-0">
                                                    <li class="mb-1" style="{{ isset($brokerMismatches['brokerage_relationship']) ? $mismatchStyle : '' }}"><span class="fw-semibold">Acceptable Brokerage Relationship:</span> {{ data_get($bid, 'get.brokerage_relationship') }}{!! isset($brokerMismatches['brokerage_relationship']) ? $mismatchBadge : '' !!}</li>
                                                </ul>
                                            </div>
                                            @endif

                                            <!-- F) Additional Terms -->
                                            @if (data_get($bid, 'get.additional_details_broker'))
                                            <div class="mb-4">
                                                <h6 class="mb-2" style="color: #049399; font-weight: 600;">F) Additional Terms</h6>
                                                <ul class="list-unstyled ps-3 mb-0">
                                                    <li class="mb-1"><span class="fw-semibold">Additional Terms:</span> {{ data_get($bid, 'get.additional_details_broker') }}</li>
                                                </ul>
                                            </div>
                                            @endif

                                        </div>
                                        @endif

                                        <!-- G) Referral Fee -->
                                        @if ($auction->isCreatedByAgent() && data_get($bid, 'get.referral_fee_percent'))
                                        <div class="mb-4">
                                            <h6 class="mb-2" style="color: #049399; font-weight: 600;">G) Referral Fee</h6>
                                            <ul class="list-unstyled ps-3 mb-0">
                                                <li class="mb-1" style="{{ isset($brokerMismatches['referral_fee_percent']) ? $mismatchStyle : '' }}">
                                                    <span class="fw-semibold">Referral Fee (%):</span>
                                                    {{ data_get($bid, 'get.referral_fee_percent') }}%{!! isset($brokerMismatches['referral_fee_percent']) ? $mismatchBadge : '' !!}
                                                </li>
                                            </ul>
                                        </div>
                                        @endif

                                        <!-- Additional Details -->
                                        @if (data_get($bid, 'get.additional_details'))
                                        <div class="mb-5">
                                            <h6 class="section-header">
                                                <i class="fa fa-info-circle me-2"></i>Additional Details
                                            </h6>
                                            <div class="text-muted" style="font-style: italic;">
                                                {{ data_get($bid, 'get.additional_details') }}
                                            </div>
                                        </div>
                                        @endif

                                        <!-- 3. Offered Services (grouped bullet points, matches Tenant) -->
                                        @php
                                            $allBidMeta = (array) data_get($bid, 'get', []);
                                            $services   = $allBidMeta['services'] ?? [];
                                            if (is_string($services)) { $services = json_decode($services, true) ?? []; }
                                            $services = array_filter((array)$services, fn($s) => !empty(trim((string)$s)) && $s !== 'Other');

                                            $normalizeStr = fn($s) => strtolower(trim(preg_replace('/[\x{2018}\x{2019}]/u', "'", preg_replace('/[\x{201C}\x{201D}]/u', '"', $s))));

                                            // Build selected services normalized map
                                            $selectedNormalized = [];
                                            foreach ($services as $svc) {
                                                $displaySvc = function_exists('normalize_service_text') ? normalize_service_text($svc) : $svc;
                                                $selectedNormalized[$normalizeStr($svc)] = $displaySvc;
                                            }

                                            // Determine property type from bid meta, falling back to auction
                                            $bidPropertyType = $allBidMeta['property_type']
                                                ?? data_get($auction, 'get.property_type', 'Residential');
                                            // Normalize to short form
                                            $bidPropNorm = strtolower(trim($bidPropertyType));
                                            if (str_contains($bidPropNorm, 'income')) {
                                                $bidPropKey = 'Income';
                                            } elseif (str_contains($bidPropNorm, 'commercial')) {
                                                $bidPropKey = 'Commercial';
                                            } elseif (str_contains($bidPropNorm, 'business')) {
                                                $bidPropKey = 'Business';
                                            } elseif (str_contains($bidPropNorm, 'vacant') || str_contains($bidPropNorm, 'land')) {
                                                $bidPropKey = 'Vacant Land';
                                            } else {
                                                $bidPropKey = 'Residential';
                                            }

                                            $sellerServicesConfig = [
                                                'Residential' => [
                                                    '📢 Property Marketing & Listing Promotion' => [
                                                        "List the property on the local Multiple Listing Service (MLS)",
                                                        "Syndicate the listing to third-party platforms (e.g., Zillow.com, Realtor.com, Trulia.com, Homes.com)",
                                                        "Create a branded flyer featuring the property\u2019s key highlights",
                                                        "Post the property on Facebook Marketplace",
                                                        "Post the property on Craigslist under the \"Homes for Sale\" category",
                                                        "Share the listing on Nextdoor in Neighborhood or Community Groups",
                                                        "Promote the listing on Facebook in Real Estate or Community Groups",
                                                        "Share the listing on Instagram using posts, stories, or reels",
                                                        "Promote the listing on LinkedIn in Professional or Real Estate Groups",
                                                        "Upload a TikTok video walkthrough of the property",
                                                        "Upload a YouTube video walkthrough of the property",
                                                        "Launch a mass email campaign promoting the listing",
                                                        "Distribute printed flyers or postcards in target geographic areas",
                                                        "Launch hyperlocal or interest-based digital ad campaigns promoting the listing",
                                                    ],
                                                    '🛠️ Listing Preparation & Presentation' => [
                                                        "Conduct a property walkthrough and provide recommendations for listing readiness",
                                                        "Provide a custom listing preparation checklist",
                                                        "Collect property details and prepare MLS remarks and a public listing description",
                                                        "Provide a visual consultation for interior layout, cleanliness, and presentation",
                                                        "Provide a curb appeal consultation focused on exterior presentation",
                                                        "Provide referrals to third-party vendors (e.g., cleaners, handypeople, electricians, landscapers). Vendor fees billed separately. Referrals only \u2014 no endorsement or warranty is made",
                                                    ],
                                                    '📸 Photography, Video & Virtual Media' => [
                                                        "Provide professional property photography",
                                                        "Provide aerial (drone) photography (subject to FAA Part 107 compliance)",
                                                        "Provide a video walkthrough tour",
                                                        "Provide a 3D virtual tour",
                                                        "Provide virtual staging (digital enhancements only; no physical staging)",
                                                        "Provide digital photo enhancements",
                                                        "Create a basic schematic floor plan (non-certified; for marketing purposes only)",
                                                    ],
                                                    '🏡 Showings & Access Coordination' => [
                                                        "Ensure proper notice is provided if the property is occupied",
                                                        "Install a real estate sign on the property",
                                                        "Install a lockbox for Agent access",
                                                        "Schedule and attend showings with prospective Buyers",
                                                        "Coordinate showings with Buyer\u2019s Agents",
                                                        "Collect and relay showing feedback to the Seller",
                                                    ],
                                                    '📑 Offer & Contract Management' => [
                                                        "Present all offers to the Seller and summarize key terms, pricing, and contingencies",
                                                        "Provide the Seller with the necessary disclosure forms required by state or local law",
                                                        "Negotiate price, terms, and contingencies with the Buyer\u2019s Agent or Buyer",
                                                        "Manage communications with the Buyer\u2019s Agent or Buyer",
                                                        "Draft and deliver counteroffers and manage revisions to the purchase agreement",
                                                        "Assist with in-person or electronic contract signing, including e-signature setup and secure delivery of executed purchase agreements, addenda, and disclosures to all parties",
                                                        "Assist with inspection-related negotiations and Buyer requests for repairs",
                                                        "Monitor contract milestones, contingency periods, and financing deadlines",
                                                        "Provide referrals to Attorneys, Title Companies, and Escrow Professionals (referrals only \u2014 no endorsement or warranty is made)",
                                                    ],
                                                    '🧾 Closing Coordination & Transaction Management' => [
                                                        "Coordinate scheduling for inspections, appraisals, and other requested evaluations",
                                                        "Coordinate with the Buyer\u2019s Agent, Lender, Title, Escrow, and/or Attorney to prepare for Closing",
                                                        "Review the Settlement Statement and coordinate with all parties if corrections are needed",
                                                        "Confirm delivery of final executed documents, wire instructions, and Closing paperwork to all relevant parties",
                                                        "Schedule and confirm the Final Walkthrough",
                                                        "Schedule and confirm the Closing Appointment",
                                                    ],
                                                    '💡 Selling Strategy & Guidance' => [
                                                        "Provide a Comparative Market Analysis (CMA) with pricing recommendations based on comparable sales, neighborhood trends, and current market conditions",
                                                        "Provide general insight on local market trends, seasonal timing, and pricing thresholds",
                                                        "Recommend adjustments to pricing or marketing strategy if the property is not receiving sufficient interest",
                                                        "Provide general guidance on Seller obligations, required disclosures, and listing preparation",
                                                    ],
                                                ],
                                                'Income' => [
                                                    '📢 Property Marketing & Listing Promotion' => [
                                                        "List the property on the local Multiple Listing Service (MLS)",
                                                        "Syndicate the listing to third-party platforms (e.g., Zillow.com, Realtor.com, Trulia.com, Homes.com)",
                                                        "List the property on Crexi.com",
                                                        "List the property on LoopNet.com",
                                                        "Create a branded flyer with key rental data (e.g., unit mix, gross income, occupancy)",
                                                        "Post the property on Craigslist under the \"Multi-Family for Sale\" category",
                                                        "Share the listing on Nextdoor in Neighborhood or Community Groups",
                                                        "Promote the listing on Facebook in Real Estate Investor or Multi-Family Buyer Groups",
                                                        "Share the listing on Instagram using posts, stories, or reels",
                                                        "Promote the listing on LinkedIn in Investment or Real Estate Groups",
                                                        "Upload a TikTok video walkthrough of the property",
                                                        "Upload a YouTube video walkthrough of the property",
                                                        "Launch a mass email campaign promoting the listing",
                                                        "Distribute printed flyers or postcards in target geographic areas",
                                                        "Launch hyperlocal or interest-based digital ad campaigns promoting the listing",
                                                    ],
                                                    '🛠️ Listing Preparation & Investment Packaging' => [
                                                        "Conduct a property walkthrough and provide recommendations for listing readiness",
                                                        "Provide a custom listing preparation checklist",
                                                        "Assist with assembling an income property packet, including rent roll, lease copies, and an income/expense summary (as available)",
                                                        "Provide a visual consultation focused on interior layout, cleanliness, and unit presentation",
                                                        "Provide a curb appeal consultation focused on exterior maintenance and first impressions",
                                                        "Provide referrals to third-party vendors (e.g., cleaners, handypeople, electricians, landscapers). Vendor fees billed separately. Referrals only \u2014 no endorsement or warranty is made",
                                                    ],
                                                    '📸 Photography, Video & Virtual Media' => [
                                                        "Provide professional property photography",
                                                        "Provide aerial (drone) photography (subject to FAA Part 107 compliance)",
                                                        "Provide a video walkthrough tour",
                                                        "Provide a 3D virtual tour",
                                                        "Provide virtual staging (digital enhancements only; no physical staging)",
                                                        "Provide digital photo enhancements",
                                                        "Create a basic schematic floor plan (non-certified; for marketing purposes only)",
                                                    ],
                                                    '🏘️ Showings & Access Coordination' => [
                                                        "Respond to Buyer inquiries and screen for general qualifications",
                                                        "Provide Non-Disclosure Agreement (NDA) templates for confidential showings or document access",
                                                        "Ensure proper notice is provided if the property is occupied",
                                                        "Install a real estate sign on the property",
                                                        "Install a lockbox for Agent access",
                                                        "Schedule and attend showings with prospective Buyers",
                                                        "Coordinate showings with Buyer\u2019s Agents",
                                                        "Collect and relay showing feedback to the Seller",
                                                    ],
                                                    '📉 Offer & Contract Management' => [
                                                        "Present all offers to the Seller and summarize key terms, pricing, and contingencies",
                                                        "Provide the Seller with the necessary disclosure forms required by state or local law",
                                                        "Negotiate deal structure, deposits, due diligence timelines, and Buyer contingencies",
                                                        "Draft and deliver counteroffers and manage revisions to the purchase agreement",
                                                        "Manage communication with the Buyer\u2019s Agent or Buyers",
                                                        "Assist with in-person or electronic contract signing, including e-signature setup and secure delivery of executed purchase agreements, addenda, and disclosures to all parties",
                                                        "Assist with inspection-related negotiations and Buyer requests for repairs",
                                                        "Monitor contract contingencies, including financing, lease audits, estoppel review, insurance, inspections, and environmental reports",
                                                        "Provide referrals to Attorneys, Title Companies, Escrow Officers, or 1031 Exchange Professionals. Referrals only \u2014 no endorsement or warranty is made",
                                                    ],
                                                    '🧾 Closing Coordination & Transaction Management' => [
                                                        "Review and organize due diligence documentation such as lease agreements, estoppel certificates, rent rolls, utility summaries, and operating expense breakdowns (as available)",
                                                        "Coordinate with the Buyer\u2019s Agent, Buyer\u2019s Lender, Title, Escrow, and/or Attorney to prepare for Closing",
                                                        "Review the Settlement Statement for accuracy and coordinate with relevant parties if corrections are needed",
                                                        "Confirm delivery of final executed documents, wire instructions, and Closing paperwork to all relevant parties",
                                                        "Schedule and confirm the Final Walkthrough",
                                                        "Schedule and confirm the Closing Appointment",
                                                    ],
                                                    '💡 Selling Strategy & Guidance' => [
                                                        "Provide a Comparative Market Analysis (CMA) with pricing insights based on recent income property sales, rental income trends, unit mix, and local investor activity",
                                                        "Assist in estimating Gross Rent Multiplier (GRM), Capitalization Rate (Cap Rate), or Price per Unit based on listing details and income property comparables",
                                                        "Provide general insight on likely Investor Buyer behavior, common value drivers, and investment strategies",
                                                        "Recommend adjustments to pricing or marketing strategy if the property is not receiving sufficient interest",
                                                        "Provide general guidance on lease transfers, rent proration, security deposits, and possession timelines",
                                                    ],
                                                ],
                                                'Commercial' => [
                                                    '📢 Property Marketing & Listing Promotion' => [
                                                        "List the property on the local Multiple Listing Service (MLS)",
                                                        "List the property on Crexi.com",
                                                        "List the property on LoopNet.com",
                                                        "Create a branded flyer summarizing the property\u2019s investment highlights and key selling points",
                                                        "Post the property on Craigslist under the \"Commercial for Sale\" category",
                                                        "Promote the listing on Facebook in Commercial or Investor Real Estate Groups",
                                                        "Share the listing on Instagram using posts, stories, or reels",
                                                        "Promote the listing on LinkedIn in Professional, Real Estate, or Commercial Investment Groups",
                                                        "Upload a TikTok video walkthrough of the property",
                                                        "Upload a YouTube video walkthrough of the property",
                                                        "Launch a mass email campaign promoting the listing",
                                                        "Distribute printed flyers or postcards in target geographic areas",
                                                        "Launch hyperlocal or interest-based digital ad campaigns promoting the listing",
                                                    ],
                                                    '🛠️ Listing Preparation & Asset Presentation' => [
                                                        "Conduct a property walkthrough and provide recommendations for listing readiness",
                                                        "Provide a visual consultation on interior layout, cleanliness, and overall presentation",
                                                        "Provide a curb appeal consultation focused on exterior appearance and first impressions",
                                                        "Provide referrals to third-party vendors such as cleaners, handypeople, electricians, and landscapers (vendor fees billed separately; referrals only \u2014 no endorsement or warranty is made)",
                                                        "Compile essential marketing materials such as rent rolls, lease summaries, financial statements, and operating data (as available)",
                                                        "Organize zoning documentation, surveys, and public record reports (as available)",
                                                    ],
                                                    '📸 Photography, Video & Virtual Media' => [
                                                        "Provide professional property photography",
                                                        "Provide aerial (drone) photography (subject to FAA Part 107 compliance)",
                                                        "Provide a video walkthrough tour",
                                                        "Provide a 3D virtual tour",
                                                        "Provide virtual staging (digital enhancements only; no physical staging)",
                                                        "Provide digital photo enhancements",
                                                        "Create a basic schematic floor plan (non-certified; for marketing purposes only)",
                                                    ],
                                                    '🏢 Showings & Access Coordination' => [
                                                        "Respond to Buyer inquiries and screen for general qualifications",
                                                        "Provide Non-Disclosure Agreement (NDA) templates for access to confidential documents or showings",
                                                        "Ensure proper notice is provided if the property is occupied",
                                                        "Install a real estate sign on the property",
                                                        "Install a lockbox for Agent access",
                                                        "Schedule and attend showings with prospective Buyers",
                                                        "Coordinate showings with Buyer\u2019s Agents",
                                                        "Collect and relay showing feedback to the Seller",
                                                    ],
                                                    '📉 Offer & Contract Management' => [
                                                        "Present all offers to the Seller and summarize key terms, pricing, and contingencies",
                                                        "Provide the Seller with the necessary disclosure forms required by state or local law",
                                                        "Coordinate Letter of Intent (LOI) submissions, counteroffers, and contract revisions",
                                                        "Negotiate deal terms such as pricing, deposit structure, closing timelines, and due diligence periods",
                                                        "Manage communication with the Buyer\u2019s Agent or Buyer",
                                                        "Assist with in-person or electronic contract signing, including e-signature setup and secure delivery of executed purchase agreements, addenda, and disclosures to all parties",
                                                        "Assist with inspection-related negotiations and Buyer requests for repairs or credits",
                                                        "Monitor contract contingencies, including financing, estoppel review, lease audits, and environmental reports",
                                                        "Provide referrals to Attorneys, Title Companies, Escrow Officers, or 1031 Exchange Professionals (referrals only \u2014 no endorsement or warranty is made)",
                                                    ],
                                                    '🧾 Closing Coordination & Transaction Management' => [
                                                        "Coordinate inspections, appraisals, and estoppel certificate delivery with the Buyer\u2019s Agent or Buyer, as applicable",
                                                        "Provide due diligence documentation such as lease agreements, estoppel certificates, rent rolls, utility summaries, and operating expense breakdowns (as available)",
                                                        "Coordinate with the Buyer\u2019s Agent, Lender, Title, Escrow, and/or Attorney to prepare for Closing",
                                                        "Review the Settlement Statement and coordinate with all parties if corrections are needed",
                                                        "Confirm delivery of final executed documents, wire instructions, and Closing paperwork to all relevant parties",
                                                        "Schedule and confirm the Final Walkthrough",
                                                        "Schedule and confirm the Closing Appointment",
                                                    ],
                                                    '💡 Selling Strategy & Guidance' => [
                                                        "Provide a Comparative Market Analysis (CMA) with pricing insights based on recent commercial property sales, rental income trends, market cap rates, and investor activity",
                                                        "Assist in estimating Capitalization Rate (Cap Rate), Price per Square Foot, or Gross Rent Multiplier (GRM) based on listing details and commercial comparables",
                                                        "Provide general insight on likely Buyer types (e.g., Owner-User, Investor, 1031 Exchange Buyer), common value drivers, and investment strategies",
                                                        "Recommend adjustments to pricing or marketing strategy if the property is not receiving sufficient interest",
                                                        "Provide general guidance on lease structures, expense ratios, and Tenant impacts",
                                                    ],
                                                ],
                                                'Business' => [
                                                    '📢 Business Marketing & Listing Promotion' => [
                                                        "List the Business Opportunity on the local Multiple Listing Service (MLS)",
                                                        "List the Business Opportunity on Crexi.com",
                                                        "List the Business Opportunity on LoopNet.com",
                                                        "List the Business Opportunity on BizBuySell.com",
                                                        "List the Business Opportunity on BizQuest.com",
                                                        "List the Business Opportunity on BusinessesForSale.com",
                                                        "Create a branded flyer summarizing the Business\u2019s key features (e.g., industry, cash flow, assets)",
                                                        "Post the Business Opportunity on Craigslist under the \"Business for Sale\" category",
                                                        "Promote the listing on Facebook in Business Buyer, Franchise, or Investor Groups",
                                                        "Share the listing on Instagram using posts, stories, or reels",
                                                        "Promote the listing on LinkedIn in Business Acquisition, Startup, or Investor Groups",
                                                        "Upload a TikTok video summarizing the Business Opportunity",
                                                        "Upload a YouTube video summarizing the Business Opportunity",
                                                        "Launch a mass email campaign promoting the listing",
                                                        "Distribute printed flyers or postcards in target geographic areas",
                                                        "Launch hyperlocal or interest-based digital ad campaigns promoting the listing",
                                                    ],
                                                    '🛠️ Listing Preparation & Confidential Marketing' => [
                                                        "Conduct a preliminary Seller consultation to gather details about the Business\u2019s operations, assets, and goals",
                                                        "Provide a business sale checklist to collect financials, licenses, lease terms, and key operational details",
                                                        "Assist with preparing a non-confidential teaser or executive summary for marketing purposes",
                                                        "Organize internal documentation such as profit and loss statements, balance sheets, FF&E summaries, inventory lists, and staffing overviews (as available)",
                                                        "Refer third-party professionals such as valuation experts, accountants, or financial consultants, if requested (referrals only \u2014 no endorsement or warranty is made)",
                                                        "Compile essential marketing materials including business overviews, location descriptions, asset lists, and financial summaries",
                                                    ],
                                                    '📸 Photography, Video & Virtual Media' => [
                                                        "Provide professional property photography",
                                                        "Provide aerial (drone) photography (subject to FAA Part 107 compliance)",
                                                        "Provide a video walkthrough tour",
                                                        "Provide a 3D virtual tour",
                                                        "Provide virtual staging (digital enhancements only; no physical staging)",
                                                        "Provide digital photo enhancements",
                                                        "Create a basic schematic floor plan (non-certified; for marketing purposes only)",
                                                    ],
                                                    '🏢 Showings & Access Coordination' => [
                                                        "Respond to Buyer inquiries and screen for general qualifications",
                                                        "Provide Non-Disclosure Agreement (NDA) templates for confidential showings or document access",
                                                        "Ensure proper notice is provided if the property or business premises is occupied",
                                                        "Install a real estate sign on the property",
                                                        "Install a lockbox for Agent access",
                                                        "Schedule and attend showings with prospective Buyers",
                                                        "Coordinate showings with Buyer\u2019s Agents",
                                                        "Coordinate directly with Tenant(s) or business staff to arrange access for showings",
                                                        "Collect and relay showing feedback to the Seller",
                                                    ],
                                                    '📉 Offer & Contract Management' => [
                                                        "Present all Letters of Intent (LOIs) or formal offers to the Seller and summarize key deal terms",
                                                        "Provide the Seller with the necessary disclosure forms required by state or local law",
                                                        "Negotiate deal terms such as purchase price, deposit structure, contingencies, transition period, and asset allocation",
                                                        "Coordinate revisions, counteroffers, and ongoing communication with the Buyer or their representatives",
                                                        "Manage communication with the Buyer\u2019s Broker or Buyer",
                                                        "Assist with in-person or electronic contract signing, including e-signature setup and secure delivery of executed purchase agreements, addenda, and disclosures to all parties",
                                                        "Monitor contract contingencies and organize delivery of due diligence materials such as leases, vendor contracts, tax filings, and financial statements",
                                                        "Refer the Seller to legal counsel for formal contract drafting and execution (referrals only \u2014 no legal advice provided)",
                                                        "Provide referrals to Business Attorneys, Escrow Officers, or Business Transfer Specialists (referrals only \u2014 no endorsement or warranty is made)",
                                                    ],
                                                    '📃 Closing Coordination & Transaction Management' => [
                                                        "Coordinate Buyer inspections, management interviews, and site visits as applicable",
                                                        "Provide a transaction checklist and track key deadlines throughout the escrow period",
                                                        "Coordinate with the Buyer\u2019s Attorney, Escrow Officer, or designated Closing Facilitator",
                                                        "Review the Settlement Statement and coordinate corrections with relevant parties",
                                                        "Confirm delivery of final executed documents, wire instructions, and Closing paperwork to all relevant parties",
                                                        "Schedule and confirm the Final Walkthrough",
                                                        "Schedule and confirm the Closing Appointment",
                                                    ],
                                                    '💡 Selling Strategy & Guidance' => [
                                                        "Provide a business market overview with insights from recent comparable listings",
                                                        "Identify likely Buyer types (e.g., Owner-Operator, Investor, Franchisee) and discuss common deal structures (e.g., asset sale, stock sale)",
                                                        "Provide general insight on common value drivers such as cash flow, recurring revenue, transferable licenses, and key staff retention",
                                                        "Provide general guidance on operational transition timelines, staff notifications, lease assignments, and post-sale training periods",
                                                        "Provide referrals to business valuation, accounting, or legal professionals (referrals only \u2014 no endorsement or warranty is made)",
                                                    ],
                                                ],
                                                'Vacant Land' => [
                                                    '📢 Property Marketing & Listing Promotion' => [
                                                        "List the property in the local Multiple Listing Service (MLS)",
                                                        "Syndicate the listing to third-party platforms (e.g., Zillow.com, Realtor.com, Trulia.com, Homes.com)",
                                                        "List the property on LandWatch.com",
                                                        "List the property on Land.com",
                                                        "List the property on LandAndFarm.com",
                                                        "Create a branded flyer highlighting lot features, zoning, and potential use",
                                                        "Post the listing on Facebook Marketplace",
                                                        "Post the listing on Craigslist under the \"Land for Sale\" category",
                                                        "Share the listing on Nextdoor in Neighborhood or Rural Groups",
                                                        "Promote the listing on Facebook in Land Buyers, Developers, or Homesteader Groups",
                                                        "Share the listing on Instagram using posts, stories, or reels",
                                                        "Promote the listing on LinkedIn in Land Acquisition or Investment Groups",
                                                        "Upload a TikTok video summarizing the land opportunity",
                                                        "Upload a YouTube video summarizing the land opportunity (e.g., drone tour, narrated overview)",
                                                        "Launch a mass email campaign promoting the listing Distribute printed flyers or postcards in target geographic areas",
                                                        "Launch hyperlocal or interest-based digital ad campaigns promoting the listing",
                                                    ],
                                                    '🛠️ Listing Preparation & Research' => [
                                                        "Provide a checklist to gather parcel data (e.g., APN, lot size, zoning, utilities, and access)",
                                                        "Assist with collecting public records, flood zone data, and land use information (as available)",
                                                        "Provide referrals to surveyors, soil testers, or land service professionals (referrals only \u2014 no endorsement or warranty is made)",
                                                    ],
                                                    '📸 Photography, Video & Virtual Media' => [
                                                        "Provide professional property photography",
                                                        "Provide aerial (drone) photography (subject to FAA Part 107 compliance)",
                                                        "Provide a video overview or narrated walkthrough",
                                                        "Provide a 3D virtual tour (if applicable)",
                                                        "Provide digital enhancements to media assets",
                                                        "Provide a parcel map, topographical image, or plot plan (non-certified; for marketing purposes only)",
                                                    ],
                                                    '🏡 Showings & Access Coordination' => [
                                                        "Install a real estate sign on the property",
                                                        "Schedule and attend showings with prospective Buyers",
                                                        "Coordinate showings with Buyer\u2019s Agents",
                                                        "Collect and relay showing feedback to the Seller",
                                                    ],
                                                    '📉 Offer & Contract Management' => [
                                                        "Present all offers to the Seller and summarize key terms, pricing, and contingencies",
                                                        "Provide the Seller with the necessary disclosure forms required by state or local law",
                                                        "Negotiate price, due diligence timelines, and closing terms",
                                                        "Draft and deliver counteroffers and manage revisions to the purchase agreement",
                                                        "Manage communication with the Buyer\u2019s Agent or Buyer",
                                                        "Assist with in-person or electronic contract signing, including e-signature setup and secure delivery of executed purchase agreements, addenda, and disclosures to all parties",
                                                        "Monitor contract contingencies, including survey, zoning verification, financing, and environmental reviews",
                                                        "Provide referrals to Attorneys, Title Companies, Escrow Officers, or Land Use Professionals (referrals only \u2014 no endorsement or warranty is made)",
                                                    ],
                                                    '📃 Closing Coordination & Transaction Management' => [
                                                        "Coordinate surveys, site visits, or environmental access with the Buyer or Buyer\u2019s Agent, as applicable",
                                                        "Coordinate with Title, Escrow, and/or Attorney to prepare for Closing",
                                                        "Review the Settlement Statement and coordinate with all parties if corrections are needed",
                                                        "Confirm delivery of final executed documents, wire instructions, and Closing paperwork to all relevant parties",
                                                        "Schedule and confirm the Final Walkthrough",
                                                        "Schedule and confirm the Closing Appointment",
                                                    ],
                                                    '💡 Selling Strategy & Guidance' => [
                                                        "Provide a Comparative Market Analysis (CMA) with pricing recommendations based on recent land sales, zoning categories, and location-based trends",
                                                        "Provide general insight on permitted uses, utility access, parcel features, and Buyer demand in the area",
                                                        "Recommend adjustments to pricing or marketing strategy if the land is not receiving sufficient interest",
                                                        "Provide general guidance on Seller obligations, disclosure requirements, and listing preparation",
                                                    ],
                                                ],
                                            ];

                                            $propConfig = $sellerServicesConfig[$bidPropKey] ?? $sellerServicesConfig['Residential'];

                                            // Build flat normalized config map for unmapped detection
                                            $configFlatNorm = [];
                                            foreach ($propConfig as $catSvcs) {
                                                foreach ($catSvcs as $s) {
                                                    $configFlatNorm[$normalizeStr($s)] = true;
                                                }
                                            }

                                            // Find unmapped services
                                            $unmappedSvcs = [];
                                            foreach ($services as $svc) {
                                                $displaySvc = function_exists('normalize_service_text') ? normalize_service_text($svc) : $svc;
                                                if (!isset($configFlatNorm[$normalizeStr($svc)]) && !isset($configFlatNorm[$normalizeStr($displaySvc)])) {
                                                    $unmappedSvcs[] = $displaySvc;
                                                }
                                            }

                                            $rawOtherSvcsModal = $allBidMeta['other_services'] ?? null;
                                            $otherSvcsModal = is_string($rawOtherSvcsModal)
                                                ? json_decode($rawOtherSvcsModal, true) ?? []
                                                : ($rawOtherSvcsModal ?? []);
                                            $otherSvcsModal = array_filter((array)$otherSvcsModal, fn($s) => is_string($s) && !empty(trim($s)));

                                            $hasAnyServices = !empty($services) || !empty($otherSvcsModal) || !empty($unmappedSvcs);
                                        @endphp

                                        @php
                                        // === Seller Offered Services - Inline Extra/Missing Badge Pattern ===
                                        // Build baseline service list from the active baseline (counter or original auction)
                                        $sellerBsRaw = $baselineData['services'] ?? [];
                                        if (is_string($sellerBsRaw)) { $sellerBsRaw = json_decode($sellerBsRaw, true) ?? []; }
                                        $sellerBsRaw = array_values(array_filter((array)$sellerBsRaw, fn($s) => is_string($s) && !empty(trim($s)) && $s !== 'Other'));

                                        $sellerBsOtherRaw = $baselineData['other_services'] ?? [];
                                        if (is_string($sellerBsOtherRaw)) { $sellerBsOtherRaw = json_decode($sellerBsOtherRaw, true) ?? []; }
                                        $sellerBsOtherRaw = array_values(array_filter((array)$sellerBsOtherRaw, fn($s) => is_string($s) && !empty(trim($s))));

                                        $sellerBaselineServices = array_merge($sellerBsRaw, $sellerBsOtherRaw);

                                        // Service style/badge variables
                                        $svcAddedStyle  = 'background-color: #d4edda; padding: 2px 6px; border-radius: 4px; border-left: 3px solid #28a745;';
                                        $svcAddedBadge  = '<span class="badge bg-success ms-2" style="font-size: 0.65rem; vertical-align: middle;">Extra Service Offered</span>';
                                        $svcMissingStyle = 'background-color: #ffe6e6; padding: 2px 6px; border-radius: 4px; border-left: 3px solid #dc3545; text-decoration: line-through; color: #721c24;';
                                        $svcMissingBadge = '<span class="badge bg-danger ms-2" style="font-size: 0.65rem; vertical-align: middle;">Not Offered by Agent</span>';

                                        // Build full normalized baseline set (includes custom/other services)
                                        $sellerNormFn = fn($s) => \App\Helpers\SellerBidMatchScoreHelper::normalizeService((string)$s);
                                        $sellerBaselineNormFull = array_unique(array_map($sellerNormFn, $sellerBaselineServices));
                                        $sellerBidNormFull = array_unique(array_map(
                                            $sellerNormFn,
                                            array_merge(array_values((array)$services), array_values((array)$otherSvcsModal))
                                        ));

                                        $checkSellerSvcInBaseline = function($svc) use ($sellerBaselineNormFull, $sellerNormFn) {
                                            return in_array($sellerNormFn($svc), $sellerBaselineNormFull, true);
                                        };

                                        $checkSellerSvcInBid = function($svc) use ($sellerBidNormFull, $sellerNormFn) {
                                            return in_array($sellerNormFn($svc), $sellerBidNormFull, true);
                                        };

                                        // Compute missing services: in baseline but not in bid
                                        $sellerMissingServices = [];
                                        foreach ($sellerBaselineServices as $bSvc) {
                                            if (!$checkSellerSvcInBid($bSvc)) {
                                                $sellerMissingServices[] = $bSvc;
                                            }
                                        }
                                        @endphp

                                        @if ($hasAnyServices)
                                        <div class="mb-5">
                                            <h6 class="section-header">
                                                <i class="fa fa-clipboard-list me-2"></i>Offered Services
                                            </h6>

                                            @foreach ($propConfig as $category => $catSvcs)
                                                @php
                                                    $selectedInCat = array_filter($catSvcs, fn($s) => isset($selectedNormalized[$normalizeStr($s)]));
                                                @endphp
                                                @if (count($selectedInCat) > 0)
                                                <div class="mb-3">
                                                    <div class="fw-bold" style="color: #34465c; font-size: 0.95rem;">{{ $category }}</div>
                                                    <ul class="mb-0" style="margin-top: 0.25rem; padding-left: 1.5rem; list-style: disc;">
                                                        @foreach ($catSvcs as $service)
                                                            @if (isset($selectedNormalized[$normalizeStr($service)]))
                                                                @php
                                                                    $sellerDisplaySvc = $selectedNormalized[$normalizeStr($service)];
                                                                    $sellerSvcInBaseline = $checkSellerSvcInBaseline($sellerDisplaySvc);
                                                                @endphp
                                                                <li style="font-size: 0.9rem; margin-bottom: 4px; {{ !$sellerSvcInBaseline ? $svcAddedStyle : '' }}">{{ $sellerDisplaySvc }}{!! !$sellerSvcInBaseline ? $svcAddedBadge : '' !!}</li>
                                                                @if (in_array($normalizeStr($service), ['provide digital photo enhancements', 'provide digital enhancements to media assets']))
                                                                    @php
                                                                        $modalPhotoEnhRaw = $allBidMeta['photo_enhancements'] ?? [];
                                                                        $modalPhotoEnhancements = is_string($modalPhotoEnhRaw) ? (json_decode($modalPhotoEnhRaw, true) ?: []) : (is_array($modalPhotoEnhRaw) ? $modalPhotoEnhRaw : []);
                                                                        $modalCustomEnh = $allBidMeta['custom_enhancement'] ?? '';
                                                                        $modalEnhOrder = ['Basic edits (brightness, contrast, cropping)', 'Twilight conversion (convert daytime photo to sunset look)', 'Object removal (e.g., cars, trash cans, furniture, etc.)', 'Virtual twilight photography', 'Color correction or sky replacement', 'Other'];
                                                                    @endphp
                                                                    @if (!empty($modalPhotoEnhancements))
                                                                        <ul style="padding-left: 1.5rem; margin: 4px 0; list-style: disc;">
                                                                            @foreach ($modalEnhOrder as $enh)
                                                                                @if (in_array($enh, $modalPhotoEnhancements))
                                                                                    @if ($enh === 'Other' && !empty($modalCustomEnh))
                                                                                        <li style="font-size: 0.85rem;">{{ $modalCustomEnh }}</li>
                                                                                    @elseif ($enh !== 'Other')
                                                                                        <li style="font-size: 0.85rem;">{{ $enh }}</li>
                                                                                    @endif
                                                                                @endif
                                                                            @endforeach
                                                                        </ul>
                                                                    @endif
                                                                @endif
                                                            @endif
                                                        @endforeach
                                                    </ul>
                                                </div>
                                                @endif
                                            @endforeach

                                            @if (!empty($unmappedSvcs))
                                            <div class="mb-3">
                                                <div class="fw-bold" style="color: #34465c; font-size: 0.95rem;">✍️ Additional Services</div>
                                                <ul class="mb-0" style="margin-top: 0.25rem; padding-left: 1.5rem; list-style: disc;">
                                                    @foreach ($unmappedSvcs as $unmappedSvc)
                                                        @php $sellerUnmappedInBaseline = $checkSellerSvcInBaseline($unmappedSvc); @endphp
                                                        <li style="font-size: 0.9rem; margin-bottom: 4px; {{ !$sellerUnmappedInBaseline ? $svcAddedStyle : '' }}">{{ $unmappedSvc }}{!! !$sellerUnmappedInBaseline ? $svcAddedBadge : '' !!}</li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                            @endif

                                            @if (!empty($otherSvcsModal))
                                            <div class="mb-3">
                                                <div class="fw-bold" style="color: #34465c; font-size: 0.95rem;">✍️ Additional Services</div>
                                                <ul class="mb-0" style="margin-top: 0.25rem; padding-left: 1.5rem; list-style: disc;">
                                                    @foreach ($otherSvcsModal as $otherService)
                                                        @php $sellerOtherInBaseline = $checkSellerSvcInBaseline($otherService); @endphp
                                                        <li style="font-size: 0.9rem; margin-bottom: 4px; {{ !$sellerOtherInBaseline ? $svcAddedStyle : '' }}">{{ $otherService }}{!! !$sellerOtherInBaseline ? $svcAddedBadge : '' !!}</li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                            @endif

                                            {{-- Show services that were in baseline but agent did not include --}}
                                            @if (!empty($sellerMissingServices))
                                            <div class="mt-4 p-3" style="background-color: #ffe6e6; border-radius: 8px; border: 1px solid #dc3545;">
                                                <div class="fw-bold mb-2" style="color: #721c24; font-size: 0.95rem;">
                                                    <i class="fa fa-times-circle me-2"></i>Services Requested But Agent Did Not Include ({{ count($sellerMissingServices) }})
                                                </div>
                                                <ul class="mb-0" style="padding-left: 1.5rem; list-style: disc;">
                                                    @foreach ($sellerMissingServices as $sellerMissingSvc)
                                                        <li style="font-size: 0.9rem; margin-bottom: 4px; {{ $svcMissingStyle }}">{{ $sellerMissingSvc }}{!! $svcMissingBadge !!}</li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                            @endif
                                        </div>
                                        @endif

                                        <!-- 4. Agent Presentation & Promotional Materials -->
                                        @if (data_get($bid, 'get.presentation_link') ||
                                             data_get($bid, 'get.video_upload') ||
                                             data_get($bid, 'get.business_card_link') ||
                                             data_get($bid, 'get.business_card') ||
                                             data_get($bid, 'get.promo_materials'))
                                        <div class="mb-5">
                                            <h6 class="section-header">
                                                <i class="fa fa-chart-line me-2"></i>Agent Presentation &amp; Promotional Materials
                                            </h6>

                                            <!-- Virtual Presentation -->
                                            @if (data_get($bid, 'get.presentation_link') || data_get($bid, 'get.video_upload'))
                                            <div class="mb-4">
                                                <div class="fw-semibold mb-2" style="color: #049399;">Virtual Agent Presentation</div>
                                                @if (data_get($bid, 'get.presentation_link'))
                                                <div class="mb-2">
                                                    @php
                                                        $presentationLink = data_get($bid, 'get.presentation_link');
                                                        if (!empty($presentationLink) && !str_starts_with($presentationLink, 'http://') && !str_starts_with($presentationLink, 'https://')) {
                                                            $presentationLink = 'https://' . $presentationLink;
                                                        }
                                                    @endphp
                                                    <a href="{{ $presentationLink }}" target="_blank" class="text-primary text-decoration-none">
                                                        <i class="fa fa-external-link-alt me-1"></i> Watch Presentation
                                                    </a>
                                                </div>
                                                @endif
                                                @if (data_get($bid, 'get.video_upload'))
                                                <div class="mb-2">
                                                    <div class="fw-medium mb-1" style="color: #049399;">Uploaded Video:</div>
                                                    @if (is_string(data_get($bid, 'get.video_upload')))
                                                    <video controls style="width: 100%; max-width: 400px; border-radius: 6px; background: #000;">
                                                        <source src="{{ asset('storage/' . data_get($bid, 'get.video_upload')) }}" type="video/mp4">
                                                        Your browser does not support the video tag.
                                                    </video>
                                                    @else
                                                    <div class="text-muted"><i class="fa fa-video me-1"></i> Video file uploaded</div>
                                                    @endif
                                                </div>
                                                @endif
                                            </div>
                                            @endif

                                            <!-- Business Card -->
                                            @if (data_get($bid, 'get.business_card_link') || data_get($bid, 'get.business_card'))
                                            <div class="mb-4">
                                                <div class="fw-semibold mb-2" style="color: #049399;">Business Card:</div>
                                                @if (data_get($bid, 'get.business_card_link'))
                                                <div class="mb-3">
                                                    @php
                                                        $businessCardLink = data_get($bid, 'get.business_card_link');
                                                        if (!empty($businessCardLink) && !str_starts_with($businessCardLink, 'http://') && !str_starts_with($businessCardLink, 'https://')) {
                                                            $businessCardLink = 'https://' . $businessCardLink;
                                                        }
                                                    @endphp
                                                    <a href="{{ $businessCardLink }}" target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary btn-sm">
                                                        <i class="fa fa-external-link-alt me-1"></i> View Business Card (Link)
                                                    </a>
                                                </div>
                                                @endif
                                                @if (data_get($bid, 'get.business_card'))
                                                <div class="mb-2">
                                                    @php
                                                        $rawBusinessCard = data_get($bid, 'get.business_card');
                                                        if (is_object($rawBusinessCard)) { $rawBusinessCard = (array) $rawBusinessCard; }
                                                        if (is_array($rawBusinessCard)) { $rawBusinessCard = $rawBusinessCard['path'] ?? $rawBusinessCard['file'] ?? $rawBusinessCard['url'] ?? (reset($rawBusinessCard) ?: null); }
                                                        $normalizedBusinessCard = is_string($rawBusinessCard) ? $rawBusinessCard : null;
                                                    @endphp
                                                    @if ($normalizedBusinessCard)
                                                    @php
                                                        $businessCardPath = $normalizedBusinessCard;
                                                        $businessCardExt  = strtolower(pathinfo($businessCardPath, PATHINFO_EXTENSION));
                                                        $businessCardUrl  = asset('storage/' . $businessCardPath);
                                                    @endphp
                                                    @if (in_array($businessCardExt, ['jpg', 'jpeg', 'png', 'gif', 'webp']))
                                                    <div class="business-card-preview mb-2">
                                                        <a href="{{ $businessCardUrl }}" target="_blank" rel="noopener noreferrer">
                                                            <img src="{{ $businessCardUrl }}" style="max-width: 450px; width: 100%; height: auto; border-radius: 8px; border: 2px solid #e0e0e0;" alt="Business Card" class="img-fluid">
                                                        </a>
                                                    </div>
                                                    <div class="d-flex gap-2 mt-2">
                                                        <a href="{{ $businessCardUrl }}" target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary btn-sm"><i class="fa fa-expand me-1"></i> View Full Size</a>
                                                        <a href="{{ $businessCardUrl }}" download class="btn btn-outline-success btn-sm"><i class="fa fa-download me-1"></i> Download</a>
                                                    </div>
                                                    @else
                                                    <div class="d-flex align-items-center p-3 border rounded bg-light">
                                                        <i class="fa fa-file-alt fa-2x text-muted me-3"></i>
                                                        <div class="flex-grow-1">
                                                            <div class="fw-medium">Business Card File</div>
                                                            <small class="text-muted">{{ strtoupper($businessCardExt) }} file</small>
                                                        </div>
                                                        <a href="{{ $businessCardUrl }}" download class="btn btn-outline-primary btn-sm"><i class="fa fa-download me-1"></i> Download</a>
                                                    </div>
                                                    @endif
                                                    @else
                                                    <div class="text-muted"><i class="fa fa-id-card me-1"></i> Business card uploaded</div>
                                                    @endif
                                                </div>
                                                @endif
                                            </div>
                                            @endif

                                            <!-- Marketing Materials -->
                                            @if (data_get($bid, 'get.promo_materials'))
                                            @php
                                                $hasAnyMaterials = false;
                                                $promoMaterialsRaw = data_get($bid, 'get.promo_materials', []);
                                                if (is_string($promoMaterialsRaw)) { $promoMaterialsRaw = json_decode($promoMaterialsRaw, true) ?? []; }
                                                $promoMaterialsNormalized = [];
                                                if (is_array($promoMaterialsRaw) || is_object($promoMaterialsRaw)) {
                                                    foreach ($promoMaterialsRaw as $m) {
                                                        $mArr = is_object($m) ? (array) $m : (is_array($m) ? $m : []);
                                                        $promoMaterialsNormalized[] = $mArr;
                                                        if (!empty($mArr['type']) || !empty($mArr['link']) || !empty($mArr['files'])) {
                                                            $hasAnyMaterials = true;
                                                        }
                                                    }
                                                }
                                            @endphp
                                            <div>
                                                <div class="fw-semibold mb-2" style="color: #049399;">Marketing Materials:</div>
                                                @if ($hasAnyMaterials)
                                                @foreach ($promoMaterialsNormalized as $index => $material)
                                                @php
                                                    $matType  = data_get($material, 'type', '');
                                                    $matOther = data_get($material, 'other', '');
                                                    $matLink  = data_get($material, 'link', '');
                                                    $matFiles = data_get($material, 'files', []);
                                                    if (is_object($matFiles)) { $matFiles = (array) $matFiles; }
                                                @endphp
                                                @if (!empty($matType) || !empty($matLink) || !empty($matFiles))
                                                <div class="mb-3 p-3 border rounded bg-light">
                                                    @if (!empty($matType))
                                                    <div class="fw-medium mb-2" style="color: #049399; font-size: 1rem;">
                                                        <i class="fa fa-folder-open me-1"></i>
                                                        {{ $matType }}@if ($matType === 'Other' && !empty($matOther)) - {{ $matOther }}@endif
                                                    </div>
                                                    @endif
                                                    @if (!empty($matLink))
                                                    <div class="mb-2">
                                                        @php
                                                            $matLinkFull = (!str_starts_with($matLink, 'http://') && !str_starts_with($matLink, 'https://')) ? 'https://' . $matLink : $matLink;
                                                        @endphp
                                                        <a href="{{ $matLinkFull }}" target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary btn-sm">
                                                            <i class="fa fa-external-link-alt me-1"></i> Open Link
                                                        </a>
                                                    </div>
                                                    @endif
                                                    @if (!empty($matFiles) && is_array($matFiles))
                                                    <div class="mb-2">
                                                        <div class="fw-medium mb-2" style="color: #34465c; font-size: 0.9rem;">Uploaded Files:</div>
                                                        <div class="row g-2">
                                                            @foreach ($matFiles as $matFile)
                                                            @php
                                                                if (is_object($matFile)) { $matFile = (array) $matFile; }
                                                                $mfPath = is_array($matFile) ? ($matFile['path'] ?? $matFile['file'] ?? $matFile['url'] ?? (reset($matFile) ?: '')) : (is_string($matFile) ? $matFile : '');
                                                                $mfExt  = strtolower(pathinfo($mfPath, PATHINFO_EXTENSION));
                                                                $mfName = basename($mfPath);
                                                                $mfUrl  = $mfPath ? asset('storage/' . $mfPath) : '';
                                                                $mfIsImage = in_array($mfExt, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                                                            @endphp
                                                            @if ($mfUrl)
                                                            <div class="col-md-6 mb-2">
                                                                <div class="border rounded p-2 bg-white d-flex align-items-center">
                                                                    @if ($mfIsImage)
                                                                    <a href="{{ $mfUrl }}" target="_blank" rel="noopener noreferrer">
                                                                        <img src="{{ $mfUrl }}" style="width: 60px; height: 60px; object-fit: cover; border-radius: 4px; margin-right: 10px;" alt="Marketing Material">
                                                                    </a>
                                                                    @else
                                                                    <div class="bg-light rounded d-flex align-items-center justify-content-center me-2" style="width: 60px; height: 60px;">
                                                                        <i class="fa fa-file fa-lg text-muted"></i>
                                                                    </div>
                                                                    @endif
                                                                    <div class="flex-grow-1 overflow-hidden">
                                                                        <div class="small text-truncate fw-medium">{{ $mfName }}</div>
                                                                        <small class="text-muted">{{ strtoupper($mfExt) }} file</small>
                                                                    </div>
                                                                    <div class="d-flex gap-1 ms-2">
                                                                        <a href="{{ $mfUrl }}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary" title="View">
                                                                            <i class="fa fa-eye"></i>
                                                                        </a>
                                                                        <a href="{{ $mfUrl }}" download class="btn btn-sm btn-outline-success" title="Download">
                                                                            <i class="fa fa-download"></i>
                                                                        </a>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            @endif
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                    @endif
                                                </div>
                                                @endif
                                                @endforeach
                                                @else
                                                <div class="text-muted small">No marketing materials uploaded.</div>
                                                @endif
                                            </div>
                                            @endif
                                        </div>
                                        @endif

                                        <!-- 5. Agent Credentials and Contact Information -->
                                        @if ($isListingOwner || $isBidOwner)
                                        <div class="mb-5">
                                            <h6 class="section-header">
                                                <i class="fa fa-address-card me-2"></i>Agent Credentials and Contact Information
                                            </h6>
                                            <div class="row">
                                                @if (data_get($bid, 'get.first_name'))
                                                <div class="col-md-6 mb-2">
                                                    <div class="fw-semibold" style="color: #049399;">First Name</div>
                                                    <div class="text-muted">{{ data_get($bid, 'get.first_name') }}</div>
                                                </div>
                                                @endif
                                                @if (data_get($bid, 'get.last_name'))
                                                <div class="col-md-6 mb-2">
                                                    <div class="fw-semibold" style="color: #049399;">Last Name</div>
                                                    <div class="text-muted">{{ data_get($bid, 'get.last_name') }}</div>
                                                </div>
                                                @endif
                                                @if (data_get($bid, 'get.phone'))
                                                <div class="col-md-6 mb-2">
                                                    <div class="fw-semibold" style="color: #049399;">Phone Number</div>
                                                    <div class="text-muted">{{ data_get($bid, 'get.phone') }}</div>
                                                </div>
                                                @endif
                                                @if (data_get($bid, 'get.email'))
                                                <div class="col-md-6 mb-2">
                                                    <div class="fw-semibold" style="color: #049399;">Email</div>
                                                    <div class="text-muted">{{ data_get($bid, 'get.email') }}</div>
                                                </div>
                                                @endif
                                                @if (data_get($bid, 'get.brokerage'))
                                                <div class="col-12 mb-2">
                                                    <div class="fw-semibold" style="color: #049399;">Brokerage</div>
                                                    <div class="text-muted">{{ data_get($bid, 'get.brokerage') }}</div>
                                                </div>
                                                @endif
                                                @if (data_get($bid, 'get.license_no'))
                                                <div class="col-md-6 mb-2">
                                                    <div class="fw-semibold" style="color: #049399;">Real Estate License #</div>
                                                    <div class="text-muted">{{ data_get($bid, 'get.license_no') }}</div>
                                                </div>
                                                @endif
                                                @if (data_get($bid, 'get.nar_id'))
                                                <div class="col-md-6 mb-2">
                                                    <div class="fw-semibold" style="color: #049399;">NAR Member ID</div>
                                                    <div class="text-muted">{{ data_get($bid, 'get.nar_id') }}</div>
                                                </div>
                                                @endif
                                            </div>
                                        </div>
                                        @endif

