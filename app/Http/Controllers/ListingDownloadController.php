<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SellerAgentAuction;
use App\Models\BuyerCriteriaAuction;
use App\Models\LandlordAgentAuction;
use App\Models\TenantAgentAuction;
use App\Helpers\ListingDisplayHelper;
use Barryvdh\DomPDF\Facade\Pdf;

class ListingDownloadController extends Controller
{
    public function seller($id)
    {
        $auction = SellerAgentAuction::with('meta', 'user')->findOrFail($id);
        $this->authorizeView($auction);

        $data = $this->buildSellerData($auction);
        $filename = 'seller-listing-' . ($auction->listing_id ?? $auction->id) . '.pdf';

        return $this->generatePdf('listing-download.seller', $data, $filename);
    }

    public function buyer($id)
    {
        $auction = BuyerCriteriaAuction::with('meta', 'user')->findOrFail($id);
        $this->authorizeView($auction);

        $data = $this->buildBuyerData($auction);
        $filename = 'buyer-listing-' . ($auction->listing_id ?? $auction->id) . '.pdf';

        return $this->generatePdf('listing-download.buyer', $data, $filename);
    }

    public function landlord($id)
    {
        $auction = LandlordAgentAuction::with('meta', 'user')->findOrFail($id);
        $this->authorizeView($auction);

        $data = $this->buildLandlordData($auction);
        $filename = 'landlord-listing-' . ($auction->listing_id ?? $auction->id) . '.pdf';

        return $this->generatePdf('listing-download.landlord', $data, $filename);
    }

