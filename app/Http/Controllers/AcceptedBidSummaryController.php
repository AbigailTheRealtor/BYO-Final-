<?php

namespace App\Http\Controllers;

use App\Models\AcceptedBidSummary;
use App\Models\AcknowledgementDocument;
use App\Services\AcceptedBidSummaryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Barryvdh\DomPDF\Facade\Pdf;

class AcceptedBidSummaryController extends Controller
{
    protected AcceptedBidSummaryService $summaryService;

    public function __construct(AcceptedBidSummaryService $summaryService)
    {
        $this->summaryService = $summaryService;
    }

    public function view($id)
    {
        $summary = AcceptedBidSummary::findOrFail($id);

        $user = Auth::user();
        if (!$this->canAccessSummary($summary, $user)) {
            abort(403, 'You are not authorized to view this summary.');
        }

        $html = $this->summaryService->getRenderedHtml($summary);

        $canUploadAcknowledgementDocuments = ($user->id === $summary->tenant_user_id);

        $existingDocs = $canUploadAcknowledgementDocuments
            ? AcknowledgementDocument::where('accepted_bid_summary_id', $summary->id)
                ->where('user_id', $user->id)
                ->first()
            : null;

        $sharedDocs = AcknowledgementDocument::where('accepted_bid_summary_id', $summary->id)
            ->where('user_id', $summary->tenant_user_id)
            ->first();

        // For buyer/tenant listing types, attempt to load property-being-offered data
        // from any accepted offer in the chain linked to this listing (via OfferAuction).
        $resolved           = $this->resolveOfferPropertyData($summary);
        $offerPropertyMetas = $resolved['metas'];
        $offerForPhotos     = $resolved['offer'];

        return view('accepted_bid_summary.view', [
            'summary'                           => $summary,
            'html'                              => $html,
            'canSign'                           => $this->canUserSign($summary, $user),
            'userRole'                          => $this->getUserRole($summary, $user),
            'canUploadAcknowledgementDocuments' => $canUploadAcknowledgementDocuments,
            'existingDocs'                      => $existingDocs,
            'sharedDocs'                        => $sharedDocs,
            'offerPropertyMetas'                => $offerPropertyMetas,
            'offerForPhotos'                    => $offerForPhotos,
        ]);
    }

    public function showSignForm($id)
    {
        $summary = AcceptedBidSummary::findOrFail($id);

        $user = Auth::user();
        if (!$this->canAccessSummary($summary, $user)) {
            abort(403, 'You are not authorized to sign this summary.');
        }

        $userRole = $this->getUserRole($summary, $user);
        
        if (!$this->canUserSign($summary, $user)) {
            return redirect()->route('accepted-bid-summary.view', $id)
                ->with('info', 'You have already signed this document.');
        }

        $html = $this->summaryService->getRenderedHtml($summary);

        // Inject property-being-offered data into sign page HTML (service-rendered path).
        $signResolved = $this->resolveOfferPropertyData($summary);
        if ($signResolved['metas'] !== null) {
            $propHtml = $this->buildOfferPropertySectionHtml($signResolved['metas'], $signResolved['offer']);
            if ($propHtml !== '') {
                $html .= $propHtml;
            }
        }

        $canUploadAcknowledgementDocuments = ($user->id === $summary->tenant_user_id);

        $existingDocs = $canUploadAcknowledgementDocuments
            ? AcknowledgementDocument::where('accepted_bid_summary_id', $summary->id)
                ->where('user_id', $user->id)
                ->first()
            : null;

        $sharedDocs = AcknowledgementDocument::where('accepted_bid_summary_id', $summary->id)
            ->where('user_id', $summary->tenant_user_id)
            ->first();

        return view('accepted_bid_summary.sign', [
            'summary'                           => $summary,
            'html'                              => $html,
            'userRole'                          => $userRole,
            'existingDocs'                      => $existingDocs,
            'sharedDocs'                        => $sharedDocs,
            'canUploadAcknowledgementDocuments' => $canUploadAcknowledgementDocuments,
            'offerPropertyMetas'                => $signResolved['metas'],
            'offerForPhotos'                    => $signResolved['offer'],
        ]);
    }

