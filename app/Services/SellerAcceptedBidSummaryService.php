<?php

namespace App\Services;

use App\Models\AcceptedBidSummary;
use App\Models\SellerAgentAuction;
use App\Models\SellerAgentAuctionBid;
use App\Models\SellerCounterTerm;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class SellerAcceptedBidSummaryService
{
    public function generateSummary(SellerAgentAuctionBid $bid, ?SellerCounterTerm $acceptedCounter = null): ?AcceptedBidSummary
    {
        try {
            $listing = $bid->auction;
            if (!$listing) {
                Log::warning('[SellerAcceptedBidSummaryService] Listing not found for bid', ['bid_id' => $bid->id]);
                return null;
            }

            $seller = User::find($listing->user_id);
            $agent  = User::find($bid->user_id);

            if (!$seller || !$agent) {
                Log::warning('[SellerAcceptedBidSummaryService] Seller or agent not found', [
                    'bid_id'           => $bid->id,
                    'listing_user_id'  => $listing->user_id,
                    'bid_user_id'      => $bid->user_id,
                ]);
                return null;
            }

            $sourceData = $acceptedCounter
                ? $this->getCounterData($acceptedCounter)
                : $this->getBidData($bid);

            $html = $this->buildSummaryHtml($listing, $bid, $seller, $agent, $sourceData, $acceptedCounter);

            $summary = AcceptedBidSummary::create([
                'listing_id'          => $listing->id,
                'accepted_bid_id'     => $bid->id,
                'accepted_counter_id' => $acceptedCounter ? $acceptedCounter->id : null,
                'tenant_user_id'      => $seller->id,
                'agent_user_id'       => $agent->id,
                'summary_html'        => $html,
            ]);

            return $summary;

        } catch (\Exception $e) {
            Log::error('[SellerAcceptedBidSummaryService] Failed to generate accepted bid summary', [
                'bid_id' => $bid->id,
                'error'  => $e->getMessage(),
                'trace'  => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    private function getBidData(SellerAgentAuctionBid $bid): array
    {
        $meta     = $bid->meta->pluck('meta_value', 'meta_key')->toArray();
        $services = $meta['services'] ?? '[]';
        return array_merge($meta, [
            'services'       => is_string($services) ? json_decode($services, true) ?? [] : (array) $services,
            'other_services' => json_decode($meta['other_services'] ?? '[]', true) ?? [],
        ]);
    }

    private function getCounterData(SellerCounterTerm $counter): array
    {
        $meta     = $counter->meta->pluck('meta_value', 'meta_key')->toArray();
        $services = $meta['services'] ?? '[]';
        return array_merge($meta, [
            'services'       => is_string($services) ? json_decode($services, true) ?? [] : (array) $services,
            'other_services' => json_decode($meta['other_services'] ?? '[]', true) ?? [],
        ]);
    }

    private function buildSummaryHtml(
        SellerAgentAuction $listing,
        SellerAgentAuctionBid $bid,
        User $seller,
        User $agent,
        array $sourceData,
        ?SellerCounterTerm $counter
    ): string {
        $listingId    = $listing->listing_id ?? 'SAA-' . $listing->id;
        $propertyType = $listing->get->property_type ?? '';
        $address      = $listing->get->address ?? ($listing->get->street_address ?? 'N/A');
        $acceptedDate = now()->format('F j, Y');

        $agentName      = trim(($sourceData['first_name'] ?? $agent->first_name ?? '') . ' ' . ($sourceData['last_name'] ?? $agent->last_name ?? ''));
        $agentBrokerage = $sourceData['brokerage']   ?? $agent->brokerage  ?? 'N/A';
        $agentLicense   = $sourceData['license_no']  ?? $agent->license_no ?? 'N/A';
        $agentNarId     = $sourceData['nar_id']       ?? $agent->nar_id     ?? 'N/A';
        $agentEmail     = $sourceData['email']        ?? $agent->email      ?? 'N/A';
        $agentPhone     = $sourceData['phone']        ?? $agent->phone      ?? 'N/A';

        $sellerName  = trim(($seller->first_name ?? '') . ' ' . ($seller->last_name ?? ''));
        $sellerEmail = $seller->email ?? 'N/A';

        $commissionStructure = $sourceData['commission_structure']      ?? '';
        $purchaseFeeType     = $sourceData['purchase_fee_type']         ?? '';
        $agencyTimeframe     = $sourceData['agency_agreement_timeframe'] ?? '';
        $protectionPeriod    = $sourceData['protection_period']         ?? '';
        $brokerageRelation   = $sourceData['brokerage_relationship']    ?? '';
        $earlyTermination    = $sourceData['early_termination_fee_option'] ?? '';
        $retainerFee         = $sourceData['retainer_fee_option']       ?? '';
        $additionalDetails   = $sourceData['additional_details']        ?? '';

        $services      = $sourceData['services']       ?? [];
        $otherServices = $sourceData['other_services'] ?? [];

        $serviceList = '';
        if (is_array($services) && count($services) > 0) {
            foreach ($services as $svc) {
                $serviceList .= '<li>' . e((string) $svc) . '</li>';
            }
        }
        if (is_array($otherServices) && count($otherServices) > 0) {
            foreach ($otherServices as $svc) {
                if (!empty($svc)) {
                    $serviceList .= '<li>' . e((string) $svc) . ' <em>(Additional)</em></li>';
                }
            }
        }
        if (empty($serviceList)) {
            $serviceList = '<li>No services specified.</li>';
        }

        $compensationRows = '';
        if ($commissionStructure) {
            $compensationRows .= '<tr><td><strong>Commission Structure</strong></td><td>' . e($commissionStructure) . '</td></tr>';
        }
        if ($purchaseFeeType) {
            $label = $this->formatPurchaseFeeType($purchaseFeeType);
            $compensationRows .= '<tr><td><strong>Purchase Fee Type</strong></td><td>' . e($label) . '</td></tr>';
            $compensationRows .= $this->buildPurchaseFeeDetails($sourceData, $purchaseFeeType);
        }
        if ($agencyTimeframe) {
            $compensationRows .= '<tr><td><strong>Agency Agreement Timeframe</strong></td><td>' . e($agencyTimeframe) . '</td></tr>';
        }
        if ($protectionPeriod) {
            $compensationRows .= '<tr><td><strong>Protection Period</strong></td><td>' . e($protectionPeriod) . '</td></tr>';
        }
        if ($brokerageRelation) {
            $compensationRows .= '<tr><td><strong>Brokerage Relationship</strong></td><td>' . e($brokerageRelation) . '</td></tr>';
        }
        if ($earlyTermination) {
            $compensationRows .= '<tr><td><strong>Early Termination Fee</strong></td><td>' . e($earlyTermination) . '</td></tr>';
        }
        if ($retainerFee) {
            $compensationRows .= '<tr><td><strong>Retainer Fee</strong></td><td>' . e($retainerFee) . '</td></tr>';
        }
        if (!$compensationRows) {
            $compensationRows = '<tr><td colspan="2">No compensation terms specified.</td></tr>';
        }

        $additionalSection = '';
        if (!empty($additionalDetails)) {
            $additionalSection = '<div class="section">
<h3>Additional Details</h3>
<p>' . nl2br(e($additionalDetails)) . '</p>
</div>';
        }

        $counterNote = $counter
            ? '<p><strong>Note:</strong> This agreement was finalized via a counter offer.</p>'
            : '';

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Accepted Bid Summary - Seller Agent Agreement</title>
<style>
body { font-family: Arial, sans-serif; font-size: 14px; color: #333; }
h2 { color: #0056b3; }
h3 { color: #444; border-bottom: 1px solid #ccc; padding-bottom: 4px; }
table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
td { padding: 6px 10px; }
tr:nth-child(even) { background: #f9f9f9; }
ul { padding-left: 20px; }
.section { margin-bottom: 24px; }
</style>
</head>
<body>
<h2>Accepted Bid Summary &mdash; Hire a Seller&rsquo;s Agent</h2>
<p><strong>Date Accepted:</strong> {$acceptedDate}</p>
{$counterNote}
<div class="section">
<h3>Listing Information</h3>
<table>
<tr><td><strong>Listing ID</strong></td><td>{$listingId}</td></tr>
<tr><td><strong>Property Address</strong></td><td>{$address}</td></tr>
<tr><td><strong>Property Type</strong></td><td>{$propertyType}</td></tr>
</table>
</div>
<div class="section">
<h3>Seller Information</h3>
<table>
<tr><td><strong>Name</strong></td><td>{$sellerName}</td></tr>
<tr><td><strong>Email</strong></td><td>{$sellerEmail}</td></tr>
</table>
</div>
<div class="section">
<h3>Agent Information</h3>
<table>
<tr><td><strong>Name</strong></td><td>{$agentName}</td></tr>
<tr><td><strong>Brokerage</strong></td><td>{$agentBrokerage}</td></tr>
<tr><td><strong>License No.</strong></td><td>{$agentLicense}</td></tr>
<tr><td><strong>NAR/MLS ID</strong></td><td>{$agentNarId}</td></tr>
<tr><td><strong>Email</strong></td><td>{$agentEmail}</td></tr>
<tr><td><strong>Phone</strong></td><td>{$agentPhone}</td></tr>
</table>
</div>
<div class="section">
<h3>Broker Compensation &amp; Agency Agreement Terms</h3>
<table>
{$compensationRows}
</table>
</div>
<div class="section">
<h3>Agreed Services</h3>
<ul>{$serviceList}</ul>
</div>
{$additionalSection}
<div class="section">
<h3>Seller Acknowledgement</h3>
<p><strong>Signature:</strong> {{tenant_signature_name}}</p>
<p><strong>Date/Time:</strong> {{tenant_signed_at}}</p>
<p><strong>IP Address:</strong> {{tenant_ip_address}}</p>
</div>
<div class="section">
<h3>Agent Acknowledgement</h3>
<p><strong>Signature:</strong> {{agent_signature_name}}</p>
<p><strong>Date/Time:</strong> {{agent_signed_at}}</p>
<p><strong>IP Address:</strong> {{agent_ip_address}}</p>
</div>
</body>
</html>
HTML;
    }

    private function formatPurchaseFeeType(string $type): string
    {
        return match ($type) {
            'flat'       => 'Flat Fee',
            'percentage' => 'Percentage',
            'combo'      => 'Combo (Flat + Percentage)',
            'other'      => 'Other',
            default      => ucfirst($type),
        };
    }

    private function buildPurchaseFeeDetails(array $d, string $type): string
    {
        $rows = '';
        if ($type === 'flat' && !empty($d['purchase_fee_flat'])) {
            $rows .= '<tr><td><strong>Flat Fee Amount</strong></td><td>' . e($d['purchase_fee_flat']) . '</td></tr>';
        }
        if ($type === 'percentage' && !empty($d['purchase_fee_percentage'])) {
            $rows .= '<tr><td><strong>Percentage</strong></td><td>' . e($d['purchase_fee_percentage']) . '%</td></tr>';
        }
        if ($type === 'combo') {
            if (!empty($d['purchase_fee_percentage_combo'])) {
                $rows .= '<tr><td><strong>Combo Percentage</strong></td><td>' . e($d['purchase_fee_percentage_combo']) . '%</td></tr>';
            }
            if (!empty($d['purchase_fee_flat_combo'])) {
                $rows .= '<tr><td><strong>Combo Flat Amount</strong></td><td>' . e($d['purchase_fee_flat_combo']) . '</td></tr>';
            }
        }
        if ($type === 'other' && !empty($d['purchase_fee_other'])) {
            $rows .= '<tr><td><strong>Other Fee Description</strong></td><td>' . e($d['purchase_fee_other']) . '</td></tr>';
        }
        return $rows;
    }
}
