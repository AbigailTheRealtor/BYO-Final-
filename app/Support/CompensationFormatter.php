<?php

namespace App\Support;

class CompensationFormatter
{
    public static function formatRetainerFeeApplication($rawValue): string
    {
        if (empty($rawValue)) {
            return '';
        }

        $normalized = strtolower(trim($rawValue));

        $appliedValues = [
            'applied',
            'apply_to_final',
            'credited',
            'credited_to_final',
            'applied toward final compensation',
        ];

        $additionalValues = [
            'additional',
            'in_addition',
            'charged_additional',
            'charged in addition to final compensation',
        ];

        if (in_array($normalized, $appliedValues, true)) {
            return 'Applied toward final compensation';
        }

        if (in_array($normalized, $additionalValues, true)) {
            return 'Charged in addition to final compensation';
        }

        return '';
    }

    /**
     * Build a clean, human-readable list of compensation rows for the
     * Direct Hire preview, matching the style used in the bid detail views.
     *
     * @param  string  $role          'buyer' | 'seller' | 'landlord' | 'tenant'
     * @param  string  $propertyType  'residential' | 'commercial' (case-insensitive)
     * @param  array   $data          Mapped preset fields (from AgentBidMapperService::mapFromProfile)
     * @return array<int, array{label: string, value: string}>
     */
    public static function formatPresetRows(string $role, string $propertyType, array $data): array
    {
        $rows = [];

        $g = fn(string $key) => (string) ($data[$key] ?? '');

        $isBlank = function ($v): bool {
            $v = trim((string) $v);
            return $v === '' || in_array(strtolower($v), ['none', 'select', '0'], true);
        };

        $addRow = function (string $label, string $value) use (&$rows, $isBlank): void {
            if (!$isBlank($value)) {
                $rows[] = ['label' => $label, 'value' => trim($value)];
            }
        };

        switch (strtolower($role)) {

            // ──────────────────────────────────────────────────────────────
            // BUYER
            // ──────────────────────────────────────────────────────────────
            case 'buyer':
                $addRow("Buyer's Broker Commission Structure", $g('commission_structure'));

                $pft = $g('purchase_fee_type');
                if (!$isBlank($pft)) {
                    $addRow("Buyer's Broker Purchase Fee", self::buyerPurchaseFee($pft, $data));
                }

                $intLease = $g('interested_lease_option');
                if (!$isBlank($intLease)) {
                    $addRow('Interested in a Lease Agreement', $intLease);
                    if (strtolower($intLease) === 'yes') {
                        $lft = $g('lease_fee_type');
                        if (!$isBlank($lft)) {
                            $addRow("Buyer's Broker Lease Fee", self::buyerLeaseFee($lft, $data));
                        }
                    }
                }

                self::leaseOptionRows($addRow, $g, $isBlank);
                self::legalTermRows($addRow, $g, $isBlank);

                $addRow('Brokerage Relationship', $g('brokerage_relationship'));
                $addRow('Additional Terms', $g('additional_details_broker') ?: $g('additional_details'));
                $refFeeRaw = $g('referral_fee_percent');
                if (!$isBlank($refFeeRaw)) {
                    $addRow('Referral Fee (%)', (strpos($refFeeRaw, '%') !== false) ? $refFeeRaw : ($refFeeRaw . '%'));
                }
                break;

            // ──────────────────────────────────────────────────────────────
            // SELLER
            // ──────────────────────────────────────────────────────────────
            case 'seller':
                $pft = $g('purchase_fee_type');
                if (!$isBlank($pft)) {
                    $addRow("Seller's Broker Purchase Fee", self::sellerPurchaseFee($pft, $data));
                }

                $addRow("Buyer's Broker Commission Structure", $g('commission_structure'));

                $cst = $g('commission_structure_type');
                if (!$isBlank($cst)) {
                    $addRow("Buyer's Broker Commission Fee", self::sellerBuyerBrokerFee($cst, $data));
                }

                $nominal = $g('nominal');
                if (!$isBlank($nominal)) {
                    $addRow('Nominal Consideration Fee', Format::money($nominal));
                }

                $intPurchase = $g('interested_purchase_fee_type');
                if (!$isBlank($intPurchase)) {
                    $addRow('Interested in Offering a Lease Agreement', $intPurchase);
                    if (strtolower($intPurchase) === 'yes') {
                        $slf = $g('seller_leasing_fee_type');
                        if (!$isBlank($slf)) {
                            $addRow("Seller's Broker Leasing Fee", self::sellerLeasingFee($slf, $data));
                        }
                    }
                }

                self::leaseOptionRows($addRow, $g, $isBlank);
                self::legalTermRows($addRow, $g, $isBlank);

                $addRow('Brokerage Relationship', $g('brokerage_relationship'));
                $addRow('Additional Terms', $g('additional_details_broker'));
                $refFeeRaw = $g('referral_fee_percent');
                if (!$isBlank($refFeeRaw)) {
                    $addRow('Referral Fee (%)', (strpos($refFeeRaw, '%') !== false) ? $refFeeRaw : ($refFeeRaw . '%'));
                }
                break;

            // ──────────────────────────────────────────────────────────────
            // LANDLORD
            // ──────────────────────────────────────────────────────────────
            case 'landlord':
                $lft = $g('purchase_fee_type');
                if (!$isBlank($lft)) {
                    $addRow("Landlord's Broker Lease Fee", self::landlordLeaseFee($lft, $data));
                }

                $timing = $g('broker_fee_timing');
                if (!$isBlank($timing)) {
                    $addRow('Payment Timing for Broker Fees', self::brokerFeeTiming($timing, $g, $isBlank));
                }

                $renewalType = $g('renewal_fee_type');
                if (!$isBlank($renewalType)) {
                    $addRow('Lease Renewal/Extension Fee', self::renewalFee($renewalType, $data));
                }

                $expPct = $g('expansion_commission_percentage');
                if (!$isBlank($expPct)) {
                    $addRow('Expansion Commission for Lease Amendment', Format::percentage($expPct) . ' of original commission');
                }

                $tbStruct = $g('tenant_broker_commission_structure');
                if (!$isBlank($tbStruct)) {
                    $addRow("Tenant's Broker Commission Structure", $tbStruct);
                    $tbs = $g('tenant_broker_fee_structure');
                    if (!$isBlank($tbs)) {
                        $addRow("Tenant's Broker Commission Fee", self::tenantBrokerFee($tbs, $data));
                    }
                }

                self::leaseOptionRows($addRow, $g, $isBlank);

                $intSelling = $g('interested_in_selling');
                if (!$isBlank($intSelling)) {
                    $addRow('Interested in Selling the Property', $intSelling);
                    if (strtolower($intSelling) === 'yes') {
                        $ist = $g('interested_in_selling_type');
                        if (!$isBlank($ist)) {
                            $addRow("Landlord's Broker Purchase Fee", self::landlordSellingFee($ist, $data));
                        }
                    }
                }

                $pmInterest = $g('interested_in_property_management');
                if (!$isBlank($pmInterest) && strtolower($pmInterest) === 'yes') {
                    $pmDisplay = self::propertyManagementFee($g, $isBlank);
                    $addRow('Property Management Fee', $pmDisplay ?: 'Yes');
                }

                self::legalTermRows($addRow, $g, $isBlank);

                $addRow('Brokerage Relationship', $g('brokerage_relationship'));
                $addRow('Additional Terms', $g('additional_details_broker'));
                $refFeeRaw = $g('referral_fee_percent');
                if (!$isBlank($refFeeRaw)) {
                    $addRow('Referral Fee (%)', (strpos($refFeeRaw, '%') !== false) ? $refFeeRaw : ($refFeeRaw . '%'));
                }
                break;

            // ──────────────────────────────────────────────────────────────
            // TENANT
            // ──────────────────────────────────────────────────────────────
            case 'tenant':
                $commStruct = $g('commission_structure');
                if (!$isBlank($commStruct)) {
                    $commDisplay = config('agent_preset_compensation.tenant.commission_structure.' . $commStruct, $commStruct);
                    $addRow("Tenant's Broker Commission Structure", $commDisplay);
                }

                $lft = $g('lease_fee_type');
                if (!$isBlank($lft)) {
                    $addRow("Tenant's Broker Lease Fee", self::tenantLeaseFee($lft, $data));
                }

                $timing = $g('broker_fee_timing');
                if (!$isBlank($timing)) {
                    $addRow('Payment Timing for Broker Fees', self::brokerFeeTiming($timing, $g, $isBlank));
                }

                $intPurchase = $g('interested_purchase_fee_type');
                if (!$isBlank($intPurchase)) {
                    $addRow('Interested in Purchase Agreement', $intPurchase);
                    if (strtolower($intPurchase) === 'yes') {
                        $pft = $g('purchase_fee_type');
                        if (!$isBlank($pft)) {
                            $addRow("Tenant's Broker Purchase Fee", self::tenantPurchaseFee($pft, $data));
                        }
                    }
                }

                self::leaseOptionRows($addRow, $g, $isBlank);
                self::legalTermRows($addRow, $g, $isBlank);

                $addRow('Brokerage Relationship', $g('brokerage_relationship'));
                $addRow('Additional Terms', $g('additional_details_broker'));
                $refFeeRaw = $g('referral_fee_percent');
                if (!$isBlank($refFeeRaw)) {
                    $addRow('Referral Fee (%)', (strpos($refFeeRaw, '%') !== false) ? $refFeeRaw : ($refFeeRaw . '%'));
                }
                break;
        }

        return $rows;
    }

