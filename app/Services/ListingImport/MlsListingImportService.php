<?php

namespace App\Services\ListingImport;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MlsListingImportService
{
    /**
     * Parse a public MLS/Matrix URL (and/or raw text) into a normalized field array.
     *
     * @return array{success: bool, data: array, error: string}
     */
    public function import(string $url, ?string $rawText = null): array
    {
        $text = '';

        // Fetch from URL when provided
        if ($url !== '') {
            if (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('/^https?:\/\//i', $url)) {
                return $this->failure('Please enter a valid HTTP or HTTPS URL.');
            }

            if ($this->isSsrfBlockedUrl($url)) {
                return $this->failure('That URL is not permitted. Please enter a public MLS listing URL.');
            }

            try {
                $response = Http::timeout(10)->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (compatible; BidYourOffer/1.0)',
                ])->get($url);

                if (!$response->successful()) {
                    return $this->failure('The page could not be retrieved (HTTP ' . $response->status() . '). Please check the URL and try again.');
                }

                $text = $this->extractVisibleText($response->body());
            } catch (\Throwable $e) {
                Log::warning('MlsListingImportService: HTTP fetch failed', ['url' => $url, 'error' => $e->getMessage()]);
                return $this->failure('The page could not be reached. Please check the URL and try again.');
            }
        }

        // Append raw text if provided
        if (!empty($rawText)) {
            $text .= "\n" . $rawText;
        }

        if (trim($text) === '') {
            return $this->failure('No content found. Please provide a URL or paste the listing text.');
        }

        $data = $this->parseFields($text);

        if (empty($data)) {
            return $this->failure('No recognizable listing fields were found. Please paste the listing text manually.');
        }

        return [
            'success' => true,
            'data'    => $data,
            'error'   => '',
        ];
    }

    // ─── Private helpers ─────────────────────────────────────────────────────

    /**
     * Strip <script>, <style> tags and HTML tags, decode entities.
     */
    private function extractVisibleText(string $html): string
    {
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', ' ', $html);
        $html = preg_replace('/<style\b[^>]*>.*?<\/style>/is', ' ', $html);
        $html = strip_tags($html);
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $html = preg_replace('/\s+/', ' ', $html);
        return trim($html);
    }

    /**
     * Parse MLS Matrix field labels from plain text.
     * Returns a normalized associative array keyed by canonical field names.
     *
     * All extracted values are passed through MlsNormalizer::normalize() so that
     * dropdown values (Yes/No, pool type, flood zone, rent frequency, etc.) arrive
     * in the app-expected format.
     *
     * Parser boundary protection: a shared $labelStop pattern is applied to every
     * multi-word capture so that greedy regexes terminate at the next recognized
     * MLS label rather than bleeding across field boundaries.
     */
    private function parseFields(string $text): array
    {
        $data = [];

        // ─── Shared stop-pattern ──────────────────────────────────────────────
        // Matches any recognized MLS field label that would begin a new field.
        // Used to terminate greedy captures (Pool, A/C, Appliances, Carport, etc.)
        // so they do not bleed into the next label's text.
        $labelStop =
            // Garage\s+Spaces? must appear BEFORE bare Garage\b so the more-specific
            // two-word label "Garage Spaces:" is caught first; otherwise "Garage\b\s*:"
            // would require a colon directly after "Garage" and miss "Garage Spaces: 2".
            'Pool\b|Spa\b|Garage\s+Spaces?\b|Garage\b|Carport\b|Appliances?\s+Included\b|Appliances?\b' .
            '|Air\s+Conditioning|A\/C\b' .
            '|Heat(?:ing)?(?:\s+(?:and|&)\s+Fuel)?\b|Fuel\b' .
            // Fireplace: require Y/N suffix so "Fireplace Y/N:" stops heating_fuel;
            // bare "Fireplace:" also still fires via the outer \s*: requirement.
            '|Fireplace(?:\s+Y\/N)?\b|Heated\s+Area\b' .
            '|Interior\s+Features?|Exterior\s+Features?' .
            '|Exterior\s+Construction\b|Exterior\s+Feat\b|Exterior\s+Information\b|Interior\s+Information\b' .
            '|Floor\s+Covering\b|Roof\b' .
            '|Furnishings?\b(?=\s*:)|Furnished\b(?=\s*:)|Available\b(?=\s*:)' .
            '|Tax\s+(?:ID|Year)|Annual\s+(?:CDD|Prop(?:erty)?|Tax)|Taxes\b|Parcel\b' .
            // Tax\b(?=\s*:) — stops "Tax: $3,908" style bare Tax labels (e.g. some
            // Stellar MLS exports use "Tax:" instead of "Tax Year:" or "Tax Amount:").
            // The colon lookahead prevents firing on mid-word occurrences like
            // "1410Tax Year:" where " Year:" follows rather than ":" directly.
            '|Tax\b(?=\s*:)' .
            '|List\b' .
            // Flood Zone: match with or without a trailing qualifier word so that
            // "Flood Zone Date:", "Flood Zone Code:", and "Flood Zone Panel:" all stop
            // captures that run into them (e.g. interior_features bleed).
            // HOA Dues/Fee: must precede bare HOA\b so "HOA Dues:" stops captures
            // before the shorter two-letter label would match.
            // Tax Legal Desc: full three-word compound label used by some Stellar MLS
            // exports instead of separate "Legal Description:". Must be the FULL label
            // "Tax Legal Desc\b" (not just "Tax Legal\b") because the boundary closure
            // requires \s*: immediately after the matched label word — "Tax Legal\b\s*:"
            // would need a colon after "Legal" but the actual label has " Desc:" instead.
            // Tax Assessment: stops Water View / other fields from bleeding into
            // "Tax Assessment:" labels in MLS exports.
            '|Tax\s+Legal\s+Desc(?:ription)?\b|Tax\s+Assessment\b' .
            '|Legal\s+Desc(?:ription)?\b|Flood\s+Zone(?:\s+\w+)?|HOA\s+(?:Dues?|Fee)\b(?=\s*:)|HOA\b(?=\s*:)|Association\b(?=\s*:)|Homestead\b' .
            // CDD: allow optional Y/N so "CDD Y/N: No" stops association_fee_frequency.
            '|CDD(?:\s+Y\/N)?\b' .
            // Lot Size: accept an extra qualifier word ("Acres", "Sq", etc.) so
            // "Lot Size Acres:" stops lot_dimensions from bleeding.
            '|Zoning\b|Lot\s+(?:Dim|Size(?:\s+\w+)?|Sq|Acr|Feat)' .
            // Total Number of Parcels is more specific than Total Number; try it first.
            '|Total\s+Number\s+of\s+Parcels\b|Total\s+(?:Acreage|Number)' .
            '|Year\s+Built|Bed(?:room)?s?\b|Bath(?:room)?s?\b|Beds?\b|Baths?\b' .
            '|(?:Heated\s+)?Sq\.?\s*Ft\.?|Square\s+Feet|CDOM\b' .
            // Waterfront Feet must appear BEFORE bare Waterfront so "Waterfront Feet:"
            // terminates captures before "Waterfront\b" fires on the shorter prefix.
            '|Waterfront\s+Feet\b|Waterfront\b|Water\s+Frontage\b|Water\s+(?:Access|View|Extra|Front)\b' .
            '|Rent\s+(?:Includes?|Price)\b|Tenant\s+Pays?\b|Terms\s+of\s+Lease\b' .
            // Minimum Security Deposit (3-word label) must precede the 2-word fallback.
            '|Application\s+Fee\b|Security\s+Deposit\b|Minimum\s+Security\s+Deposit\b|Minimum\s+(?:Lease|Security)\b' .
            // Monthly Fee: e.g. "Monthly Fee: $150" in condo/commercial exports.
            '|Monthly\s+Fee\b|(?:Monthly|Lease)\s+(?:Rent|Amount)\b|Remarks?\b|Description\b' .
            '|Directions?\b|MLS\s*(?:#|Num|No\.?|Number)' .
            '|List\s+Price|Price\b|City\b(?=\s*:)|County\b|State\b|Zip\b|Address\b' .
            '|Kitchen\b|Living\s+Room\b|Primary\s+Bed(?:room)?\b|Rooms\b' .
            '|Community\s+Information\b|Housing\b' .
            // Special Assessment: include Y/N variant so "Special Assessment Y/N: No"
            // stops captures (bare ":" comes after Y/N in that MLS export form).
            // School District and Neighborhood: not in the original list, causing city
            // and carport fields to bleed into those following label words.
            '|Special\s+Assessment(?:\s+Y\/N)?\b|Homeowners?\s+Assoc|Subdivision\b' .
            '|School\s+District\b|Neighborhood\b' .
            '|Foundation\b|Sewer\b(?=\s*:)|Utilities\b|Roof\s+Type\b' .
            '|Number\s+of\s+Units\b|Total\s+Units\b|Cap\s+Rate\b' .
            // Commercial Sale specific labels
            '|Building\s+Size\b|Ceiling\s+Height\b|Parking\s+Spaces\b' .
            '|Net\s+Operating\s+Income\b|NOI\b' .
            '|Building\s+Features?\b|Current\s+Use\b' .
            '|Lease\s+Rate\s+Type\b|Rental\s+Rate\s+Type\b|Pets?\s+Allowed\b|Office\s+Area\b' .
            // Business Opportunity labels — stops greedy captures from bleeding
            // across business-specific fields.
            '|Business\s+Type\b|Annual\s+Revenue\b|Annual\s+Net\s+Income\b' .
            '|Number\s+of\s+Employees?\b|Inventory\s+Included\b' .
            '|Seller\s+Financing\b|Lease\s+Type\b';

        // ─── Bare section-header stop pattern ────────────────────────────────
        // Some MLS exports emit section headers (e.g. "Interior Information",
        // "Rooms") as bare words WITHOUT a trailing colon.  The main boundary
        // closure requires label + colon, so these headers must be caught by a
        // separate alternation that does NOT require the colon suffix.
        // Only include multi-word or unambiguous labels that can never appear as
        // part of a legitimate field value.
        $sectionHeaderStop =
            'Exterior\s+Information|Interior\s+Information' .
            '|Rooms\b|Neighborhood\b|School\s+District\b' .
            // Assessment: stops Water View and other fields from bleeding into bare
            // "Assessment" or "Tax Assessment" when they appear without a colon
            // (section-header style). The labelStop already handles "Tax Assessment:"
            // with a colon; this catches the colon-free variant.
            '|(?:Tax\s+)?Assessment\b' .
            // County appears without a colon in some Stellar MLS exports when the
            // school district block is embedded in the address line
            // (e.g. "City: SEMINOLE Pinellas County Unified State: FL").
            // Adding it here stops city and other fields from capturing "County Unified".
            '|County\b';

        /**
         * Extract a value from $text matching one of $patterns.
         *
         * @param  string[] $patterns   PCRE patterns; capture group 1 is the raw value.
         * @param  bool     $boundary   When true, trim the captured value at the next
         *                              recognized MLS label (prevents field bleed).
         *                              Two alternations fire:
         *                              1. label + \s*: (colon-delimited field labels)
         *                              2. bare section headers (no colon required)
         */
        $extract = function (array $patterns, bool $boundary = false) use ($text, $labelStop, $sectionHeaderStop): ?string {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $text, $m)) {
                    $val = trim($m[1] ?? '');
                    if ($boundary && $val !== '') {
                        // Trim at the first occurrence of a known label word boundary.
                        // Using \s* (not \s+) handles the no-separator case where a label
                        // immediately follows the captured value with no space
                        // (e.g. "1 SpacesCarport:No" or "Central AirFloor Covering:").
                        // Second alternation catches bare section headers without colons.
                        if (preg_match('/^(.*?)(?:\s*(?:' . $labelStop . ')\s*:|\s*(?:' . $sectionHeaderStop . '))/is', $val, $sm)) {
                            $val = trim($sm[1]);
                        }
                    }
                    if ($val !== '') {
                        return $val;
                    }
                }
            }
            return null;
        };

        // ─── MLS Number ───────────────────────────────────────────────────────
        if ($v = $extract(['/MLS\s*(?:#|Number|Num\.?|No\.?)[\s:]+([A-Za-z0-9\-]+)/i'])) {
            $data['mls_number'] = $v;
        }

        // ─── Property Type ────────────────────────────────────────────────────
        // Must appear before address/price parsers so "Property Type:" is captured
        // before the address regex can consume "Property" as a prefix.
        if ($v = $extract(['/Property\s+Type[\s:]+([^\|\n]{1,80})/i'], true)) {
            $data['property_type'] = $v;
        }

        // ─── Garage Spaces ────────────────────────────────────────────────────
        // Dedicated parser for the numeric garage-spaces count. Must appear before
        // the general Garage (boolean) parser so "Garage Spaces: 2" does not get
        // absorbed by the broader Garage branch.
        if ($v = $extract(['/Garage\s+Spaces?[\s:]+(\d+)/i'])) {
            $data['garage_spaces'] = (int) $v;
        }

        // ─── Address ──────────────────────────────────────────────────────────
        if ($v = $extract(['/(?:Property\s+)?Address[\s:]+([^\|,\n]{5,80})/i'])) {
            $data['address'] = $v;
        }

        // ─── City ─────────────────────────────────────────────────────────────
        // Boundary protection: stops at next recognized label so "City: SEMINOLE County:"
        // does not bleed "County" into the city value.
        if ($v = $extract(['/City[\s:]+([^\|\n,]{2,50})/i'], true)) {
            $data['city'] = trim($v, ', ');
        }

        // ─── State ────────────────────────────────────────────────────────────
        if ($v = $extract(['/\bState[\s:]+([A-Za-z]{2})\b/i'])) {
            $data['state'] = strtoupper($v);
        }

        // ─── ZIP ──────────────────────────────────────────────────────────────
        if ($v = $extract([
            '/\bZip(?:\s*Code)?[\s:]+(\d{5}(?:-\d{4})?)/i',
            '/\bPostal\s+Code[\s:]+(\d{5}(?:-\d{4})?)/i',
        ])) {
            $data['zip'] = $v;
        }

        // ─── County ───────────────────────────────────────────────────────────
        // Boundary protection: stops at next recognized label so "County: PinellasList"
        // does not capture the "List" label text.
        if ($v = $extract(['/County[\s:]+([^\|\n,]{2,50})/i'], true)) {
            $data['county'] = trim($v, ', ');
        }

        // ─── Price / List Price ───────────────────────────────────────────────
        if ($v = $extract(['/(?:List\s+Price|Price)[\s:]+\$?([\d,]+)/i'])) {
            $data['price'] = preg_replace('/[^\d.]/', '', $v);
        }

        // ─── Monthly Rent / Rental Rate ───────────────────────────────────────
        if ($v = $extract(['/(?:Monthly\s+Rent|Rent(?:al)?\s+(?:Price|Rate))[\s:]+\$?([\d,]+)/i'])) {
            $data['price'] = preg_replace('/[^\d.]/', '', $v);
            $data['rental_rate_type'] = 'monthly';
        }

        // ─── Rental Rate Type (any mention signals rental) ────────────────────
        if ($v = $extract(['/Rental\s+Rate\s+Type[\s:]+([^\|\n]{1,50})/i'])) {
            $data['rental_rate_type'] = trim($v);
        }

        // ─── Bedrooms ─────────────────────────────────────────────────────────
        if ($v = $extract([
            '/Bed(?:room)?s?[\s:]+(\d+(?:\.\d+)?)/i',
            '/Beds?[\s:]+(\d+)/i',
        ])) {
            $data['bedrooms'] = $v;
        }

        // ─── Bathrooms ────────────────────────────────────────────────────────
        if ($v = $extract([
            '/Bath(?:room)?s?[\s:]+(\d+(?:\.\d+)?)/i',
            '/Baths?[\s:]+(\d+(?:\.\d+)?)/i',
        ])) {
            $data['bathrooms'] = $v;
        }

        // ─── Heated Sq Ft ─────────────────────────────────────────────────────
        if ($v = $extract([
            '/(?:Heated|Living|Sq\.?\s*Ft\.?|Square\s+Feet)[\s:]+(\d[\d,]*)/i',
            '/(\d[\d,]+)\s*(?:sq\.?\s*ft\.?|square\s+feet)/i',
        ])) {
            $data['heated_sqft'] = preg_replace('/[^\d]/', '', $v);
        }

        // ─── Lot Dimensions ───────────────────────────────────────────────────
        if ($v = $extract(['/Lot\s+(?:Dim(?:ension)?s?|Size\s+Dim)[\s:]+([^\|\n]{3,40})/i'], true)) {
            $data['lot_dimensions'] = $v;
        }

        // ─── Lot Size Acres ───────────────────────────────────────────────────
        if ($v = $extract([
            '/Lot\s+(?:Acres|Acreage|Size\s+Acres?)[\s:]+(\d+(?:\.\d+)?)/i',
            '/(\d+(?:\.\d+)?)\s*Acres?/i',
        ])) {
            $data['lot_size_acres'] = $v;
        }

        // ─── Lot Size Sqft ────────────────────────────────────────────────────
        if ($v = $extract(['/Lot\s+(?:Sq\.?\s*Ft\.?|Square\s+Feet)[\s:]+(\d[\d,]*)/i'])) {
            $data['lot_size_sqft'] = preg_replace('/[^\d]/', '', $v);
        }

        // ─── Year Built ───────────────────────────────────────────────────────
        if ($v = $extract([
            '/Year\s+Built[\s:]+(\d{4})/i',
            '/Built\s+(?:in\s+)?(\d{4})/i',
        ])) {
            $data['year_built'] = $v;
        }

        // ─── Pool ─────────────────────────────────────────────────────────────
        // Boundary protection: stops at next known label so "Pool: Yes Garage: 2"
        // does not capture "Yes Garage: 2".
        if ($v = $extract(['/Pool[\s:]+([^\|\n]{1,60})/i'], true)) {
            $data['pool'] = MlsNormalizer::normalize('pool', $v);
        } elseif (preg_match('/\bPool\b/i', $text)) {
            $data['pool'] = 'yes';
        }

        // ─── Garage ───────────────────────────────────────────────────────────
        if ($v = $extract([
            '/Garage[\s:]+([^\|\n]{1,60})/i',
            '/Garage\s+(?:Spaces?|Dim)[\s:]+([^\|\n]{1,40})/i',
        ], true)) {
            $data['garage'] = MlsNormalizer::normalize('garage', $v);
        }

        // ─── Carport ──────────────────────────────────────────────────────────
        // Boundary protection prevents "Carport: Yes Appliances: Dishwasher" bleed.
        if ($v = $extract([
            '/Carport[\s:]+([^\|\n]{1,60})/i',
            '/Carport\s+Spaces?[\s:]+(\d+)/i',
        ], true)) {
            $data['carport'] = MlsNormalizer::normalize('carport', $v);
        }

        // ─── Furnished ────────────────────────────────────────────────────────
        if ($v = $extract(['/Furnished[\s:]+([^\|\n]{1,40})/i'], true)) {
            $data['furnished'] = MlsNormalizer::normalize('furnished', $v);
        }

        // ─── Available Date ───────────────────────────────────────────────────
        if ($v = $extract([
            '/(?:Available|Avail\.?)\s*(?:Date)?[\s:]+(\d{1,2}\/\d{1,2}\/\d{2,4}|\d{4}-\d{2}-\d{2}|[A-Za-z]+ \d{1,2},?\s*\d{4})/i',
        ])) {
            $data['available_date'] = $v;
        }

        // ─── Application Fee ──────────────────────────────────────────────────
        if ($v = $extract(['/Application\s+Fee[\s:]+\$?([\d,]+(?:\.\d{2})?)/i'])) {
            $data['application_fee'] = preg_replace('/[^\d.]/', '', $v);
        }

        // ─── Tax / Parcel ID ──────────────────────────────────────────────────
        // Non-greedy capture with a lookahead that stops before a Title Case label word
        // (e.g. "Tax" in "1410Tax Year:") or whitespace.  This handles the Stellar MLS
        // no-separator pattern where the parcel ID runs directly into the next label.
        if ($v = $extract(['/(?:Tax\s+ID|Parcel\s+(?:ID|Number))[\s:]+([A-Za-z0-9\-\.]+?)(?=[A-Z][a-z]|\s|$|\||\n)/i'])) {
            $data['tax_id'] = $v;
        }

        // ─── Tax Year ─────────────────────────────────────────────────────────
        if ($v = $extract(['/Tax\s+Year[\s:\*]+(\d{4})/i'])) {
            $data['tax_year'] = $v;
        }

        // ─── Annual Property Taxes ────────────────────────────────────────────
        // Covers Stellar MLS label variants: "Taxes (Annual Amount):", "Annual Property
        // Taxes:", "Annual Taxes:", "Tax Amount:", "Tax Amt:", "Ann. Tax:", "Annual Tax:".
        // Bare "Tax:" also matched with a negative lookahead excluding Tax Year/ID/Legal.
        if ($v = $extract([
            '/Taxes?\s*\(Annual\s+Amount\)[\s:]+\$?([\d,]+(?:\.\d{2})?)/i',
            '/Annual\s+(?:Property\s+)?Taxes?[\s:]+\$?([\d,]+(?:\.\d{2})?)/i',
            '/Tax\s+Amount[\s:]+\$?([\d,]+(?:\.\d{2})?)/i',
            '/Tax\s+Amt[\s:]+\$?([\d,]+(?:\.\d{2})?)/i',
            '/Ann\.?\s+Tax[\s:]+\$?([\d,]+(?:\.\d{2})?)/i',
            '/Annual\s+Tax[\s:]+\$?([\d,]+(?:\.\d{2})?)/i',
            '/\bTax\b(?!\s*(?:ID|Year|Legal|Assessment))[\s:]+\$?([\d,]+(?:\.\d{2})?)/i',
        ])) {
            $data['annual_taxes'] = preg_replace('/[^\d.]/', '', $v);
        }

        // ─── Legal Description ────────────────────────────────────────────────
        // Covers "Legal Description:", "Legal Desc:", and "Tax Legal Desc:" variants,
        // including ALL-CAPS label forms from some MLS exports (requires /i flag).
        // Boundary closure via $labelStop is used instead of the old Title-Case lookahead
        // which misfired on Title-Case words that are part of the description value itself
        // (e.g. "Section 23 Township 29 South").
        if ($v = $extract([
            '/(?:Tax\s+)?Legal\s+Desc(?:ription)?[\s:]+(.{5,500})/is',
        ], true)) {
            $data['legal_description'] = trim($v);
        }

        // ─── Flood Zone Code ──────────────────────────────────────────────────
        // 3-word "Flood Zone Code:" tried first (most specific).
        // 2-word "Flood Zone:" variant (e.g. Stellar data grid exports) requires a
        // colon immediately after "Zone" to avoid matching "Flood Zone Panel:",
        // "Flood Zone Date:", or "Flood Zone Code:" (which have a word before the colon).
        // Char class [A-Za-z0-9\-\/] excludes spaces so adjacent labels aren't captured.
        if ($v = $extract([
            '/Flood\s+Zone\s+Code[\s:\*]+([A-Za-z0-9\-\/]{1,15})/i',
            '/Flood\s+Zone\s*:[\s:\*]*([A-Za-z0-9\-\/]{1,15})/i',
        ])) {
            $data['flood_zone_code'] = MlsNormalizer::normalize('flood_zone_code', $v);
        }

        // ─── Flood Zone Date ──────────────────────────────────────────────────
        if ($v = $extract(['/Flood\s+Zone\s+Date[\s:]+(\d{1,2}\/\d{1,2}\/\d{2,4}|\d{4}-\d{2}-\d{2})/i'])) {
            $data['flood_zone_date'] = $v;
        }

        // ─── Flood Zone Panel ─────────────────────────────────────────────────
        // Matches "Flood Zone Panel:", "FEMA Flood Zone Panel:", and short "Panel:" /
        // "FEMA Panel:" variants that appear in some MLS exports.
        if ($v = $extract([
            '/Flood\s+Zone\s+Panel[\s:]+([A-Za-z0-9\-]{1,30})/i',
            '/FEMA\s+(?:Flood\s+Zone\s+)?Panel[\s:]+([A-Za-z0-9\-]{1,30})/i',
            '/\bPanel[\s:]+([A-Za-z0-9\-]{1,30})/i',
        ], true)) {
            $data['flood_zone_panel'] = $v;
        }

        // ─── Zoning ───────────────────────────────────────────────────────────
        if ($v = $extract(['/Zoning[\s:\*]+([A-Za-z0-9\-\/]{1,30})/i'], true)) {
            $data['zoning'] = $v;
        }

        // ─── Additional Parcels ───────────────────────────────────────────────
        // Tight boolean-only capture: MLS emits Yes/No/Y/N or the "Y/N:No" colon-prefix
        // form.  Grabbing only the boolean token avoids bleed into "Total Number of
        // Parcels:" which appears in the same line of many Stellar MLS exports.
        if ($v = $extract([
            '/Additional\s+Parcels\s+Y\/N[\s:*]+(Y\/N\s*:\s*[Yy]es|Y\/N\s*:\s*[Nn]o|[Yy]es|[Nn]o|[YyNn])\b/i',
            '/Additional\s+Parcels[\s:*]+(Y\/N\s*:\s*[Yy]es|Y\/N\s*:\s*[Nn]o|[Yy]es|[Nn]o)\b/i',
        ])) {
            $data['additional_parcels'] = MlsNormalizer::normalize('additional_parcels', $v);
        }

        // ─── Total Number of Parcels ──────────────────────────────────────────
        if ($v = $extract(['/Total\s+Number\s+of\s+Parcels[\s:]+(\d+)/i'])) {
            $data['total_parcel_count'] = $v;
        }

        // ─── Minimum Security Deposit ─────────────────────────────────────────
        if ($v = $extract(['/Minimum\s+Security\s+Deposit[\s:]+\$?([\d,]+(?:\.\d{2})?)/i'])) {
            $data['minimum_security_deposit'] = preg_replace('/[^\d.]/', '', $v);
        }

        // ─── Lease Amount Frequency ───────────────────────────────────────────
        if ($v = $extract(['/Lease\s+Amount\s+Frequency[\s:]+([^\|\n]{1,40})/i'], true)) {
            $data['lease_amount_frequency'] = MlsNormalizer::normalize('lease_amount_frequency', $v);
        }

        // ─── Terms of Lease ───────────────────────────────────────────────────
        if ($v = $extract(['/Terms\s+of\s+Lease[\s:]+([^\|\n]{1,200})/i'], true)) {
            $data['terms_of_lease'] = $v;
        }

        // ─── Tenant Pays ──────────────────────────────────────────────────────
        if ($v = $extract(['/Tenant\s+Pays[\s:]+([^\|\n]{1,200})/i'], true)) {
            $data['tenant_pays'] = $v;
        }

        // ─── HOA / Association ────────────────────────────────────────────────
        // Tight boolean capture only — no bleed possible regardless of what follows.
        // Includes "Homeowners Association:", "Homeowner Assoc:", and Y/N variants.
        if ($v = $extract([
            '/Association\s+Y\/N[\s:]+([Yy]es|[Nn]o|[YyNn])\b/i',
            '/Homeowners?\s+Assoc(?:iation)?[\s:]+([Yy]es|[Nn]o|[YyNn])\b/i',
            '/(?:HOA|Association)[\s:]+([Yy]es|[Nn]o|[YyNn])\b/i',
        ])) {
            $data['has_hoa'] = MlsNormalizer::normalize('has_hoa', $v);
        }

        // "Association Name:"
        if ($v = $extract(['/Association\s+Name[\s:]+([^\|\n]{1,120})/i'], true)) {
            $data['association_name'] = trim($v);
        }

        // "Association Fee:", "HOA Fee:"
        if ($v = $extract([
            '/Association\s+Fee(?!\s+Freq)[\s:\$]+([0-9,\.]+)/i',
            '/HOA\s+Fee[\s:\$]+([0-9,\.]+)/i',
        ])) {
            $data['association_fee_amount'] = preg_replace('/[^\d.]/', '', $v);
        }

        // "Association Fee Freq:", "Association Fee Frequency:", bare "Freq:"
        // The bare "Freq:" pattern is only reached when the longer forms don't match,
        // so it acts as a fallback for terse MLS exports.
        if ($v = $extract([
            '/Association\s+Fee\s+Freq(?:uency)?[\s:]+([^\|\n]{1,30})/i',
            '/HOA\s+Fee\s+Freq(?:uency)?[\s:]+([^\|\n]{1,30})/i',
            '/\bFreq(?:uency)?[\s:]+([^\|\n]{1,30})/i',
        ], true)) {
            $data['association_fee_frequency'] = MlsNormalizer::normalize('association_fee_frequency', $v);
        }

        // "CDD Y/N", "Community Development District:", "CDD:"
        // Tight boolean capture only — no bleed possible.
        if ($v = $extract([
            '/CDD\s+Y\/N[\s:]+([Yy]es|[Nn]o|[YyNn])\b/i',
            '/(?:CDD|Community\s+Development\s+District)[\s:]+([Yy]es|[Nn]o|[YyNn])\b/i',
        ])) {
            $data['has_cdd'] = MlsNormalizer::normalize('has_cdd', $v);
        }

        // "CDD Annual Amount:", "CDD Fee:"
        if ($v = $extract([
            '/CDD\s+(?:Annual\s+Amount|Fee)[\s:\$]+([0-9,\.]+)/i',
            '/Community\s+Development\s+District\s+(?:Annual\s+Amount|Fee)[\s:\$]+([0-9,\.]+)/i',
        ])) {
            $data['annual_cdd_fee'] = preg_replace('/[^\d.]/', '', $v);
        }

        // ─── A/C ──────────────────────────────────────────────────────────────
        // Boundary protection: stops at Heating, Interior Features, etc.
        if ($v = $extract([
            '/Air\s+Conditioning[\s:]+([^\|\n]{1,120})/i',
            '/A\/C[\s:]+([^\|\n]{1,120})/i',
        ], true)) {
            $data['air_conditioning'] = $v;
        }

        // ─── Heating & Fuel (combined MLS field) ──────────────────────────────
        // Canonical key 'heating_fuel' covers both "Heating and Fuel:" / "Heating & Fuel:"
        // AND plain "Heating:" labels so both patterns land on the same import key.
        // The "and Fuel" variant is tried first; the plain "Heating:" fallback only fires
        // when the combined label is absent, avoiding double-capture on the same field.
        if ($v = $extract([
            '/Heat(?:ing)?\s+(?:and|&)\s+Fuel[\s:]+([^\|\n]{1,120})/i',
            '/Heat(?:ing)?(?!\s+(?:and|&)\s+Fuel)[\s:]+([^\|\n]{1,120})/i',
        ], true)) {
            $data['heating_fuel'] = $v;
        }

        // ─── Interior Features ────────────────────────────────────────────────
        if ($v = $extract(['/Interior\s+Features?[\s:]+([^\|\n]{1,300})/i'], true)) {
            $data['interior_features'] = $v;
        }

        // ─── Roof Type ────────────────────────────────────────────────────────
        if ($v = $extract(['/Roof\s+Type[\s:]+([^\|\n]{1,120})/i'], true)) {
            $data['roof_type'] = $v;
        }

        // ─── Exterior Construction ────────────────────────────────────────────
        if ($v = $extract(['/Exterior\s+Construction[\s:]+([^\|\n]{1,120})/i'], true)) {
            $data['exterior_construction'] = $v;
        }

        // ─── Foundation ───────────────────────────────────────────────────────
        if ($v = $extract(['/Foundation[\s:]+([^\|\n]{1,120})/i'], true)) {
            $data['foundation'] = $v;
        }

        // ─── Water ────────────────────────────────────────────────────────────
        // Requires colon immediately after "Water" to avoid matching "Water Access:",
        // "Water View:", or "Waterfront:" which are handled by dedicated branches.
        if ($v = $extract(['/\bWater\s*:[\s]*([^\|\n]{1,120})/i'], true)) {
            $data['water'] = $v;
        }

        // ─── Sewer ────────────────────────────────────────────────────────────
        if ($v = $extract(['/Sewer[\s:]+([^\|\n]{1,120})/i'], true)) {
            $data['sewer'] = $v;
        }

        // ─── Utilities ────────────────────────────────────────────────────────
        if ($v = $extract(['/Utilities[\s:]+([^\|\n]{1,120})/i'], true)) {
            $data['utilities'] = $v;
        }

        // ─── Sqft Heated Source ───────────────────────────────────────────────
        if ($v = $extract([
            '/Sq\.?\s*Ft\.?\s*Heated\s+Source[\s:]+([^\|\n]{1,60})/i',
            '/(?:Heated\s+)?Sq(?:uare)?\s*Ft\s+Source[\s:]+([^\|\n]{1,60})/i',
        ], true)) {
            $data['sqft_heated_source'] = $v;
        }

        // ─── Flood Insurance Required ─────────────────────────────────────────
        // Tight boolean-only capture avoids bleed into "Flood Zone Code:" which
        // immediately follows this field in many Stellar MLS single-line exports.
        if ($v = $extract(['/Flood\s+Insurance\s+Req(?:uired)?[\s:]+([Yy]es|[Nn]o|[YyNn])\b/i'])) {
            $data['flood_insurance_required'] = MlsNormalizer::normalize('flood_insurance_required', $v);
        }

        // ─── Special Assessments ──────────────────────────────────────────────
        // Boolean Y/N from "Special Assessments Y/N:" or bare "Special Assessments: Yes/No".
        if ($v = $extract([
            '/Special\s+Assessments?\s+Y\/N[\s:]+([Yy]es|[Nn]o|[YyNn])\b/i',
            '/Special\s+Assessments?[\s:]+([Yy]es|[Nn]o|[YyNn])\b/i',
        ])) {
            $data['has_special_assessments'] = MlsNormalizer::normalize('has_special_assessments', $v);
        }

        // Dollar amount from "Special Assessment Amount:" / "Special Assessment Fee:".
        if ($v = $extract([
            '/Special\s+Assessment\s+(?:Annual\s+)?(?:Amount|Fee)[\s:\$]+([0-9,\.]+)/i',
        ])) {
            $data['special_assessment_amount'] = preg_replace('/[^\d.]/', '', $v);
        }

        // Free-text description from "Special Assessment Description:" / "Notes:".
        if ($v = $extract([
            '/Special\s+Assessment\s+(?:Description|Notes?)[\s:]+([^\|\n]{1,300})/i',
        ], true)) {
            $data['special_assessment_description'] = trim($v);
        }

        // ─── Appliances ───────────────────────────────────────────────────────
        // Boundary protection: stops at the next known label (colon-delimited) or
        // at a bare section header (e.g. "Rooms", "Exterior Information") caught
        // by the $sectionHeaderStop alternation in the boundary closure.
        if ($v = $extract(['/Appliances?(?:\s+Included)?[\s:]+([^\|\n]{1,300})/i'], true)) {
            $data['appliances'] = $v;
        }

        // ─── Rent Includes ────────────────────────────────────────────────────
        // boundary=true required: without it the 200-char capture bleeds into
        // Waterfront, Tax ID, Special Assessment, and other following fields.
        if ($v = $extract(['/Rent\s+Includes?[\s:]+([^\|\n]{1,200})/i'], true)) {
            $data['rent_includes'] = $v;
        }

        // ─── Lease Rate Type ──────────────────────────────────────────────────
        // Captures NNN / Gross / Modified Gross and similar commercial lease rate
        // type labels.  Boundary stop prevents bleed into following fields.
        if ($v = $extract(['/Lease\s+Rate\s+Type[\s:]+([^\|\n]{1,50})/i'], true)) {
            $data['lease_rate_type'] = MlsNormalizer::normalize('lease_rate_type', $v);
        }

        // ─── Pets Allowed ─────────────────────────────────────────────────────
        // Tight boolean-only capture — avoids bleed into descriptive text.
        if ($v = $extract(['/Pets?\s+Allowed[\s:]+([Yy]es|[Nn]o|[YyNn])\b/i'])) {
            $data['pets_allowed'] = MlsNormalizer::normalize('pets_allowed', $v);
        }

        // ─── Minimum Lease (Months) ───────────────────────────────────────────
        // Captures numeric months value from "Minimum Lease: 12",
        // "Minimum Lease (Months): 12", or "Minimum Lease Term: 12".
        if ($v = $extract(['/Minimum\s+Lease(?:\s*\(Months?\)|\s+(?:Term|Months?))?[\s:]+(\d+)/i'])) {
            $data['minimum_lease_months'] = $v;
        }

        // ─── Office Area (Sq Ft) ──────────────────────────────────────────────
        // Captures numeric square footage from "Office Area (Sq Ft): 1800" or
        // "Office Area: 1800".  Strips commas from the captured value.
        if ($v = $extract(['/Office\s+Area(?:\s*\(?Sq\.?\s*Ft\.?\)?)?[\s:]+(\d[\d,]*)/i'])) {
            $data['office_area_sqft'] = preg_replace('/[^\d]/', '', $v);
        }

        // ─── Water Frontage ───────────────────────────────────────────────────
        // Some Stellar MLS exports emit "Water Frontage Y/N: Yes/No" as a boolean
        // before the "Water Frontage:" water-body-name field.  Parse the Y/N variant
        // first and store it in its own key so that the free-text branch below does
        // not capture "Y/N: No" as a water body description.
        // NOTE: waterfront_yn is not in any field map and is never applied to the form.
        if ($v = $extract(['/Water\s+Frontage\s+Y\/N[\s:]+([Yy]es|[Nn]o|[YyNn])\b/i'])) {
            $data['waterfront_yn'] = MlsNormalizer::normalize('waterfront', $v);
        }

        // "Water Frontage:" describes the type of water body the property fronts
        // (e.g. "Intracoastal Waterway", "Bay/Harbor", "Canal").
        // Must appear BEFORE Waterfront parser to prevent the Waterfront regex from
        // consuming "Water Frontage: <value> Waterfront: <bool>".
        // Negative lookahead (?!\s+Y\/N) ensures the Y/N boolean variant above is
        // not re-matched here even if the boolean branch did not fire.
        // NOTE: No Livewire property exists on Seller/Landlord for this field yet;
        //       it is parsed and stored in $data but does not apply to the form.
        //       A human-readable label is registered in MlsFieldMap::fieldLabels().
        if ($v = $extract(['/Water\s+Frontage(?!\s+Y\/N)[\s:]+([^\|\n]{1,100})/i'], true)) {
            $data['water_frontage'] = $v;
        }

        // ─── Waterfront Feet ─────────────────────────────────────────────────
        // "Waterfront Feet:" is the numeric linear-footage of water frontage.
        // Must appear BEFORE Waterfront parser: "/Waterfront[\s:]+/" would otherwise
        // match "Waterfront Feet:" and capture the feet value into $data['waterfront'].
        // NOTE: No Livewire property exists on Seller/Landlord for this field yet;
        //       it is parsed and stored in $data but does not apply to the form.
        //       A human-readable label is registered in MlsFieldMap::fieldLabels().
        // Note: use !== null (not truthiness) because a valid value of "0" is falsy
        // in PHP and would incorrectly cause the assignment to short-circuit.
        if (($v = $extract(['/Waterfront\s+Feet[\s:]+(\d+(?:\.\d+)?)/i'])) !== null) {
            $data['waterfront_feet'] = $v;
        }

        // ─── Waterfront ───────────────────────────────────────────────────────
        // Requires a colon immediately after "Waterfront" (with optional spaces) so
        // that "Waterfront Feet: 0 Waterfront: No" is not matched on "Waterfront Feet:"
        // first — the old pattern `/Waterfront[\s:]+/` would consume "Waterfront "
        // (with trailing space) and then capture "Feet: 0" as the boolean value.
        if ($v = $extract(['/Waterfront\s*:\s*([^\|\n]{1,50})/i'], true)) {
            $data['waterfront'] = MlsNormalizer::normalize('waterfront', $v);
        }

        // ─── Water Access ─────────────────────────────────────────────────────
        if ($v = $extract(['/Water\s+Access[\s:]+([^\|\n]{1,50})/i'], true)) {
            $data['water_access'] = $v;
        }

        // ─── Water View ───────────────────────────────────────────────────────
        if ($v = $extract(['/Water\s+View[\s:]+([^\|\n]{1,50})/i'], true)) {
            $data['water_view'] = $v;
        }

        // ─── Description / Remarks ────────────────────────────────────────────
        // "Public Remarks (English Only)" is the canonical MLS field name; also
        // matches "Public Remarks:", "Remarks:", "Property Description:", and "About:".
        // Maps directly to canonical key 'description' → Livewire 'additional_details'.
        // Boundary closure via $labelStop terminates capture at the next MLS label.
        if ($v = $extract([
            '/Public\s+Remarks?\s*\(English\s+Only\)[\s:]+(.{10,2000})/si',
            '/(?:Public\s+)?Remarks?[\s:]+(.{10,1000})/si',
            '/Property\s+Description[\s:]+(.{10,1000})/si',
            '/\bAbout[\s:]+(.{10,1000})/si',
            '/\bDescription[\s:]+(.{10,1000})/si',
        ], true)) {
            $v = trim($v);

            // Strip MLS page header/address block from the beginning of the description.
            // MLS exports frequently prepend the property address and city/state/ZIP line
            // before the actual narrative remarks body.  The pattern anchors to the start
            // of the captured string and matches any leading block that contains no
            // lowercase letters (i.e. all-caps address tokens, digits, punctuation) up to
            // a recognised US state abbreviation followed by a five-digit ZIP code.
            // Only fires when:
            //   (a) a valid US state abbreviation precedes the ZIP, AND
            //   (b) non-empty prose remains after the stripped block —
            // so we never accidentally remove real narrative content.
            // This is a post-capture cleanup step and does NOT affect parser boundaries.
            static $mlsHeaderUsStates = [
                'AL','AK','AZ','AR','CA','CO','CT','DE','FL','GA','HI','ID','IL','IN',
                'IA','KS','KY','LA','ME','MD','MA','MI','MN','MS','MO','MT','NE','NV',
                'NH','NJ','NM','NY','NC','ND','OH','OK','OR','PA','RI','SC','SD','TN',
                'TX','UT','VT','VA','WA','WV','WI','WY','DC',
            ];
            if (preg_match(
                '/^([A-Z0-9][^a-z]{0,250}?)\b([A-Z]{2})\b[\s,\-]*(\d{5}(?:-\d{4})?)\s+([\s\S]+)/su',
                $v,
                $hdr
            ) && in_array($hdr[2], $mlsHeaderUsStates, true) && trim($hdr[4]) !== '') {
                $v = trim($hdr[4]);
            }

            $data['description'] = $v;
        }

        // ─── Number of Units (Income / Multifamily) ───────────────────────────
        if ($v = $extract([
            '/(?:Number\s+of\s+Units|Total\s+Units)[\s:]+(\d+)/i',
        ], true)) {
            $data['number_of_units'] = preg_replace('/[^\d]/', '', $v);
        }

        // ─── Unit Types / Unit Mix (raw, preview-only) ────────────────────────
        if ($v = $extract([
            '/(?:Unit\s+(?:Types?|Mix))[\s:]+([^\|\n]{2,100})/i',
        ], true)) {
            $data['unit_types_raw'] = trim($v);
        }

        // ─── Gross Annual Income ──────────────────────────────────────────────
        if ($v = $extract([
            '/(?:Annual\s+Gross\s+Income|Gross\s+Annual\s+Income)[\s:]+\$?([\d,]+(?:\.\d{1,2})?)/i',
        ], true)) {
            $data['gross_annual_income'] = preg_replace('/[^\d.]/', '', str_replace(',', '', $v));
        }

        // ─── Annual Operating Expenses ────────────────────────────────────────
        if ($v = $extract([
            '/Annual\s+(?:Operating\s+)?Expenses[\s:]+\$?([\d,]+(?:\.\d{1,2})?)/i',
        ], true)) {
            $data['annual_operating_expenses'] = preg_replace('/[^\d.]/', '', str_replace(',', '', $v));
        }

        // ─── Net Operating Income / NOI (raw, preview-only) ──────────────────
        if ($v = $extract([
            '/(?:Net\s+Operating\s+Income|NOI)[\s:]+([^\|\n]{1,80})/i',
        ], true)) {
            $data['net_operating_income_raw'] = trim($v);
        }

        // ─── Occupancy Rate (raw, preview-only) ───────────────────────────────
        if ($v = $extract([
            '/(?:Occupancy\s+Rate|Occupancy\s*%)[\s:]+([^\|\n]{1,50})/i',
        ], true)) {
            $data['occupancy_rate_raw'] = trim($v);
        }

        // ─── Building Size (Sq Ft) — Commercial Sale ──────────────────────────
        // Distinct from Heated Sq. Ft. (minimum_heated_square).  The MLS Commercial
        // Sale form has a "Building Size" field representing gross building area.
        // Boundary protection: "Ceiling Height" and other Commercial labels can follow.
        if ($v = $extract([
            '/Building\s+Size(?:\s+\(Sq\.?\s*Ft\.?\))?[\s:]+(\d[\d,]*)/i',
            '/Total\s+(?:Building\s+)?Sq(?:uare)?\s*Ft(?:\.)?[\s:]+(\d[\d,]*)/i',
        ])) {
            $data['building_size_sqft'] = preg_replace('/[^\d]/', '', $v);
        }

        // ─── Ceiling Height (Ft) — Commercial Sale ────────────────────────────
        if ($v = $extract(['/Ceiling\s+Height(?:\s+\(Ft\.?\))?[\s:]+(\d+(?:\.\d+)?)/i'])) {
            $data['ceiling_height_ft'] = $v;
        }

        // ─── Parking Spaces — Commercial Sale ────────────────────────────────
        // Distinct from the Garage branch which handles residential garage spaces.
        // "Parking Spaces:" is a standalone commercial count field on the MLS form.
        if ($v = $extract(['/Parking\s+Spaces[\s:]+(\d+)/i'])) {
            $data['parking_spaces_count'] = $v;
        }

        // ─── Net Operating Income (NOI) — Commercial Sale ─────────────────────
        // Normalizer strips $ and commas; writes to 'net_operating_income' (mapped field).
        // The income/multifamily branch above writes 'net_operating_income_raw' (preview-only).
        if ($v = $extract([
            '/Net\s+Operating\s+Income(?:\s+\(NOI\))?[\s:]+\$?([\d,]+(?:\.\d{2})?)/i',
            '/\bNOI[\s:]+\$?([\d,]+(?:\.\d{2})?)/i',
        ])) {
            $data['net_operating_income'] = MlsNormalizer::normalize('net_operating_income', $v);
        }

        // ─── Cap Rate — Commercial Sale ───────────────────────────────────────
        // Uses normalizer to strip trailing %; overwrites any raw cap_rate set by
        // the income/multifamily branch above with the properly normalized value.
        if ($v = $extract(['/Cap\s+Rate[\s:]+(\d+(?:\.\d+)?)\s*%?/i'])) {
            $data['cap_rate'] = MlsNormalizer::normalize('cap_rate', $v);
        }

        // ─── Building Features — Commercial Sale ──────────────────────────────
        // Comma-separated or pipe-separated feature list (Loading Dock, High Bay, etc.)
        // Boundary protection stops at next recognized commercial label.
        if ($v = $extract(['/Building\s+Features?(?:\s+\/\s+Amenities)?[\s:]+([^\|\n]{2,300})/i'], true)) {
            $v = trim($v, ", \t");
            if ($v !== '') {
                $data['building_features_list'] = $v;
            }
        }

        // ─── Current Use — Commercial Sale ───────────────────────────────────
        // Multi-select: "Light Industrial, Warehouse" etc.
        // Boundary protection stops at next recognized label.
        if ($v = $extract(['/Current\s+Use[\s:]+([^\|\n]{2,200})/i'], true)) {
            $v = trim($v, ", \t");
            if ($v !== '') {
                $data['current_use_list'] = $v;
            }
        }

        // ─── Business Type ────────────────────────────────────────────────────
        if ($v = $extract(['/Business\s+Type[\s:]+([^\|\n]{1,80})/i'], true)) {
            $data['business_type'] = $v;
        }

        // ─── Annual Revenue ───────────────────────────────────────────────────
        if ($v = $extract(['/Annual\s+Revenue[\s:\$]+([0-9,\.]+)/i'])) {
            $data['annual_revenue'] = preg_replace('/[^\d.]/', '', $v);
        }

        // ─── Annual Net Income (Business) ─────────────────────────────────────
        if ($v = $extract(['/Annual\s+Net\s+Income[\s:\$]+([0-9,\.]+)/i'])) {
            $data['annual_net_income_business'] = preg_replace('/[^\d.]/', '', $v);
        }

        // ─── Number of Employees ──────────────────────────────────────────────
        if ($v = $extract(['/Number\s+of\s+Employees?[\s:]+(\d+)/i'])) {
            $data['employee_count'] = $v;
        }

        // ─── Inventory Included (Business) ───────────────────────────────────
        // Tight boolean capture only.
        if ($v = $extract([
            '/Inventory\s+Included\s+Y\/N[\s:]+([Yy]es|[Nn]o|[YyNn])\b/i',
            '/Inventory\s+Included[\s:]+([Yy]es|[Nn]o|[YyNn])\b/i',
        ])) {
            $data['inventory_included'] = MlsNormalizer::normalize('inventory_included', $v);
        }

        // ─── Seller Financing Y/N (Business) ─────────────────────────────────
        // Tight boolean capture only.
        if ($v = $extract([
            '/Seller\s+Financing\s+Y\/N[\s:]+([Yy]es|[Nn]o|[YyNn])\b/i',
            '/Seller\s+Financing[\s:]+([Yy]es|[Nn]o|[YyNn])\b/i',
        ])) {
            $data['seller_financing_yn'] = MlsNormalizer::normalize('seller_financing_yn', $v);
        }

        // ─── Business Lease Type ──────────────────────────────────────────────
        // Boundary protection prevents bleed into subsequent labels.
        if ($v = $extract(['/Lease\s+Type[\s:]+([^\|\n]{1,60})/i'], true)) {
            $data['business_lease_type'] = $v;
        }

        // ─── Directions ───────────────────────────────────────────────────────
        if ($v = $extract(['/Directions?[\s:]+([^\|\n]{5,300})/i'])) {
            $data['directions'] = $v;
        }

        // ─── listing_type_hint derived from rental signals ────────────────────
        $isRental = isset($data['rental_rate_type'])
            || preg_match('/Rental\s+Rate\s+Type|Monthly\s+Rent|Rent\s+Price/i', $text)
            || preg_match('/\bfor\s+rent\b|\bfor\s+lease\b/i', $text)
            || isset($data['lease_rate_type']);

        $data['listing_type_hint'] = $isRental ? 'rental' : 'sale';

        // rental_rate_type was a signal only — remove from user-facing data
        unset($data['rental_rate_type']);

        return $data;
    }

    // ─── SSRF guard ──────────────────────────────────────────────────────────

    /**
     * Returns true when the URL resolves to a private, loopback, or reserved IP
     * address that must not be fetched (SSRF prevention).
     */
    private function isSsrfBlockedUrl(string $url): bool
    {
        $parsed = parse_url($url);
        if (!$parsed || empty($parsed['host'])) {
            return true;
        }

        $host = $parsed['host'];

        // Strip IPv6 brackets: http://[::1]/ → ::1
        if (str_starts_with($host, '[') && str_ends_with($host, ']')) {
            $host = substr($host, 1, -1);
        }

        // If the host is already a bare IP address, check it directly.
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ip = $host;
        } else {
            $resolved = gethostbyname($host);
            if ($resolved === $host || !filter_var($resolved, FILTER_VALIDATE_IP)) {
                return false;
            }
            $ip = $resolved;
        }

        // FILTER_FLAG_NO_PRIV_RANGE blocks: 10/8, 172.16/12, 192.168/16
        // FILTER_FLAG_NO_RES_RANGE blocks:  0/8, 127/8, 169.254/16, 240/4, ::1
        return !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }

    // ─── Result helpers ──────────────────────────────────────────────────────

    private function failure(string $message): array
    {
        return [
            'success' => false,
            'data'    => [],
            'error'   => $message,
        ];
    }
}
