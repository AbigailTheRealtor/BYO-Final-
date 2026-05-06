
                                        {{-- ========== MATCH SCORE PANEL ========== --}}
                                        @if ($hasAnyBaseline)
                                        <div class="match-score-panel mb-4 p-3" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 10px; border: 1px solid #dee2e6;">
                                            @if ($showDualScore && $originalScore && $latestCounterScore)
                                            {{-- DUAL SCORE: Original Match + Latest Counter Match --}}
                                            <h6 class="mb-2" style="color: #1a3a5c; font-weight: 600;">
                                                <i class="fa-solid fa-chart-pie me-2"></i>Match Summary
                                            </h6>
                                            <p class="small text-muted mb-3">
                                                <i class="fa-solid fa-circle-info me-1"></i>
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
                                                    <i class="fa-solid fa-chart-pie me-2"></i>Match Score
                                                </h6>
                                                <span class="badge" style="background: {{ $getScoreColor($totalScore) }}; font-size: 1.1rem; padding: 8px 16px;">
                                                    {{ $totalScore }}% Match
                                                </span>
                                            </div>
                                            <p class="small text-muted mb-3">
                                                <i class="fa-solid fa-circle-info me-1"></i>Match Score compares this bid only to the Seller's original request. Added services or added terms are shown for transparency but do not increase the score.<br>
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
                                            <i class="fa-solid fa-circle-info me-1"></i>No match data available for this listing.
                                        </div>
                                        @endif
                                        {{-- ========== END MATCH SCORE PANEL ========== --}}

                                        <!-- 1. Agent Overview & Qualifications -->
                                        @if ($isListingOwner || $isBidOwner)
                                        <div class="mb-5">
                                            <h6 class="section-header">
                                                <i class="fa-solid fa-user-tie me-2"></i>Agent Overview &amp; Qualifications
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
                                                            <i class="fa-solid fa-arrow-up-right-from-square me-1"></i>
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
                                                        // website_link may be stored as a plain string or a JSON-decoded array (hire-me auto-bids)
                                                        if (is_array($wLink)) {
                                                            $wLink = $wLink[0] ?? '';
                                                        }
                                                        $wLink = (string) $wLink;
                                                        if (!empty($wLink) && !str_starts_with($wLink, 'http://') && !str_starts_with($wLink, 'https://')) {
                                                            $wLink = 'https://' . $wLink;
                                                        }
                                                    @endphp
                                                    @if(!empty($wLink))
                                                    <a href="{{ $wLink }}" target="_blank" rel="noopener noreferrer" class="text-primary text-decoration-none">
                                                        <i class="fa-solid fa-globe me-1"></i> Visit Website
                                                    </a>
                                                    @endif
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
                                                            <i class="fa-brands fa-{{ strtolower($socialArray['platform']) }} me-1"></i>
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

                                            {{-- ===== AGENT HIGHLIGHTS STRIP ===== --}}
                                            @php
                                                $bidAgentHighlightProfile = \App\Models\AgentDefaultProfile::where('user_id', $bid->user_id)
                                                    ->whereNotNull('profile_data')
                                                    ->orderByDesc('updated_at')
                                                    ->first();
                                                $bidHighlights = $bidAgentHighlightProfile?->profile_data ?? [];
                                                $hlYearsExp    = $bidHighlights['years_experience'] ?? null;
                                                $hlTxns        = $bidHighlights['transactions_last_12_months'] ?? null;
                                                $hlResponse    = $bidHighlights['avg_response_time'] ?? null;
                                                $hlAreas       = $bidHighlights['primary_areas_served'] ?? null;
                                                $hlReview      = $bidHighlights['review_1'] ?? null;
                                                $hlHasAny      = $hlYearsExp || $hlTxns || $hlResponse || $hlAreas || $hlReview;
                                                $hlShortId     = optional($bid->user)->short_id;
                                            @endphp
                                            @if ($hlHasAny)
                                            <div class="agent-highlights-strip mb-3 p-3"
                                                 style="background:#f0fafa;border:1px solid #b2e0e0;border-radius:8px;border-left:4px solid #049399;">
                                                <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap" style="gap:6px;">
                                                    <span class="fw-semibold" style="color:#049399;font-size:0.9rem;">
                                                        <i class="fa-solid fa-star me-1"></i>Agent Highlights
                                                    </span>
                                                    @if ($hlShortId)
                                                    <a href="{{ route('agent.profile.public', $hlShortId) }}" target="_blank"
                                                       class="text-decoration-none small" style="color:#049399;">
                                                        <i class="fa-solid fa-arrow-up-right-from-square me-1"></i>View Full Profile
                                                    </a>
                                                    @endif
                                                </div>
                                                <div class="row g-2">
                                                    @if ($hlYearsExp)
                                                    <div class="col-6 col-md-4">
                                                        <div class="small text-muted">Years of Experience</div>
                                                        <div class="fw-semibold" style="font-size:0.95rem;">{{ $hlYearsExp }}</div>
                                                    </div>
                                                    @endif
                                                    @if ($hlTxns)
                                                    <div class="col-6 col-md-4">
                                                        <div class="small text-muted">Transactions (12 mo.)</div>
                                                        <div class="fw-semibold" style="font-size:0.95rem;">{{ $hlTxns }}</div>
                                                    </div>
                                                    @endif
                                                    @if ($hlResponse)
                                                    <div class="col-6 col-md-4">
                                                        <div class="small text-muted">Avg. Response Time</div>
                                                        <div class="fw-semibold" style="font-size:0.95rem;">{{ $hlResponse }}</div>
                                                    </div>
                                                    @endif
                                                    @if ($hlAreas)
                                                    <div class="col-12 col-md-6">
                                                        <div class="small text-muted">Primary Areas Served</div>
                                                        <div class="fw-semibold" style="font-size:0.95rem;">{{ $hlAreas }}</div>
                                                    </div>
                                                    @endif
                                                </div>
                                                @if ($hlReview)
                                                <div class="mt-2 pt-2" style="border-top:1px dashed #b2e0e0;">
                                                    <div class="small text-muted mb-1"><i class="fa-solid fa-quote-left me-1"></i>Featured Review</div>
                                                    <div class="fst-italic text-muted small" style="font-size:0.88rem;">{{ $hlReview }}</div>
                                                </div>
                                                @endif
                                            </div>
                                            @endif
                                            {{-- ===== END AGENT HIGHLIGHTS STRIP ===== --}}

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
                                                <i class="fa-solid fa-handshake me-2"></i>Broker Compensation &amp; Agency Agreement Terms
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
                                        @php
                                            $_refFeeRaw = data_get($bid, 'get.referral_fee_percent');
                                            $_refFeeDisplay = (strpos((string)$_refFeeRaw, '%') !== false) ? $_refFeeRaw : ($_refFeeRaw . '%');
                                        @endphp
                                        <div class="mb-4">
                                            <h6 class="mb-2" style="color: #049399; font-weight: 600;">G) Referral Fee</h6>
                                            <ul class="list-unstyled ps-3 mb-0">
                                                <li class="mb-1" style="{{ isset($brokerMismatches['referral_fee_percent']) ? $mismatchStyle : '' }}">
                                                    <span class="fw-semibold">Referral Fee (%):</span>
                                                    {{ $_refFeeDisplay }}{!! isset($brokerMismatches['referral_fee_percent']) ? $mismatchBadge : '' !!}
                                                </li>
                                            </ul>
                                        </div>
                                        @endif

                                        <!-- Additional Details -->
                                        @if (data_get($bid, 'get.additional_details'))
                                        <div class="mb-5">
                                            <h6 class="section-header">
                                                <i class="fa-solid fa-circle-info me-2"></i>Additional Details
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

                                            // groupedCatalog() supplies the category=>services structure needed for grouped display;
                                            // orderSelectedServices() is for ordering a flat list, not for category rendering.
                                            $sellerFlowKey = \App\Support\ServicesFormatter::keyForSellerAgent($bidPropKey);
                                            $sellerServicesConfig = \App\Support\ServicesFormatter::groupedCatalog($sellerFlowKey) ?: \App\Support\ServicesFormatter::groupedCatalog('seller_agent.residential');

                                            $propConfig = $sellerServicesConfig;

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
                                                <i class="fa-solid fa-clipboard-list me-2"></i>Offered Services
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
                                                    <i class="fa-solid fa-circle-xmark me-2"></i>Services Requested But Agent Did Not Include ({{ count($sellerMissingServices) }})
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
                                                <i class="fa-solid fa-chart-line me-2"></i>Agent Presentation &amp; Promotional Materials
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
                                                        <i class="fa-solid fa-arrow-up-right-from-square me-1"></i> Watch Presentation
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
                                                    <div class="text-muted"><i class="fa-solid fa-video me-1"></i> Video file uploaded</div>
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
                                                        <i class="fa-solid fa-arrow-up-right-from-square me-1"></i> View Business Card (Link)
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
                                                        <a href="{{ $businessCardUrl }}" target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary btn-sm"><i class="fa-solid fa-expand me-1"></i> View Full Size</a>
                                                        <a href="{{ $businessCardUrl }}" download class="btn btn-outline-success btn-sm"><i class="fa-solid fa-download me-1"></i> Download</a>
                                                    </div>
                                                    @else
                                                    <div class="d-flex align-items-center p-3 border rounded bg-light">
                                                        <i class="fa-solid fa-file-lines fa-2x text-muted me-3"></i>
                                                        <div class="flex-grow-1">
                                                            <div class="fw-medium">Business Card File</div>
                                                            <small class="text-muted">{{ strtoupper($businessCardExt) }} file</small>
                                                        </div>
                                                        <a href="{{ $businessCardUrl }}" download class="btn btn-outline-primary btn-sm"><i class="fa-solid fa-download me-1"></i> Download</a>
                                                    </div>
                                                    @endif
                                                    @else
                                                    <div class="text-muted"><i class="fa-solid fa-id-card me-1"></i> Business card uploaded</div>
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
                                                        <i class="fa-solid fa-folder-open me-1"></i>
                                                        {{ $matType }}@if ($matType === 'Other' && !empty($matOther)) - {{ $matOther }}@endif
                                                    </div>
                                                    @endif
                                                    @if (!empty($matLink))
                                                    <div class="mb-2">
                                                        @php
                                                            $matLinkFull = (!str_starts_with($matLink, 'http://') && !str_starts_with($matLink, 'https://')) ? 'https://' . $matLink : $matLink;
                                                        @endphp
                                                        <a href="{{ $matLinkFull }}" target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary btn-sm">
                                                            <i class="fa-solid fa-arrow-up-right-from-square me-1"></i> Open Link
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
                                                                        <i class="fa-solid fa-file fa-lg text-muted"></i>
                                                                    </div>
                                                                    @endif
                                                                    <div class="flex-grow-1 overflow-hidden">
                                                                        <div class="small text-truncate fw-medium">{{ $mfName }}</div>
                                                                        <small class="text-muted">{{ strtoupper($mfExt) }} file</small>
                                                                    </div>
                                                                    <div class="d-flex gap-1 ms-2">
                                                                        <a href="{{ $mfUrl }}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary" title="View">
                                                                            <i class="fa-solid fa-eye"></i>
                                                                        </a>
                                                                        <a href="{{ $mfUrl }}" download class="btn btn-sm btn-outline-success" title="Download">
                                                                            <i class="fa-solid fa-download"></i>
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
                                                <i class="fa-solid fa-address-card me-2"></i>Agent Credentials and Contact Information
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