    /**
     * Strip any row whose label is 'Referral Fee (%)' from a comp-rows array.
     *
     * This is the single, canonical server-side filter applied everywhere a
     * non-agent viewer can see compensation rows. Pass the result to the view
     * and also use it as the source for the Review-tab JSON embed in counter.blade.php.
     *
     * @param  array<int, array{label: string, value: string}>  $compRows
     * @return array<int, array{label: string, value: string}>
     */
    public static function filterReferralFeeRows(array $compRows): array
    {
        return array_values(array_filter(
            $compRows,
            fn($row) => ($row['label'] ?? '') !== 'Referral Fee (%)'
        ));
    }

    // ── Shared section builders ────────────────────────────────────────────

    /**
     * Normalise curly/smart apostrophes (Unicode \u2018 / \u2019) to straight
     * apostrophes so stored option values match our comparison strings regardless
     * of which apostrophe variant was persisted via the form.
     */
    private static function norm(string $s): string
    {
        return str_replace(["\u{2018}", "\u{2019}"], "'", $s);
    }

    private static function leaseOptionRows(callable $addRow, callable $g, callable $isBlank): void
    {
        $loa = $g('interested_lease_option_agreement');
        if ($isBlank($loa)) {
            return;
        }
        $addRow('Interested in a Lease-Option Agreement', $loa);
        if (strtolower($loa) !== 'yes') {
            return;
        }
        // Canonical percent detection: matches 'percent' / '%' stored values and
        // values that already contain a '%' sign (mirrors hire_seller_agent/view.blade.php).
        $isPercent = fn(string $typeVal, string $amount): bool =>
            in_array(strtolower(trim($typeVal)), ['%', 'percent'], true) ||
            str_contains($amount, '%');

        $lv = $g('lease_value');
        if (!$isBlank($lv)) {
            $lt       = $g('lease_type');
            $loCreate = $isPercent($lt, $lv)
                ? rtrim(str_replace('%', '', $lv)) . '% of Total Purchase Price'
                : Format::money($lv);
            $addRow('Compensation for Creating Lease-Option Agreement', $loCreate);
        }
        $pv = $g('purchase_value');
        if (!$isBlank($pv)) {
            $pt         = $g('purchase_type');
            $loExercise = $isPercent($pt, $pv)
                ? rtrim(str_replace('%', '', $pv)) . '% of Total Purchase Price'
                : Format::money($pv);
            $addRow('Compensation if Purchase Option is Exercised', $loExercise);
        }
    }

