<?php

namespace App\Services\Documents;

use App\Models\LandlordAgentAuction;
use App\Models\SellerAgentAuction;

/**
 * HI-05 — the trusted, static registry of listing documents.
 *
 * This is the single source of truth for WHICH document keys exist, how their
 * stored path is resolved from listing meta, which private-disk directory backs
 * them, their human label, and their classification. It is intentionally a
 * hard-coded map (no DB) for the B1.4 foundation — persisted per-document
 * classification overrides belong to the follow-up Document Access batch.
 *
 * A `documentKey` is server-trusted: the delivery controller only ever serves a
 * key present here, and resolves the on-disk path from the listing's own meta —
 * never from a request-supplied path — which is what makes direct URL guessing,
 * path traversal, and cross-listing access structurally impossible.
 *
 * Path shapes (both stored as EAV meta strings on the listing):
 *   - disclosures  → meta holds a full relative path, e.g.
 *                    "seller-disclosures/{id}/seller-disclosure/{uuid}.pdf"
 *                    (pathIncludesDir = true)
 *   - listing docs → meta holds a bare filename stored under "auction/documents"
 *                    (pathIncludesDir = false; dir is prepended)
 */
final class ListingDocumentCatalog
{
    /** listingType => auction model class. Seller Offer Listings live in seller_agent_auctions. */
    private const LISTING_MODELS = [
        'seller'   => SellerAgentAuction::class,
        'landlord' => LandlordAgentAuction::class,
    ];

    /**
     * documentKey => descriptor.
     *
     * @var array<string, array{path:string, dir:string, pathIncludesDir:bool, classification:string, label:string}>
     */
    private const DOCUMENTS = [
        'seller_disclosure_file' => [
            'path' => 'seller_disclosure_file_path', 'dir' => 'seller-disclosures',
            'pathIncludesDir' => true, 'classification' => DocumentClassification::AI_READABLE,
            'label' => 'Seller Disclosure',
        ],
        // Landlord disclosure is a distinct key (landlord uses landlord_disclosure_file_path,
        // stored as a full relative path under landlord-disclosures/). The remaining six
        // disclosure keys + listing_documents are shared with seller by meta-key name and
        // resolve against the landlord-resolved model, so they need no separate entry.
        'landlord_disclosure_file' => [
            'path' => 'landlord_disclosure_file_path', 'dir' => 'landlord-disclosures',
            'pathIncludesDir' => true, 'classification' => DocumentClassification::AI_READABLE,
            'label' => 'Landlord Disclosure',
        ],
        'flood_disclosure_file' => [
            'path' => 'flood_disclosure_file_path', 'dir' => 'seller-disclosures',
            'pathIncludesDir' => true, 'classification' => DocumentClassification::AI_READABLE,
            'label' => 'Flood Disclosure',
        ],
        'lead_based_paint_file' => [
            'path' => 'lead_based_paint_file_path', 'dir' => 'seller-disclosures',
            'pathIncludesDir' => true, 'classification' => DocumentClassification::AI_READABLE,
            'label' => 'Lead-Based Paint Disclosure',
        ],
        'hoa_condo_docs_file' => [
            'path' => 'hoa_condo_docs_file_path', 'dir' => 'seller-disclosures',
            'pathIncludesDir' => true, 'classification' => DocumentClassification::AI_READABLE,
            'label' => 'HOA / Condo Documents',
        ],
        'survey_file' => [
            'path' => 'survey_file_path', 'dir' => 'seller-disclosures',
            'pathIncludesDir' => true, 'classification' => DocumentClassification::AI_READABLE,
            'label' => 'Survey',
        ],
        'inspection_report_file' => [
            'path' => 'inspection_report_file_path', 'dir' => 'seller-disclosures',
            'pathIncludesDir' => true, 'classification' => DocumentClassification::AI_READABLE,
            'label' => 'Inspection Report',
        ],
        'environmental_report_file' => [
            'path' => 'environmental_report_file_path', 'dir' => 'seller-disclosures',
            'pathIncludesDir' => true, 'classification' => DocumentClassification::AI_READABLE,
            'label' => 'Environmental Report',
        ],
        // General listing document (bare filename under auction/documents).
        'listing_documents' => [
            'path' => 'listing_documents', 'dir' => 'auction/documents',
            'pathIncludesDir' => false, 'classification' => DocumentClassification::REQUEST_REQUIRED,
            'label' => 'Listing Document',
        ],
    ];

    public static function has(string $key): bool
    {
        return array_key_exists($key, self::DOCUMENTS);
    }

    /** @return array{path:string, dir:string, pathIncludesDir:bool, classification:string, label:string}|null */
    public static function get(string $key): ?array
    {
        return self::DOCUMENTS[$key] ?? null;
    }

    /** @return array<int, string> */
    public static function keys(): array
    {
        return array_keys(self::DOCUMENTS);
    }

    public static function classificationFor(string $key): ?string
    {
        return self::DOCUMENTS[$key]['classification'] ?? null;
    }

    public static function supportsListingType(string $listingType): bool
    {
        return array_key_exists($listingType, self::LISTING_MODELS);
    }

    /** @return class-string|null */
    public static function modelFor(string $listingType): ?string
    {
        return self::LISTING_MODELS[$listingType] ?? null;
    }

    /**
     * The relative storage path for a stored meta value, honouring the two path
     * shapes. Returns null when the meta value is empty.
     */
    public static function relativePath(string $key, ?string $storedValue): ?string
    {
        $storedValue = trim((string) $storedValue);
        if ($storedValue === '') {
            return null;
        }
        $entry = self::get($key);
        if ($entry === null) {
            return null;
        }

        return $entry['pathIncludesDir']
            ? $storedValue
            : trim($entry['dir'], '/') . '/' . ltrim($storedValue, '/');
    }
}
