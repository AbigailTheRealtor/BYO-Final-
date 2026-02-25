<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SellerAgentAuction;
use App\Models\BuyerCriteriaAuction;
use App\Models\LandlordAgentAuction;
use App\Models\TenantAgentAuction;
use App\Exports\ListingPdfDataBuilder;
use App\Exports\ListingExportFormatter;
use App\Exports\ListingFieldMaps\SellerFieldMap;
use App\Exports\ListingFieldMaps\BuyerFieldMap;
use App\Exports\ListingFieldMaps\LandlordFieldMap;
use App\Exports\ListingFieldMaps\TenantFieldMap;
use Barryvdh\DomPDF\Facade\Pdf;

class ListingDownloadController extends Controller
{
    public function seller($id)
    {
        $auction = SellerAgentAuction::with('meta', 'user')->findOrFail($id);
        $this->authorizeView($auction);

        $meta = $auction->get ?? (object) [];
        $packet = ListingPdfDataBuilder::build(
            $meta,
            SellerFieldMap::sections(),
            SellerFieldMap::otherPairs(),
            $this->defaultNormalizers()
        );

        $listingId = $auction->listing_id ?? $auction->id;
        $filename = "seller-listing-{$listingId}.pdf";

        return $this->generatePdf('listing-download.packet', [
            'role' => 'Seller',
            'listingId' => $listingId,
            'packet' => $packet,
        ], $filename);
    }

    public function buyer($id)
    {
        $auction = BuyerCriteriaAuction::with('meta', 'user')->findOrFail($id);
        $this->authorizeView($auction);

        $meta = $auction->get ?? (object) [];
        $packet = ListingPdfDataBuilder::build(
            $meta,
            BuyerFieldMap::sections(),
            BuyerFieldMap::otherPairs(),
            $this->defaultNormalizers()
        );

        $listingId = $auction->listing_id ?? $auction->id;
        $filename = "buyer-listing-{$listingId}.pdf";

        return $this->generatePdf('listing-download.packet', [
            'role' => 'Buyer',
            'listingId' => $listingId,
            'packet' => $packet,
        ], $filename);
    }

    public function landlord($id)
    {
        $auction = LandlordAgentAuction::with('meta', 'user')->findOrFail($id);
        $this->authorizeView($auction);

        $meta = $auction->get ?? (object) [];
        $packet = ListingPdfDataBuilder::build(
            $meta,
            LandlordFieldMap::sections(),
            LandlordFieldMap::otherPairs(),
            $this->defaultNormalizers()
        );

        $listingId = $auction->listing_id ?? $auction->id;
        $filename = "landlord-listing-{$listingId}.pdf";

        return $this->generatePdf('listing-download.packet', [
            'role' => 'Landlord',
            'listingId' => $listingId,
            'packet' => $packet,
        ], $filename);
    }

    public function tenant($id)
    {
        $auction = TenantAgentAuction::with('meta', 'user')->findOrFail($id);
        $this->authorizeView($auction);

        $meta = $auction->get ?? (object) [];
        $packet = ListingPdfDataBuilder::build(
            $meta,
            TenantFieldMap::sections(),
            TenantFieldMap::otherPairs(),
            $this->defaultNormalizers()
        );

        $listingId = $auction->listing_id ?? $auction->id;
        $filename = "tenant-listing-{$listingId}.pdf";

        return $this->generatePdf('listing-download.packet', [
            'role' => 'Tenant',
            'listingId' => $listingId,
            'packet' => $packet,
        ], $filename);
    }

    protected function authorizeView($auction)
    {
        if (!auth()->check()) {
            abort(403, 'You must be logged in to download listings.');
        }
    }

    protected function generatePdf(string $view, array $data, string $filename)
    {
        try {
            $pdf = Pdf::loadView($view, $data);
            $pdf->setPaper('letter', 'portrait');
            return $pdf->download($filename);
        } catch (\Exception $e) {
            $html = view($view, $data)->render();
            return response($html)
                ->header('Content-Type', 'text/html')
                ->header('Content-Disposition', 'attachment; filename="' . str_replace('.pdf', '.html', $filename) . '"');
        }
    }