    private static function legalTermRows(callable $addRow, callable $g, callable $isBlank): void
    {
        $pp = $g('protection_period');
        if (!$isBlank($pp)) {
            $addRow('Protection Period', $pp . ' days');
        }

        $etOpt = $g('early_termination_fee_option');
        if (!$isBlank($etOpt)) {
            $etAmt   = $g('early_termination_fee_amount');
            $etIsYes = in_array(strtolower($etOpt), ['yes', 'y'], true);
            if ($etIsYes && !$isBlank($etAmt)) {
                $addRow('Early Termination Fee', 'Yes (' . Format::money($etAmt) . ')');
            } else {
                $addRow('Early Termination Fee', ucfirst(strtolower($etOpt)));
            }
        }

        $retOpt = $g('retainer_fee_option');
        if (!$isBlank($retOpt)) {
            $retAmt   = $g('retainer_fee_amount');
            $retApp   = $g('retainer_fee_application');
            $retIsYes = in_array(strtolower($retOpt), ['yes', 'y'], true);
            if ($retIsYes && !$isBlank($retAmt)) {
                $addRow('Retainer Fee', 'Yes (' . Format::money($retAmt) . ')');
            } else {
                $addRow('Retainer Fee', ucfirst(strtolower($retOpt)));
            }
            if ($retIsYes && !$isBlank($retApp)) {
                $appFormatted = self::formatRetainerFeeApplication($retApp);
                if ($appFormatted) {
                    $addRow('Retainer Application', $appFormatted);
                }
            }
        }

        $agencyTf     = $g('agency_agreement_timeframe');
        $agencyCustom = $g('agency_agreement_custom');
        if (!$isBlank($agencyTf)) {
            $isCustom = in_array(strtolower(trim($agencyTf)), ['custom', 'other'], true);
            $addRow('Agreement Timeframe', $isCustom ? ($agencyCustom ?: 'Custom') : $agencyTf);
        }
    }

