<?php

namespace App\Services;

use App\Models\AcceptedBidSummary;
use App\Models\BuyerAgentAuction;
use App\Models\BuyerAgentAuctionBid;
use App\Models\BuyerCounterBidding;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class BuyerAcceptedBidSummaryService
{
    public function generateSummary(BuyerAgentAuctionBid $bid, ?BuyerCounterBidding $acceptedCounter = null): ?AcceptedBidSummary
    {
        try {
            $listing = $bid->auction;
            if (!$listing) {
                Log::warning('[BuyerAcceptedBidSummaryService] Listing not found for bid', ['bid_id' => $bid->id]);
                return null;
            }

            $buyer = User::find($listing->user_id);
            $agent = User::find($bid->user_id);

            if (!$buyer || !$agent) {
                Log::warning('[BuyerAcceptedBidSummaryService] Buyer or agent not found', [
                    'bid_id' => $bid->id,
                    'listing_user_id' => $listing->user_id,
                    'bid_user_id' => $bid->user_id,
                ]);
                return null;
            }

            $sourceData = $acceptedCounter
                ? $this->getCounterData($acceptedCounter)
                : $this->getBidData($bid);

            $html = $this->buildSummaryHtml($listing, $bid, $buyer, $agent, $sourceData, $acceptedCounter);

            $summary = AcceptedBidSummary::create([
                'listing_id'         => $listing->id,
                'accepted_bid_id'    => $bid->id,
                'accepted_counter_id' => $acceptedCounter ? $acceptedCounter->id : null,
                'tenant_user_id'     => $buyer->id,
                'agent_user_id'      => $agent->id,
                'summary_html'       => $html,
            ]);

            return $summary;
        } catch (\Exception $e) {
            Log::error('[BuyerAcceptedBidSummaryService] Failed to generate accepted bid summary', [
                'bid_id' => $bid->id,
                'error'  => $e->getMessage(),
                'trace'  => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    private function getBidData(BuyerAgentAuctionBid $bid): array
    {
        $meta = $bid->meta->pluck('meta_value', 'meta_key')->toArray();
        $services = $meta['services'] ?? '[]';
        return array_merge($meta, [
            'services'     => is_string($services) ? json_decode($services, true) ?? [] : (array) $services,
            'other_services' => json_decode($meta['other_services'] ?? '[]', true) ?? [],
        ]);
    }

    private function getCounterData(BuyerCounterBidding $counter): array
    {
        $meta = $counter->meta->pluck('meta_value', 'meta_key')->toArray();
        $services = $meta['services'] ?? '[]';
        return array_merge($meta, [
            'services'     => is_string($services) ? json_decode($services, true) ?? [] : (array) $services,
            'other_services' => json_decode($meta['other_services'] ?? '[]', true) ?? [],
        ]);
    }

    private function buildSummaryHtml(
        BuyerAgentAuction $listing,
        BuyerAgentAuctionBid $bid,
        User $buyer,
        User $agent,
        array $sourceData,
        ?BuyerCounterBidding $counter
    ): string {
        $listingId = $listing->listing_id ?? 'BAA-' . $listing->id;
        $address = $listing->get->address ?? 'N/A';
        $propertyType = $listing->get->property_type ?? '';
        $acceptedDate = now()->format('F j, Y');

        $agentName = trim(($sourceData['first_name'] ?? $agent->first_name ?? '') . ' ' . ($sourceData['last_name'] ?? $agent->last_name ?? ''));
        $agentBrokerage = $sourceData['brokerage'] ?? $agent->brokerage ?? 'N/A';
        $agentLicense = $sourceData['license_no'] ?? $agent->license_no ?? 'N/A';
        $agentEmail = $sourceData['email'] ?? $agent->email ?? 'N/A';
        $agentPhone = $sourceData['phone'] ?? $agent->phone ?? 'N/A';

        $buyerName = trim(($buyer->first_name ?? '') . ' ' . ($buyer->last_name ?? ''));
        $buyerEmail = $buyer->email ?? 'N/A';

        $commissionStructure = $sourceData['commission_structure'] ?? 'N/A';
        $purchaseFeeType = $sourceData['purchase_fee_type'] ?? 'N/A';
        $agencyTimeframe = $sourceData['agency_agreement_timeframe'] ?? 'N/A';
        $protectionPeriod = $sourceData['protection_period'] ?? 'N/A';

        $services = $sourceData['services'] ?? [];
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

        $counterNote = $counter
            ? '<p><strong>Note:</strong> This agreement was finalized via a counter offer.</p>'
            : '';

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Accepted Bid Summary - Buyer Agent Agreement</title>
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
<h2>Accepted Bid Summary &mdash; Hire a Buyer&rsquo;s Agent</h2>
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
<h3>Buyer Information</h3>
<table>
<tr><td><strong>Name</strong></td><td>{$buyerName}</td></tr>
<tr><td><strong>Email</strong></td><td>{$buyerEmail}</td></tr>
</table>
</div>
<div class="section">
<h3>Agent Information</h3>
<table>
<tr><td><strong>Name</strong></td><td>{$agentName}</td></tr>
<tr><td><strong>Brokerage</strong></td><td>{$agentBrokerage}</td></tr>
<tr><td><strong>License No.</strong></td><td>{$agentLicense}</td></tr>
<tr><td><strong>Email</strong></td><td>{$agentEmail}</td></tr>
<tr><td><strong>Phone</strong></td><td>{$agentPhone}</td></tr>
</table>
</div>
<div class="section">
<h3>Compensation &amp; Agreement Terms</h3>
<table>
<tr><td><strong>Commission Structure</strong></td><td>{$commissionStructure}</td></tr>
<tr><td><strong>Purchase Fee Type</strong></td><td>{$purchaseFeeType}</td></tr>
<tr><td><strong>Agency Agreement Timeframe</strong></td><td>{$agencyTimeframe}</td></tr>
<tr><td><strong>Protection Period</strong></td><td>{$protectionPeriod}</td></tr>
</table>
</div>
<div class="section">
<h3>Agreed Services</h3>
<ul>{$serviceList}</ul>
</div>
<div class="section">
<h3>Buyer Acknowledgement</h3>
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
}
