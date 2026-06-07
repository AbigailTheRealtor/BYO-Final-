{{--
    Estimated Monthly Payment Calculator
    Receives: $calcData (array with listing values and admin defaults)
--}}
@php
    $price          = isset($calcData['price'])          && $calcData['price']          ? (float) $calcData['price']          : 0;
    $hoaMonthly     = isset($calcData['hoa_monthly'])    && $calcData['hoa_monthly']    ? (float) $calcData['hoa_monthly']    : 0;
    $taxesAnnual    = isset($calcData['taxes_annual'])   && $calcData['taxes_annual']   ? (float) $calcData['taxes_annual']   : 0;

    $interestRate   = isset($calcData['interest_rate'])  ? (float) $calcData['interest_rate']  : 6.5;
    $downPct        = isset($calcData['down_pct'])       ? (float) $calcData['down_pct']       : 10;
    $loanTerm       = isset($calcData['loan_term'])      ? (int)   $calcData['loan_term']      : 30;
    $taxRate        = isset($calcData['tax_rate'])       ? (float) $calcData['tax_rate']       : 1.1;
    $insuranceRate  = isset($calcData['insurance_rate']) ? (float) $calcData['insurance_rate'] : 0.5;
    $pmiRate        = isset($calcData['pmi_rate'])       ? (float) $calcData['pmi_rate']       : 0.85;

    // Agent monthly insurance override — when set, used instead of rate-based calculation
    $insuranceMonthlyOverride = isset($calcData['insurance_monthly_override']) && $calcData['insurance_monthly_override'] !== null
        ? (float) $calcData['insurance_monthly_override']
        : null;

    // show_buydown_options defaults to true; false hides the Advanced Options section entirely
    $showBuydownOptions = isset($calcData['show_buydown_options']) ? (bool) $calcData['show_buydown_options'] : true;

    $priceSource       = $calcData['price_source']      ?? 'estimated';
    $hoaSource         = $calcData['hoa_source']        ?? 'estimated';
    $hoaAssumed        = $calcData['hoa_assumed']       ?? false;
    $taxesSource       = $calcData['taxes_source']      ?? 'estimated';
    $insuranceSource   = $calcData['insurance_source']  ?? 'estimated';

    $hoaBadge = $hoaAssumed ? 'assumed monthly' : $hoaSource;
@endphp

