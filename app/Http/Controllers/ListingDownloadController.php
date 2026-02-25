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
            'lease_purchase_rent_credit_amount' => fn($v) => ListingExportFormatter::fmtMoney($v),
            'lease_purchase_deposit' => fn($v) => ListingExportFormatter::fmtMoney($v),
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
        ];
    }
}