    // ── Per-role fee builders ──────────────────────────────────────────────

    private static function buyerPurchaseFee(string $pft, array $data): string
    {
        $g = fn($k) => (string) ($data[$k] ?? '');
        if ($pft === 'Flat Fee') {
            return Format::money($g('purchase_fee_flat')) ?: 'Flat Fee';
        }
        if ($pft === 'Percentage of the Total Purchase Price') {
            $p = $g('purchase_fee_percentage');
            return $p ? Format::percentage($p) . ' of Total Purchase Price' : $pft;
        }
        if ($pft === 'Percentage of the Total Purchase Price + Flat Fee') {
            $parts = [];
            if ($p = $g('purchase_fee_percentage_combo')) {
                $parts[] = Format::percentage($p) . ' of Total Purchase Price';
            }
            if ($f = $g('purchase_fee_flat_combo')) {
                $parts[] = Format::money($f);
            }
            return $parts ? implode(' + ', $parts) : $pft;
        }
        if (strtolower($pft) === 'other') {
            return $g('purchase_fee_other') ?: 'Other';
        }
        return $pft;
    }

    private static function buyerLeaseFee(string $lft, array $data): string
    {
        $g = fn($k) => (string) ($data[$k] ?? '');
        // Buyer flat fee slug is 'flat' (not 'Flat Fee')
        if ($lft === 'flat') {
            return Format::money($g('lease_fee_flat')) ?: 'Flat Fee';
        }
        if ($lft === 'Percentage of Monthly Rent') {
            $p = $g('lease_fee_percentage_monthly_rent');
            if (!$p) {
                return $lft;
            }
            $display = Format::percentage($p) . ' of Monthly Rent';
            if ($months = $g('lease_fee_percentage_monthly_number')) {
                $display .= ' x ' . $months . ' Months';
            }
            return $display;
        }
        if ($lft === 'Percentage of the Gross Lease Value') {
            $p = $g('lease_fee_percentage');
            return $p ? Format::percentage($p) . ' of Gross Lease Value' : $lft;
        }
        if ($lft === 'Flat Fee + Percentage of the Gross Lease Value') {
            $parts = [];
            if ($f = $g('lease_fee_flat_combo')) {
                $parts[] = Format::money($f);
            }
            if ($p = $g('lease_fee_percentage_combo')) {
                $parts[] = Format::percentage($p) . ' of Gross Lease Value';
            }
            return $parts ? implode(' + ', $parts) : $lft;
        }
        if ($lft === 'Percentage of the Net Aggregate Rent') {
            $p = $g('lease_fee_percentage_net');
            return $p ? Format::percentage($p) . ' of Net Aggregate Rent' : $lft;
        }
        if ($lft === 'Flat Fee + Percentage of the Net Aggregate Rent') {
            $parts = [];
            if ($f = $g('lease_fee_flat_combo')) {
                $parts[] = Format::money($f);
            }
            if ($p = $g('lease_fee_percentage_combo')) {
                $parts[] = Format::percentage($p) . ' of Net Aggregate Rent';
            }
            return $parts ? implode(' + ', $parts) : $lft;
        }
        if (strtolower($lft) === 'other') {
            return $g('lease_fee_other') ?: 'Other';
        }
        return $lft;
    }