    protected function defaultNormalizers(): array
    {
        return [
            'property_type' => fn($v) => ListingExportFormatter::normalizePropertyType($v),
            'cities' => function($v) {
                $items = ListingExportFormatter::toList($v);
                return ListingExportFormatter::stripStateSuffixList($items);
            },
            'counties' => function($v) {
                $items = ListingExportFormatter::toList($v);
                return ListingExportFormatter::stripStateSuffixList($items);
            },
            'property_items' => function($v) {
                $items = ListingExportFormatter::toList($v);
                return array_values(array_unique(array_map([ListingExportFormatter::class, 'normalizeDuplex'], $items)));
            },
            'financings' => function($v) {
                $items = ListingExportFormatter::toList($v);
                $order = ['Assumable','Cash','Conventional','Cryptocurrency','Exchange/Trade','FHA','Jumbo','Lease Option','Lease Purchase','No-Doc','Non-QM','NFT','Non-Fungible Token (NFT)','Seller Financing','USDA','VA'];
                usort($items, function($a, $b) use ($order) {
                    $aIdx = array_search($a, $order);
                    $bIdx = array_search($b, $order);
                    if ($aIdx === false && strtolower($a) === 'other') return 1;
                    if ($bIdx === false && strtolower($b) === 'other') return -1;
                    if ($aIdx === false) $aIdx = 999;
                    if ($bIdx === false) $bIdx = 999;
                    return $aIdx - $bIdx;
                });
                return $items;
            },
            'offered_financing' => function($v) {
                $items = ListingExportFormatter::toList($v);
                $order = ['Assumable','Cash','Conventional','Cryptocurrency','Exchange/Trade','FHA','Jumbo','Lease Option','Lease Purchase','No-Doc','Non-QM','NFT','Non-Fungible Token (NFT)','Seller Financing','USDA','VA'];
                usort($items, function($a, $b) use ($order) {
                    $aIdx = array_search($a, $order);
                    $bIdx = array_search($b, $order);
                    if ($aIdx === false && strtolower($a) === 'other') return 1;
                    if ($bIdx === false && strtolower($b) === 'other') return -1;
                    if ($aIdx === false) $aIdx = 999;
                    if ($bIdx === false) $bIdx = 999;
                    return $aIdx - $bIdx;
                });
                return $items;
            },
            'maximum_budget' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'purchase_price' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'pre_approval_amount' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'minimum_annual_net_income' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'minimum_heated_square' => fn($v) => ListingExportFormatter::fmtNumber($v),
            'minimum_leaseable' => fn($v) => ListingExportFormatter::fmtNumber($v),
            'total_square_feet' => fn($v) => ListingExportFormatter::fmtNumber($v),
            'minimum_cap_rate' => fn($v) => ListingExportFormatter::fmtPercent($v),
            'desired_rental_amount' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'desired_rental_amount_tenant' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'seller_financing_amount' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'down_payment_amount' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'balloon_payment_amount' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'prepayment_penalty_amount' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'gap_payment_amount' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'assignment_fee_amount' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'exchange_item_value' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'lease_option_price' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'lease_option_payment' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'option_fee_amount' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'lease_purchase_price' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'lease_purchase_payment' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'lease_purchase_option_fee_amount' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'lease_fee_flat' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'lease_fee_flat_combo' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'purchase_fee_flat' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'purchase_fee_flat_combo' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'lease_option_fee_flat' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'early_termination_fee_amount' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'retainer_fee_amount' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'lease_value' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'purchase_value' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'assumable_fee_amount' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'assumable_monthly_escrow' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'max_monthly_payment' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'additional_cash' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'cash_budget' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'budget' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'monthly_income' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'flat_fee' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'total_marketing_fee' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'total_flat_fee' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'landlord_broker_flate_fee' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'landlord_broker_dollar_price' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'landlord_broker_purchase_price' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'tenant_broker_flat_fee' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'expansion_flat_fee' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'seller_lease_purchase_rent_credit_amount' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'seller_lease_purchase_deposit' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'seller_late_fee_amount' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'seller_down_payment_amount' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'seller_broker_leasing_fee' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'seller_leasing_gross' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'seller_leasing_gross_flat_combo' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'seller_leasing_gross_purchase_fee_flat_amount' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'lease_purchase_rent_credit_amount' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'lease_purchase_deposit' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'outstanding_balance' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'retained_deposits' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'purchase_fee_flat_commercial' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'purchase_fee_flat_exercised' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'purchase_pice_commercial' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'renewal_fee_first_month' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'renewal_fee_flat_free' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'commission_structure_type_fee_flat' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'commission_structure_type_fee_flat_combo' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'lease_fee_flat_combo_net' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'email_marketing_fee' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'email_notifications_fee' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'launch_ads_fee' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'market_groups_fee' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'marketing_materials_fee' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'mls_filter_fee' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'off_market_search_fee' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'promote_social_fee' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'neighborhood_marketing_fee' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'neighborhood_materials_fee' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'schedule_showings_fee' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'attend_showings_fee' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'virtual_tours_fee' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'collect_documents_fee' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'review_lease_fee' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'provide_lease_form_fee' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'coordinate_signing_fee' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'move_in_inspection_fee' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'moving_resources_fee' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'short_term_housing_fee' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'rental_rights_fee' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'lease_advice_fee' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'neighborhood_insights_fee' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'list_criteria_fee' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'assist_application_fee' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'submit_application_fee' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'prepare_application_fee' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'purchase_fee_net_aggregate' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'purchase_fee_purchase_price' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'renewal_fee_lease_value' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'renewal_fee_sales_tax_first_month' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'renewal_fee_sales_tax_flat_fee' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'renewal_fee_sales_tax_lease_value' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'interested_in_property_management_fee' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'interested_in_property_management_fee_flate_free' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'seller_leasing_gross_sales_tax_flat_free_gross' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'seller_leasing_gross_purchase_fee_flat_amount' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'interest_rate' => fn($v) => ListingExportFormatter::fmtPercent($v),
            'max_assumable_rate' => fn($v) => ListingExportFormatter::fmtPercent($v),
            'crypto_percentage' => fn($v) => ListingExportFormatter::fmtPercent($v),
            'nft_percentage' => fn($v) => ListingExportFormatter::fmtPercent($v),
            'cash_percentage_crypto' => fn($v) => ListingExportFormatter::fmtPercent($v),
            'cash_percentage_nft' => fn($v) => ListingExportFormatter::fmtPercent($v),
            'lease_fee_percentage' => fn($v) => ListingExportFormatter::fmtPercent($v),
            'lease_fee_percentage_combo' => fn($v) => ListingExportFormatter::fmtPercent($v),
            'purchase_fee_percentage' => fn($v) => ListingExportFormatter::fmtPercent($v),
            'purchase_fee_percentage_combo' => fn($v) => ListingExportFormatter::fmtPercent($v),
            'lease_option_fee_percentage' => fn($v) => ListingExportFormatter::fmtPercent($v),
            'landlord_broker_percentage_price' => fn($v) => ListingExportFormatter::fmtPercent($v),
            'tenant_broker_percentage' => fn($v) => ListingExportFormatter::fmtPercent($v),
            'tenant_broker_commission_percentage' => fn($v) => ListingExportFormatter::fmtPercent($v),
            'expansion_commission_percentage' => fn($v) => ListingExportFormatter::fmtPercent($v),
            'expansion_gross_percentage' => fn($v) => ListingExportFormatter::fmtPercent($v),
            'expansion_first_month_percentage' => fn($v) => ListingExportFormatter::fmtPercent($v),
            'renewal_fee_percentage' => fn($v) => ListingExportFormatter::fmtPercent($v),
            'gross_percentage_rent' => fn($v) => ListingExportFormatter::fmtPercent($v),
            'seller_lease_option_fee_credit_percent' => fn($v) => ListingExportFormatter::fmtPercent($v),
            'lease_option_fee_credit_percentage' => fn($v) => ListingExportFormatter::fmtPercent($v),
            'purchase_fee_monthly_percentage' => fn($v) => ListingExportFormatter::fmtPercent($v),
            'purchase_fee_gross_rent' => fn($v) => ListingExportFormatter::fmtPercent($v),
            'commission_structure_type_fee_percentage' => fn($v) => ListingExportFormatter::fmtPercent($v),
            'commission_structure_type_fee_percentage_combo' => fn($v) => ListingExportFormatter::fmtPercent($v),
            'lease_fee_percentage_combo_net' => fn($v) => ListingExportFormatter::fmtPercent($v),
            'lease_fee_percentage_net' => fn($v) => ListingExportFormatter::fmtPercent($v),
            'seller_leasing_gross_percentage_combo' => fn($v) => ListingExportFormatter::fmtPercent($v),
            'seller_leasing_gross_percentage_net_combo' => fn($v) => ListingExportFormatter::fmtPercent($v),
            'seller_leasing_gross_ross_percentage_rent' => fn($v) => ListingExportFormatter::fmtPercent($v),
            'lease_option_fee_percentage_combo' => fn($v) => ListingExportFormatter::fmtPercent($v),
            'month_percentage_rent' => fn($v) => ListingExportFormatter::fmtPercent($v),
            'seller_leasing_gross_percentage_no_of_months' => fn($v) => ListingExportFormatter::fmtPercent($v),
            'interested_in_property_management_fee_gross_lease' => fn($v) => ListingExportFormatter::fmtPercent($v),
            'interested_in_property_management_fee_rental_periord' => fn($v) => ListingExportFormatter::fmtPercent($v),
        ];
    }
}
