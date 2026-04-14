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

        return view('accepted_bid_summary.view', [
            'summary' => $summary,
            'html' => $html,
            'canSign' => $this->canUserSign($summary, $user),
            'userRole' => $this->getUserRole($summary, $user),
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

        $existingDocs = AcknowledgementDocument::where('accepted_bid_summary_id', $summary->id)
            ->where('user_id', $user->id)
            ->first();

        // Access = ownership of the listing-side (tenant_user_id), not role string.
        // All four flows (buyer, tenant, seller, landlord) store the listing owner in tenant_user_id.
        $canUploadAcknowledgementDocuments = ($user->id === $summary->tenant_user_id);

        return view('accepted_bid_summary.sign', [
            'summary'                          => $summary,
            'html'                             => $html,
            'userRole'                         => $userRole,
            'existingDocs'                     => $existingDocs,
            'canUploadAcknowledgementDocuments' => $canUploadAcknowledgementDocuments,
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

        return redirect()->route('accepted-bid-summary.sign', $id)
            ->with('doc_success', 'Your documents were saved. You can continue to review and sign below.');
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
}