    private static function sellerPurchaseFee(string $pft, array $data): string
    {
        $g = fn($k) => (string) ($data[$k] ?? '');
        if ($pft === 'flat') {
            return Format::money($g('purchase_fee_flat')) ?: 'Flat Fee';
        }
        if ($pft === 'percentage') {
            $p = $g('purchase_fee_percentage');
            return $p ? Format::percentage($p) . ' of Total Purchase Price' : 'Percentage of the Total Purchase Price';
        }
        if ($pft === 'combo') {
            $parts = [];
            if ($p = $g('purchase_fee_percentage_combo')) {
                $parts[] = Format::percentage($p) . ' of Total Purchase Price';
            }
            if ($f = $g('purchase_fee_flat_combo')) {
                $parts[] = Format::money($f);
            }
            return $parts ? implode(' + ', $parts) : 'Percentage + Flat Fee';
        }
        if ($pft === 'other') {
            return $g('purchase_fee_other') ?: 'Other';
        }
        return $pft;
    }

    private static function sellerBuyerBrokerFee(string $cst, array $data): string
    {
        $g = fn($k) => (string) ($data[$k] ?? '');
        if ($cst === 'Flat Fee') {
            return Format::money($g('commission_structure_type_fee_flat')) ?: 'Flat Fee';
        }
        if ($cst === 'Percentage of the Total Purchase Price') {
            $p = $g('commission_structure_type_fee_percentage');
            return $p ? Format::percentage($p) . ' of Total Purchase Price' : $cst;
        }
        if ($cst === 'Flat Fee + Percentage') {
            $parts = [];
            if ($p = $g('commission_structure_type_fee_percentage_combo')) {
                $parts[] = Format::percentage($p) . ' of Total Purchase Price';
            }
            if ($f = $g('commission_structure_type_fee_flat_combo')) {
                $parts[] = Format::money($f);
            }
            return $parts ? implode(' + ', $parts) : $cst;
        }
        if (strtolower($cst) === 'other') {
            return $g('commission_structure_type_fee_other') ?: 'Other';
        }
        return $cst;
    }

    private static function sellerLeasingFee(string $slf, array $data): string
    {
        $g   = fn($k) => (string) ($data[$k] ?? '');
        $slf = self::norm($slf);
        if ($slf === 'Flat Fee') {
            return Format::money($g('seller_leasing_gross_purchase_fee_flat_amount')) ?: 'Flat Fee';
        }
        if ($slf === 'Percentage of the Gross Lease Value') {
            $p = $g('seller_leasing_gross');
            return $p ? Format::percentage($p) . ' of the Gross Lease Value' : $slf;
        }
        if ($slf === 'Percentage of the Rent Due Each Rental Period') {
            $p = $g('seller_leasing_gross_rental');
            return $p ? Format::percentage($p) . ' of the Rent Due Each Rental Period' : $slf;
        }
        if ($slf === "Percentage of the First Month's Rent") {
            $p = $g('seller_leasing_gross_month_rent');
            return $p ? Format::percentage($p) . " of the First Month's Rent" : $slf;
        }
        if ($slf === "Percentage of Month's Rent") {
            $p = $g('seller_leasing_gross_month_rent');
            if (!$p) {
                return $slf;
            }
            $display = Format::percentage($p) . " of Month's Rent";
            $months  = $g('seller_leasing_gross_no_of_months');
            if ($months && $months !== 'null') {
                $display .= ' x ' . intval($months) . ' Months';
            }
            return $display;
        }
        if ($slf === 'Percentage of Net Aggregate Rent') {
            $netVal = $g('seller_leasing_gross_other') ?: $g('seller_leasing_gross');
            return $netVal ? Format::percentage($netVal) . ' of Net Aggregate Rent' : $slf;
        }
        if ($slf === 'Percentage of Gross Rent') {
            $grossVal = $g('seller_leasing_gross_percentage');
            return $grossVal ? Format::percentage($grossVal) . ' of Gross Rent' : $slf;
        }
        if ($slf === 'Flat Fee + Percentage of the Gross Lease Value') {
            $parts = [];
            if ($f = $g('seller_leasing_gross_flat_combo')) {
                $parts[] = Format::money($f);
            }
            if ($p = $g('seller_leasing_gross_percentage_combo')) {
                $parts[] = Format::percentage($p) . ' of Gross Lease Value';
            }
            return $parts ? implode(' + ', $parts) : $slf;
        }
        if ($slf === 'Flat Fee + Percentage of the Net Aggregate Rent') {
            $parts = [];
            if ($f = $g('seller_leasing_gross_flat_net_combo')) {
                $parts[] = Format::money($f);
            }
            if ($p = $g('seller_leasing_gross_percentage_net_combo')) {
                $parts[] = Format::percentage($p) . ' of Net Aggregate Rent';
            }
            return $parts ? implode(' + ', $parts) : $slf;
        }
        if (strtolower($slf) === 'other') {
            return $g('seller_leasing_gross_purchase_fee_other') ?: 'Other';
        }
        return $slf;
    }

