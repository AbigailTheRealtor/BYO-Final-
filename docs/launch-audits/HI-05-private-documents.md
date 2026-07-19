# HI-05 — Private Listing Documents (Phase 1 Batch B1.4)

**Status:** Foundational scope implemented in B1.4. Feature workflow deferred to a
separate **Document Access** batch (see below).
**Branch:** `phase-1-batch-b1-4-idor-upload-hardening`

## Core principle

All uploaded listing documents are private by default. A document is never
reachable simply because a listing is published, a URL is known, or a file
exists on a public disk. Every view/download is authorized on every request.

---

## What B1.4 implemented (foundation)

1. **Private storage.** New Seller Offer Listing document uploads write to the
   `private` disk (`storage/app/private`, no `url`): the seven disclosures
   (`seller_disclosure_file`, `flood_disclosure_file`, `lead_based_paint_file`,
   `hoa_condo_docs_file`, `survey_file`, `inspection_report_file`,
   `environmental_report_file`), `listing_documents`, and `additional_documents`
   (`doc_rows`). Marketing **photos/videos are intentionally left public** — they
   are not documents.
2. **Authenticated, authorized delivery.** `ListingDocumentController`
   (`/listings/{listingType}/{listingId}/document/{documentKey}` and
   `…/additional-document/{index}`, both `auth`) re-checks authorization on every
   request. No `Storage::url()` / `asset('storage/...')` document links remain in
   the Seller Offer Listing views.
3. **Authorization service.** `ListingDocumentAccessService::canViewDownload()`
   applies, in order: (1) **listing owner or authorized listing agent** →
   allowed for any document; (2) **interim gated-viewer** — an authenticated user
   viewing a **publicly visible** listing (approved, not draft, not archived) may
   access a document classified **`AI_READABLE`** (the seven buyer-facing
   due-diligence categories); (3) otherwise denied. Guests are always denied;
   draft/unpublished/archived documents and `REQUEST_REQUIRED` /
   `ALWAYS_RESTRICTED` documents remain owner/authorized-agent only.
   Authorization is derived from the resolved listing and trusted classification,
   never from request-supplied ids/paths/filenames.

   > **⚠️ Interim rule.** Rule (2) exists only because those disclosures are
   > freely downloadable today (public disk, public listing page). It preserves
   > that buyer/agent visibility while killing guest, enumerable, draft, and
   > public-URL access. The **Document Access batch REPLACES rule (2) with
   > explicit request / approval / revocation controls** — broad authenticated
   > access is temporary.
4. **Capability model (design).** `ai_query_access` and `view_download_access`
   are distinct. `DocumentClassification` + `ListingDocumentCatalog` classify
   every document as `AI_READABLE`, `REQUEST_REQUIRED`, or `ALWAYS_RESTRICTED`.
5. **Compatibility.** The delivery controller also serves **legacy** records
   whose file is still physically on the public disk, so nothing breaks.
6. **Structural safety.** Document keys are an allow-list; on-disk paths are
   resolved from the listing's own meta (not the request); `..`/absolute paths
   are rejected — so URL guessing, path traversal, and cross-listing access are
   structurally prevented.

### Classification (§3/§4 of the product direction)

| Class | Meaning | Documents |
|---|---|---|
| `AI_READABLE` | Buyer-facing due-diligence; **may** be Ask-AI-readable once retrieval exists (design only today) | seller / flood / lead-paint / HOA-condo / survey / inspection / environmental disclosures |
| `REQUEST_REQUIRED` | View/download requires an approved request | `listing_documents`, `additional_documents` |
| `ALWAYS_RESTRICTED` | Never automatic; explicit authorization only | IDs, bank statements, tax returns, wire instructions, proof of funds, signed contracts, signature pages (e.g. the existing `AcknowledgementDocument` set) |

> **Ask AI document support is NOT active.** There is no document ingestion,
> extraction, embedding, or retrieval today. `ai_query_access` is a capability
> the follow-up batch will enforce; the B1.4 code neither reads document content
> nor feeds any Ask AI path.

---

## ⚠️ Operational backfill (required, NOT performed automatically)

Existing documents created before this change are still **physically on the
public disk**, and **their old direct public URLs remain reachable** until they
are moved. B1.4 does **not** silently move or delete any file.

A follow-up operational task must, for each affected listing:

1. Copy `storage/app/public/{seller-disclosures,seller-doc-uploads,auction/documents}/…`
   → `storage/app/private/…` (same relative paths).
2. Verify each file resolves through `ListingDocumentController`.
3. **Delete the public copies** (this is what actually closes the legacy exposure).
4. Optionally re-point any absolute paths (none expected — paths are relative meta).

Until step 3 runs, legacy files are exposed by their old public URL even though
new uploads are private. No schema migration is required for this backfill.

---

## Follow-up batch — "Document Access" (separate, requires migrations)

Deferred because the required models, migrations, ingestion, retrieval, approval
state, and audit infrastructure **do not exist yet**:

- **Request Documents workflow** — `document_access_requests` model + migration,
  statuses `Pending / Approved / Denied / Revoked`, no duplicate active request
  per (listing, user), optional expiration only if already supported.
- **Approval authority & scope** — owner / authorized listing agent approve,
  deny, revoke; narrowest practical scope (single document → category → all
  buyer-facing due-diligence); approval for Listing A never grants Listing B.
- **Approval / Revocation UI** and **notifications** (reuse existing
  `Notification` classes).
- **Ask AI document retrieval + enforcement** — build ingestion (PDF text
  extraction), per-document permission checks at query time, redaction/disclosure
  safeguards, answer-source logging, and immediate revocation of future
  retrieval. Never return whole documents, raw text, storage paths, internal
  filenames, or hidden metadata. A user with access to Listing A must never
  retrieve from Listing B.
- **Audit trail** — requester, listing, document scope, request/decision/
  revocation timestamps and actors.
- **Migrate `additional_documents`/`listing_documents` on the hire-agent
  surfaces** (`hire_seller_agent`/`hire_landlord_agent` views + the shared
  `listing-photos-tours-documents` partial) if any document (non-photo/video)
  uploads there also need privatizing.

### Revocation limitation (to document in the follow-up)

Revocation immediately blocks future viewing, downloading, Ask AI retrieval,
search, and summaries. **Files already downloaded before revocation cannot be
technically recalled.**