    public function tenant($id)
    {
        $auction = TenantAgentAuction::with('meta', 'user')->findOrFail($id);
        $this->authorizeView($auction);

        $data = $this->buildTenantData($auction);
        $filename = 'tenant-listing-' . ($auction->listing_id ?? $auction->id) . '.pdf';

        return $this->generatePdf('listing-download.tenant', $data, $filename);
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

    protected function getMeta($auction): object
    {
        return $auction->get ?? (object) [];
    }

    protected function getServices($auction): array
    {
        $meta = $this->getMeta($auction);
        $services = is_array($meta->services ?? null) ? $meta->services : [];
        $otherServices = is_array($meta->other_services ?? null) ? $meta->other_services : [];
        return array_merge($services, $otherServices);
    }

    protected function buildSellerData($auction): array
    {
        $meta = $this->getMeta($auction);
        $propType = ListingDisplayHelper::normalizePropertyType($meta->property_type ?? '');

        $sections = [];

        $sections['Listing Details'] = array_filter([
            'Listing ID' => $auction->listing_id ?? null,
            'Listing Type' => $meta->auction_type ?? null,
            'Property Type' => $propType,
        ], fn($v) => ListingDisplayHelper::hasValue($v));

        $sections['Property Details'] = array_filter([
            'Address' => $auction->address ?? null,
            'Property Style' => $this->formatListOrText($meta->property_items ?? null, $meta->other_property_style ?? null),
            'Bedrooms' => $meta->bedrooms ?? null,
            'Bathrooms' => $meta->bathrooms ?? null,
            'Square Footage' => $meta->sqft ? ListingDisplayHelper::fmtNumber($meta->sqft) : null,
            'Year Built' => $meta->year_built ?? null,
            'Lot Size' => $meta->lot_size ?? null,
            'Property Condition' => $this->formatListOrText($meta->property_conditions ?? null, $meta->other_property_condition ?? null),
        ], fn($v) => ListingDisplayHelper::hasValue($v));

        if (in_array($meta->property_type ?? '', ['Commercial', 'Commercial Property', 'Business', 'Business Opportunity', 'Income', 'Income Property'])) {
            $investmentMetrics = array_filter([
                'Annual Net Income' => isset($meta->minimum_annual_net_income) ? ListingDisplayHelper::fmtMoney($meta->minimum_annual_net_income) : null,
                'Cap Rate' => isset($meta->minimum_cap_rate) ? ListingDisplayHelper::fmtPercent($meta->minimum_cap_rate) : null,
                'Included Assets' => $this->formatListOrText($meta->assets ?? null, $meta->assets_other ?? null),
            ], fn($v) => ListingDisplayHelper::hasValue($v));
            if (!empty($investmentMetrics)) {
                $sections['Income & Investment Metrics'] = $investmentMetrics;
            }
        }

        $sections['Sale Terms'] = array_filter([
            'Asking Price' => isset($meta->asking_price) ? ListingDisplayHelper::fmtMoney($meta->asking_price) : null,
            'Offered Financing' => $this->formatListOrText($meta->offeredFinancing ?? null),
            'Special Sale Provisions' => $this->formatListOrText($meta->special_sale_provisions ?? null, $meta->special_sale_other ?? null),
        ], fn($v) => ListingDisplayHelper::hasValue($v));

        $services = $this->getServices($auction);

        return [
            'auction' => $auction,
            'meta' => $meta,
            'role' => 'Seller',
            'sections' => array_filter($sections, fn($s) => !empty($s)),
            'services' => $services,
        ];
    }

    protected function buildBuyerData($auction): array
    {
        $meta = $this->getMeta($auction);
        $propType = ListingDisplayHelper::normalizePropertyType($meta->property_type ?? $meta->titleListing ?? '');

        $sections = [];

        $sections['Listing Details'] = array_filter([
            'Listing ID' => $auction->listing_id ?? null,
            'Listing Type' => $meta->auction_type ?? null,
            'Property Type' => $propType,
            'Service Type' => $meta->service_type ?? null,
        ], fn($v) => ListingDisplayHelper::hasValue($v));

        $sections['Property Preferences'] = array_filter([
            'Property Style' => $this->formatListOrText($meta->property_items ?? null, $meta->other_property_style ?? null),
            'Bedrooms' => $meta->bedrooms ?? null,
            'Bathrooms' => $meta->bathrooms ?? $meta->bathroomsRes ?? null,
            'Min Square Footage' => isset($meta->min_sqft) ? ListingDisplayHelper::fmtNumber($meta->min_sqft) : null,
            'Acceptable Cities' => $this->formatListOrText($meta->cities ?? null),
            'Acceptable Counties' => $this->formatListOrText($meta->counties ?? null),
            'State' => $meta->state ?? null,
        ], fn($v) => ListingDisplayHelper::hasValue($v));

        $sections['Financing'] = array_filter([
            'Offered Financing' => $this->formatListOrText($meta->financings ?? null, $meta->financingOther ?? null),
            'Max Purchase Price' => isset($meta->max_purchase_price) ? ListingDisplayHelper::fmtMoney($meta->max_purchase_price) : null,
        ], fn($v) => ListingDisplayHelper::hasValue($v));

        if (in_array($meta->titleListing ?? '', ['Income Property', 'Commercial Property'])) {
            $investmentMetrics = array_filter([
                'Minimum Annual Net Income' => isset($meta->minimum_annual_net_income) ? ListingDisplayHelper::fmtMoney($meta->minimum_annual_net_income) : null,
                'Minimum Cap Rate' => isset($meta->minimum_cap_rate) ? ListingDisplayHelper::fmtPercent($meta->minimum_cap_rate) : null,
            ], fn($v) => ListingDisplayHelper::hasValue($v));
            if (!empty($investmentMetrics)) {
                $sections['Income & Investment Metrics'] = $investmentMetrics;
            }
        }

        return [
            'auction' => $auction,
            'meta' => $meta,
            'role' => 'Buyer',
            'sections' => array_filter($sections, fn($s) => !empty($s)),
            'services' => [],
        ];
    }

    protected function buildLandlordData($auction): array
    {
        $meta = $this->getMeta($auction);
        $propType = ListingDisplayHelper::normalizePropertyType($meta->property_type ?? '');
        $isCommercial = str_contains(strtolower($meta->property_type ?? ''), 'commercial');

        $sections = [];

        $sections['Listing Details'] = array_filter([
            'Listing ID' => $auction->listing_id ?? null,
            'Listing Type' => $meta->auction_type ?? null,
            'Property Type' => $propType,
        ], fn($v) => ListingDisplayHelper::hasValue($v));

        $sections['Property Details'] = array_filter([
            'City' => $meta->property_city ?? null,
            'County' => $meta->property_county ?? null,
            'State' => $meta->property_state ?? $meta->state ?? null,
            'Zip Code' => $meta->property_zip ?? $meta->zip_code ?? null,
            'Property Style' => $this->formatListOrText($meta->property_items ?? null, $meta->other_property_style ?? null),
            'Bedrooms' => $meta->bedrooms ?? null,
            'Bathrooms' => $meta->bathrooms ?? null,
            'Square Footage' => isset($meta->sqft) ? ListingDisplayHelper::fmtNumber($meta->sqft) : null,
        ], fn($v) => ListingDisplayHelper::hasValue($v));

        $leasingTerms = array_filter([
            'Occupant Type' => $meta->occupant_status ?? null,
            'Leasing Space' => $meta->leasing_spaces ?? null,
            'Desired Rental Amount' => isset($meta->desired_rental_amount) ? ListingDisplayHelper::fmtMoney($meta->desired_rental_amount) : null,
            'Lease Amount Frequency' => $meta->lease_amount_frequency ?? null,
            'Tenant Responsible For' => $this->formatListOrText($meta->tenant_pays ?? null, $meta->other_tenant_pays ?? null),
        ], fn($v) => ListingDisplayHelper::hasValue($v));

        if ($isCommercial) {
            $ownerPays = $this->formatListOrText($meta->owner_pays ?? null, $meta->other_owner_pays ?? null);
            if (ListingDisplayHelper::hasValue($ownerPays)) {
                $leasingTerms['Owner Responsible For'] = $ownerPays;
            }
        }

        if (!empty($leasingTerms)) {
            $sections['Leasing Terms'] = $leasingTerms;
        }

        $services = $this->getServices($auction);

        return [
            'auction' => $auction,
            'meta' => $meta,
            'role' => 'Landlord',
            'sections' => array_filter($sections, fn($s) => !empty($s)),
            'services' => $services,
        ];
    }

    protected function buildTenantData($auction): array
    {
        $meta = $this->getMeta($auction);
        $propType = ListingDisplayHelper::normalizePropertyType($meta->property_type ?? '');

        $sections = [];

        $sections['Listing Details'] = array_filter([
            'Listing ID' => $auction->listing_id ?? null,
            'Listing Type' => $meta->auction_type ?? null,
            'Acceptable Property Type' => $propType,
        ], fn($v) => ListingDisplayHelper::hasValue($v));

        $sections['Property Preferences'] = array_filter([
            'Property Style' => $this->formatListOrText($meta->property_items ?? null, $meta->other_property_style ?? null),
            'Bedrooms' => $meta->bedrooms ?? null,
            'Bathrooms' => $meta->bathrooms ?? null,
            'Min Square Footage' => isset($meta->sqft) ? ListingDisplayHelper::fmtNumber($meta->sqft) : null,
            'Acceptable Cities' => $this->formatListOrText($meta->cities ?? null),
            'Acceptable Counties' => $this->formatListOrText($meta->counties ?? null),
            'State' => $meta->state ?? null,
        ], fn($v) => ListingDisplayHelper::hasValue($v));

        $sections['Leasing Terms'] = array_filter([
            'Desired Rental Amount' => isset($meta->desired_rental_amount) ? ListingDisplayHelper::fmtMoney($meta->desired_rental_amount) : null,
            'Lease Amount Frequency' => $meta->lease_amount_frequency ?? null,
            'Desired Move-In Date' => isset($meta->desired_move_in) ? ListingDisplayHelper::fmtDate($meta->desired_move_in) : null,
        ], fn($v) => ListingDisplayHelper::hasValue($v));

        $services = $this->getServices($auction);

        return [
            'auction' => $auction,
            'meta' => $meta,
            'role' => 'Tenant',
            'sections' => array_filter($sections, fn($s) => !empty($s)),
            'services' => $services,
        ];
    }

    protected function formatListOrText($value, $otherText = null): ?string
    {
        $items = ListingDisplayHelper::normalizeList($value, $otherText);
        if (empty($items)) return null;
        return implode(', ', $items);
    }
}