    private static function landlordLeaseFee(string $lft, array $data): string
    {
        $g   = fn($k) => (string) ($data[$k] ?? '');
        $lft = self::norm($lft);
        if ($lft === 'Flat Fee') {
            $flat = $g('purchase_fee_flat') ?: $g('purchase_fee_flat_commercial');
            return $flat ? Format::money($flat) . ' Flat Fee' : 'Flat Fee';
        }
        if ($lft === 'Percentage of the Rent Due Each Rental Period') {
            $p = $g('purchase_fee_rental_period');
            return $p ? Format::percentage($p) . ' of Rent Due Each Rental Period' : $lft;
        }
        if ($lft === 'Percentage of the Gross Lease Value') {
            $p = $g('purchase_fee_percentage_combo');
            return $p ? Format::percentage($p) . ' of Gross Lease Value' : $lft;
        }
        if ($lft === "Percentage of the First Month's Rent") {
            $p = $g('purchase_fee_flat_combo');
            return $p ? Format::percentage($p) . " of First Month's Rent" : $lft;
        }
        if ($lft === 'Percentage of the Net Aggregate Rent') {
            $p = $g('purchase_fee_net_aggregate');
            return $p ? Format::percentage($p) . ' of Net Aggregate Rent' : $lft;
        }
        if ($lft === 'Percentage of the Gross Rent') {
            $p = $g('purchase_fee_gross_rent');
            return $p ? Format::percentage($p) . ' of Gross Rent' : $lft;
        }
        if ($lft === "Percentage of Month's Rent") {
            $p = $g('purchase_fee_monthly_percentage');
            if (!$p) {
                return $lft;
            }
            $display = Format::percentage($p) . " of Month's Rent";
            if ($months = $g('purchase_fee_months')) {
                $display .= ' x ' . $months . ' Months';
            }
            return $display;
        }
        if (strtolower($lft) === 'other') {
            $oth = $g('purchase_fee_other') ?: $g('purchase_fee_other_commercial');
            return $oth ? 'Other: ' . $oth : 'Other';
        }
        return $lft;
    }

    private static function renewalFee(string $renewalType, array $data): string
    {
        $g          = fn($k) => (string) ($data[$k] ?? '');
        $renewalType = self::norm($renewalType);
        if ($renewalType === 'Flat Fee') {
            $flat = $g('renewal_fee_flat_fee');
            return $flat ? Format::money($flat) . ' Flat Fee' : 'Flat Fee';
        }
        if ($renewalType === 'Percentage of the Rent Due Each Rental Period') {
            $p = $g('renewal_fee_percentage');
            return $p ? Format::percentage($p) . ' of Rent Due Each Rental Period' : $renewalType;
        }
        if ($renewalType === 'Percentage of the Gross Lease Value') {
            $p = $g('renewal_fee_lease_value');
            return $p ? Format::percentage($p) . ' of Gross Lease Value' : $renewalType;
        }
        if (in_array($renewalType, ["Percentage of the First Month's Rent", "Percentage of First Month's Rent"], true)) {
            $p = $g('renewal_fee_first_month');
            return $p ? Format::percentage($p) . " of First Month's Rent" : $renewalType;
        }
        if ($renewalType === 'Percentage of the Net Aggregate Rent') {
            $p = $g('renewal_fee_percentage');
            return $p ? Format::percentage($p) . ' of Net Aggregate Rent' : $renewalType;
        }
        if ($renewalType === 'Percentage of the Gross Rent') {
            $p = $g('renewal_fee_lease_value');
            return $p ? Format::percentage($p) . ' of Gross Rent' : $renewalType;
        }
        if ($renewalType === "Percentage of Month's Rent") {
            $p = $g('renewal_fee_first_month');
            if (!$p) {
                return $renewalType;
            }
            $display = Format::percentage($p) . " of Month's Rent";
            if ($months = $g('renewal_fee_no_of_months')) {
                $display .= ' x ' . $months . ' Months';
            }
            return $display;
        }
        if (strtolower($renewalType) === 'other') {
            $custom = $g('renewal_fee_custom');
            return $custom ? 'Other: ' . $custom : 'Other';
        }
        return $renewalType;
    }