<div id="mortgage-calc-widget" class="mt-3 mb-2">

    {{-- Collapsed Summary --}}
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <span class="fw-bold">Estimated Monthly Payment:</span>
        <span id="calc-summary-amount" class="text-success fw-bold fs-5">$—/mo</span>
        <button type="button"
                id="calc-toggle-btn"
                class="btn btn-sm btn-outline-secondary ms-2"
                style="font-size:0.8rem;"
                aria-expanded="false"
                aria-controls="calc-expand-panel">
            Customize Payment ▾
        </button>
    </div>

    {{-- Expand/Collapse Panel --}}
    <div id="calc-expand-panel" style="display:none;" class="border rounded p-3 mt-2 bg-light">

        <div class="row g-2">

            {{-- Purchase Price --}}
            <div class="col-md-6">
                <label class="form-label mb-0" style="font-size:0.85rem;">
                    Purchase Price
                    <span class="badge bg-secondary ms-1" style="font-size:0.7rem;">{{ $priceSource }}</span>
                </label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text">$</span>
                    <input type="number" id="calc-price" class="form-control"
                           value="{{ $price > 0 ? $price : '' }}" min="1" step="1000">
                </div>
            </div>

            {{-- Down Payment % --}}
            <div class="col-md-3">
                <label class="form-label mb-0" style="font-size:0.85rem;">Down Payment %</label>
                <div class="input-group input-group-sm">
                    <input type="number" id="calc-down-pct" class="form-control"
                           value="{{ $downPct }}" min="0" max="100" step="0.5">
                    <span class="input-group-text">%</span>
                </div>
            </div>

            {{-- Down Payment $ --}}
            <div class="col-md-3">
                <label class="form-label mb-0" style="font-size:0.85rem;">Down Payment $</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text">$</span>
                    <input type="number" id="calc-down-dollar" class="form-control"
                           value="0" min="0" step="1000">
                </div>
            </div>

            {{-- Interest Rate --}}
            <div class="col-md-3">
                <label class="form-label mb-0" style="font-size:0.85rem;">Interest Rate</label>
                <div class="input-group input-group-sm">
                    <input type="number" id="calc-rate" class="form-control"
                           value="{{ $interestRate }}" min="0" max="30" step="0.125">
                    <span class="input-group-text">%</span>
                </div>
            </div>

            {{-- Loan Term --}}
            <div class="col-md-3">
                <label class="form-label mb-0" style="font-size:0.85rem;">Loan Term (years)</label>
                <input type="number" id="calc-term" class="form-control form-control-sm"
                       value="{{ $loanTerm }}" min="1" max="50" step="1">
            </div>

            {{-- Monthly Taxes --}}
            <div class="col-md-3">
                <label class="form-label mb-0" style="font-size:0.85rem;">
                    Monthly Taxes
                    <span id="calc-taxes-badge" class="badge bg-secondary ms-1" style="font-size:0.7rem;">{{ $taxesSource }}</span>
                </label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text">$</span>
                    <input type="number" id="calc-taxes" class="form-control"
                           value="{{ $taxesAnnual > 0 ? round($taxesAnnual / 12, 2) : 0 }}" min="0" step="1">
                </div>
            </div>

            {{-- Insurance --}}
            <div class="col-md-3">
                <label class="form-label mb-0" style="font-size:0.85rem;">
                    Monthly Insurance
                    <span class="badge bg-secondary ms-1" style="font-size:0.7rem;">{{ $insuranceSource }}</span>
                </label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text">$</span>
                    <input type="number" id="calc-insurance" class="form-control"
                           value="0" min="0" step="1">
                </div>
            </div>

            {{-- HOA --}}
            <div class="col-md-3">
                <label class="form-label mb-0" style="font-size:0.85rem;">
                    Monthly HOA
                    <span class="badge bg-secondary ms-1" style="font-size:0.7rem;">{{ $hoaBadge }}</span>
                </label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text">$</span>
                    <input type="number" id="calc-hoa" class="form-control"
                           value="{{ $hoaMonthly }}" min="0" step="1">
                </div>
            </div>

            {{-- PMI --}}
            <div class="col-md-3">
                <label class="form-label mb-0" style="font-size:0.85rem;">Monthly PMI</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text">$</span>
                    <input type="number" id="calc-pmi" class="form-control"
                           value="0" min="0" step="1">
                </div>
            </div>

        </div>{{-- .row --}}

        {{-- Breakdown --}}
        <div class="mt-3 border-top pt-2">
            <ul class="list-unstyled mb-0" style="font-size:0.9rem;">
                <li class="d-flex justify-content-between"><span>Principal &amp; Interest</span><span id="calc-pi">$—</span></li>
                <li class="d-flex justify-content-between"><span>Monthly Taxes</span><span id="calc-breakdown-taxes">$—</span></li>
                <li class="d-flex justify-content-between"><span>Monthly Insurance</span><span id="calc-breakdown-insurance">$—</span></li>
                <li class="d-flex justify-content-between"><span>HOA</span><span id="calc-breakdown-hoa">$—</span></li>
                <li class="d-flex justify-content-between"><span>PMI</span><span id="calc-breakdown-pmi">$—</span></li>
                <li class="d-flex justify-content-between fw-bold border-top pt-1 mt-1">
                    <span>Estimated Total</span><span id="calc-total">$—/mo</span>
                </li>
            </ul>
        </div>

        {{-- Advanced Options Accordion — hidden when agent sets show_buydown_options = false --}}
        @if ($showBuydownOptions)
        <div class="mt-3">
            <button type="button"
                    id="calc-adv-toggle"
                    class="btn btn-sm btn-link text-decoration-none ps-0"
                    style="font-size:0.85rem;"
                    aria-expanded="false"
                    aria-controls="calc-adv-panel">
                ▶ Advanced Options (Rate Buydown)
            </button>
            <div id="calc-adv-panel" style="display:none;" class="mt-2">

                <div class="col-md-6 mb-2">
                    <label class="form-label mb-0" style="font-size:0.85rem;">Buydown Type</label>
                    <select id="calc-buydown-type" class="form-select form-select-sm">
                        <option value="none">None</option>
                        <option value="permanent">Permanent</option>
                        <option value="1-0">1-0 Temporary</option>
                        <option value="2-1">2-1 Temporary</option>
                        <option value="3-2-1">3-2-1 Temporary</option>
                    </select>
                </div>

                <div id="calc-permanent-row" style="display:none;" class="col-md-4 mb-2">
                    <label class="form-label mb-0" style="font-size:0.85rem;">Reduced Rate</label>
                    <div class="input-group input-group-sm">
                        <input type="number" id="calc-perm-rate" class="form-control"
                               value="0" min="0" max="30" step="0.125">
                        <span class="input-group-text">%</span>
                    </div>
                </div>

                <div id="calc-buydown-table-wrap" style="display:none;" class="mt-2">
                    <table class="table table-sm table-bordered mb-0" style="font-size:0.82rem;">
                        <thead class="table-light">
                            <tr>
                                <th>Year</th>
                                <th>Rate</th>
                                <th>Est. Payment (P&amp;I)</th>
                                <th>Monthly Savings</th>
                            </tr>
                        </thead>
                        <tbody id="calc-buydown-tbody"></tbody>
                    </table>
                </div>

                <div id="calc-temp-disclaimer"
                     style="display:none; font-size:0.8rem;"
                     class="alert alert-warning p-2 mt-2 mb-0">
                    <small>Temporary buydowns may require seller, lender, or third-party credits and are subject to lender approval. This estimate is for informational purposes only and is not a loan quote.</small>
                </div>

            </div>{{-- #calc-adv-panel --}}
        </div>{{-- Advanced Options --}}
        @endif

    </div>{{-- #calc-expand-panel --}}

    {{-- General Disclaimer --}}
    <p class="text-muted mt-1 mb-0" style="font-size:0.75rem;">
        Estimated payment is for informational purposes only and does not constitute a loan offer or guarantee of financing. Actual costs will vary.
    </p>

</div>{{-- #mortgage-calc-widget --}}

<script>
(function () {
    var PRICE_INIT        = {{ $price > 0 ? $price : 0 }};
    var DOWN_PCT_INIT     = {{ $downPct }};
    var RATE_INIT         = {{ $interestRate }};
    var TERM_INIT         = {{ $loanTerm }};
    var TAXES_ANNUAL_INIT = {{ $taxesAnnual }};
    var HOA_MONTHLY_INIT  = {{ $hoaMonthly }};
    var TAX_RATE          = {{ $taxRate }};
    var INS_RATE          = {{ $insuranceRate }};
    var PMI_RATE          = {{ $pmiRate }};
    var INS_MONTHLY_OVERRIDE = {{ $insuranceMonthlyOverride !== null ? (int) round($insuranceMonthlyOverride) : 'null' }};
    var SHOW_BUYDOWN      = {{ $showBuydownOptions ? 'true' : 'false' }};

    // True when taxes came from the listing or agent override — don't recalculate on price change
    var TAXES_FROM_LISTING = {{ $taxesAnnual > 0 ? 'true' : 'false' }};

    function clamp(val, min, max) {
        if (isNaN(val) || val === null) return min;
        if (max !== undefined && val > max) return max;
        if (val < min) return min;
        return val;
    }

    function fmtMoney(n) {
        return '$' + Math.round(n).toLocaleString('en-US');
    }

    function calcPI(principal, annualRate, termYears) {
        if (annualRate <= 0) return principal / (termYears * 12);
        var r = annualRate / 100 / 12;
        var n = termYears * 12;
        return principal * (r * Math.pow(1 + r, n)) / (Math.pow(1 + r, n) - 1);
    }

    function get(id) { return document.getElementById(id); }
    function val(id) { return parseFloat(get(id).value) || 0; }

    function recalc() {
        var price     = clamp(val('calc-price'), 1);
        var downDollar = clamp(val('calc-down-dollar'), 0, price);
        var rate      = clamp(val('calc-rate'), 0);
        var term      = clamp(val('calc-term'), 1);
        var taxes     = clamp(val('calc-taxes'), 0);
        var insurance = clamp(val('calc-insurance'), 0);
        var hoa       = clamp(val('calc-hoa'), 0);
        var pmi       = clamp(val('calc-pmi'), 0);

        var principal = price - downDollar;
        if (principal < 0) principal = 0;

        var pi = calcPI(principal, rate, term);

        var total = pi + taxes + insurance + hoa + pmi;

        get('calc-pi').textContent                  = fmtMoney(pi) + '/mo';
        get('calc-breakdown-taxes').textContent     = fmtMoney(taxes) + '/mo';
        get('calc-breakdown-insurance').textContent = fmtMoney(insurance) + '/mo';
        get('calc-breakdown-hoa').textContent       = fmtMoney(hoa) + '/mo';
        get('calc-breakdown-pmi').textContent       = fmtMoney(pmi) + '/mo';
        get('calc-total').textContent               = fmtMoney(total) + '/mo';
        get('calc-summary-amount').textContent      = fmtMoney(total) + '/mo';

        if (SHOW_BUYDOWN) {
            recalcBuydown(principal, rate, term);
        }
    }

    function recalcBuydown(principal, baseRate, term) {
        var bdType = get('calc-buydown-type').value;

        get('calc-buydown-table-wrap').style.display = 'none';
        get('calc-temp-disclaimer').style.display    = 'none';
        get('calc-permanent-row').style.display      = 'none';

        if (bdType === 'none') return;

        get('calc-buydown-table-wrap').style.display = '';

        var basePI  = calcPI(principal, baseRate, term);
        var tbody   = get('calc-buydown-tbody');
        tbody.innerHTML = '';

        if (bdType === 'permanent') {
            get('calc-permanent-row').style.display = '';
            var permRate = clamp(parseFloat(get('calc-perm-rate').value) || 0, 0);
            var permPI   = calcPI(principal, permRate, term);
            var savings  = basePI - permPI;
            appendBuydownRow(tbody, 'All years', permRate, permPI, savings);
            return;
        }

        var offsets = [];
        if (bdType === '1-0')   offsets = [1];
        if (bdType === '2-1')   offsets = [2, 1];
        if (bdType === '3-2-1') offsets = [3, 2, 1];

        get('calc-temp-disclaimer').style.display = '';

        for (var i = 0; i < offsets.length; i++) {
            var yr   = i + 1;
            var r    = Math.max(0, baseRate - offsets[i]);
            var pi   = calcPI(principal, r, term);
            var sav  = basePI - pi;
            appendBuydownRow(tbody, 'Year ' + yr, r, pi, sav);
        }
        appendBuydownRow(tbody, 'Year ' + (offsets.length + 1) + '+', baseRate, basePI, 0);
    }

    function appendBuydownRow(tbody, yearLabel, rate, pi, savings) {
        var tr = document.createElement('tr');
        tr.innerHTML =
            '<td>' + yearLabel + '</td>' +
            '<td>' + rate.toFixed(3) + '%</td>' +
            '<td>' + fmtMoney(pi) + '/mo</td>' +
            '<td>' + (savings > 0 ? fmtMoney(savings) + '/mo' : '—') + '</td>';
        tbody.appendChild(tr);
    }

    // Recalculate PMI from the configured rate based on current price & down payment.
    // Always zeroed when down payment >= 20%.
    function syncPmiFromDown() {
        var price  = clamp(val('calc-price'), 1);
        var dollar = clamp(val('calc-down-dollar'), 0, price);
        var pct    = price > 0 ? (dollar / price) * 100 : 0;
        if (pct >= 20) {
            get('calc-pmi').value = 0;
        } else {
            get('calc-pmi').value = Math.round(price * (PMI_RATE / 100) / 12);
        }
    }

    function syncDownPctToDollar() {
        var price = clamp(val('calc-price'), 1);
        var pct   = clamp(val('calc-down-pct'), 0, 100);
        get('calc-down-dollar').value = Math.round(price * pct / 100);
        syncPmiFromDown();
    }

    function syncDownDollarToPct() {
        var price  = clamp(val('calc-price'), 1);
        var dollar = clamp(val('calc-down-dollar'), 0, price);
        get('calc-down-pct').value = parseFloat((dollar / price * 100).toFixed(2));
        syncPmiFromDown();
    }

    function syncInsurance() {
        // When an agent-level monthly insurance override is set, don't recalculate on price change
        if (INS_MONTHLY_OVERRIDE !== null) return;
        var price = clamp(val('calc-price'), 1);
        get('calc-insurance').value = Math.round(price * (INS_RATE / 100) / 12);
    }

    // Recalculate estimated taxes when price changes, only when taxes were not from the listing.
    function syncTaxes() {
        if (!TAXES_FROM_LISTING) {
            var price = clamp(val('calc-price'), 1);
            get('calc-taxes').value = Math.round(price * (TAX_RATE / 100) / 12);
        }
    }

    function initDefaults() {
        var price = PRICE_INIT > 0 ? PRICE_INIT : 0;
        get('calc-price').value = price;

        var downDollar = Math.round(price * DOWN_PCT_INIT / 100);
        get('calc-down-pct').value    = DOWN_PCT_INIT;
        get('calc-down-dollar').value = downDollar;

        var taxesMonthly = TAXES_ANNUAL_INIT > 0
            ? Math.round(TAXES_ANNUAL_INIT / 12)
            : (price > 0 ? Math.round(price * (TAX_RATE / 100) / 12) : 0);
        get('calc-taxes').value = taxesMonthly;

        var insMonthly;
        if (INS_MONTHLY_OVERRIDE !== null) {
            insMonthly = INS_MONTHLY_OVERRIDE;
        } else {
            insMonthly = price > 0 ? Math.round(price * (INS_RATE / 100) / 12) : 0;
        }
        get('calc-insurance').value = insMonthly;

        get('calc-hoa').value = HOA_MONTHLY_INIT;

        var pmiMonthly = DOWN_PCT_INIT >= 20 ? 0
            : (price > 0 ? Math.round(price * (PMI_RATE / 100) / 12) : 0);
        get('calc-pmi').value = pmiMonthly;

        recalc();
    }

    document.addEventListener('DOMContentLoaded', function () {

        initDefaults();

        get('calc-toggle-btn').addEventListener('click', function () {
            var panel = get('calc-expand-panel');
            var expanded = panel.style.display !== 'none';
            panel.style.display = expanded ? 'none' : '';
            this.setAttribute('aria-expanded', !expanded);
            this.textContent = expanded ? 'Customize Payment ▾' : 'Customize Payment ▴';
        });

        @if ($showBuydownOptions)
        get('calc-adv-toggle').addEventListener('click', function () {
            var panel = get('calc-adv-panel');
            var expanded = panel.style.display !== 'none';
            panel.style.display = expanded ? 'none' : '';
            this.setAttribute('aria-expanded', !expanded);
            this.textContent = expanded
                ? '▶ Advanced Options (Rate Buydown)'
                : '▼ Advanced Options (Rate Buydown)';
        });
        get('calc-buydown-type').addEventListener('change', recalc);
        get('calc-perm-rate').addEventListener('input', recalc);
        @endif

        get('calc-down-pct').addEventListener('input', function () {
            syncDownPctToDollar();
            recalc();
        });
        get('calc-down-dollar').addEventListener('input', function () {
            syncDownDollarToPct();
            recalc();
        });
        get('calc-price').addEventListener('input', function () {
            syncDownPctToDollar();
            syncInsurance();
            syncTaxes();
            recalc();
        });

        ['calc-rate', 'calc-term', 'calc-taxes', 'calc-insurance', 'calc-hoa', 'calc-pmi'].forEach(function (id) {
            get(id).addEventListener('input', recalc);
        });
    });
})();
</script>