    public function sign(Request $request, $id)
    {
        $request->validate([
            'signature_name' => 'required|string|min:2|max:255',
            'timezone' => 'nullable|string|max:100',
            'checkbox_confirmed' => 'required|accepted',
        ], [
            'checkbox_confirmed.required' => 'You must confirm you reviewed the Accepted Bid Summary before signing.',
            'checkbox_confirmed.accepted' => 'You must confirm you reviewed the Accepted Bid Summary before signing.',
        ]);

        $summary = AcceptedBidSummary::findOrFail($id);

        $user = Auth::user();
        if (!$this->canAccessSummary($summary, $user)) {
            abort(403, 'You are not authorized to sign this summary.');
        }

        $userRole = $this->getUserRole($summary, $user);
        
        if (!$this->canUserSign($summary, $user)) {
            return redirect()->route('accepted-bid-summary.view', $id)
                ->with('info', 'You have already signed this document.');
        }

        $ipAddress = $this->getClientIp($request);
        $timezone = $this->sanitizeTimezone($request->input('timezone'));
        $userAgent = $request->userAgent();

        try {
            $this->summaryService->updateSignature($summary, $userRole, $request->signature_name, $ipAddress, $timezone, $userAgent);

            if ($summary->isFullySigned()) {
                $this->generatePdf($summary);
            }

            return redirect()->route('accepted-bid-summary.view', $id)
                ->with('success', 'Your acknowledgement has been recorded successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to record signature', [
                'summary_id' => $id,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return redirect()->back()->with('error', 'Failed to record your acknowledgement. Please try again.');
        }
    }

    public function downloadPdf($id)
    {
        $summary = AcceptedBidSummary::findOrFail($id);

        $user = Auth::user();
        if (!$this->canAccessSummary($summary, $user)) {
            abort(403, 'You are not authorized to download this summary.');
        }

        if (!$summary->isFullySigned()) {
            return redirect()->route('accepted-bid-summary.view', $id)
                ->with('error', 'PDF is only available after both parties have signed.');
        }

        if ($summary->summary_pdf_path && file_exists(storage_path('app/' . $summary->summary_pdf_path))) {
            return response()->download(
                storage_path('app/' . $summary->summary_pdf_path),
                'accepted-bid-summary-' . $summary->id . '.pdf'
            );
        }

        $pdf = $this->generatePdf($summary);

        return response()->download(
            storage_path('app/' . $summary->summary_pdf_path),
            'accepted-bid-summary-' . $summary->id . '.pdf'
        );
    }

    protected function generatePdf(AcceptedBidSummary $summary): AcceptedBidSummary
    {
        try {
            $html = $this->summaryService->getRenderedHtml($summary);

            // Inject property-being-offered section for buyer/tenant summaries.
            // This ensures PDF output (via getRenderedHtml service path) includes property data.
            $pdfResolved = $this->resolveOfferPropertyData($summary);
            if ($pdfResolved['metas'] !== null) {
                $propHtml = $this->buildOfferPropertySectionHtml($pdfResolved['metas'], $pdfResolved['offer']);
                if ($propHtml !== '') {
                    $html .= $propHtml;
                }
            }

            $pdf = Pdf::loadHTML($html);
            $pdf->setPaper('A4', 'portrait');

            $filename = 'accepted_bid_summaries/summary_' . $summary->id . '_' . time() . '.pdf';
            $pdfContent = $pdf->output();

            if (!file_exists(storage_path('app/accepted_bid_summaries'))) {
                mkdir(storage_path('app/accepted_bid_summaries'), 0755, true);
            }

            file_put_contents(storage_path('app/' . $filename), $pdfContent);

            $summary->summary_pdf_path = $filename;
            $summary->save();

            return $summary;
        } catch (\Exception $e) {
            Log::error('Failed to generate PDF for accepted bid summary', [
                'summary_id' => $summary->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function storeDocuments(Request $request, $id)
    {
        $summary = AcceptedBidSummary::findOrFail($id);
        $user    = Auth::user();

        if (!$this->canAccessSummary($summary, $user)) {
            abort(403, 'You are not authorized to upload documents for this summary.');
        }

        // Only the listing owner (tenant_user_id) may upload documents
        if ($user->id !== $summary->tenant_user_id) {
            return redirect()->route('accepted-bid-summary.sign', $id)
                ->with('error', 'Only the listing owner can upload documents.');
        }

        $uploadDir = storage_path('app/acknowledgement_documents');
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $doc = AcknowledgementDocument::firstOrNew([
            'accepted_bid_summary_id' => $summary->id,
            'user_id'                 => $user->id,
        ]);
        $doc->selected_agent_user_id = $summary->agent_user_id;

        $fileFields = [
            'id_document'         => 'id_document_path',
            'proof_of_funds'      => 'proof_of_funds_path',
            'pre_approval_letter' => 'pre_approval_letter_path',
            'proof_of_income'     => 'proof_of_income_path',
        ];

        foreach ($fileFields as $inputName => $column) {
            if ($request->hasFile($inputName) && $request->file($inputName)->isValid()) {
                $file = $request->file($inputName);
                $ext  = strtolower($file->getClientOriginalExtension());
                if (in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'])) {
                    $filename = 'ack_doc_' . $summary->id . '_' . $user->id . '_' . Str::random(8) . '.' . $ext;
                    $file->move($uploadDir, $filename);
                    $doc->{$column} = 'acknowledgement_documents/' . $filename;
                }
            }
        }

        if ($request->filled('property_record_link')) {
            $doc->property_record_link = $request->input('property_record_link');
        }

        $doc->save();

        return redirect()->route('accepted-bid-summary.view', $id)
            ->with('doc_success', 'Your documents were saved successfully.');
    }

    public function downloadDocument(Request $request, $id, $type)
    {
        $summary = AcceptedBidSummary::findOrFail($id);

        $user = Auth::user();
        if (!$this->canAccessSummary($summary, $user)) {
            abort(403, 'You are not authorized to access this file.');
        }

        $allowedTypes = [
            'id_document'         => 'id_document_path',
            'proof_of_funds'      => 'proof_of_funds_path',
            'pre_approval_letter' => 'pre_approval_letter_path',
            'proof_of_income'     => 'proof_of_income_path',
        ];

        if (!array_key_exists($type, $allowedTypes)) {
            abort(404, 'Document type not found.');
        }

        $doc = AcknowledgementDocument::where('accepted_bid_summary_id', $summary->id)
            ->where('user_id', $summary->tenant_user_id)
            ->first();

        if (!$doc) {
            abort(404, 'No documents found for this summary.');
        }

        $column = $allowedTypes[$type];
        $path   = $doc->{$column};

        if (empty($path)) {
            abort(404, 'This document has not been uploaded.');
        }

        $fullPath = storage_path('app/' . $path);

        if (!file_exists($fullPath)) {
            abort(404, 'File not found on disk.');
        }

        return response()->file($fullPath, [
            'Content-Disposition' => 'inline; filename="' . basename($path) . '"',
        ]);
    }

    protected function canAccessSummary(AcceptedBidSummary $summary, $user): bool
    {
        return $user->id === $summary->tenant_user_id || $user->id === $summary->agent_user_id;
    }

    protected function canUserSign(AcceptedBidSummary $summary, $user): bool
    {
        if ($user->id === $summary->tenant_user_id && !$summary->isTenantSigned()) {
            return true;
        }
        if ($user->id === $summary->agent_user_id && !$summary->isAgentSigned()) {
            return true;
        }
        return false;
    }

    protected function getUserRole(AcceptedBidSummary $summary, $user): ?string
    {
        if ($user->id === $summary->tenant_user_id) {
            return 'tenant';
        }
        if ($user->id === $summary->agent_user_id) {
            return 'agent';
        }
        return null;
    }

    public function getByBid($bidId)
    {
        $summary = AcceptedBidSummary::where('accepted_bid_id', $bidId)->first();

        if (!$summary) {
            return redirect()->back()->with('error', 'No accepted bid summary found for this bid.');
        }

        $user = Auth::user();
        if (!$this->canAccessSummary($summary, $user)) {
            abort(403, 'You are not authorized to view this summary.');
        }

        return redirect()->route('accepted-bid-summary.view', $summary->id);
    }

    protected function getClientIp(Request $request): ?string
    {
        $forwardedFor = $request->header('X-Forwarded-For');
        if ($forwardedFor) {
            $ips = array_map('trim', explode(',', $forwardedFor));
            $clientIp = $ips[0] ?? null;
            if ($clientIp && filter_var($clientIp, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $clientIp;
            }
        }

        $realIp = $request->header('X-Real-IP');
        if ($realIp && filter_var($realIp, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return $realIp;
        }

        return $request->ip();
    }

    protected function sanitizeTimezone(?string $timezone): string
    {
        if (empty($timezone)) {
            return 'Unknown';
        }

        $validTimezones = timezone_identifiers_list();
        if (in_array($timezone, $validTimezones)) {
            return $timezone;
        }

        return 'Unknown';
    }

    /**
     * Locate the root offer's property/match metas for a buyer or tenant summary.
     *
     * For the Offer response system (separate from the agent-hire AcceptedBidSummary flow),
     * property data lives on the root offer's OfferMeta records. Counter-offers inherit these
     * metas via the termMetaKeys copy-on-counter logic; we walk the parent_offer_id chain to
     * confirm the root is always the canonical source.
     *
     * Scoped by both listing_id AND role (= listing_type) to prevent cross-listing data exposure.
     *
     * Returns ['metas' => Collection|null, 'offer' => Offer|null].
     */
    private function resolveOfferPropertyData(AcceptedBidSummary $summary): array
    {
        $result = ['metas' => null, 'offer' => null];

        if (!in_array($summary->listing_type, ['buyer', 'tenant'])) {
            return $result;
        }

        try {
            // Scope to the specific bidder (tenant_user_id = the buyer/tenant who submitted the offer)
            // to avoid picking a different accepted offer chain if the listing ever has multiple
            // historical accepted records.
            $acceptedLeaf = \App\Models\Offer::whereHas('offerAuction', function ($q) use ($summary) {
                $q->where('listing_id', $summary->listing_id);
            })
            ->where('role', $summary->listing_type)
            ->where('status', 'accepted')
            ->where('user_id', $summary->tenant_user_id)
            ->orderByDesc('id')
            ->with('metas')
            ->first();

            if (!$acceptedLeaf) {
                return $result;
            }

            // Walk parent chain to root — property info is always stored on the root offer.
            $rootOffer = $acceptedLeaf;
            $visited   = [];
            while ($rootOffer->parent_offer_id && !isset($visited[$rootOffer->id])) {
                $visited[$rootOffer->id] = true;
                $parent = \App\Models\Offer::with('metas')->find($rootOffer->parent_offer_id);
                if (!$parent) {
                    break;
                }
                $rootOffer = $parent;
            }

            $propMetas = $rootOffer->metas->pluck('meta_value', 'meta_key');
            if ($propMetas->get('prop_type') || $propMetas->get('match_explanation')) {
                $result['metas'] = $propMetas;
                $result['offer'] = $rootOffer;
            }
        } catch (\Throwable $e) {
            Log::warning('[AcceptedBidSummaryController] Failed to load offer property data', [
                'summary_id' => $summary->id,
                'error'      => $e->getMessage(),
            ]);
        }

        return $result;
    }

    /**
     * Build a self-contained HTML block for the "Property Being Offered" section.
     *
     * Used when injecting property data into the service-rendered HTML path (getRenderedHtml),
     * which drives both the sign page display and the PDF generated by generatePdf().
     * This ensures property/match data appears consistently in all document outputs for
     * buyer/tenant listing types — not just in the Blade view wrapper.
     *
     * $offer is optional only for backward-compatibility; both live callers now supply it so
     * that uploaded-photo asset URLs and external photo/media links are rendered in full.
     */
    private function buildOfferPropertySectionHtml(\Illuminate\Support\Collection $metas, ?\App\Models\Offer $offer = null): string
    {
        $fields = [
            'prop_street'               => 'Street Address',
            'prop_city'                 => 'City',
            'prop_state'                => 'State',
            'prop_zip'                  => 'ZIP Code',
            'prop_type'                 => 'Property Type',
            'prop_subtype'              => 'Property Style / Subtype',
            'prop_listing_status'       => 'Listing Status',
            'prop_mls_number'           => 'MLS #',
            'prop_listing_url'          => 'Listing URL',
            'prop_available_date'       => 'Available Date',
            'prop_occupancy_status'     => 'Occupancy Status',
            'prop_showing_availability' => 'Showing Availability',
            // Property attribute groups (type-conditional, mirrors seller/landlord property-preferences)
            'prop_attr_condition'       => 'Property Condition',
            'prop_attr_bedrooms'        => 'Bedrooms',
            'prop_attr_bathrooms'       => 'Bathrooms',
            'prop_attr_heated_sqft'     => 'Heated SqFt',
            'prop_attr_net_leasable_sqft' => 'Net Leasable SqFt',
            'prop_attr_total_sqft'      => 'Total SqFt',
            'prop_attr_sqft_source'     => 'SqFt Source',
            'prop_attr_total_acreage'   => 'Total Acreage',
            'prop_attr_garage'          => 'Garage',
            'prop_attr_garage_spaces'   => 'Garage Spaces',
            'prop_attr_pool'            => 'Pool',
            'prop_attr_year_built'      => 'Year Built',
            'prop_attr_zoning'          => 'Zoning',
        ];

        $rows = [];
        foreach ($fields as $key => $label) {
            $val = trim((string)($metas->get($key) ?? ''));
            if ($val !== '') {
                $rows[] = '<p style="margin:4px 0;"><strong>' . e($label) . ':</strong> ' . e($val) . '</p>';
            }
        }

        // ── Media links (virtual tour, video) ───────────────────────────────
        $mediaRows = [];
        foreach ([
            'prop_virtual_tour_url' => 'Virtual Tour',
            'prop_video_url'        => 'Video Tour',
        ] as $metaKey => $mediaLabel) {
            $url = trim((string)($metas->get($metaKey) ?? ''));
            if ($url !== '' && preg_match('#^https?://#i', $url)) {
                $mediaRows[] = '<p style="margin:4px 0;"><strong>' . e($mediaLabel) . ':</strong> '
                    . '<a href="' . e($url) . '" style="color:#1a5fa8;">' . e($url) . '</a></p>';
            }
        }

        // ── External photo URLs ──────────────────────────────────────────────
        $rawPhotoUrls = trim((string)($metas->get('prop_photo_urls') ?? ''));
        if ($rawPhotoUrls !== '') {
            $safeUrls = array_values(array_filter(
                array_map('trim', explode("\n", $rawPhotoUrls)),
                fn($u) => $u !== '' && preg_match('#^https?://#i', $u)
            ));
            foreach ($safeUrls as $idx => $u) {
                $mediaRows[] = '<p style="margin:4px 0;"><strong>Photo ' . ($idx + 1) . ':</strong> '
                    . '<a href="' . e($u) . '" style="color:#1a5fa8;">' . e($u) . '</a></p>';
            }
        }

        // ── Uploaded photos (stored as JSON filenames on the root offer) ────
        $uploadedPhotos = json_decode((string)($metas->get('prop_photos') ?? '[]'), true) ?: [];
        if ($offer && !empty($uploadedPhotos)) {
            foreach ($uploadedPhotos as $idx => $filename) {
                // R2-E0 (HI-05A): route public offer-photo URLs through the storage
                // seam so a public read-flip (LISTING_PUBLIC_READ=object_first) reaches
                // this summary surface too. Default (local) output is byte-equivalent to
                // the prior config('app.url').'/storage/...' URL. e() escapes the
                // resolved URL for the HTML href/text context.
                $assetUrl = e(\App\Support\Storage\ListingMediaUrl::get(
                    'offer-property-photos/' . $offer->id . '/' . $filename
                ));
                $mediaRows[] = '<p style="margin:4px 0;"><strong>Uploaded Photo ' . ($idx + 1) . ':</strong> '
                    . '<a href="' . $assetUrl . '" style="color:#1a5fa8;">' . $assetUrl . '</a></p>';
            }
        }

        $explanation = trim((string)($metas->get('match_explanation') ?? ''));
        $compromise  = trim((string)($metas->get('match_compromise_note') ?? ''));

        $matchRows = [];
        if ($explanation !== '') {
            $matchRows[] = '<p style="margin:4px 0;"><strong>Why It Matches:</strong></p>'
                . '<p style="margin:4px 0 12px;white-space:pre-line;">' . e($explanation) . '</p>';
        }
        if ($compromise !== '') {
            $matchRows[] = '<p style="margin:4px 0;"><strong>Noted Compromises / Differences:</strong></p>'
                . '<p style="margin:4px 0 12px;white-space:pre-line;">' . e($compromise) . '</p>';
        }

        if (empty($rows) && empty($mediaRows) && empty($matchRows)) {
            return '';
        }

        $html  = '<div style="background:#f0f7ff;padding:20px;border:1px solid #4a90d9;border-radius:8px;margin-top:20px;">';
        $html .= '<h2 style="color:#333;border-bottom:2px solid #4a90d9;padding-bottom:10px;margin-top:0;">Property Being Offered</h2>';

        if (!empty($rows)) {
            $html .= implode('', $rows);
        }

        if (!empty($mediaRows)) {
            $html .= '<div style="margin-top:12px;padding-top:12px;border-top:1px solid #c0d8f0;">';
            $html .= '<h4 style="color:#333;margin-top:0;">Photos &amp; Media</h4>';
            $html .= implode('', $mediaRows);
            $html .= '</div>';
        }

        if (!empty($matchRows)) {
            $html .= '<div style="margin-top:12px;padding-top:12px;border-top:1px solid #c0d8f0;">';
            $html .= '<h4 style="color:#333;margin-top:0;">Match Explanation</h4>';
            $html .= implode('', $matchRows);
            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
    }
}