    private static function tenantBrokerFee(string $tbs, array $data): string
    {
        $g    = fn($k) => (string) ($data[$k] ?? '');
        $tbsL = strtolower(trim($tbs));
        if (str_contains($tbsL, 'rent due each')) {
            $p = $g('tenant_broker_percentage');
            return $p ? Format::percentage($p) . ' of Rent Due Each Rental Period' : $tbs;
        }
        if (str_contains($tbsL, 'gross lease')) {
            $p = $g('tenant_broker_gross_lease');
            return $p ? Format::percentage($p) . ' of Gross Lease Value' : $tbs;
        }
        if (str_contains($tbsL, 'first month')) {
            $p = $g('tenant_broker_first_month_rent');
            return $p ? Format::percentage($p) . " of First Month's Rent" : $tbs;
        }
        // Handles both 'Flat fee' (legacy stored value) and 'Flat Fee' (normalised)
        if ($tbsL === 'flat fee') {
            $flat = $g('tenant_broker_flat_fee');
            return $flat ? Format::money($flat) . ' Flat Fee' : $tbs;
        }
        if ($tbsL === 'other') {
            $oth = $g('tenant_broker_other');
            return $oth ? 'Other: ' . $oth : 'Other';
        }
        return $tbs;
    }

    private static function landlordSellingFee(string $ist, array $data): string
    {
        $g = fn($k) => (string) ($data[$k] ?? '');
        if ($ist === 'Percentage of the Total Purchase Price') {
            $p = $g('landlord_broker_purchase_price');
            return $p ? Format::percentage($p) . ' of Total Purchase Price' : $ist;
        }
        if ($ist === 'Percentage of the Total Purchase Price + Flat Fee') {
            $parts = [];
            if ($p = $g('landlord_broker_percentage_price')) {
                $parts[] = Format::percentage($p) . ' of Total Purchase Price';
            }
            if ($f = $g('landlord_broker_dollar_price')) {
                $parts[] = Format::money($f);
            }
            return $parts ? implode(' + ', $parts) : $ist;
        }
        if ($ist === 'Flat Fee') {
            $flat = $g('landlord_broker_flate_fee');
            return $flat ? Format::money($flat) . ' Flat Fee' : 'Flat Fee';
        }
        if ($ist === 'Other') {
            $oth = $g('landlord_broker_other');
            return $oth ? 'Other: ' . $oth : 'Other';
        }
        return $ist;
    }

    private static function propertyManagementFee(callable $g, callable $isBlank): string
    {
        $feeType = $g('interested_in_property_management_fee');
        if ($isBlank($feeType)) {
            return '';
        }
        if ($feeType === 'Percentage of the Gross Lease Value') {
            $p = $g('interested_in_property_management_fee_gross_lease');
            return $p ? Format::percentage($p) . ' of Gross Lease Value' : $feeType;
        }
        if ($feeType === 'Percentage of the Rent Due Each Rental Period') {
            $p = $g('interested_in_property_management_fee_rental_periord');
            return $p ? Format::percentage($p) . ' of Rent Due Each Rental Period' : $feeType;
        }
        if ($feeType === 'Flat Fee') {
            $flat = $g('interested_in_property_management_fee_flate_free');
            return $flat ? Format::money($flat) . ' Flat Fee' : 'Flat Fee';
        }
        if ($feeType === 'Other') {
            $oth = $g('interested_in_property_management_fee_other');
            return $oth ? 'Other: ' . $oth : 'Other';
        }
        return $feeType;
    }

