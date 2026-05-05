
                                                                        {{-- ========== MATCH SCORE PANEL ========== --}}
                                                                        @if ($cardHasAnyBaseline)
                                                                        <div class="match-score-panel mb-4 p-3" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 10px; border: 1px solid #dee2e6;">
                                                                            @if ($cardShowDualScore && $cardOriginalScore && $cardLatestCounterScore)
                                                                            {{-- DUAL SCORE: Original Match + Latest Counter Match --}}
                                                                            <h6 class="mb-2" style="color: #1a3a5c; font-weight: 600;">
                                                                                <i class="fa-solid fa-chart-pie me-2"></i>Match Summary
                                                                            </h6>
                                                                            <p class="small text-muted mb-3">
                                                                                <i class="fa-solid fa-circle-info me-1"></i>
                                                                                <strong>Original Match</strong> compares this bid to the Buyer's original listing request.<br>
                                                                                <strong>Counter Match</strong> compares this bid to the Buyer's most recent counteroffer.<br>
                                                                                Added services or terms do not increase either score.
                                                                            </p>
                                                                            <div class="row g-3">
                                                                                {{-- Original Match column --}}
                                                                                @php $omColor = $cardGetScoreColor($cardOriginalScore['overall_percent']); @endphp
                                                                                <div class="col-md-6">
                                                                                    <div class="p-3 bg-white rounded" style="border: 1px solid #dee2e6; border-top: 3px solid #6c757d;">
                                                                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                                                                            <span class="small fw-semibold" style="color: #6c757d;">Original Match</span>
                                                                                            <span class="badge" style="background: {{ $omColor }}; font-size: 1rem; padding: 5px 12px; color: white;">{{ $cardOriginalScore['overall_percent'] }}%</span>
                                                                                        </div>
                                                                                        <div class="small text-muted mb-2">vs. Buyer's Original Request</div>
                                                                                        <div class="d-flex justify-content-between small">
                                                                                            <div>
                                                                                                <div class="fw-semibold" style="color: {{ $cardGetScoreColor($cardOriginalScore['services_match_percent']) }};">Services {{ $cardOriginalScore['services_match_percent'] }}%</div>
                                                                                                <div class="text-muted">{{ $cardOriginalScore['services_baseline_total'] > 0 ? $cardOriginalScore['services_matched_count'].'/'.$cardOriginalScore['services_baseline_total'] : 'No services requested' }}</div>
                                                                                            </div>
                                                                                            <div>
                                                                                                <div class="fw-semibold" style="color: {{ $cardGetScoreColor($cardOriginalScore['terms_match_percent']) }};">Terms {{ $cardOriginalScore['terms_match_percent'] }}%</div>
                                                                                                <div class="text-muted">{{ $cardOriginalScore['terms_baseline_total'] > 0 ? $cardOriginalScore['terms_matched_count'].'/'.$cardOriginalScore['terms_baseline_total'] : 'No terms provided' }}</div>
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                                {{-- Counter Match column --}}
                                                                                @php $cmColor = $cardGetScoreColor($cardLatestCounterScore['overall_percent']); @endphp
                                                                                <div class="col-md-6">
                                                                                    <div class="p-3 rounded" style="background: #f0f9ff; border: 1px solid #bde0fe; border-top: 3px solid {{ $cmColor }};">
                                                                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                                                                            <span class="small fw-semibold" style="color: #1a3a5c;">Counter Match</span>
                                                                                            <span class="badge" style="background: {{ $cmColor }}; font-size: 1rem; padding: 5px 12px; color: white;">{{ $cardLatestCounterScore['overall_percent'] }}%</span>
                                                                                        </div>
                                                                                        <div class="small text-muted mb-2">vs. Your Latest Counter</div>
                                                                                        <div class="d-flex justify-content-between small">
                                                                                            <div>
                                                                                                <div class="fw-semibold" style="color: {{ $cardGetScoreColor($cardLatestCounterScore['services_match_percent']) }};">Services {{ $cardLatestCounterScore['services_match_percent'] }}%</div>
                                                                                                <div class="text-muted">{{ $cardLatestCounterScore['services_baseline_total'] > 0 ? $cardLatestCounterScore['services_matched_count'].'/'.$cardLatestCounterScore['services_baseline_total'] : 'No services requested' }}</div>
                                                                                                @if($cardLatestCounterScore['services_baseline_total'] > 0 && $cardLatestCounterScore['services_extra_count'] > 0)<div style="color: #6c757d;">+{{ $cardLatestCounterScore['services_extra_count'] }} added</div>@endif
                                                                                                @if($cardLatestCounterScore['services_baseline_total'] > 0 && $cardLatestCounterScore['services_missing_count'] > 0)<div style="color: #dc3545;">{{ $cardLatestCounterScore['services_missing_count'] }} missing</div>@endif
                                                                                            </div>
                                                                                            <div>
                                                                                                <div class="fw-semibold" style="color: {{ $cardGetScoreColor($cardLatestCounterScore['terms_match_percent']) }};">Terms {{ $cardLatestCounterScore['terms_match_percent'] }}%</div>
                                                                                                <div class="text-muted">{{ $cardLatestCounterScore['terms_baseline_total'] > 0 ? $cardLatestCounterScore['terms_matched_count'].'/'.$cardLatestCounterScore['terms_baseline_total'] : 'No terms provided' }}</div>
                                                                                                @if($cardLatestCounterScore['terms_changed_count'] > 0)<div style="color: #dc3545;">{{ $cardLatestCounterScore['terms_changed_count'] }} changed</div>@endif
                                                                                                @if($cardLatestCounterScore['terms_added_count'] > 0)<div style="color: #6c757d;">+{{ $cardLatestCounterScore['terms_added_count'] }} added</div>@endif
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
                                                                                <span class="badge" style="background: {{ $scoreColor }}; font-size: 1.1rem; padding: 8px 16px;">
                                                                                    {{ $overallScore }}% Match
                                                                                </span>
                                                                            </div>
                                                                            <p class="small text-muted mb-3">
                                                                                <i class="fa-solid fa-circle-info me-1"></i>Match Score compares this bid only to the Buyer's original request. Added services or added terms are shown for transparency but do not increase the score.<br>
                                                                                Comparing to: <strong>{{ $buyerBaselineLabel }}</strong>
                                                                            </p>
                                                                            <div class="row g-3">
                                                                                <div class="col-md-6">
                                                                                    <div class="p-2 bg-white rounded" style="border-left: 4px solid {{ $cardGetScoreColor($score['services_percent'] ?? 100) }};">
                                                                                        <div class="d-flex justify-content-between align-items-center">
                                                                                            <span class="small fw-semibold">Services Match</span>
                                                                                            <span class="badge" style="background: {{ $cardGetScoreColor($score['services_percent'] ?? 100) }};">{{ $score['services_percent'] ?? 100 }}%</span>
                                                                                        </div>
                                                                                        <div class="small text-muted mt-1">
                                                                                            {{ ($score['services_baseline_total'] ?? 0) > 0 ? 'Matched Original: '.($score['services_matched_count'] ?? 0).'/'.($score['services_baseline_total'] ?? 0) : 'No services requested' }}
                                                                                        </div>
                                                                                        @if ($cardServicesExtraCount > 0)
                                                                                        <div class="small mt-1 d-flex align-items-center flex-wrap" style="gap: 3px 5px;" title="Extra services were included by the Agent beyond the Buyer&#39;s original request. These do not increase the match score but may provide additional value.">
                                                                                            <span>&#11088;</span>
                                                                                            <span style="font-weight: 500; color: #856404;">Extra Value Added: {{ $cardServicesExtraCount }} {{ $cardServicesExtraCount === 1 ? 'Service' : 'Services' }}</span>
                                                                                        </div>
                                                                                        @endif
                                                                                        @if (count($missingServices) > 0)
                                                                                        <div class="small mt-1" style="color: #dc3545;">Missing from Original: {{ count($missingServices) }}</div>
                                                                                        @endif
                                                                                    </div>
                                                                                </div>
                                                                                <div class="col-md-6">
                                                                                    <div class="p-2 bg-white rounded" style="border-left: 4px solid {{ $cardGetScoreColor($score['broker_comp_percent'] ?? 100) }};">
                                                                                        <div class="d-flex justify-content-between align-items-center">
                                                                                            <span class="small fw-semibold">Terms Match</span>
                                                                                            <span class="badge" style="background: {{ $cardGetScoreColor($score['broker_comp_percent'] ?? 100) }};">{{ $score['broker_comp_percent'] ?? 100 }}%</span>
                                                                                        </div>
                                                                                        <div class="small text-muted mt-1">
                                                                                            {{ ($score['broker_comp_total'] ?? 0) > 0 ? 'Matched Original: '.($score['broker_comp_matched'] ?? 0).'/'.($score['broker_comp_total'] ?? 0) : 'No terms provided' }}
                                                                                        </div>
                                                                                        @if (($score['broker_comp_total'] ?? 0) > 0 && ($score['terms_changed_count'] ?? 0) > 0)
                                                                                        <div class="small mt-1" style="color: #dc3545;">Changed from Baseline: {{ $score['terms_changed_count'] }}</div>
                                                                                        @endif
                                                                                        @if (($score['terms_added_count'] ?? 0) > 0)
                                                                                        <div class="small mt-1" style="color: #6c757d;">Added by Agent: {{ $score['terms_added_count'] }}</div>
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
                                                                                        style="color: #049399;">About Agent
                                                                                    </div>
                                                                                    <div class="text-muted">
                                                                                        {{ data_get($bid, 'get.bio') }}
                                                                                    </div>
                                                                                </div>
                                                                            @endif

                                                                            <!-- Why Hire You -->
                                                                            @if (data_get($bid, 'get.why_hire_you'))
                                                                                <div class="mb-3">
                                                                                    <div class="fw-semibold"
                                                                                        style="color: #049399;">Why Should
                                                                                        You Be Hired</div>
                                                                                    <div class="text-muted">
                                                                                        {{ data_get($bid, 'get.why_hire_you') }}
                                                                                    </div>
                                                                                </div>
                                                                            @endif

                                                                            <!-- What Sets You Apart -->
                                                                            @if (data_get($bid, 'get.what_sets_you_apart'))
                                                                                <div class="mb-3">
                                                                                    <div class="fw-semibold"
                                                                                        style="color: #049399;">What Sets You Apart From Other Agents</div>
                                                                                    <div class="text-muted">
                                                                                        {{ data_get($bid, 'get.what_sets_you_apart') }}
                                                                                    </div>
                                                                                </div>
                                                                            @endif

                                                                            <!-- Marketing Strategy -->
                                                                            @if (data_get($bid, 'get.marketing_plan'))
                                                                                <div class="mb-3">
                                                                                    <div class="fw-semibold"
                                                                                        style="color: #049399;">What Is Your Marketing Strategy</div>
                                                                                    <div class="text-muted">
                                                                                        {{ data_get($bid, 'get.marketing_plan') }}
                                                                                    </div>
                                                                                </div>
                                                                            @endif

                                                                            <!-- Reviews Links -->
                                                                            @php
                                                                                $buyerReviewLinks = data_get($bid, 'get.reviews_links', []);
                                                                                $hasAnyBuyerReviewUrl = !empty(array_filter((array) $buyerReviewLinks, fn($rl) => !empty(is_object($rl) ? $rl->url : ($rl['url'] ?? ''))));
                                                                            @endphp
                                                                            @if ($hasAnyBuyerReviewUrl)
                                                                                <div class="mb-3">
                                                                                    <div class="fw-semibold"
                                                                                        style="color: #049399;">Review Links:</div>
                                                                                    <div>
                                                                                        @foreach ($buyerReviewLinks as $reviewLink)
                                                                                            @php
                                                                                                $rlUrlVal = is_object($reviewLink) ? $reviewLink->url : ($reviewLink['url'] ?? '');
                                                                                            @endphp
                                                                                            @if (!empty($rlUrlVal))
                                                                                                @php
                                                                                                    $rlFinal = $rlUrlVal;
                                                                                                    if (!str_starts_with($rlFinal, 'http://') && !str_starts_with($rlFinal, 'https://')) {
                                                                                                        $rlFinal = 'https://' . $rlFinal;
                                                                                                    }
                                                                                                    $rlText = is_object($reviewLink) ? ($reviewLink->text ?? '') : ($reviewLink['text'] ?? '');
                                                                                                @endphp
                                                                                                <div class="mb-1">
                                                                                                    <a href="{{ $rlFinal }}"
                                                                                                        target="_blank"
                                                                                                        rel="noopener noreferrer"
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
                                                                            @php
                                                                                $bidWebsiteLink = data_get($bid, 'get.website_link');
                                                                                if (is_array($bidWebsiteLink)) {
                                                                                    $bidWebsiteLink = count($bidWebsiteLink) > 0 ? (string) $bidWebsiteLink[0] : null;
                                                                                }
                                                                                if (!empty($bidWebsiteLink) && !str_starts_with($bidWebsiteLink, 'http://') && !str_starts_with($bidWebsiteLink, 'https://')) {
                                                                                    $bidWebsiteLink = 'https://' . $bidWebsiteLink;
                                                                                }
                                                                            @endphp
                                                                            @if (!empty($bidWebsiteLink))
                                                                                <div class="mb-3">
                                                                                    <div class="fw-semibold"
                                                                                        style="color: #049399;">Website
                                                                                        Link</div>
                                                                                    <div>
                                                                                        <a href="{{ $bidWebsiteLink }}"
                                                                                            target="_blank"
                                                                                            class="text-primary text-decoration-none">
                                                                                            <i
                                                                                                class="fa-solid fa-globe me-1"></i>
                                                                                            Visit Website
                                                                                        </a>
                                                                                    </div>
                                                                                </div>
                                                                            @endif

                                                                            <!-- Social Media Platforms -->
                                                                            @if (data_get($bid, 'get.social_media'))
                                                                                <div class="mb-3">
                                                                                    <div class="fw-semibold"
                                                                                        style="color: #049399;">Social
                                                                                        Media</div>
                                                                                    <div>
                                                                                        @foreach (data_get($bid, 'get.social_media') as $social)
                                                                                            @php
                                                                                                // Convert object to array
                                                                                                $socialArray = (array) $social;
                                                                                            @endphp
                                                                                            @if (!empty($socialArray['platform']) && !empty($socialArray['url']))
                                                                                                <div class="mb-1">
                                                                                                    @php
                                                                                                        $socialUrl =
                                                                                                            $socialArray[
                                                                                                                'url'
                                                                                                            ];
                                                                                                        // Agar link mein http:// ya https:// nahi hai toh add karo
                                                                                                        if (
                                                                                                            !empty(
                                                                                                                $socialUrl
                                                                                                            ) &&
                                                                                                            !str_starts_with(
                                                                                                                $socialUrl,
                                                                                                                'http://',
                                                                                                            ) &&
                                                                                                            !str_starts_with(
                                                                                                                $socialUrl,
                                                                                                                'https://',
                                                                                                            )
                                                                                                        ) {
                                                                                                            $socialUrl =
                                                                                                                'https://' .
                                                                                                                $socialUrl;
                                                                                                        }
                                                                                                    @endphp
                                                                                                    <a href="{{ $socialUrl }}"
                                                                                                        target="_blank"
                                                                                                        class="text-primary text-decoration-none">
                                                                                                        <i
                                                                                                            class="fa-brands fa-{{ strtolower($socialArray['platform']) }} me-1"></i>
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
                                                                        @if (data_get($bid, 'get.commission_structure') ||
                                                                            data_get($bid, 'get.purchase_fee_type') ||
                                                                            data_get($bid, 'get.interested_lease_option') ||
                                                                            data_get($bid, 'get.lease_fee_type') ||
                                                                            data_get($bid, 'get.interested_lease_option_agreement') ||
                                                                            data_get($bid, 'get.protection_period') ||
                                                                            data_get($bid, 'get.early_termination_fee_option') ||
                                                                            data_get($bid, 'get.retainer_fee_option') ||
                                                                            data_get($bid, 'get.agency_agreement_timeframe') ||
                                                                            data_get($bid, 'get.brokerage_relationship'))
                                                                        <div class="mb-5">
                                                                            <h6 class="section-header">
                                                                                <i class="fa-solid fa-handshake me-2"></i>Broker Compensation & Agency Agreement Terms
                                                                            </h6>

                                                                            @php
                                                                            $mismatchStyle = 'background-color: #ffe6e6; padding: 2px 6px; border-radius: 4px; border-left: 3px solid #dc3545;';
                                                                            $mismatchBadge = '<span class="badge bg-danger ms-2" style="font-size: 0.7rem; vertical-align: middle;">Mismatch</span>';
                                                                            @endphp

                                                                            <!-- A) Buyer's Broker Compensation -->
                                                                            @if (data_get($bid, 'get.commission_structure') || data_get($bid, 'get.purchase_fee_type'))
                                                                            <div class="mb-4">
                                                                                <h6 class="mb-2" style="color: #049399; font-weight: 600;">A) Buyer's Broker Compensation</h6>
                                                                                <ul class="list-unstyled ps-3 mb-0">
                                                                                    @if (data_get($bid, 'get.commission_structure'))
                                                                                    <li class="mb-1" style="{{ isset($brokerMismatches['commission_structure']) ? $mismatchStyle : '' }}"><span class="fw-semibold">Buyer's Broker Commission Structure:</span> {{ data_get($bid, 'get.commission_structure') }}{!! isset($brokerMismatches['commission_structure']) ? $mismatchBadge : '' !!}</li>
                                                                                    @endif
                                                                                    @if (data_get($bid, 'get.purchase_fee_type'))
                                                                                    @php
                                                                                        $bidPurchaseFeeType = data_get($bid, 'get.purchase_fee_type') ?? '';
                                                                                        $bidPurchaseFeeCombined = '—';
                                                                                        if ($bidPurchaseFeeType === 'Flat Fee') {
                                                                                            $bidPurchaseFeeCombined = $fmtMoney(data_get($bid, 'get.purchase_fee_flat')) ?? '—';
                                                                                        } elseif ($bidPurchaseFeeType === 'Percentage of the Total Purchase Price') {
                                                                                            $pct = data_get($bid, 'get.purchase_fee_percentage');
                                                                                            $bidPurchaseFeeCombined = $pct ? ($fmtPercent($pct) . ' of Total Purchase Price') : '—';
                                                                                        } elseif ($bidPurchaseFeeType === 'Percentage of the Total Purchase Price + Flat Fee') {
                                                                                            $bidPurchaseFeeCombined = $joinParts([
                                                                                                $fmtMoney(data_get($bid, 'get.purchase_fee_flat_combo')),
                                                                                                data_get($bid, 'get.purchase_fee_percentage_combo') ? ($fmtPercent(data_get($bid, 'get.purchase_fee_percentage_combo')) . ' of Total Purchase Price') : null,
                                                                                            ]) ?? '—';
                                                                                        } elseif ($bidPurchaseFeeType === 'other') {
                                                                                            $bidPurchaseFeeCombined = data_get($bid, 'get.purchase_fee_other') ?? '—';
                                                                                        } else {
                                                                                            $bidPurchaseFeeCombined = $bidPurchaseFeeType;
                                                                                        }
                                                                                    @endphp
                                                                                    <li class="mb-1" style="{{ isset($brokerMismatches['purchase_fee_type']) ? $mismatchStyle : '' }}"><span class="fw-semibold">Buyer's Broker Purchase Fee:</span> {{ $bidPurchaseFeeCombined }}{!! isset($brokerMismatches['purchase_fee_type']) ? $mismatchBadge : '' !!}</li>
                                                                                    @endif
                                                                                </ul>
                                                                            </div>
                                                                            @endif

                                                                            <!-- B) Buyer's Broker Lease Fee -->
                                                                            @if (data_get($bid, 'get.interested_lease_option') || data_get($bid, 'get.lease_fee_type'))
                                                                            <div class="mb-4">
                                                                                <h6 class="mb-2" style="color: #049399; font-weight: 600;">B) Buyer's Broker Lease Fee</h6>
                                                                                <ul class="list-unstyled ps-3 mb-0">
                                                                                    @if (data_get($bid, 'get.interested_lease_option'))
                                                                                    <li class="mb-1" style="{{ isset($brokerMismatches['interested_lease_option']) ? $mismatchStyle : '' }}"><span class="fw-semibold">Interested in a Lease Agreement:</span> {{ data_get($bid, 'get.interested_lease_option') }}{!! isset($brokerMismatches['interested_lease_option']) ? $mismatchBadge : '' !!}</li>
                                                                                    @endif
                                                                                    @if (data_get($bid, 'get.interested_lease_option') === 'Yes' && data_get($bid, 'get.lease_fee_type'))
                                                                                    @php
                                                                                        $bidLeaseFeeType = data_get($bid, 'get.lease_fee_type') ?? '';
                                                                                        $bidLeaseFeeCombined = '—';
                                                                                        if ($bidLeaseFeeType === 'flat' && data_get($bid, 'get.lease_fee_flat')) {
                                                                                            $bidLeaseFeeCombined = $fmtMoney(data_get($bid, 'get.lease_fee_flat'));
                                                                                        } elseif ($bidLeaseFeeType === 'Percentage of the Gross Lease Value' && data_get($bid, 'get.lease_fee_percentage')) {
                                                                                            $bidLeaseFeeCombined = $fmtPercent(data_get($bid, 'get.lease_fee_percentage')) . ' of Gross Lease Value';
                                                                                        } elseif ($bidLeaseFeeType === 'Percentage of Monthly Rent' && data_get($bid, 'get.lease_fee_percentage_monthly_rent')) {
                                                                                            $display = $fmtPercent(data_get($bid, 'get.lease_fee_percentage_monthly_rent')) . ' of Monthly Rent';
                                                                                            if (data_get($bid, 'get.lease_fee_percentage_monthly_number')) {
                                                                                                $display .= ' x ' . data_get($bid, 'get.lease_fee_percentage_monthly_number') . ' Months';
                                                                                            }
                                                                                            $bidLeaseFeeCombined = $display;
                                                                                        } elseif ($bidLeaseFeeType === 'Flat Fee + Percentage of the Gross Lease Value') {
                                                                                            $bidLeaseFeeCombined = $joinParts([
                                                                                                $fmtMoney(data_get($bid, 'get.lease_fee_flat_combo')),
                                                                                                data_get($bid, 'get.lease_fee_percentage_combo') ? ($fmtPercent(data_get($bid, 'get.lease_fee_percentage_combo')) . ' of Gross Lease Value') : null,
                                                                                            ]) ?? '—';
                                                                                        } elseif ($bidLeaseFeeType === 'Percentage of the Net Aggregate Rent' && data_get($bid, 'get.lease_fee_percentage_net')) {
                                                                                            $bidLeaseFeeCombined = $fmtPercent(data_get($bid, 'get.lease_fee_percentage_net')) . ' of Net Aggregate Rent';
                                                                                        } elseif (strtolower($bidLeaseFeeType) === 'other' && data_get($bid, 'get.lease_fee_other')) {
                                                                                            $bidLeaseFeeCombined = data_get($bid, 'get.lease_fee_other');
                                                                                        } elseif ($bidLeaseFeeType) {
                                                                                            $bidLeaseFeeCombined = $bidLeaseFeeType;
                                                                                        }
                                                                                    @endphp
                                                                                    <li class="mb-1" style="{{ isset($brokerMismatches['lease_fee_type']) ? $mismatchStyle : '' }}"><span class="fw-semibold">Buyer's Broker Lease Fee:</span> {{ $bidLeaseFeeCombined }}{!! isset($brokerMismatches['lease_fee_type']) ? $mismatchBadge : '' !!}</li>
                                                                                    @endif
                                                                                </ul>
                                                                            </div>
                                                                            @endif

                                                                            <!-- C) Lease-Option Details -->
                                                                            @if (data_get($bid, 'get.interested_lease_option_agreement'))
                                                                            <div class="mb-4">
                                                                                <h6 class="mb-2" style="color: #049399; font-weight: 600;">C) Lease-Option Details</h6>
                                                                                <ul class="list-unstyled ps-3 mb-0">
                                                                                    <li class="mb-1" style="{{ isset($brokerMismatches['interested_lease_option_agreement']) ? $mismatchStyle : '' }}"><span class="fw-semibold">Interested in a Lease-Option Agreement:</span> {{ data_get($bid, 'get.interested_lease_option_agreement') }}{!! isset($brokerMismatches['interested_lease_option_agreement']) ? $mismatchBadge : '' !!}</li>
                                                                                    @if (data_get($bid, 'get.interested_lease_option_agreement') === 'Yes')
                                                                                        @if (data_get($bid, 'get.lease_value'))
                                                                                        <li class="mb-1" style="{{ (isset($brokerMismatches['lease_type']) || isset($brokerMismatches['lease_value'])) ? $mismatchStyle : '' }}"><span class="fw-semibold">Compensation for Creating the Lease-Option Agreement:</span>
                                                                                            @if (data_get($bid, 'get.lease_type') === 'percent')
                                                                                                {{ data_get($bid, 'get.lease_value') }}%
                                                                                            @else
                                                                                                {{ \App\Support\Format::money(data_get($bid, 'get.lease_value')) }}
                                                                                            @endif
                                                                                            {!! (isset($brokerMismatches['lease_type']) || isset($brokerMismatches['lease_value'])) ? $mismatchBadge : '' !!}
                                                                                        </li>
                                                                                        @elseif (isset($brokerMismatches['lease_type']) || isset($brokerMismatches['lease_value']))
                                                                                        <li class="mb-1" style="{{ $mismatchStyle }}"><span class="fw-semibold">Compensation for Creating the Lease-Option Agreement:</span> —{!! $mismatchBadge !!}</li>
                                                                                        @endif
                                                                                        @if (data_get($bid, 'get.purchase_value'))
                                                                                        <li class="mb-1" style="{{ (isset($brokerMismatches['purchase_type']) || isset($brokerMismatches['purchase_value'])) ? $mismatchStyle : '' }}"><span class="fw-semibold">Compensation if Purchase Option is Exercised:</span>
                                                                                            @if (data_get($bid, 'get.purchase_type') === 'percent')
                                                                                                {{ data_get($bid, 'get.purchase_value') }}%
                                                                                            @else
                                                                                                {{ \App\Support\Format::money(data_get($bid, 'get.purchase_value')) }}
                                                                                            @endif
                                                                                            {!! (isset($brokerMismatches['purchase_type']) || isset($brokerMismatches['purchase_value'])) ? $mismatchBadge : '' !!}
                                                                                        </li>
                                                                                        @elseif (isset($brokerMismatches['purchase_type']) || isset($brokerMismatches['purchase_value']))
                                                                                        <li class="mb-1" style="{{ $mismatchStyle }}"><span class="fw-semibold">Compensation if Purchase Option is Exercised:</span> —{!! $mismatchBadge !!}</li>
                                                                                        @endif
                                                                                    @endif
                                                                                </ul>
                                                                            </div>
                                                                            @endif

                                                                            <!-- D) Legal Terms -->
                                                                            @if (data_get($bid, 'get.protection_period') || data_get($bid, 'get.early_termination_fee_option') || data_get($bid, 'get.retainer_fee_option') || data_get($bid, 'get.agency_agreement_timeframe'))
                                                                            <div class="mb-4">
                                                                                <h6 class="mb-2" style="color: #049399; font-weight: 600;">D) Legal Terms</h6>
                                                                                <ul class="list-unstyled ps-3 mb-0">
                                                                                    @if (data_get($bid, 'get.protection_period'))
                                                                                    <li class="mb-1" style="{{ isset($brokerMismatches['protection_period']) ? $mismatchStyle : '' }}"><span class="fw-semibold">Protection Period Timeframe:</span> {{ data_get($bid, 'get.protection_period') }} days{!! isset($brokerMismatches['protection_period']) ? $mismatchBadge : '' !!}</li>
                                                                                    @endif
                                                                                    @if (data_get($bid, 'get.early_termination_fee_option'))
                                                                                    <li class="mb-1" style="{{ isset($brokerMismatches['early_termination_fee_option']) ? $mismatchStyle : '' }}"><span class="fw-semibold">Early Termination Fee:</span> {{ ucfirst(strtolower(data_get($bid, 'get.early_termination_fee_option'))) }}{!! isset($brokerMismatches['early_termination_fee_option']) ? $mismatchBadge : '' !!}</li>
                                                                                    @if (strtolower(data_get($bid, 'get.early_termination_fee_option')) === 'yes' && data_get($bid, 'get.early_termination_fee_amount'))
                                                                                    <li class="mb-1" style="{{ isset($brokerMismatches['early_termination_fee_amount']) ? $mismatchStyle : '' }}"><span class="fw-semibold">Termination Fee Amount:</span> {{ $fmtMoney(data_get($bid, 'get.early_termination_fee_amount')) }}{!! isset($brokerMismatches['early_termination_fee_amount']) ? $mismatchBadge : '' !!}</li>
                                                                                    @elseif (strtolower(data_get($bid, 'get.early_termination_fee_option')) === 'yes' && isset($brokerMismatches['early_termination_fee_amount']))
                                                                                    <li class="mb-1" style="{{ $mismatchStyle }}"><span class="fw-semibold">Termination Fee Amount:</span> —{!! $mismatchBadge !!}</li>
                                                                                    @endif
                                                                                    @endif
                                                                                    @if (data_get($bid, 'get.retainer_fee_option'))
                                                                                    <li class="mb-1" style="{{ isset($brokerMismatches['retainer_fee_option']) ? $mismatchStyle : '' }}"><span class="fw-semibold">Retainer Fee:</span> {{ ucfirst(strtolower(data_get($bid, 'get.retainer_fee_option'))) }}{!! isset($brokerMismatches['retainer_fee_option']) ? $mismatchBadge : '' !!}</li>
                                                                                        @if (strtolower(data_get($bid, 'get.retainer_fee_option')) === 'yes' && data_get($bid, 'get.retainer_fee_amount'))
                                                                                        <li class="mb-1" style="{{ isset($brokerMismatches['retainer_fee_amount']) ? $mismatchStyle : '' }}"><span class="fw-semibold">Retainer Fee Amount:</span> {{ $fmtMoney(data_get($bid, 'get.retainer_fee_amount')) }}{!! isset($brokerMismatches['retainer_fee_amount']) ? $mismatchBadge : '' !!}</li>
                                                                                        @elseif (isset($brokerMismatches['retainer_fee_amount']))
                                                                                        <li class="mb-1" style="{{ $mismatchStyle }}"><span class="fw-semibold">Retainer Fee Amount:</span> —{!! $mismatchBadge !!}</li>
                                                                                        @endif
                                                                                        @if (strtolower(data_get($bid, 'get.retainer_fee_option')) === 'yes' && data_get($bid, 'get.retainer_fee_application'))
                                                                                        @php $bidFormattedRetainer = \App\Support\CompensationFormatter::formatRetainerFeeApplication(data_get($bid, 'get.retainer_fee_application')); @endphp
                                                                                        @if (!empty($bidFormattedRetainer))
                                                                                        <li class="mb-1" style="{{ isset($brokerMismatches['retainer_fee_application']) ? $mismatchStyle : '' }}"><span class="fw-semibold">Retainer Fee Application:</span> {{ $bidFormattedRetainer }}{!! isset($brokerMismatches['retainer_fee_application']) ? $mismatchBadge : '' !!}</li>
                                                                                        @endif
                                                                                        @endif
                                                                                        @if (strtolower(data_get($bid, 'get.retainer_fee_option')) === 'yes' && !data_get($bid, 'get.retainer_fee_application') && isset($brokerMismatches['retainer_fee_application']))
                                                                                        <li class="mb-1" style="{{ $mismatchStyle }}"><span class="fw-semibold">Retainer Fee Application:</span> —{!! $mismatchBadge !!}</li>
                                                                                        @endif
                                                                                    @endif
                                                                                    @if (data_get($bid, 'get.agency_agreement_timeframe'))
                                                                                    <li class="mb-1" style="{{ isset($brokerMismatches['agency_agreement_timeframe']) ? $mismatchStyle : '' }}"><span class="fw-semibold">Buyer Agency Agreement Timeframe:</span> {{ data_get($bid, 'get.agency_agreement_timeframe') === 'custom' ? data_get($bid, 'get.agency_agreement_custom') : data_get($bid, 'get.agency_agreement_timeframe') }}{!! isset($brokerMismatches['agency_agreement_timeframe']) ? $mismatchBadge : '' !!}</li>
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
                                                                                    <li class="mb-1">{{ data_get($bid, 'get.additional_details_broker') }}</li>
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

                                                                        <!-- 3. Additional Details -->
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

                                                                        <!-- 3b. Offered Services -->
                                                                        @php
                                                                            $bidPropType = @$auction->get->property_type ?? 'Residential';

                                                                            $buyerResidentialCategories = [
                                                                                "📣 Buyer Criteria Marketing & Promotion" => [
                                                                                    "Create a branded flyer summarizing the Buyer's purchase criteria",
                                                                                    "Post the Buyer's purchase criteria on Craigslist under the \"Real Estate Wanted\" section",
                                                                                    "Share the Buyer's purchase criteria on Nextdoor in Neighborhood or Community Groups",
                                                                                    "Promote the Buyer's purchase criteria on Facebook in Real Estate or Housing Groups",
                                                                                    "Share the Buyer's purchase criteria on Instagram using posts, stories, or reels",
                                                                                    "Promote the Buyer's purchase criteria on LinkedIn in Real Estate or Housing Groups",
                                                                                    "Upload a TikTok video summarizing the Buyer's purchase criteria",
                                                                                    "Upload a YouTube video summarizing the Buyer's purchase criteria",
                                                                                    "Launch a mass email campaign promoting the Buyer's purchase criteria",
                                                                                    "Distribute branded postcards or flyers in the Buyer's preferred neighborhoods",
                                                                                    "Launch hyperlocal digital ads targeting the Buyer's preferred purchase areas",
                                                                                ],
                                                                                "🔍 Property Search, Alerts & Matching" => [
                                                                                    "Send email alerts with new listings from the MLS that match the Buyer's purchase criteria",
                                                                                    "Search for off-market, pre-market, distressed, withdrawn, canceled, or expired properties that meet the Buyer's purchase criteria",
                                                                                    "Communicate with the Seller's Agent or Seller to confirm availability, purchase terms, and showing instructions",
                                                                                    "Evaluate properties with the Buyer and provide insights on pricing, terms, potential, and overall fit",
                                                                                ],
                                                                                "🏡 Property Showings & Virtual Tours" => [
                                                                                    "Schedule and attend property showings with the Buyer",
                                                                                    "Coordinate or conduct virtual showings via live video or pre-recorded walkthroughs",
                                                                                    "Preview properties on behalf of the Buyer upon request",
                                                                                    "Provide factual observations on property layout and condition",
                                                                                ],
                                                                                "📝 Offer & Contract Coordination" => [
                                                                                    "Draft and submit offers using state-approved purchase forms",
                                                                                    "Provide the Buyer with the necessary disclosure forms required by state or local law",
                                                                                    "Draft and deliver counteroffers and manage revisions to the purchase agreement",
                                                                                    "Negotiate price, deposits, and contingencies with the Seller's Agent or Seller (as permitted under the agency agreement)",
                                                                                    "Manage communications with the Seller's Agent or Seller",
                                                                                    "Assist with in-person or electronic contract signing, including e-signature setup and secure delivery of executed purchase agreements, addenda, and disclosures to all parties",
                                                                                    "Assist with inspection-related negotiations and Buyer requests for repairs",
                                                                                    "Monitor contract milestones, contingency periods, and financing deadlines",
                                                                                    "Provide referrals to Attorneys, Title Companies, Escrow Professionals, or Lenders (referrals only — no endorsement or warranty is made)",
                                                                                ],
                                                                                "📋 Closing Coordination & Transaction Management" => [
                                                                                    "Coordinate inspections, appraisals, and lease audits (if applicable)",
                                                                                    "Coordinate with the Lender, Title, Escrow, and/or Attorney to prepare for Closing",
                                                                                    "Review the Settlement Statement for accuracy and coordinate with relevant parties if corrections are needed (no legal or financial advice provided)",
                                                                                    "Confirm delivery of final executed documents, wire instructions, and Closing paperwork to all relevant parties",
                                                                                    "Schedule and confirm the Final Walkthrough",
                                                                                    "Schedule and confirm the Closing Appointment",
                                                                                ],
                                                                                "💡 Buying Strategy & Guidance" => [
                                                                                    "Provide a Comparative Market Analysis (CMA) with pricing recommendations based on comparable sales, neighborhood trends, and current market conditions (for informational purposes only — not a formal appraisal)",
                                                                                    "Answer general questions about financing, loan options, property taxes, insurance, and escrow timelines (non-legal guidance)",
                                                                                    "Provide factual information about neighborhood characteristics, school zones, crime data, and local amenities using third-party sources (no personal opinions or steering)",
                                                                                    "Offer general guidance on inspection expectations, common repair requests, and contingency planning during the offer process (non-legal advice)",
                                                                                ],
                                                                            ];

                                                                            $buyerIncomeCategories = [
                                                                                "📣 Buyer Criteria Marketing & Promotion" => [
                                                                                    "Create a branded flyer summarizing the Buyer's purchase criteria",
                                                                                    "Post the Buyer's purchase criteria on Craigslist under the \"Real Estate Wanted\" section",
                                                                                    "Share the Buyer's purchase criteria on Nextdoor in Neighborhood or Community Groups",
                                                                                    "Promote the Buyer's purchase criteria on Facebook in Real Estate Investor or Multifamily Groups",
                                                                                    "Share the Buyer's purchase criteria on Instagram using posts, stories, or reels",
                                                                                    "Promote the Buyer's purchase criteria on LinkedIn in Investment or Property Management Groups",
                                                                                    "Upload a TikTok video summarizing the Buyer's purchase criteria",
                                                                                    "Upload a YouTube video summarizing the Buyer's purchase criteria",
                                                                                    "Launch a mass email campaign promoting the Buyer's purchase criteria",
                                                                                    "Distribute branded postcards or flyers in the Buyer's preferred neighborhoods",
                                                                                    "Launch hyperlocal digital ads targeting the Buyer's preferred purchase areas",
                                                                                ],
                                                                                "🔍 Property Search, Alerts & Matching" => [
                                                                                    "Send email alerts with new listings that match the Buyer's purchase criteria",
                                                                                    "Search for off-market, pre-market, distressed, withdrawn, canceled, or expired properties that meet the Buyer's purchase criteria",
                                                                                    "Communicate with the Seller's Agent or Sellers to confirm pricing, rental income, expenses, and showing instructions",
                                                                                    "Evaluate investment properties with the Buyer and provide insights on cash flow, cap rates, and value-add potential",
                                                                                ],
                                                                                "🏘 Property Showings & Virtual Tours" => [
                                                                                    "Schedule and attend property showings with the Buyer",
                                                                                    "Coordinate or conduct virtual showings via live video or pre-recorded walkthroughs",
                                                                                    "Preview properties on behalf of the Buyer upon request",
                                                                                    "Provide observations on tenant occupancy, building condition, and operating expenses",
                                                                                ],
                                                                                "📝 Offer & Contract Management" => [
                                                                                    "Draft and submit offers using state-approved purchase forms",
                                                                                    "Provide the Buyer with the necessary disclosure forms required by state or local law",
                                                                                    "Draft and deliver counteroffers and manage revisions to the purchase agreement",
                                                                                    "Negotiate price, deposits, and contingencies with the Seller's Agent or Seller",
                                                                                    "Manage communication with the Seller's Agent or Seller",
                                                                                    "Assist with in-person or electronic contract signing, including e-signature setup and secure delivery of executed purchase agreements, addenda, and disclosures to all parties",
                                                                                    "Assist with inspection-related negotiations and Buyer requests for repairs",
                                                                                    "Monitor contract milestones, contingency periods, and financing deadlines",
                                                                                    "Provide referrals to Attorneys, Title Companies, Escrow Professionals, Lenders, or 1031 Exchange Intermediaries (referrals only — no endorsement or warranty is made)",
                                                                                ],
                                                                                "📋 Closing Coordination & Transaction Management" => [
                                                                                    "Review and provide due diligence documents such as lease agreements, estoppel certificates, rent rolls, utility summaries, and operating expense breakdowns (as available)",
                                                                                    "Coordinate with the Seller's Agent, Buyer's Lender, Title, Escrow, and/or Attorney to prepare for Closing",
                                                                                    "Review the Settlement Statement for accuracy and coordinate with relevant parties if corrections are needed (no legal or financial advice provided)",
                                                                                    "Confirm delivery of final executed documents, wire instructions, and Closing paperwork to all relevant parties",
                                                                                    "Schedule and confirm the Final Walkthrough",
                                                                                    "Schedule and confirm the Closing Appointment",
                                                                                ],
                                                                                "💡 Buying Strategy & Guidance" => [
                                                                                    "Provide a Comparative Market Analysis (CMA) with pricing recommendations, rental comps, and Cap Rate estimates (for informational purposes only — not a formal appraisal)",
                                                                                    "Answer general questions about financing options, rent control, property taxes, and Landlord responsibilities",
                                                                                    "Provide factual information on rental demand, turnover rates, and sub market conditions using third-party sources",
                                                                                    "Offer general guidance on due diligence steps, lease audits, and estoppel reviews (non-legal advice)",
                                                                                ],
                                                                            ];

                                                                            $buyerCommercialCategories = [
                                                                                "📣 Buyer Criteria Marketing & Promotion" => [
                                                                                    "Create a branded flyer summarizing the Buyer's purchase criteria",
                                                                                    "Post the Buyer's criteria on Craigslist under \"Real Estate Wanted – Commercial\"",
                                                                                    "Promote the Buyer's criteria on Facebook in Commercial Real Estate or Investment Groups",
                                                                                    "Share the Buyer's criteria on Instagram using posts, stories, or reels",
                                                                                    "Promote the Buyer's criteria on LinkedIn in Commercial or Investment Groups",
                                                                                    "Upload a TikTok video summarizing the Buyer's purchase criteria",
                                                                                    "Upload a YouTube video summarizing the Buyer's purchase criteria",
                                                                                    "Launch a mass email campaign promoting the Buyer's purchase criteria",
                                                                                    "Distribute branded postcards or flyers in the Buyer's preferred purchase areas",
                                                                                    "Launch hyperlocal or interest-based digital ad campaigns targeting desired commercial property types",
                                                                                ],
                                                                                "🔍 Property Search, Alerts & Matching" => [
                                                                                    "Send listing alerts from real estate platforms that match the Buyer's purchase criteria",
                                                                                    "Send property alerts that match the Buyer's purchase criteria from the MLS or commercial listing platforms",
                                                                                    "Search for off-market, pre-market, distressed, withdrawn, canceled, or expired listings that meet the Buyer's criteria",
                                                                                    "Communicate with the Seller's Agent or Seller to confirm availability, purchase terms, and showing instructions",
                                                                                    "Analyze building class, property zoning, income potential, and redevelopment opportunities",
                                                                                ],
                                                                                "🏢 Property Showings & Virtual Tours" => [
                                                                                    "Schedule and attend property showings with the Buyer",
                                                                                    "Coordinate or conduct virtual showings via live video or recorded walkthroughs",
                                                                                    "Preview properties on behalf of the Buyer upon request",
                                                                                    "Provide insights on layout, access, visibility, tenant mix, and surrounding infrastructure",
                                                                                ],
                                                                                "📝 Offer & Contract Management" => [
                                                                                    "Draft and submit offers using state-approved purchase agreements or Letters of Intent (LOIs)",
                                                                                    "Provide the Buyer with the necessary disclosure forms required by state or local law",
                                                                                    "Draft and deliver counteroffers and manage revisions to the purchase agreement",
                                                                                    "Negotiate price, deposit structure, timelines, and contingencies with the Seller or Seller's Agent",
                                                                                    "Manage communication with the Seller's Agent or Seller",
                                                                                    "Assist with in-person or electronic contract signing, including e-signature setup and secure delivery of executed purchase agreements, addenda, and disclosures to all parties",
                                                                                    "Assist with due diligence negotiations, including repair requests or credits",
                                                                                    "Monitor contract contingencies, including financing, estoppel review, lease audits, and environmental reports",
                                                                                    "Provide referrals to Attorneys, Title Companies, Escrow Officers, Commercial Lenders, or 1031 Exchange Intermediaries (referrals only — no endorsement or warranty is made)",
                                                                                ],
                                                                                "📋 Closing Coordination & Transaction Management" => [
                                                                                    "Coordinate inspections, appraisals, environmental assessments, and estoppel certificate collection as needed",
                                                                                    "Review and request due diligence documentation such as lease agreements, estoppel certificates, rent rolls, utility summaries, and operating expense breakdowns (as available)",
                                                                                    "Coordinate with the Lender, Title Company, Escrow Officer, and/or Attorney to prepare for Closing",
                                                                                    "Review the Settlement Statement for accuracy and coordinate with all parties if corrections are needed (no legal or financial advice provided)",
                                                                                    "Confirm delivery of final executed documents, wire instructions, and Closing paperwork to all relevant parties",
                                                                                    "Schedule and confirm the Final Walkthrough",
                                                                                    "Schedule and confirm the Closing Appointment",
                                                                                ],
                                                                                "💡 Buying Strategy & Guidance" => [
                                                                                    "Provide a Comparative Market Analysis (CMA) with recent sales comps, lease comps, and an estimated value range (for informational purposes only — not a formal appraisal)",
                                                                                    "Answer general questions about zoning regulations, permitted uses, and rental income potential",
                                                                                    "Provide factual data on traffic counts, commercial market trends, and area demographics using third-party sources (no personal opinions or steering)",
                                                                                    "Offer general guidance on lease types, contingency timelines, due diligence, and environmental risks (non-legal advice only)",
                                                                                ],
                                                                            ];

                                                                            $buyerBusinessCategories = [
                                                                                "📣 Buyer Criteria Marketing & Promotion" => [
                                                                                    "Create a branded flyer summarizing the Buyer's purchase criteria",
                                                                                    "Post the Buyer's purchase criteria on Craigslist under \"Business for Sale\" or \"Real Estate Wanted – Commercial\"",
                                                                                    "Promote the Buyer's purchase criteria on Facebook in Business Opportunity or Franchise Groups",
                                                                                    "Share the Buyer's purchase criteria on Instagram using posts, stories, or reels",
                                                                                    "Promote the Buyer's purchase criteria on LinkedIn in Business, Commercial, or Startup Groups",
                                                                                    "Upload a TikTok video summarizing the Buyer's purchase criteria",
                                                                                    "Upload a YouTube video summarizing the Buyer's purchase criteria",
                                                                                    "Launch a mass email campaign promoting the Buyer's purchase criteria",
                                                                                    "Distribute branded postcards or flyers in the Buyer's preferred neighborhoods",
                                                                                    "Launch hyperlocal digital ads targeting the Buyer's preferred purchase areas",
                                                                                ],
                                                                                "🔍 Business Search, Alerts & Matching" => [
                                                                                    "Send alerts for businesses that match the Buyer's acquisition criteria from MLS, BizBuySell, or other listing platforms",
                                                                                    "Send alerts for businesses that match the Buyer's acquisition criteria from available business listing sources",
                                                                                    "Search for off-market, pre-market, distressed, or recently closed businesses that meet the Buyer's criteria",
                                                                                    "Communicate with the Seller's Broker or Seller to confirm pricing, lease terms, licensing status, and showing availability",
                                                                                    "Analyze financials, lease assignments, business licensing requirements, and overall market positioning",
                                                                                ],
                                                                                "🏢 Property Showings & Virtual Tours" => [
                                                                                    "Schedule and attend property or business showings with the Buyer",
                                                                                    "Coordinate or conduct virtual showings via live video or pre-recorded walkthroughs",
                                                                                    "Preview properties or business locations on behalf of the Buyer upon request",
                                                                                    "Provide insights on foot traffic, customer base, operational setup, competitive advantages, and location dynamics",
                                                                                ],
                                                                                "📝 Offer & Contract Management" => [
                                                                                    "Draft and submit offers using appropriate business purchase or asset sale forms",
                                                                                    "Provide the Buyer with required disclosures, financial summaries, and documentation made available by the Seller",
                                                                                    "Negotiate terms such as purchase price, deposit structure, inventory inclusions, non-compete agreements, and contingencies",
                                                                                    "Draft and deliver counteroffers and manage revisions to the purchase agreement",
                                                                                    "Manage communication with the Seller's Broker or Seller",
                                                                                    "Assist with in-person or electronic contract signing, including e-signature setup and secure delivery of executed purchase agreements, addenda, and disclosures to all parties",
                                                                                    "Assist with due diligence coordination, Buyer-requested repairs, and adjustment negotiations",
                                                                                    "Monitor contingency periods, financing milestones, and deal approval timelines",
                                                                                    "Provide referrals to Business Attorneys, CPAs, Escrow Officers, or Lenders (referrals only — no endorsement or warranty is made)",
                                                                                ],
                                                                                "📋 Closing Coordination & Transaction Management" => [
                                                                                    "Coordinate inspections, licensing verifications, lease assignments, and inventory counts",
                                                                                    "Coordinate with Lenders, Attorneys, Escrow Officers, Title Companies, CPAs, and other involved parties to prepare for Closing",
                                                                                    "Review the Settlement Statement or Closing Worksheet for accuracy and coordinate with all parties if corrections are needed (no legal or financial advice provided)",
                                                                                    "Confirm delivery of final executed documents, wire instructions, and business transition materials",
                                                                                    "Schedule and confirm the Final Walkthrough",
                                                                                    "Schedule and confirm the Closing Appointment",
                                                                                ],
                                                                                "💡 Buying Strategy & Guidance" => [
                                                                                    "Provide a Comparative Market Review based on similar business sales, financial performance, and industry benchmarks (for informational purposes only — not a formal appraisal or valuation)",
                                                                                    "Answer general questions about licensing, zoning, SBA financing, registration steps, and transition timing (non-legal guidance)",
                                                                                    "Offer general guidance on due diligence preparation, key documents to review, and red flags during the acquisition process (non-legal advice only)",
                                                                                ],
                                                                            ];

                                                                            $buyerVacantLandCategories = [
                                                                                "📣 Buyer Criteria Marketing & Promotion" => [
                                                                                    "Create a branded flyer summarizing the Buyer's purchase criteria",
                                                                                    "Post the Buyer's criteria on Craigslist under \"Real Estate Wanted – Land\"",
                                                                                    "Share the Buyer's criteria on Nextdoor in Neighborhood or Rural Groups",
                                                                                    "Promote the Buyer's criteria on Facebook in Land Buyers, Developers, or Homesteader Groups",
                                                                                    "Share the Buyer's criteria on Instagram using posts, stories, or reels",
                                                                                    "Promote the Buyer's criteria on LinkedIn in Land Acquisition or Investment Groups",
                                                                                    "Upload a TikTok video summarizing the Buyer's purchase criteria",
                                                                                    "Upload a YouTube video summarizing the Buyer's purchase criteria",
                                                                                    "Launch a mass email campaign promoting the Buyer's purchase criteria",
                                                                                    "Distribute branded postcards or flyers in the Buyer's preferred neighborhoods",
                                                                                    "Launch hyperlocal digital ads targeting the Buyer's preferred purchase areas",
                                                                                ],
                                                                                "🔍 Property Search, Alerts & Matching" => [
                                                                                    "Send property alerts for land listings that match the Buyer's goals from MLS and land-specific platforms",
                                                                                    "Send property alerts for land listings that match the Buyer's goals from relevant real estate and land-specific platforms",
                                                                                    "Search for off-market, pre-market, distressed, withdrawn, canceled, or expired properties that meet the Buyer's purchase criteria",
                                                                                    "Communicate with the Seller's Agent or Seller to confirm zoning, access, utilities, and pricing",
                                                                                    "Assess development feasibility, land use restrictions, or agricultural potential (non-legal advice)",
                                                                                ],
                                                                                "🏡 Property Showings & Virtual Tours" => [
                                                                                    "Schedule and attend land visits with the Buyer",
                                                                                    "Coordinate or conduct virtual walkthroughs using maps, aerials, and site photos",
                                                                                    "Preview parcels on behalf of the Buyer upon request",
                                                                                    "Provide observations on topography, road frontage, and surrounding land uses",
                                                                                ],
                                                                                "📜 Offer & Contract Management" => [
                                                                                    "Draft and submit offers using state-approved purchase forms",
                                                                                    "Provide the Buyer with required state or local disclosure forms",
                                                                                    "Draft and deliver counteroffers and manage revisions to the purchase agreement",
                                                                                    "Negotiate price, deposits, and contingencies (as permitted under the agency agreement)",
                                                                                    "Manage communication with the Seller's Agent or Seller",
                                                                                    "Assist with in-person or electronic contract signing, including e-signature setup and secure delivery of executed documents to all parties",
                                                                                    "Assist with due diligence coordination, including survey review, soil testing, zoning checks, and permit verification (non-legal guidance only)",
                                                                                    "Monitor contract milestones, contingency deadlines, and financing timelines",
                                                                                    "Provide referrals to Attorneys, Title Companies, Escrow Officers, Surveyors, or Land Use Consultants (referrals only — no endorsement or warranty is made)",
                                                                                ],
                                                                                "📋 Closing Coordination & Transaction Management" => [
                                                                                    "Coordinate surveys, appraisals, inspections, and environmental assessments",
                                                                                    "Coordinate with the Lender, Title Company, Escrow Officer, and/or Attorney to prepare for Closing",
                                                                                    "Review the Settlement Statement for accuracy and coordinate with all parties if corrections are needed (no legal or financial advice provided)",
                                                                                    "Confirm delivery of final executed documents, wire instructions, and Closing paperwork to all relevant parties",
                                                                                    "Schedule and confirm the Final Walkthrough",
                                                                                    "Schedule and confirm the Closing Appointment",
                                                                                ],
                                                                                "💡 Buying Strategy & Guidance" => [
                                                                                    "Provide a Comparative Market Analysis (CMA) based on recent land sales, acreage comps, and price-per-acre benchmarks (for informational purposes only — not a formal appraisal)",
                                                                                    "Answer general questions about zoning, utilities, development potential, and environmental constraints (non-legal guidance only)",
                                                                                    "Provide factual data on flood zones, wetlands, and land use maps using third-party sources (no legal or engineering advice)",
                                                                                    "Offer general guidance on feasibility timelines, inspection steps, and rural financing considerations (non-legal advice only)",
                                                                                ],
                                                                            ];

                                                                            if ($bidPropType === 'Income') {
                                                                                $buyerCategories = $buyerIncomeCategories;
                                                                            } elseif ($bidPropType === 'Commercial') {
                                                                                $buyerCategories = $buyerCommercialCategories;
                                                                            } elseif ($bidPropType === 'Business') {
                                                                                $buyerCategories = $buyerBusinessCategories;
                                                                            } elseif ($bidPropType === 'Vacant Land') {
                                                                                $buyerCategories = $buyerVacantLandCategories;
                                                                            } else {
                                                                                $buyerCategories = $buyerResidentialCategories;
                                                                            }

                                                                            $flattenBuyer = function($data) use (&$flattenBuyer) {
                                                                                $result = [];
                                                                                if (is_array($data) || is_object($data)) {
                                                                                    foreach ((array)$data as $value) {
                                                                                        if (is_string($value) && !empty(trim($value)) && $value !== 'Other') {
                                                                                            $result[] = trim($value);
                                                                                        } elseif (is_array($value) || is_object($value)) {
                                                                                            $result = array_merge($result, $flattenBuyer($value));
                                                                                        }
                                                                                    }
                                                                                } elseif (is_string($data) && !empty(trim($data)) && $data !== 'Other') {
                                                                                    $result[] = trim($data);
                                                                                }
                                                                                return $result;
                                                                            };

                                                                            $rawBuyerServices = data_get($bid, 'get.services', []);
                                                                            if (is_string($rawBuyerServices) && !empty($rawBuyerServices)) {
                                                                                $decodedBuyer = json_decode($rawBuyerServices, true);
                                                                                $parsedBuyerServices = (json_last_error() === JSON_ERROR_NONE && is_array($decodedBuyer)) ? $decodedBuyer : [];
                                                                            } elseif (is_array($rawBuyerServices) || is_object($rawBuyerServices)) {
                                                                                $parsedBuyerServices = $rawBuyerServices;
                                                                            } else {
                                                                                $parsedBuyerServices = [];
                                                                            }
                                                                            $buyerAllServices = array_unique($flattenBuyer($parsedBuyerServices));

                                                                            $rawBuyerOther = data_get($bid, 'get.other_services', []);
                                                                            if (is_string($rawBuyerOther) && !empty($rawBuyerOther)) {
                                                                                $decodedBuyerOther = json_decode($rawBuyerOther, true);
                                                                                $buyerOtherServices = (json_last_error() === JSON_ERROR_NONE && is_array($decodedBuyerOther)) ? $decodedBuyerOther : [];
                                                                            } elseif (is_array($rawBuyerOther) || is_object($rawBuyerOther)) {
                                                                                $buyerOtherServices = (array)$rawBuyerOther;
                                                                            } else {
                                                                                $buyerOtherServices = [];
                                                                            }
                                                                            $buyerOtherServices = array_values(array_filter($buyerOtherServices, fn($s) => is_string($s) && !empty(trim($s))));

                                                                            $hasBuyerServices = !empty($buyerAllServices) || !empty($buyerOtherServices);

                                                                            // Normalize for badge checks
                                                                            $normBuyerSvc = fn($s) => mb_strtolower(trim(str_replace(
                                                                                ["\u{2018}", "\u{2019}", "\u{201C}", "\u{201D}"],
                                                                                ["'", "'", '"', '"'],
                                                                                $s
                                                                            )));
                                                                            $buyerExtraSvcNorm   = array_map($normBuyerSvc, $extraServices ?? []);
                                                                            $buyerMissingSvcNorm = array_map($normBuyerSvc, $missingServices ?? []);
                                                                            $checkBuyerSvcIsExtra = fn($svc) => in_array($normBuyerSvc($svc), $buyerExtraSvcNorm, true);

                                                                            $buyerSvcAddedStyle   = 'background-color: #d4edda; padding: 2px 6px; border-radius: 4px; border-left: 3px solid #28a745;';
                                                                            $buyerSvcAddedBadge   = '<span class="badge bg-success ms-2" style="font-size: 0.65rem; vertical-align: middle;">Extra Service Offered</span>';
                                                                            $buyerSvcMissingStyle = 'background-color: #ffe6e6; padding: 2px 6px; border-radius: 4px; border-left: 3px solid #dc3545; text-decoration: line-through; color: #721c24;';
                                                                            $buyerSvcMissingBadge = '<span class="badge bg-danger ms-2" style="font-size: 0.65rem; vertical-align: middle;">Not Offered by Agent</span>';
                                                                        @endphp

                                                                        @if ($hasBuyerServices)
                                                                        <div class="mb-5">
                                                                            <h6 class="section-header">
                                                                                <i class="fa-solid fa-clipboard-list me-2"></i>Offered Services
                                                                            </h6>
                                                                            @php
                                                                                $normalizeSvcStr = function(string $s): string {
                                                                                    $s = preg_replace('/[\x{2018}\x{2019}]/u', "'", $s);
                                                                                    $s = preg_replace('/[\x{201C}\x{201D}]/u', '"', $s);
                                                                                    $s = preg_replace('/[\x{2013}\x{2014}]/u', '-', $s);
                                                                                    return trim($s);
                                                                                };
                                                                            @endphp
                                                                            @foreach ($buyerCategories as $catName => $catServices)
                                                                                @php
                                                                                    $matchedBuyerSvcs = array_values(array_filter($buyerAllServices, function($svc) use ($catServices, $normalizeSvcStr) {
                                                                                        $normSvc = $normalizeSvcStr($svc);
                                                                                        foreach ($catServices as $catEntry) {
                                                                                            $normCat = $normalizeSvcStr($catEntry);
                                                                                            if ($normCat === $normSvc) return true;
                                                                                            // Catalog entry has extra parenthetical appended: stored string is a prefix
                                                                                            if (str_starts_with($normCat, $normSvc)) return true;
                                                                                            // Stored string has extra text: catalog string is a prefix
                                                                                            if (str_starts_with($normSvc, $normCat)) return true;
                                                                                        }
                                                                                        return false;
                                                                                    }));
                                                                                @endphp
                                                                                @if (!empty($matchedBuyerSvcs))
                                                                                <div class="mb-3">
                                                                                    <div class="fw-bold" style="color: #34465c; font-size: 0.95rem;">{{ $catName }}</div>
                                                                                    <ul class="mb-0" style="margin-top: 0.25rem; padding-left: 1.5rem; list-style: disc;">
                                                                                        @foreach ($matchedBuyerSvcs as $svc)
                                                                                            @php
                                                                                                $displayBuyerSvc = function_exists('normalize_service_text') ? normalize_service_text($svc) : $svc;
                                                                                                $buyerSvcIsExtra = $checkBuyerSvcIsExtra($svc);
                                                                                            @endphp
                                                                                            <li style="font-size: 0.9rem; margin-bottom: 4px; {{ $buyerSvcIsExtra ? $buyerSvcAddedStyle : '' }}">
                                                                                                {{ $displayBuyerSvc }}{!! $buyerSvcIsExtra ? $buyerSvcAddedBadge : '' !!}
                                                                                            </li>
                                                                                        @endforeach
                                                                                    </ul>
                                                                                </div>
                                                                                @endif
                                                                            @endforeach
                                                                            @if (!empty($buyerOtherServices))
                                                                            <div class="mb-3">
                                                                                <div class="fw-bold" style="color: #34465c; font-size: 0.95rem;">✍️ Additional Services</div>
                                                                                <ul class="mb-0" style="margin-top: 0.25rem; padding-left: 1.5rem; list-style: disc;">
                                                                                    @foreach ($buyerOtherServices as $otherSvc)
                                                                                        @php
                                                                                            $displayBuyerOther = function_exists('normalize_service_text') ? normalize_service_text($otherSvc) : $otherSvc;
                                                                                            $buyerOtherIsExtra = $checkBuyerSvcIsExtra($otherSvc);
                                                                                        @endphp
                                                                                        <li style="font-size: 0.9rem; margin-bottom: 4px; {{ $buyerOtherIsExtra ? $buyerSvcAddedStyle : '' }}">
                                                                                            {{ $displayBuyerOther }}{!! $buyerOtherIsExtra ? $buyerSvcAddedBadge : '' !!}
                                                                                        </li>
                                                                                    @endforeach
                                                                                </ul>
                                                                            </div>
                                                                            @endif
                                                                            @if (!empty($missingServices))
                                                                            <div class="mt-4 p-3" style="background-color: #ffe6e6; border-radius: 8px; border: 1px solid #dc3545;">
                                                                                <div class="fw-bold mb-2" style="color: #721c24; font-size: 0.95rem;">
                                                                                    <i class="fa-solid fa-circle-xmark me-2"></i>Services Requested But Agent Did Not Include ({{ count($missingServices) }})
                                                                                </div>
                                                                                <ul class="mb-0" style="padding-left: 1.5rem; list-style: disc;">
                                                                                    @foreach ($missingServices as $buyerMissingSvc)
                                                                                        <li style="font-size: 0.9rem; margin-bottom: 4px; {{ $buyerSvcMissingStyle }}">{{ ucfirst($buyerMissingSvc) }}{!! $buyerSvcMissingBadge !!}</li>
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
                                                                                data_get($bid, 'get.promoMaterials'))
                                                                            <div class="mb-5">
                                                                                <h6 class="section-header">
                                                                                    <i
                                                                                        class="fa-solid fa-chart-line me-2"></i>Agent
                                                                                    Presentation & Promotional Materials
                                                                                </h6>

                                                                                <!-- Virtual Presentation Section -->
                                                                                @if (data_get($bid, 'get.presentation_link') || data_get($bid, 'get.video_upload'))
                                                                                    <div class="mb-4">
                                                                                        <div class="fw-semibold mb-2"
                                                                                            style="color: #049399;">Virtual
                                                                                            Agent Presentation</div>

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
                                                                                            Business Card</div>

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
                                                                                                    if (is_array($rawBusinessCard)) {
                                                                                                        $rawBusinessCard = $rawBusinessCard['path'] ?? $rawBusinessCard['file'] ?? $rawBusinessCard['url'] ?? (reset($rawBusinessCard) ?: null);
                                                                                                    }
                                                                                                    $normalizedBusinessCard = is_string($rawBusinessCard) ? $rawBusinessCard : null;
                                                                                                @endphp
                                                                                                @if ($normalizedBusinessCard)
                                                                                                    @php
                                                                                                        $businessCardPath = $normalizedBusinessCard;
                                                                                                        $businessCardExtension = pathinfo($businessCardPath, PATHINFO_EXTENSION);
                                                                                                        $businessCardUrl = asset('storage/' . $businessCardPath);
                                                                                                    @endphp

                                                                                                    @if (in_array(strtolower($businessCardExtension), ['jpg', 'jpeg', 'png', 'gif', 'webp']))
                                                                                                        <div class="business-card-preview mb-2">
                                                                                                            <a href="{{ $businessCardUrl }}" target="_blank" rel="noopener noreferrer" title="Click to view full size">
                                                                                                                <img src="{{ $businessCardUrl }}"
                                                                                                                    style="max-width: 450px; width: 100%; height: auto; border-radius: 8px; border: 2px solid #e0e0e0; cursor: pointer; box-shadow: 0 2px 8px rgba(0,0,0,0.1);"
                                                                                                                    alt="Business Card"
                                                                                                                    class="img-fluid">
                                                                                                            </a>
                                                                                                        </div>
                                                                                                        <div class="d-flex gap-2 mt-2">
                                                                                                            <a href="{{ $businessCardUrl }}" target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary btn-sm">
                                                                                                                <i class="fa-solid fa-expand me-1"></i> View Full Size
                                                                                                            </a>
                                                                                                            <a href="{{ $businessCardUrl }}" download class="btn btn-outline-success btn-sm">
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
                                                                                                            <a href="{{ $businessCardUrl }}" download class="btn btn-outline-primary btn-sm">
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
                                                                                    <div>
                                                                                        <div class="fw-semibold mb-2"
                                                                                            style="color: #049399;">
                                                                                            Marketing Materials</div>

                                                                                        @foreach ($promoMaterialsNormalized as $index => $material)
                                                                                            @php
                                                                                                $matFiles = data_get($material, 'files', []);
                                                                                                if (is_object($matFiles)) { $matFiles = (array) $matFiles; }
                                                                                                elseif (is_string($matFiles)) { $matFiles = $matFiles !== '' ? [$matFiles] : []; }
                                                                                                elseif (!is_array($matFiles)) { $matFiles = []; }
                                                                                            @endphp
                                                                                            @if (!empty($material['type']) || !empty($material['link']) || !empty($matFiles))
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

                                                                                                    @if (!empty($matFiles))
                                                                                                        <div
                                                                                                            class="mb-2">
                                                                                                            <div class="fw-medium mb-1"
                                                                                                                style="color: #049399;">
                                                                                                                Uploaded
                                                                                                                Files:</div>
                                                                                                            <div
                                                                                                                class="row">
                                                                                                                @foreach ($matFiles as $fileIndex => $rawFilePath)
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

                                                                                                                        @php $fileUrl = asset('storage/' . $filePath); @endphp
                                                                                                                        <div class="col-md-6 mb-2">
                                                                                                                            <div class="border rounded p-2 bg-white d-flex align-items-center">
                                                                                                                                @if ($isImage)
                                                                                                                                    <a href="{{ $fileUrl }}" target="_blank" rel="noopener noreferrer">
                                                                                                                                        <img src="{{ $fileUrl }}"
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
                                                                                                                                    <a href="{{ $fileUrl }}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary" title="View">
                                                                                                                                        <i class="fa-solid fa-eye"></i>
                                                                                                                                    </a>
                                                                                                                                    <a href="{{ $fileUrl }}" download class="btn btn-sm btn-outline-success" title="Download">
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
                                                                                    </div>
                                                                                @endif
                                                                            </div>
                                                                        @endif

                                                                        <!-- 5. Agent Information -->
                                                                        <div class="mb-4">
                                                                            <h6 class="section-header">
                                                                                <i
                                                                                    class="fa-solid fa-address-card me-2"></i>Agent
                                                                                Information
                                                                            </h6>

                                                                            <div class="row">
                                                                                <!-- First Name -->
                                                                                @if (data_get($bid, 'get.first_name'))
                                                                                    <div class="col-md-6 mb-2">
                                                                                        <div class="fw-semibold"
                                                                                            style="color: #049399;">First
                                                                                            Name</div>
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
                                                                                            Name</div>
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
                                                                                            Number</div>
                                                                                        <div class="text-muted">
                                                                                            {{ data_get($bid, 'get.phone') }}
                                                                                        </div>
                                                                                    </div>
                                                                                @endif

                                                                                <!-- Email -->
                                                                                @if (data_get($bid, 'get.email'))
                                                                                    <div class="col-md-6 mb-2">
                                                                                        <div class="fw-semibold"
                                                                                            style="color: #049399;">Email
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
                                                                                            Brokerage</div>
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
                                                                                            Estate License #</div>
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
                                                                                            Member ID</div>
                                                                                        <div class="text-muted">
                                                                                            {{ data_get($bid, 'get.nar_id') }}
                                                                                        </div>
                                                                                    </div>
                                                                                @endif
                                                                            </div>
                                                                        </div>

