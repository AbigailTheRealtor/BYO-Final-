
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
                                                            <strong>Original Match</strong> compares this bid to the Landlord's original listing request.<br>
                                                            <strong>Counter Match</strong> compares this bid to the Landlord's most recent counteroffer.<br>
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
                                                                    <div class="small text-muted mb-2">vs. Landlord's Original Request</div>
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
                                                            <i class="fa-solid fa-circle-info me-1"></i>Match Score compares this bid only to the Landlord's original request. Added services or added terms are shown for transparency but do not increase the score.<br>
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
                                                                    <div class="small mt-1 d-flex align-items-center flex-wrap" style="gap: 3px 5px;" title="Extra services were included by the Agent beyond the Landlord&#39;s original request. These do not increase the match score but may provide additional value.">
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
                                                                    @if ($brokerTotal > 0 && $termsChangedCount > 0)
                                                                    <div class="small mt-1" style="color: #dc3545;">Changed from Baseline: {{ $termsChangedCount }}</div>
                                                                    @endif
                                                                    @if ($termsAddedCount > 0)
                                                                    <div class="small mt-1" style="color: #6c757d;">Added by Agent: {{ $termsAddedCount }}</div>
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
                                                    <div class="mb-5">
                                                        <h6 class="section-header">
                                                            <i class="fa-solid fa-user-tie me-2"></i>Agent
                                                            Overview & Qualifications
                                                        </h6>

                                                        <!-- About Agent -->
                                                        @if (data_get($bid, 'get.bio'))
                                                        <div class="mb-3">
                                                            <div class="fw-semibold"
                                                                style="color: #049399;">About Agent:</div>
                                                            <div class="text-muted">
                                                                {{ data_get($bid, 'get.bio') }}
                                                            </div>
                                                        </div>
                                                        @endif

                                                        <!-- Why Hire This Agent -->
                                                        @if (data_get($bid, 'get.why_hire_you'))
                                                        <div class="mb-3">
                                                            <div class="fw-semibold"
                                                                style="color: #049399;">Why Hire This Agent:</div>
                                                            <div class="text-muted">
                                                                {{ data_get($bid, 'get.why_hire_you') }}
                                                            </div>
                                                        </div>
                                                        @endif

                                                        <!-- What Sets This Agent Apart -->
                                                        @if (data_get($bid, 'get.what_sets_you_apart'))
                                                        <div class="mb-3">
                                                            <div class="fw-semibold"
                                                                style="color: #049399;">What Sets This Agent Apart:</div>
                                                            <div class="text-muted">
                                                                {{ data_get($bid, 'get.what_sets_you_apart') }}
                                                            </div>
                                                        </div>
                                                        @endif

                                                        <!-- Marketing Strategy -->
                                                        @if (data_get($bid, 'get.marketing_plan'))
                                                        <div class="mb-3">
                                                            <div class="fw-semibold"
                                                                style="color: #049399;">Marketing Strategy:</div>
                                                            <div class="text-muted">
                                                                {{ data_get($bid, 'get.marketing_plan') }}
                                                            </div>
                                                        </div>
                                                        @endif

                                                        <!-- Review Links -->
                                                        @php
                                                            $landlordReviewLinks = data_get($bid, 'get.reviews_links', []);
                                                            $hasAnyReviewUrl = !empty(array_filter((array) $landlordReviewLinks, fn($rl) => !empty(is_object($rl) ? $rl->url : ($rl['url'] ?? ''))));
                                                        @endphp
                                                        @if ($hasAnyReviewUrl)
                                                        <div class="mb-3">
                                                            <div class="fw-semibold"
                                                                style="color: #049399;">Review Links:</div>
                                                            <div>
                                                                @foreach ($landlordReviewLinks as $reviewLink)
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
                                                                    <a href="{{ $rlFinal }}"
                                                                        target="_blank"
                                                                        class="text-primary text-decoration-none">
                                                                        <i class="fa-solid fa-arrow-up-right-from-square me-1"></i>
                                                                        {{ !empty($rlText) ? $rlText : $rlUrlVal }}
                                                                    </a>
                                                                </div>
                                                                @endif
                                                                @endforeach
                                                            </div>
                                                        </div>
                                                        @endif

                                                        <!-- Website Link -->
                                                        @if (data_get($bid, 'get.website_link'))
                                                        <div class="mb-3">
                                                            <div class="fw-semibold"
                                                                style="color: #049399;">Website Link:</div>
                                                            <div>
                                                                @php
                                                                    $websiteLink = data_get($bid, 'get.website_link');
                                                                    if (is_array($websiteLink)) {
                                                                        $websiteLink = $websiteLink[0] ?? '';
                                                                    }
                                                                    $websiteLink = (string) $websiteLink;
                                                                    if (!empty($websiteLink) && !str_starts_with($websiteLink, 'http://') && !str_starts_with($websiteLink, 'https://')) {
                                                                        $websiteLink = 'https://' . $websiteLink;
                                                                    }
                                                                @endphp
                                                                @if(!empty($websiteLink))
                                                                <a href="{{ $websiteLink }}"
                                                                    target="_blank"
                                                                    rel="noopener noreferrer"
                                                                    class="text-primary text-decoration-none">
                                                                    <i class="fa-solid fa-globe me-1"></i>
                                                                    Visit Website
                                                                </a>
                                                                @endif
                                                            </div>
                                                        </div>
                                                        @endif

                                                        <!-- Social Media Platforms -->
                                                        @if (data_get($bid, 'get.social_media'))
                                                        <div class="mb-3">
                                                            <div class="fw-semibold"
                                                                style="color: #049399;">Social Media Platforms:</div>
                                                            <div>
                                                                @foreach (data_get($bid, 'get.social_media') as $social)
                                                                @php $socialArray = (array) $social; @endphp
                                                                @if (!empty($socialArray['platform']) && !empty($socialArray['url']))
                                                                <div class="mb-1">
                                                                    @php
                                                                    $socialUrl = $socialArray['url'];
                                                                    if (!empty($socialUrl) && !str_starts_with($socialUrl, 'http://') && !str_starts_with($socialUrl, 'https://')) {
                                                                        $socialUrl = 'https://' . $socialUrl;
                                                                    }
                                                                    @endphp
                                                                    <a href="{{ $socialUrl }}"
                                                                        target="_blank"
                                                                        class="text-primary text-decoration-none">
                                                                        <i class="fa-brands fa-{{ strtolower($socialArray['platform']) }} me-1"></i>
                                                                        @if (!empty($socialArray['text']))
                                                                        {{ $socialArray['text'] }}
                                                                        @else
                                                                        {{ $socialArray['platform'] }}
                                                                        @endif
                                                                    </a>
                                                                </div>
                                                                @endif
                                                                @endforeach
                                                            </div>
                                                        </div>
                                                        @endif

                                                        <!-- Licensed Year -->
                                                        @if (data_get($bid, 'get.year_licensed'))
                                                        <div class="mb-3">
                                                            <div class="fw-semibold"
                                                                style="color: #049399;">Licensed Year:</div>
                                                            <div class="text-muted">
                                                                {{ data_get($bid, 'get.year_licensed') }}
                                                            </div>
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

                                                    <!-- 2. Broker Compensation & Agency Agreement Terms -->
                                                    @if (data_get($bid, 'get.purchase_fee_type') ||
                                                    data_get($bid, 'get.interested_lease_option_agreement') ||
                                                    data_get($bid, 'get.interested_in_selling') ||
                                                    data_get($bid, 'get.broker_fee_timing') ||
                                                    data_get($bid, 'get.renewal_fee_type') ||
                                                    data_get($bid, 'get.expansion_commission_percentage') ||
                                                    data_get($bid, 'get.tenant_broker_commission_structure') ||
                                                    data_get($bid, 'get.protection_period') ||
                                                    data_get($bid, 'get.early_termination_fee_option') ||
                                                    data_get($bid, 'get.agency_agreement_timeframe') ||
                                                    data_get($bid, 'get.interested_in_property_management') ||
                                                    data_get($bid, 'get.brokerage_relationship') ||
                                                    data_get($bid, 'get.additional_details_broker'))
                                                    <div class="mb-5">
                                                        <h6 class="section-header">
                                                            <i class="fa-solid fa-handshake me-2"></i>Broker Compensation & Agency Agreement Terms
                                                        </h6>

                                                        @php
                                                        $mismatchStyle = 'background-color: #ffe6e6; padding: 2px 6px; border-radius: 4px; border-left: 3px solid #dc3545;';
                                                        $mismatchBadge = '<span class="badge bg-danger ms-2" style="font-size: 0.7rem; vertical-align: middle;">Mismatch</span>';

                                                        // ── A) Lease Fee composite display ──────────────────────────
                                                        $leaseFeeType = data_get($bid, 'get.purchase_fee_type', '');
                                                        $leaseFeeDisplay = $leaseFeeType;
                                                        if ($leaseFeeType === 'Flat Fee') {
                                                            $lf = data_get($bid,'get.purchase_fee_flat') ?: data_get($bid,'get.purchase_fee_flat_commercial');
                                                            if ($lf) $leaseFeeDisplay = '$'.number_format((float)$lf,2).' Flat Fee';
                                                        } elseif ($leaseFeeType === 'Percentage of the Rent Due Each Rental Period') {
                                                            $pct = data_get($bid,'get.purchase_fee_rental_period');
                                                            if ($pct) $leaseFeeDisplay = $pct.'% of Rent Due Each Rental Period';
                                                        } elseif ($leaseFeeType === 'Percentage of the Gross Lease Value') {
                                                            $pct = data_get($bid,'get.purchase_fee_percentage_combo');
                                                            if ($pct) $leaseFeeDisplay = $pct.'% of Gross Lease Value';
                                                        } elseif ($canon($leaseFeeType) === "Percentage of the First Month's Rent") {
                                                            $pct = data_get($bid,'get.purchase_fee_flat_combo');
                                                            if ($pct) $leaseFeeDisplay = $pct."% of First Month's Rent";
                                                        } elseif ($leaseFeeType === 'Percentage of the Net Aggregate Rent') {
                                                            $pct = data_get($bid,'get.purchase_fee_net_aggregate');
                                                            if ($pct) $leaseFeeDisplay = $pct.'% of Net Aggregate Rent';
                                                        } elseif ($leaseFeeType === 'Percentage of the Gross Rent') {
                                                            $pct = data_get($bid,'get.purchase_fee_gross_rent');
                                                            if ($pct) $leaseFeeDisplay = $pct.'% of Gross Rent';
                                                        } elseif ($canon($leaseFeeType) === "Percentage of Month's Rent") {
                                                            $pct    = data_get($bid,'get.purchase_fee_monthly_percentage');
                                                            $months = data_get($bid,'get.purchase_fee_months');
                                                            if ($pct) $leaseFeeDisplay = $pct."% of Month's Rent".($months ? " × $months months" : '');
                                                        } elseif ($leaseFeeType === 'other') {
                                                            $oth = data_get($bid,'get.purchase_fee_other') ?: data_get($bid,'get.purchase_fee_other_commercial');
                                                            $leaseFeeDisplay = 'Other: '.($oth ?: 'See details');
                                                        }

                                                        // ── A) Payment Timing composite display ─────────────────────
                                                        $feeTimingRaw = data_get($bid,'get.broker_fee_timing','');
                                                        $feeTimingDisplay = match($feeTimingRaw) {
                                                            'full_execution' => 'Full amount upon execution of lease, sales contract, or other transfer agreement',
                                                            default => $feeTimingRaw,
                                                        };
                                                        if ($feeTimingRaw === 'Deducted from Rent Collected') {
                                                            $d = data_get($bid,'get.broker_fee_days_from_rent');
                                                            if ($d) $feeTimingDisplay .= " ($d calendar days)";
                                                        } elseif ($feeTimingRaw === 'Paid Within Calendar Days After Executed Lease') {
                                                            $d = data_get($bid,'get.broker_fee_days_after_lease');
                                                            if ($d) $feeTimingDisplay = "Within $d days after executed lease";
                                                        } elseif ($feeTimingRaw === 'Paid Within Calendar Days of Tenant Rent Payment') {
                                                            $d = data_get($bid,'get.broker_fee_days_after_rent');
                                                            if ($d) $feeTimingDisplay = "Within $d days of tenant rent payment";
                                                        } elseif (strcasecmp($feeTimingRaw, 'Other') === 0) {
                                                            $oth = data_get($bid,'get.broker_fee_timing_other');
                                                            $feeTimingDisplay = $oth ?: 'Custom arrangement';
                                                        } elseif (in_array($feeTimingRaw, ['50% due upon execution, 50% due upon commencement of agreement','50% due upon execution, 50% due upon occupancy of premises'])) {
                                                            $d2 = data_get($bid,'get.broker_fee_days_after_due_event');
                                                            if ($d2) $feeTimingDisplay .= " (second installment within $d2 days)";
                                                        }

                                                        // ── A) Renewal Fee composite display ────────────────────────
                                                        $renewalFeeType = data_get($bid,'get.renewal_fee_type','');
                                                        $renewalFeeDisplay = $renewalFeeType;
                                                        if ($renewalFeeType === 'Flat Fee') {
                                                            $flat = data_get($bid,'get.renewal_fee_flat_fee');
                                                            if ($flat) $renewalFeeDisplay = '$'.number_format((float)$flat,2).' Flat Fee';
                                                        } elseif ($renewalFeeType === 'Percentage of the Rent Due Each Rental Period') {
                                                            $pct = data_get($bid,'get.renewal_fee_percentage');
                                                            if ($pct) $renewalFeeDisplay = $pct.'% of Rent Due Each Rental Period';
                                                        } elseif ($renewalFeeType === 'Percentage of the Gross Lease Value') {
                                                            $pct = data_get($bid,'get.renewal_fee_lease_value');
                                                            if ($pct) $renewalFeeDisplay = $pct.'% of Gross Lease Value';
                                                        } elseif ($canon($renewalFeeType) === "Percentage of the First Month's Rent") {
                                                            $pct = data_get($bid,'get.renewal_fee_first_month');
                                                            if ($pct) $renewalFeeDisplay = $pct."% of First Month's Rent";
                                                        } elseif ($renewalFeeType === 'Percentage of the Net Aggregate Rent') {
                                                            $pct = data_get($bid,'get.renewal_fee_percentage');
                                                            if ($pct) $renewalFeeDisplay = $pct.'% of Net Aggregate Rent';
                                                        } elseif ($renewalFeeType === 'Percentage of the Gross Rent') {
                                                            $pct = data_get($bid,'get.renewal_fee_lease_value');
                                                            if ($pct) $renewalFeeDisplay = $pct.'% of Gross Rent';
                                                        } elseif ($canon($renewalFeeType) === "Percentage of Month's Rent") {
                                                            $pct    = data_get($bid,'get.renewal_fee_first_month');
                                                            $months = data_get($bid,'get.renewal_fee_no_of_months');
                                                            if ($pct) $renewalFeeDisplay = $pct."% of Month's Rent".($months ? " × $months months" : '');
                                                        } elseif ($renewalFeeType === 'other') {
                                                            $oth = data_get($bid,'get.renewal_fee_custom');
                                                            $renewalFeeDisplay = 'Other: '.($oth ?: 'See details');
                                                        }

                                                        // ── B) Tenant Broker — structure and fee displayed SEPARATELY ─
                                                        $tenantBrokerStructure  = data_get($bid,'get.tenant_broker_commission_structure','');
                                                        $tenantBrokerFeeDisplay = '';
                                                        $tbs = data_get($bid,'get.tenant_broker_fee_structure','');
                                                        if ($tenantBrokerStructure && $tbs) {
                                                            $tbsNorm = strtolower(trim($tbs));
                                                            if (str_contains($tbsNorm, 'rent due each')) {
                                                                $pct = data_get($bid,'get.tenant_broker_percentage');
                                                                if ($pct) $tenantBrokerFeeDisplay = $pct.'% of Rent Due Each Rental Period';
                                                            } elseif (str_contains($tbsNorm, 'gross lease')) {
                                                                $pct = data_get($bid,'get.tenant_broker_gross_lease');
                                                                if ($pct) $tenantBrokerFeeDisplay = $pct.'% of Gross Lease Value';
                                                            } elseif (str_contains($tbsNorm, 'first month')) {
                                                                $pct = data_get($bid,'get.tenant_broker_first_month_rent');
                                                                if ($pct) $tenantBrokerFeeDisplay = $pct."% of First Month's Rent";
                                                            } elseif ($tbsNorm === 'flat fee' || str_contains($tbsNorm, 'flat')) {
                                                                $flat = data_get($bid,'get.tenant_broker_flat_fee');
                                                                if ($flat) $tenantBrokerFeeDisplay = '$'.number_format((float)$flat,2).' Flat Fee';
                                                            } elseif ($tbsNorm === 'other') {
                                                                $oth = data_get($bid,'get.tenant_broker_other');
                                                                if ($oth) $tenantBrokerFeeDisplay = 'Other: '.$oth;
                                                            }
                                                            // Fallback: tbs present but unmatched — try all fee meta keys in priority order
                                                            if (!$tenantBrokerFeeDisplay) {
                                                                if (data_get($bid,'get.tenant_broker_flat_fee')) {
                                                                    $tenantBrokerFeeDisplay = '$'.number_format((float)data_get($bid,'get.tenant_broker_flat_fee'),2).' Flat Fee';
                                                                } elseif (data_get($bid,'get.tenant_broker_percentage')) {
                                                                    $tenantBrokerFeeDisplay = data_get($bid,'get.tenant_broker_percentage').'% of Rent Due Each Rental Period';
                                                                } elseif (data_get($bid,'get.tenant_broker_gross_lease')) {
                                                                    $tenantBrokerFeeDisplay = data_get($bid,'get.tenant_broker_gross_lease').'% of Gross Lease Value';
                                                                } elseif (data_get($bid,'get.tenant_broker_first_month_rent')) {
                                                                    $tenantBrokerFeeDisplay = data_get($bid,'get.tenant_broker_first_month_rent')."% of First Month's Rent";
                                                                } elseif (data_get($bid,'get.tenant_broker_other')) {
                                                                    $tenantBrokerFeeDisplay = 'Other: '.data_get($bid,'get.tenant_broker_other');
                                                                }
                                                            }
                                                        } elseif ($tenantBrokerStructure && !$tbs) {
                                                            // Structure without fee sub-type: show flat/percentage directly
                                                            if (data_get($bid,'get.tenant_broker_flat_fee')) {
                                                                $tenantBrokerFeeDisplay = '$'.number_format((float)data_get($bid,'get.tenant_broker_flat_fee'),2).' Flat Fee';
                                                            } elseif (data_get($bid,'get.tenant_broker_percentage')) {
                                                                $tenantBrokerFeeDisplay = data_get($bid,'get.tenant_broker_percentage').'%';
                                                            } elseif (data_get($bid,'get.tenant_broker_gross_lease')) {
                                                                $tenantBrokerFeeDisplay = data_get($bid,'get.tenant_broker_gross_lease').'% of Gross Lease Value';
                                                            } elseif (data_get($bid,'get.tenant_broker_first_month_rent')) {
                                                                $tenantBrokerFeeDisplay = data_get($bid,'get.tenant_broker_first_month_rent')."% of First Month's Rent";
                                                            } elseif (data_get($bid,'get.tenant_broker_other')) {
                                                                $tenantBrokerFeeDisplay = 'Other: '.data_get($bid,'get.tenant_broker_other');
                                                            }
                                                        }
                                                        // Combined display kept for counter-term comparison
                                                        $tenantBrokerDisplay = $tenantBrokerStructure . ($tenantBrokerFeeDisplay ? ' – '.$tenantBrokerFeeDisplay : '');

                                                        // ── C) Lease-Option composite displays ──────────────────────
                                                        $leaseOptInterest = data_get($bid,'get.interested_lease_option_agreement','');
                                                        $leaseOptionCreatedDisplay  = '-';
                                                        $leaseOptionExercisedDisplay = '-';
                                                        if ($leaseOptInterest === 'Yes') {
                                                            $lt = data_get($bid,'get.lease_type');
                                                            $lv = data_get($bid,'get.lease_value');
                                                            if ($lt && $lv) {
                                                                $leaseOptionCreatedDisplay = ($lt === 'percent')
                                                                    ? ($fmtPercent($lv) ? $fmtPercent($lv).' of Total Purchase Price' : '-')
                                                                    : ($fmtMoney($lv) ?? '-');
                                                            }
                                                            $pt = data_get($bid,'get.purchase_type');
                                                            $pv = data_get($bid,'get.purchase_value');
                                                            if ($pt && $pv) {
                                                                $leaseOptionExercisedDisplay = ($pt === 'percent')
                                                                    ? ($fmtPercent($pv) ? $fmtPercent($pv).' of Total Purchase Price' : '-')
                                                                    : ($fmtMoney($pv) ?? '-');
                                                            }
                                                        }

                                                        // ── D) Purchase Fee composite display ───────────────────────
                                                        $sellingInterest  = data_get($bid,'get.interested_in_selling','');
                                                        $purchaseFeeDisplay = '-';
                                                        if ($sellingInterest === 'Yes') {
                                                            $ist = data_get($bid,'get.interested_in_selling_type','');
                                                            if ($ist === 'Percentage of the Total Purchase Price') {
                                                                $pct = data_get($bid,'get.landlord_broker_purchase_price');
                                                                $purchaseFeeDisplay = $pct ? $fmtPercent($pct).' of Total Purchase Price' : $ist;
                                                            } elseif ($ist === 'Percentage of the Total Purchase Price + Flat Fee') {
                                                                $pct  = data_get($bid,'get.landlord_broker_percentage_price');
                                                                $flat = data_get($bid,'get.landlord_broker_dollar_price');
                                                                $purchaseFeeDisplay = trim(($pct ? $fmtPercent($pct).' of Total Purchase Price' : '').($pct && $flat ? ' + ' : '').($flat ? $fmtMoney($flat) : ''));
                                                                if (!$purchaseFeeDisplay) $purchaseFeeDisplay = $ist;
                                                            } elseif ($ist === 'Flat Fee') {
                                                                $flat = data_get($bid,'get.landlord_broker_flate_fee');
                                                                $purchaseFeeDisplay = $flat ? '$'.number_format((float)$flat,2).' Flat Fee' : $ist;
                                                            } elseif ($ist === 'Other') {
                                                                $oth = data_get($bid,'get.landlord_broker_other');
                                                                $purchaseFeeDisplay = $oth ? 'Other: '.$oth : 'Other';
                                                            } else {
                                                                $purchaseFeeDisplay = $ist ?: '-';
                                                            }
                                                        }

                                                        // ── E) Agency Agreement Timeframe display ───────────────────
                                                        $agencyTimeframe = data_get($bid,'get.agency_agreement_timeframe','');
                                                        $agencyTimeframeDisplay = (strtolower(trim($agencyTimeframe)) === 'other')
                                                            ? (data_get($bid,'get.agency_agreement_custom') ?: 'Other')
                                                            : $agencyTimeframe;

                                                        // ── E) Property Management Fee composite display ─────────────
                                                        $pmFeeDisplay = '-';
                                                        if (data_get($bid,'get.interested_in_property_management') === 'yes') {
                                                            $pmFeeType = data_get($bid,'get.interested_in_property_management_fee','');
                                                            $pmFeeDisplay = $pmFeeType;
                                                            if ($pmFeeType === 'Percentage of the Gross Lease Value') {
                                                                $pct = data_get($bid,'get.interested_in_property_management_fee_gross_lease');
                                                                if ($pct) $pmFeeDisplay = $pct.'% of Gross Lease Value';
                                                            } elseif ($pmFeeType === 'Percentage of the Rent Due Each Rental Period') {
                                                                $pct = data_get($bid,'get.interested_in_property_management_fee_rental_periord');
                                                                if ($pct) $pmFeeDisplay = $pct.'% of Rent Due Each Rental Period';
                                                            } elseif ($pmFeeType === 'Flat Fee') {
                                                                $flat = data_get($bid,'get.interested_in_property_management_fee_flate_free');
                                                                if ($flat) $pmFeeDisplay = '$'.number_format((float)$flat,2).' Flat Fee';
                                                            } elseif ($pmFeeType === 'Other') {
                                                                $oth = data_get($bid,'get.interested_in_property_management_fee_other');
                                                                if ($oth) $pmFeeDisplay = 'Other: '.$oth;
                                                            }
                                                        }

                                                        // ── A) Sales Tax for Lease Fee (Commercial only) ─────────────
                                                        $leaseSalesTaxDisplay = '';
                                                        if ($leaseFeeType === 'Percentage of the Gross Rent') {
                                                            $v = data_get($bid,'get.sales_tax_option_gross');
                                                            if ($v && $v !== 'null') $leaseSalesTaxDisplay = $v === 'including' ? 'Including Sales Tax' : ($v === 'excluding' ? 'Excluding Sales Tax' : $v);
                                                        } elseif ($canon($leaseFeeType) === "Percentage of Month's Rent") {
                                                            $v = data_get($bid,'get.sales_tax_option_monthly');
                                                            if ($v && $v !== 'null') $leaseSalesTaxDisplay = $v === 'including' ? 'Including Sales Tax' : ($v === 'excluding' ? 'Excluding Sales Tax' : $v);
                                                        } elseif ($leaseFeeType === 'Flat Fee') {
                                                            $v = data_get($bid,'get.sales_tax_option_flat');
                                                            if ($v && $v !== 'null') $leaseSalesTaxDisplay = $v === 'including' ? 'Including Sales Tax' : ($v === 'excluding' ? 'Excluding Sales Tax' : $v);
                                                        }

                                                        // ── A) Sales Tax for Renewal Fee (Commercial only) ───────────
                                                        $renewalSalesTaxDisplay = '';
                                                        if ($renewalFeeType === 'Percentage of the Gross Rent') {
                                                            $v = data_get($bid,'get.renewal_fee_sales_tax_lease_value');
                                                            if ($v && $v !== 'null') $renewalSalesTaxDisplay = $v === 'including' ? 'Including Sales Tax' : ($v === 'excluding' ? 'Excluding Sales Tax' : $v);
                                                        } elseif ($canon($renewalFeeType) === "Percentage of Month's Rent") {
                                                            $v = data_get($bid,'get.renewal_fee_sales_tax_first_month');
                                                            if ($v && $v !== 'null') $renewalSalesTaxDisplay = $v === 'including' ? 'Including Sales Tax' : ($v === 'excluding' ? 'Excluding Sales Tax' : $v);
                                                        } elseif ($renewalFeeType === 'Flat Fee') {
                                                            $v = data_get($bid,'get.renewal_fee_sales_tax_flat_fee');
                                                            if ($v && $v !== 'null') $renewalSalesTaxDisplay = $v === 'including' ? 'Including Sales Tax' : ($v === 'excluding' ? 'Excluding Sales Tax' : $v);
                                                        }
                                                        @endphp

                                                        <!-- A) Landlord's Broker Lease Fee -->
                                                        @if (data_get($bid, 'get.purchase_fee_type') || data_get($bid, 'get.broker_fee_timing') || data_get($bid, 'get.renewal_fee_type') || data_get($bid, 'get.expansion_commission_percentage'))
                                                        <div class="mb-4">
                                                            <h6 class="mb-2" style="color: #049399; font-weight: 600;">A) Landlord's Broker Lease Fee</h6>
                                                            <ul class="list-unstyled ps-3 mb-0">
                                                                @if (data_get($bid, 'get.purchase_fee_type'))
                                                                <li class="mb-1" style="{{ isset($brokerMismatches['purchase_fee_type']) ? $mismatchStyle : '' }}"><span class="fw-semibold">Landlord's Broker Lease Fee:</span> {{ $leaseFeeDisplay }}{!! isset($brokerMismatches['purchase_fee_type']) ? $mismatchBadge : '' !!}</li>
                                                                @if ($leaseSalesTaxDisplay)
                                                                <li class="mb-1"><span class="fw-semibold">Sales Tax (Lease Fee):</span> {{ $leaseSalesTaxDisplay }}</li>
                                                                @endif
                                                                @endif
                                                                @if (data_get($bid, 'get.broker_fee_timing'))
                                                                <li class="mb-1" style="{{ isset($brokerMismatches['broker_fee_timing']) ? $mismatchStyle : '' }}"><span class="fw-semibold">Payment Timing for Broker Fees:</span> {{ $feeTimingDisplay }}{!! isset($brokerMismatches['broker_fee_timing']) ? $mismatchBadge : '' !!}</li>
                                                                @endif
                                                                @if (data_get($bid, 'get.renewal_fee_type'))
                                                                <li class="mb-1" style="{{ isset($brokerMismatches['renewal_fee_type']) ? $mismatchStyle : '' }}"><span class="fw-semibold">Lease Renewal/Extension Fee:</span> {{ $renewalFeeDisplay }}{!! isset($brokerMismatches['renewal_fee_type']) ? $mismatchBadge : '' !!}</li>
                                                                @if ($renewalSalesTaxDisplay)
                                                                <li class="mb-1"><span class="fw-semibold">Sales Tax (Renewal Fee):</span> {{ $renewalSalesTaxDisplay }}</li>
                                                                @endif
                                                                @endif
                                                                @if (data_get($bid, 'get.expansion_commission_percentage'))
                                                                <li class="mb-1" style="{{ isset($brokerMismatches['expansion_commission_percentage']) ? $mismatchStyle : '' }}"><span class="fw-semibold">Expansion Commission for Lease Amendment:</span> {{ data_get($bid,'get.expansion_commission_percentage') }}% of original commission{!! isset($brokerMismatches['expansion_commission_percentage']) ? $mismatchBadge : '' !!}</li>
                                                                @endif
                                                            </ul>
                                                        </div>

                                                        @endif


                                                        <!-- B) Tenant's Broker Compensation -->
                                                        @if (data_get($bid, 'get.tenant_broker_commission_structure'))
                                                        <div class="mb-4">
                                                            <h6 class="mb-2" style="color: #049399; font-weight: 600;">B) Tenant's Broker Compensation</h6>
                                                            <ul class="list-unstyled ps-3 mb-0">
                                                                <li class="mb-1" style="{{ isset($brokerMismatches['tenant_broker_commission_structure']) ? $mismatchStyle : '' }}"><span class="fw-semibold">Tenant's Broker Commission Structure:</span> {{ $tenantBrokerStructure }}{!! isset($brokerMismatches['tenant_broker_commission_structure']) ? $mismatchBadge : '' !!}</li>
                                                                @php $tbFeeMismatch = isset($brokerMismatches['tenant_broker_fee_structure']) || isset($brokerMismatches['tenant_broker_percentage']) || isset($brokerMismatches['tenant_broker_gross_lease']) || isset($brokerMismatches['tenant_broker_first_month_rent']) || isset($brokerMismatches['tenant_broker_flat_fee']) || isset($brokerMismatches['tenant_broker_other']); @endphp
                                                                <li class="mb-1" style="{{ $tbFeeMismatch ? $mismatchStyle : '' }}"><span class="fw-semibold">Tenant's Broker Commission Fee:</span> {{ $tenantBrokerFeeDisplay ?: '—' }}{!! $tbFeeMismatch ? $mismatchBadge : '' !!}</li>
                                                            </ul>
                                                        </div>
                                                        @endif

                                                        <!-- C) Lease-Option Details -->
                                                        @if (data_get($bid, 'get.interested_lease_option_agreement'))
                                                        <div class="mb-4">
                                                            <h6 class="mb-2" style="color: #049399; font-weight: 600;">C) Lease-Option Details</h6>
                                                            <ul class="list-unstyled ps-3 mb-0">
                                                                <li class="mb-1" style="{{ isset($brokerMismatches['interested_lease_option_agreement']) ? $mismatchStyle : '' }}"><span class="fw-semibold">Interested in Offering a Lease-Option Agreement:</span> {{ data_get($bid,'get.interested_lease_option_agreement') }}{!! isset($brokerMismatches['interested_lease_option_agreement']) ? $mismatchBadge : '' !!}</li>
                                                                @if (data_get($bid, 'get.interested_lease_option_agreement') === 'Yes')
                                                                    @if ($leaseOptionCreatedDisplay !== '-')
                                                                    <li class="mb-1" style="{{ (isset($brokerMismatches['lease_type']) || isset($brokerMismatches['lease_value'])) ? $mismatchStyle : '' }}"><span class="fw-semibold">Compensation for Creating the Lease-Option Agreement:</span> {{ $leaseOptionCreatedDisplay }}{!! (isset($brokerMismatches['lease_type']) || isset($brokerMismatches['lease_value'])) ? $mismatchBadge : '' !!}</li>
                                                                    @elseif (isset($brokerMismatches['lease_type']) || isset($brokerMismatches['lease_value']))
                                                                    <li class="mb-1" style="{{ $mismatchStyle }}"><span class="fw-semibold">Compensation for Creating the Lease-Option Agreement:</span> —{!! $mismatchBadge !!}</li>
                                                                    @endif
                                                                    @if ($leaseOptionExercisedDisplay !== '-')
                                                                    <li class="mb-1" style="{{ (isset($brokerMismatches['purchase_type']) || isset($brokerMismatches['purchase_value'])) ? $mismatchStyle : '' }}"><span class="fw-semibold">Compensation if Purchase Option is Exercised:</span> {{ $leaseOptionExercisedDisplay }}{!! (isset($brokerMismatches['purchase_type']) || isset($brokerMismatches['purchase_value'])) ? $mismatchBadge : '' !!}</li>
                                                                    @elseif (isset($brokerMismatches['purchase_type']) || isset($brokerMismatches['purchase_value']))
                                                                    <li class="mb-1" style="{{ $mismatchStyle }}"><span class="fw-semibold">Compensation if Purchase Option is Exercised:</span> —{!! $mismatchBadge !!}</li>
                                                                    @endif
                                                                @endif
                                                            </ul>
                                                        </div>
                                                        @endif

                                                        <!-- D) Purchase Fee Details -->
                                                        @if (data_get($bid, 'get.interested_in_selling'))
                                                        <div class="mb-4">
                                                            <h6 class="mb-2" style="color: #049399; font-weight: 600;">D) Purchase Fee Details</h6>
                                                            <ul class="list-unstyled ps-3 mb-0">
                                                                <li class="mb-1" style="{{ isset($brokerMismatches['interested_in_selling']) ? $mismatchStyle : '' }}"><span class="fw-semibold">Interested in Selling the Property:</span> {{ data_get($bid,'get.interested_in_selling') }}{!! isset($brokerMismatches['interested_in_selling']) ? $mismatchBadge : '' !!}</li>
                                                                @if (data_get($bid, 'get.interested_in_selling') === 'Yes' && $purchaseFeeDisplay !== '-')
                                                                <li class="mb-1" style="{{ isset($brokerMismatches['interested_in_selling_type']) ? $mismatchStyle : '' }}"><span class="fw-semibold">Purchase Fee:</span> {{ $purchaseFeeDisplay }}{!! isset($brokerMismatches['interested_in_selling_type']) ? $mismatchBadge : '' !!}</li>
                                                                @endif
                                                            </ul>
                                                        </div>
                                                        @endif

                                                        <!-- E) Legal Terms -->
                                                        @if (data_get($bid, 'get.protection_period') || data_get($bid, 'get.early_termination_fee_option') || data_get($bid, 'get.agency_agreement_timeframe') || data_get($bid, 'get.interested_in_property_management'))
                                                        <div class="mb-4">
                                                            <h6 class="mb-2" style="color: #049399; font-weight: 600;">E) Legal Terms</h6>
                                                            <ul class="list-unstyled ps-3 mb-0">
                                                                @if (data_get($bid, 'get.protection_period'))
                                                                <li class="mb-1" style="{{ isset($brokerMismatches['protection_period']) ? $mismatchStyle : '' }}"><span class="fw-semibold">Protection Period Timeframe:</span> {{ data_get($bid,'get.protection_period') }} days{!! isset($brokerMismatches['protection_period']) ? $mismatchBadge : '' !!}</li>
                                                                @endif
                                                                @if (data_get($bid, 'get.early_termination_fee_option'))
                                                                <li class="mb-1" style="{{ isset($brokerMismatches['early_termination_fee_option']) ? $mismatchStyle : '' }}"><span class="fw-semibold">Early Termination Fee:</span> {{ data_get($bid,'get.early_termination_fee_option') === 'yes' ? 'Yes' : 'No' }}{!! isset($brokerMismatches['early_termination_fee_option']) ? $mismatchBadge : '' !!}</li>
                                                                @if (data_get($bid, 'get.early_termination_fee_option') === 'yes' && data_get($bid, 'get.early_termination_fee_amount'))
                                                                <li class="mb-1" style="{{ isset($brokerMismatches['early_termination_fee_amount']) ? $mismatchStyle : '' }}"><span class="fw-semibold">Termination Fee Amount:</span> {{ $fmtMoney(data_get($bid,'get.early_termination_fee_amount')) ?? ('$'.data_get($bid,'get.early_termination_fee_amount')) }}{!! isset($brokerMismatches['early_termination_fee_amount']) ? $mismatchBadge : '' !!}</li>
                                                                @elseif (data_get($bid, 'get.early_termination_fee_option') === 'yes' && isset($brokerMismatches['early_termination_fee_amount']))
                                                                <li class="mb-1" style="{{ $mismatchStyle }}"><span class="fw-semibold">Termination Fee Amount:</span> —{!! $mismatchBadge !!}</li>
                                                                @endif
                                                                @endif
                                                                @if (data_get($bid, 'get.agency_agreement_timeframe'))
                                                                <li class="mb-1" style="{{ isset($brokerMismatches['agency_agreement_timeframe']) ? $mismatchStyle : '' }}"><span class="fw-semibold">Landlord Agency Agreement Timeframe:</span> {{ $agencyTimeframeDisplay }}{!! isset($brokerMismatches['agency_agreement_timeframe']) ? $mismatchBadge : '' !!}</li>
                                                                @endif
                                                                @if (data_get($bid, 'get.interested_in_property_management'))
                                                                <li class="mb-1" style="{{ isset($brokerMismatches['interested_in_property_management']) ? $mismatchStyle : '' }}"><span class="fw-semibold">Interested in Property Management:</span> {{ data_get($bid,'get.interested_in_property_management') === 'yes' ? 'Yes' : 'No' }}{!! isset($brokerMismatches['interested_in_property_management']) ? $mismatchBadge : '' !!}</li>
                                                                @if (data_get($bid, 'get.interested_in_property_management') === 'yes' && $pmFeeDisplay !== '-')
                                                                <li class="mb-1"><span class="fw-semibold">Property Management Fee:</span> {{ $pmFeeDisplay }}</li>
                                                                @endif
                                                                @endif
                                                            </ul>
                                                        </div>
                                                        @endif

                                                        <!-- F) Brokerage Relationship -->
                                                        @if (data_get($bid, 'get.brokerage_relationship'))
                                                        <div class="mb-4">
                                                            <h6 class="mb-2" style="color: #049399; font-weight: 600;">F) Brokerage Relationship</h6>
                                                            <ul class="list-unstyled ps-3 mb-0">
                                                                <li class="mb-1" style="{{ isset($brokerMismatches['brokerage_relationship']) ? $mismatchStyle : '' }}"><span class="fw-semibold">Acceptable Brokerage Relationship:</span> {{ data_get($bid,'get.brokerage_relationship') }}{!! isset($brokerMismatches['brokerage_relationship']) ? $mismatchBadge : '' !!}</li>
                                                            </ul>
                                                        </div>
                                                        @endif

                                                        <!-- G) Additional Terms -->
                                                        @if (data_get($bid, 'get.additional_details_broker'))
                                                        <div class="mb-4">
                                                            <h6 class="mb-2" style="color: #049399; font-weight: 600;">G) Additional Terms</h6>
                                                            <ul class="list-unstyled ps-3 mb-0">
                                                                <li class="mb-1"><span class="fw-semibold">Additional Terms:</span> {{ data_get($bid,'get.additional_details_broker') }}</li>
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








                                                    <!-- Services Offered -->
                                                    @php
                                                    // Parse bid services
                                                    $rawModalSvcs = data_get($bid, 'get.services', []);
                                                    if (is_string($rawModalSvcs) && !empty($rawModalSvcs)) {
                                                        $parsedModalSvcs = json_decode($rawModalSvcs, true) ?: [];
                                                    } else {
                                                        $parsedModalSvcs = is_array($rawModalSvcs) ? $rawModalSvcs : [];
                                                    }
                                                    $parsedModalSvcs = array_values(array_filter($parsedModalSvcs, fn($s) => is_string($s) && trim($s) !== '' && $s !== 'Other'));

                                                    // Parse other_services
                                                    $rawModalOther = data_get($bid, 'get.other_services', []);
                                                    if (is_string($rawModalOther) && !empty($rawModalOther)) {
                                                        $parsedModalOther = json_decode($rawModalOther, true) ?: [];
                                                    } else {
                                                        $parsedModalOther = is_array($rawModalOther) ? $rawModalOther : [];
                                                    }
                                                    $parsedModalOther = array_values(array_filter($parsedModalOther, fn($s) => is_string($s) && trim($s) !== ''));

                                                    $hasModalSvcs = !empty($parsedModalSvcs) || !empty($parsedModalOther);

                                                    // Baseline: matched + missing = what the auction listing asked for
                                                    $modalBaselineNorm = array_merge($matchScore['matched_services'], $matchScore['missing_services']);
                                                    // Current bid normalized services
                                                    $modalCurrentNorm  = array_merge($matchScore['matched_services'], $matchScore['extra_services']);

                                                    // Per-service color helper
                                                    $isModalSvcMatched = fn($svc) => in_array(
                                                        \App\Helpers\LandlordBidMatchScoreHelper::normalizeService((string)$svc),
                                                        $modalBaselineNorm
                                                    );

                                                    // Normalize helper that also handles literal \u2019 text (not actual Unicode char)
                                                    // Used for category-membership matching so that strings with literal \u2019
                                                    // in the category arrays match DB strings containing the actual ' char.
                                                    $normForCat = function(string $s): string {
                                                        $s = mb_strtolower(trim($s));
                                                        // Replace actual Unicode smart quotes
                                                        $s = str_replace(["\u{2019}", "\u{2018}", "\u{201C}", "\u{201D}"], ["'", "'", '"', '"'], $s);
                                                        // Replace literal escape text \u2019 / \u2018 / etc. (6-char sequences)
                                                        $s = str_replace(['\\u2019', '\\u2018', '\\u201c', '\\u201d', '\\u201C', '\\u201D'], ["'", "'", '"', '"', '"', '"'], $s);
                                                        // Normalize em-dash \u2014 and literal escape
                                                        $s = str_replace(["\u{2014}", '\\u2014'], ['-', '-'], $s);
                                                        $s = preg_replace('/\s+/', ' ', $s);
                                                        return trim($s);
                                                    };

                                                    // Missing services: services the auction asked for but bid did NOT offer
                                                    $baselineSvcsRaw = $landlordBaselineData['services'] ?? [];
                                                    if (is_string($baselineSvcsRaw)) {
                                                        $baselineSvcsRaw = json_decode($baselineSvcsRaw, true) ?: [];
                                                    }
                                                    $modalMissingSvcs = [];
                                                    foreach ((array)$baselineSvcsRaw as $bSvc) {
                                                        $bNorm = \App\Helpers\LandlordBidMatchScoreHelper::normalizeService((string)$bSvc);
                                                        if (!in_array($bNorm, $modalCurrentNorm, true)) {
                                                            $modalMissingSvcs[] = $bSvc;
                                                        }
                                                    }

                                                    // Landlord service categories (Residential)
                                                    // groupedCatalog() supplies category=>services map for grouped display;
                                                    // orderSelectedServices() is for a flat selected-services list only.
                                                    $landlordResCats = \App\Support\ServicesFormatter::groupedCatalog('landlord_agent.residential');

                                                    // Landlord service categories (Commercial)
                                                    $landlordComCats = \App\Support\ServicesFormatter::groupedCatalog('landlord_agent.commercial');

                                                    $modalCats = $isCommercial ? $landlordComCats : $landlordResCats;
                                                    @endphp

                                                    @php
                                                    // Badge styles matching Tenant modal Services section
                                                    $svcAddedStyle = 'background-color: #d4edda; padding: 2px 6px; border-radius: 4px; border-left: 3px solid #28a745;';
                                                    $svcAddedBadge = '<span class="badge bg-success ms-2" style="font-size: 0.65rem; vertical-align: middle;">Extra Service Offered</span>';
                                                    $svcMissingStyle = 'background-color: #ffe6e6; padding: 2px 6px; border-radius: 4px; border-left: 3px solid #dc3545; text-decoration: line-through; color: #721c24;';
                                                    $svcMissingBadge = '<span class="badge bg-danger ms-2" style="font-size: 0.65rem; vertical-align: middle;">Not Offered by Agent</span>';
                                                    @endphp
                                                    <div class="mb-5">
                                                        <h6 class="section-header">
                                                            <i class="fa-solid fa-clipboard-list me-2"></i>Offered Services
                                                        </h6>

                                                        @if ($hasModalSvcs)
                                                            @foreach ($modalCats as $catName => $catSvcs)
                                                                @php
                                                                    $normCatSvcs = array_map($normForCat, $catSvcs);
                                                                    $matchedInCat = array_filter($parsedModalSvcs, fn($svc) => in_array($normForCat($svc), $normCatSvcs));
                                                                @endphp
                                                                @if (!empty($matchedInCat))
                                                                <div class="mb-3">
                                                                    <div class="fw-bold" style="color: #34465c; font-size: 0.95rem;">{{ $catName }}</div>
                                                                    <ul class="mb-0" style="margin-top: 0.25rem; padding-left: 1.5rem; list-style: disc;">
                                                                        @foreach ($matchedInCat as $svc)
                                                                            @php $svcInBaseline = $isModalSvcMatched($svc); @endphp
                                                                            <li style="font-size: 0.9rem; margin-bottom: 4px; {{ !$svcInBaseline ? $svcAddedStyle : '' }}">{{ $svc }}{!! !$svcInBaseline ? $svcAddedBadge : '' !!}</li>
                                                                            @if (trim($normForCat($svc)) === 'provide digital photo enhancements')
                                                                                @php
                                                                                    $modalPhotoEnhRaw = data_get($bid, 'get.photo_enhancements', []);
                                                                                    $modalPhotoEnhancements = is_string($modalPhotoEnhRaw) ? (json_decode($modalPhotoEnhRaw, true) ?: []) : (is_array($modalPhotoEnhRaw) ? $modalPhotoEnhRaw : []);
                                                                                    $modalCustomEnh = data_get($bid, 'get.custom_enhancement', '');
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
                                                                        @endforeach
                                                                    </ul>
                                                                </div>
                                                                @endif
                                                            @endforeach

                                                            @if (!empty($parsedModalOther))
                                                            <div class="mb-3">
                                                                <div class="fw-bold" style="color: #34465c; font-size: 0.95rem;">✍️ Additional Services</div>
                                                                <ul class="mb-0" style="margin-top: 0.25rem; padding-left: 1.5rem; list-style: disc;">
                                                                    @foreach ($parsedModalOther as $otherSvc)
                                                                        @php $svcInBaseline = $isModalSvcMatched($otherSvc); @endphp
                                                                        <li style="font-size: 0.9rem; margin-bottom: 4px; {{ !$svcInBaseline ? $svcAddedStyle : '' }}">{{ $otherSvc }}{!! !$svcInBaseline ? $svcAddedBadge : '' !!}</li>
                                                                    @endforeach
                                                                </ul>
                                                            </div>
                                                            @endif

                                                            {{-- Missing Services Section --}}
                                                            @if (!empty($modalMissingSvcs))
                                                            <div class="mt-4 p-3" style="background-color: #ffe6e6; border-radius: 8px; border: 1px solid #dc3545;">
                                                                <div class="fw-bold mb-2" style="color: #721c24; font-size: 0.95rem;">
                                                                    <i class="fa-solid fa-circle-xmark me-2"></i>Services Requested But Agent Did Not Include ({{ count($modalMissingSvcs) }})
                                                                </div>
                                                                <ul class="mb-0" style="padding-left: 1.5rem; list-style: disc;">
                                                                    @foreach ($modalMissingSvcs as $missingSvc)
                                                                    <li style="font-size: 0.9rem; margin-bottom: 4px; {{ $svcMissingStyle }}">{{ $missingSvc }}{!! $svcMissingBadge !!}</li>
                                                                    @endforeach
                                                                </ul>
                                                            </div>
                                                            @endif
                                                        @else
                                                        <div class="text-muted" style="font-style: italic;">No services selected for this bid.</div>
                                                        @endif
                                                    </div>

                                                    <!-- 4. Agent Presentation & Promotional Materials -->
                                                    @if (data_get($bid, 'get.presentation_link') ||
                                                    data_get($bid, 'get.video_upload') ||
                                                    data_get($bid, 'get.business_card_link') ||
                                                    data_get($bid, 'get.business_card') ||
                                                    data_get($bid, 'get.promoMaterials'))
                                                    <div class="mb-5">
                                                        <h6 class="section-header">
                                                            <i
                                                                class="fa-solid fa-chart-line me-2"></i>Agent
                                                            Presentation & Promotional Materials:
                                                        </h6>

                                                        <!-- Virtual Presentation Section -->
                                                        @if (data_get($bid, 'get.presentation_link') || data_get($bid, 'get.video_upload'))
                                                        <div class="mb-4">
                                                            <div class="fw-semibold mb-2"
                                                                style="color: #049399;">Virtual
                                                                Agent Presentation:</div>

                                                            @if (data_get($bid, 'get.presentation_link'))
                                                            <div class="mb-2">
                                                                @php
                                                                $presentationLink = data_get(
                                                                $bid,
                                                                'get.presentation_link',
                                                                );
                                                                // Agar link mein http:// ya https:// nahi hai toh add karo
                                                                if (
                                                                !empty(
                                                                $presentationLink
                                                                ) &&
                                                                !str_starts_with(
                                                                $presentationLink,
                                                                'http://',
                                                                ) &&
                                                                !str_starts_with(
                                                                $presentationLink,
                                                                'https://',
                                                                )
                                                                ) {
                                                                $presentationLink =
                                                                'https://' .
                                                                $presentationLink;
                                                                }
                                                                @endphp
                                                                <a href="{{ $presentationLink }}"
                                                                    target="_blank"
                                                                    class="text-primary text-decoration-none">
                                                                    <i
                                                                        class="fa-solid fa-arrow-up-right-from-square me-1"></i>
                                                                    Watch Presentation
                                                                </a>
                                                            </div>
                                                            @endif

                                                            @if (data_get($bid, 'get.video_upload'))
                                                            <div class="mb-2">
                                                                <div class="fw-medium mb-1"
                                                                    style="color: #049399;">
                                                                    Uploaded Video:</div>
                                                                @if (is_string(data_get($bid, 'get.video_upload')))
                                                                <video controls
                                                                    style="width: 100%; max-width: 400px; border-radius: 6px; background: #000;">
                                                                    <source
                                                                        src="{{ asset('storage/' . data_get($bid, 'get.video_upload')) }}"
                                                                        type="video/mp4">
                                                                    Your browser does
                                                                    not support the
                                                                    video tag.
                                                                </video>
                                                                @else
                                                                <div
                                                                    class="text-muted">
                                                                    <i
                                                                        class="fa-solid fa-video me-1"></i>
                                                                    Video file uploaded
                                                                </div>
                                                                @endif
                                                            </div>
                                                            @endif
                                                        </div>
                                                        @endif

                                                        <!-- Business Card Section -->
                                                        @if (data_get($bid, 'get.business_card_link') || data_get($bid, 'get.business_card'))
                                                        <div class="mb-4">
                                                            <div class="fw-semibold mb-2"
                                                                style="color: #049399;">
                                                                Business Card:</div>

                                                            @if (data_get($bid, 'get.business_card_link'))
                                                            <div class="mb-2">
                                                                @php
                                                                $businessCardLink = data_get(
                                                                $bid,
                                                                'get.business_card_link',
                                                                );
                                                                // Agar link mein http:// ya https:// nahi hai toh add karo
                                                                if (
                                                                !empty(
                                                                $businessCardLink
                                                                ) &&
                                                                !str_starts_with(
                                                                $businessCardLink,
                                                                'http://',
                                                                ) &&
                                                                !str_starts_with(
                                                                $businessCardLink,
                                                                'https://',
                                                                )
                                                                ) {
                                                                $businessCardLink =
                                                                'https://' .
                                                                $businessCardLink;
                                                                }
                                                                @endphp
                                                                <a href="{{ $businessCardLink }}"
                                                                    target="_blank"
                                                                    class="text-primary text-decoration-none">
                                                                    <i
                                                                        class="fa-solid fa-arrow-up-right-from-square me-1"></i>
                                                                    View Business Card
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
                                                                $businessCardExtension = pathinfo(
                                                                $businessCardPath,
                                                                PATHINFO_EXTENSION,
                                                                );
                                                                @endphp

                                                                @if (in_array(strtolower($businessCardExtension), ['jpg', 'jpeg', 'png', 'gif', 'webp']))
                                                                <div class="business-card-preview mb-2">
                                                                    <a href="{{ asset('storage/' . $businessCardPath) }}" target="_blank" rel="noopener noreferrer" title="Click to view full size">
                                                                        <img src="{{ asset('storage/' . $businessCardPath) }}"
                                                                            style="max-width: 450px; width: 100%; height: auto; border-radius: 8px; border: 2px solid #e0e0e0; cursor: pointer; box-shadow: 0 2px 8px rgba(0,0,0,0.1);"
                                                                            alt="Business Card"
                                                                            class="img-fluid">
                                                                    </a>
                                                                </div>
                                                                <div class="d-flex gap-2 mt-2">
                                                                    <a href="{{ asset('storage/' . $businessCardPath) }}" target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary btn-sm">
                                                                        <i class="fa-solid fa-expand me-1"></i> View Full Size
                                                                    </a>
                                                                    <a href="{{ asset('storage/' . $businessCardPath) }}" download class="btn btn-outline-success btn-sm">
                                                                        <i class="fa-solid fa-download me-1"></i> Download
                                                                    </a>
                                                                </div>
                                                                @else
                                                                <div class="d-flex align-items-center p-3 border rounded bg-light">
                                                                    <i class="fa-solid fa-file-lines fa-2x text-muted me-3"></i>
                                                                    <div class="flex-grow-1">
                                                                        <div class="fw-medium">Business Card File</div>
                                                                        <small class="text-muted">{{ strtoupper($businessCardExtension) }} file</small>
                                                                    </div>
                                                                    <a href="{{ asset('storage/' . $businessCardPath) }}" download class="btn btn-outline-primary btn-sm">
                                                                        <i class="fa-solid fa-download me-1"></i> Download
                                                                    </a>
                                                                </div>
                                                                @endif
                                                                @else
                                                                <div
                                                                    class="text-muted">
                                                                    <i
                                                                        class="fa-solid fa-id-card me-1"></i>
                                                                    Business card
                                                                    uploaded
                                                                </div>
                                                                @endif
                                                            </div>
                                                            @endif
                                                        </div>
                                                        @endif

                                                        <!-- Marketing Materials Section -->
                                                        @if (data_get($bid, 'get.promoMaterials'))
                                                        @php
                                                            $hasAnyMaterials = false;
                                                            $promoMaterialsRaw = data_get($bid, 'get.promoMaterials', []);
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
                                                        @if ($hasAnyMaterials)
                                                        <div>
                                                            <div class="fw-semibold mb-2"
                                                                style="color: #049399;">
                                                                Marketing Materials:</div>

                                                            @foreach ($promoMaterialsNormalized as $index => $material)
                                                            @if (!empty($material['type']) || !empty($material['link']) || !empty($material['files']))
                                                            <div
                                                                class="mb-3 p-3 border rounded">
                                                                @if (!empty($material['type']))
                                                                <div class="fw-medium mb-2"
                                                                    style="color: #049399;">
                                                                    {{ $material['type'] }}
                                                                    @if ($material['type'] === 'Other' && !empty($material['other']))
                                                                    -
                                                                    {{ $material['other'] }}
                                                                    @endif
                                                                </div>
                                                                @endif

                                                                @if (!empty($material['link']))
                                                                <div
                                                                    class="mb-2">
                                                                    @php
                                                                    $materialLink =
                                                                    $material[
                                                                    'link'
                                                                    ];
                                                                    // Agar link mein http:// ya https:// nahi hai toh add karo
                                                                    if (
                                                                    !empty(
                                                                    $materialLink
                                                                    ) &&
                                                                    !str_starts_with(
                                                                    $materialLink,
                                                                    'http://',
                                                                    ) &&
                                                                    !str_starts_with(
                                                                    $materialLink,
                                                                    'https://',
                                                                    )
                                                                    ) {
                                                                    $materialLink =
                                                                    'https://' .
                                                                    $materialLink;
                                                                    }
                                                                    @endphp
                                                                    <a href="{{ $materialLink }}"
                                                                        target="_blank"
                                                                        rel="noopener noreferrer"
                                                                        class="btn btn-outline-primary btn-sm">
                                                                        <i class="fa-solid fa-arrow-up-right-from-square me-1"></i>
                                                                        Open Link
                                                                    </a>
                                                                </div>
                                                                @endif

                                                                @php
                                                                $matFilesL = $material['files'] ?? [];
                                                                if (is_object($matFilesL)) { $matFilesL = (array) $matFilesL; }
                                                                elseif (is_string($matFilesL)) { $matFilesL = $matFilesL !== '' ? [$matFilesL] : []; }
                                                                elseif (!is_array($matFilesL)) { $matFilesL = []; }
                                                                @endphp
                                                                @if (!empty($matFilesL))
                                                                <div
                                                                    class="mb-2">
                                                                    <div class="fw-medium mb-1"
                                                                        style="color: #049399;">
                                                                        Uploaded
                                                                        Files:</div>
                                                                    <div
                                                                        class="row">
                                                                        @foreach ($matFilesL as $fileIndex => $rawFilePath)
                                                                        @php
                                                                        if (is_object($rawFilePath)) { $rawFilePath = (array) $rawFilePath; }
                                                                        if (is_array($rawFilePath)) { $rawFilePath = $rawFilePath['path'] ?? $rawFilePath['file'] ?? $rawFilePath['url'] ?? (reset($rawFilePath) ?: null); }
                                                                        $filePath = is_string($rawFilePath) ? $rawFilePath : null;
                                                                        @endphp
                                                                        @if ($filePath)
                                                                        @php
                                                                        $fileExtension = pathinfo(
                                                                        $filePath,
                                                                        PATHINFO_EXTENSION,
                                                                        );
                                                                        $fileName = basename(
                                                                        $filePath,
                                                                        );
                                                                        $imageExtensions = [
                                                                        'jpg',
                                                                        'jpeg',
                                                                        'png',
                                                                        'gif',
                                                                        'webp',
                                                                        ];
                                                                        $isImage = in_array(
                                                                        strtolower(
                                                                        $fileExtension,
                                                                        ),
                                                                        $imageExtensions,
                                                                        );
                                                                        @endphp

                                                                        <div
                                                                            class="col-md-6 col-lg-4 mb-2">
                                                                            <div
                                                                                class="border rounded p-2 d-flex align-items-center">
                                                                                @if ($isImage)
                                                                                <a href="{{ asset('storage/' . $filePath) }}" target="_blank" rel="noopener noreferrer">
                                                                                    <img src="{{ asset('storage/' . $filePath) }}"
                                                                                        style="width: 60px; height: 60px; object-fit: cover; border-radius: 4px; margin-right: 10px;"
                                                                                        alt="Marketing Material">
                                                                                </a>
                                                                                @else
                                                                                <div class="bg-light rounded d-flex align-items-center justify-content-center me-2"
                                                                                    style="width: 60px; height: 60px;">
                                                                                    <i class="fa-solid fa-file fa-lg text-muted"></i>
                                                                                </div>
                                                                                @endif
                                                                                <div class="flex-grow-1 overflow-hidden">
                                                                                    <div class="small text-truncate fw-medium">{{ $fileName }}</div>
                                                                                    <small class="text-muted">{{ strtoupper($fileExtension) }} file</small>
                                                                                </div>
                                                                                <div class="d-flex gap-1 ms-2">
                                                                                    <a href="{{ asset('storage/' . $filePath) }}"
                                                                                        target="_blank"
                                                                                        rel="noopener noreferrer"
                                                                                        class="btn btn-sm btn-outline-primary"
                                                                                        title="View">
                                                                                        <i class="fa-solid fa-eye"></i>
                                                                                    </a>
                                                                                    <a href="{{ asset('storage/' . $filePath) }}"
                                                                                        download
                                                                                        class="btn btn-sm btn-outline-success"
                                                                                        title="Download">
                                                                                        <i class="fa-solid fa-download"></i>
                                                                                    </a>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        @else
                                                                        <div
                                                                            class="col-md-6 col-lg-4 mb-2">
                                                                            <div
                                                                                class="border rounded p-2 d-flex align-items-center">
                                                                                <div class="bg-light rounded d-flex align-items-center justify-content-center me-2"
                                                                                    style="width: 60px; height: 60px;">
                                                                                    <i
                                                                                        class="fa-solid fa-file text-muted"></i>
                                                                                </div>
                                                                                <div
                                                                                    class="flex-grow-1">
                                                                                    <div
                                                                                        class="small">
                                                                                        File
                                                                                        {{ $fileIndex + 1 }}
                                                                                    </div>
                                                                                    <small
                                                                                        class="text-muted">Uploaded
                                                                                        file</small>
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
                                                        </div>
                                                        @endif
                                                        @endif
                                                    </div>
                                                    @endif

                                                    <!-- 5. Agent Information -->
                                                    <div class="mb-4">
                                                        <h6 class="section-header">
                                                            <i
                                                                class="fa-solid fa-address-card me-2"></i>Agent
                                                            Information:
                                                        </h6>

                                                        <div class="row">
                                                            <!-- First Name -->
                                                            @if (data_get($bid, 'get.first_name'))
                                                            <div class="col-md-6 mb-2">
                                                                <div class="fw-semibold"
                                                                    style="color: #049399;">First
                                                                    Name:</div>
                                                                <div class="text-muted">
                                                                    {{ data_get($bid, 'get.first_name') }}
                                                                </div>
                                                            </div>
                                                            @endif

                                                            <!-- Last Name -->
                                                            @if (data_get($bid, 'get.last_name'))
                                                            <div class="col-md-6 mb-2">
                                                                <div class="fw-semibold"
                                                                    style="color: #049399;">Last
                                                                    Name:</div>
                                                                <div class="text-muted">
                                                                    {{ data_get($bid, 'get.last_name') }}
                                                                </div>
                                                            </div>
                                                            @endif

                                                            <!-- Phone Number -->
                                                            @if (data_get($bid, 'get.phone'))
                                                            <div class="col-md-6 mb-2">
                                                                <div class="fw-semibold"
                                                                    style="color: #049399;">Phone
                                                                    Number:</div>
                                                                <div class="text-muted">
                                                                    {{ data_get($bid, 'get.phone') }}
                                                                </div>
                                                            </div>
                                                            @endif

                                                            <!-- Email -->
                                                            @if (data_get($bid, 'get.email'))
                                                            <div class="col-md-6 mb-2">
                                                                <div class="fw-semibold"
                                                                    style="color: #049399;">Email:
                                                                </div>
                                                                <div class="text-muted">
                                                                    {{ data_get($bid, 'get.email') }}
                                                                </div>
                                                            </div>
                                                            @endif

                                                            <!-- Brokerage -->
                                                            @if (data_get($bid, 'get.brokerage'))
                                                            <div class="col-12 mb-2">
                                                                <div class="fw-semibold"
                                                                    style="color: #049399;">
                                                                    Brokerage:</div>
                                                                <div class="text-muted">
                                                                    {{ data_get($bid, 'get.brokerage') }}
                                                                </div>
                                                            </div>
                                                            @endif

                                                            <!-- License Number -->
                                                            @if (data_get($bid, 'get.license_no'))
                                                            <div class="col-md-6 mb-2">
                                                                <div class="fw-semibold"
                                                                    style="color: #049399;">Real
                                                                    Estate License #:</div>
                                                                <div class="text-muted">
                                                                    {{ data_get($bid, 'get.license_no') }}
                                                                </div>
                                                            </div>
                                                            @endif

                                                            <!-- NAR Member ID -->
                                                            @if (data_get($bid, 'get.nar_id'))
                                                            <div class="col-md-6 mb-2">
                                                                <div class="fw-semibold"
                                                                    style="color: #049399;">NAR
                                                                    Member ID:</div>
                                                                <div class="text-muted">
                                                                    {{ data_get($bid, 'get.nar_id') }}
                                                                </div>
                                                            </div>
                                                            @endif
                                                        </div>
                                                    </div>