    private static function tenantLeaseFee(string $lft, array $data): string
    {
        $g = fn($k) => (string) ($data[$k] ?? '');
        // Tenant stores 'Flat Fee' (full text, unlike buyer which uses 'flat' slug)
        if ($lft === 'Flat Fee') {
            return Format::money($g('lease_fee_flat')) ?: 'Flat Fee';
        }
        if ($lft === 'Percentage of Monthly Rent') {
            $p = $g('lease_fee_percentage_monthly_rent');
            if (!$p) {
                return $lft;
            }
            $display = Format::percentage($p) . ' of Monthly Rent';
            if ($months = $g('lease_fee_percentage_monthly_number')) {
                $display .= ' x ' . $months . ' Months';
            }
            return $display;
        }
        if ($lft === 'Percentage of the Gross Lease Value') {
            $p = $g('lease_fee_percentage');
            return $p ? Format::percentage($p) . ' of Gross Lease Value' : $lft;
        }
        if ($lft === 'Flat Fee + Percentage of the Gross Lease Value') {
            $parts = [];
            if ($f = $g('lease_fee_flat_combo')) {
                $parts[] = Format::money($f);
            }
            if ($p = $g('lease_fee_percentage_combo')) {
                $parts[] = Format::percentage($p) . ' of Gross Lease Value';
            }
            return $parts ? implode(' + ', $parts) : $lft;
        }
        if ($lft === 'Percentage of the Net Aggregate Rent') {
            $p = $g('lease_fee_percentage_net');
            return $p ? Format::percentage($p) . ' of Net Aggregate Rent' : $lft;
        }
        if ($lft === 'Flat Fee + Percentage of the Net Aggregate Rent') {
            $parts = [];
            if ($f = $g('lease_fee_flat_combo_net') ?: $g('lease_fee_flat_combo')) {
                $parts[] = Format::money($f);
            }
            if ($p = $g('lease_fee_percentage_combo_net') ?: $g('lease_fee_percentage_combo')) {
                $parts[] = Format::percentage($p) . ' of Net Aggregate Rent';
            }
            return $parts ? implode(' + ', $parts) : $lft;
        }
        if (strtolower($lft) === 'other') {
            return $g('lease_fee_other') ?: 'Other';
        }
        return $lft;
    }

    private static function tenantPurchaseFee(string $pft, array $data): string
    {
        $g = fn($k) => (string) ($data[$k] ?? '');
        if ($pft === 'Flat Fee') {
            return Format::money($g('purchase_fee_flat')) ?: 'Flat Fee';
        }
        if ($pft === 'Percentage of the Total Purchase Price') {
            $p = $g('purchase_fee_percentage');
            return $p ? Format::percentage($p) . ' of Total Purchase Price' : $pft;
        }
        if ($pft === 'Percentage of the Total Purchase Price + Flat Fee') {
            $parts = [];
            if ($p = $g('purchase_fee_percentage_combo')) {
                $parts[] = Format::percentage($p) . ' of Total Purchase Price';
            }
            if ($f = $g('purchase_fee_flat_combo')) {
                $parts[] = Format::money($f);
            }
            return $parts ? implode(' + ', $parts) : $pft;
        }
        if (strtolower($pft) === 'other') {
            return $g('purchase_fee_other') ?: 'Other';
        }
        return $pft;
    }

    private static function brokerFeeTiming(string $timing, callable $g, callable $isBlank): string
    {
        // Slug → display label mappings (landlord commercial + tenant commercial)
        $slugMap = [
            'full_execution'                  => 'Full amount upon execution of lease, sales contract, or other transfer agreement',
            'half_execution_half_commencement' => '50% due upon execution, 50% due upon commencement of agreement',
            'half_execution_half_occupancy'    => '50% due upon execution, 50% due upon occupancy of premises',
        ];
        if (isset($slugMap[$timing])) {
            return $slugMap[$timing];
        }

        // Fix known stored typo (missing space before 'occupancy')
        if ($timing === '50% due upon execution, 50% due uponoccupancy of premises') {
            return '50% due upon execution, 50% due upon occupancy of premises';
        }

        // Day-count sub-fields (residential landlord + tenant)
        if ($timing === 'Deducted from Rent Collected') {
            $days = $g('broker_fee_days_from_rent');
            return $timing . (!$isBlank($days) ? " ($days calendar days)" : '');
        }
        if ($timing === 'Paid Within Calendar Days After Executed Lease') {
            $days = $g('broker_fee_days_after_lease');
            return !$isBlank($days) ? "Within $days days after executed lease" : $timing;
        }
        if ($timing === 'Paid Within Calendar Days of Tenant Rent Payment') {
            $days = $g('broker_fee_days_after_rent');
            return !$isBlank($days) ? "Within $days days of tenant rent payment" : $timing;
        }

        // 'other' (lowercase, residential) or 'Other' (commercial)
        if (strtolower($timing) === 'other') {
            $oth = $g('broker_fee_timing_other');
            return !$isBlank($oth) ? $oth : 'Custom arrangement';
        }

        return $timing;
    }
}
