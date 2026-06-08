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
            'Pool\b|Spa\b|Garage\b|Carport\b|Appliances?\b' .
            '|Air\s+Conditioning|A\/C\b' .
            '|Heat(?:ing)?(?:\s+and\s+Fuel)?\b|Fuel\b' .
            '|Interior\s+Features?|Exterior\s+Features?' .
            '|Furnishings?\b|Furnished\b|Available\b' .
            '|Tax\s+(?:ID|Year)|Annual\s+(?:CDD|Prop(?:erty)?|Tax)|Taxes\b|Parcel\b' .
            '|Legal\s+Desc|Flood\s+Zone|HOA\b|Association\b|Homestead\b|CDD\b' .
            '|Zoning\b|Lot\s+(?:Dim|Size|Sq|Acr|Feat)|Total\s+(?:Acreage|Number)' .
            '|Year\s+Built|Bed(?:room)?s?\b|Bath(?:room)?s?\b|Beds?\b|Baths?\b' .
            '|(?:Heated\s+)?Sq\.?\s*Ft\.?|Square\s+Feet' .
            '|Waterfront\b|Water\s+(?:Access|View|Extra|Front)\b' .
            '|Rent\s+(?:Includes?|Price)\b|Tenant\s+Pays?\b|Terms\s+of\s+Lease\b' .
            '|Application\s+Fee\b|Security\s+Deposit\b|Minimum\s+(?:Lease|Security)\b' .
            '|(?:Monthly|Lease)\s+(?:Rent|Amount)\b|Remarks?\b|Description\b' .
            '|Directions?\b|MLS\s*(?:#|Num|No\.?|Number)' .
            '|List\s+Price|Price\b|City\b|County\b|State\b|Zip\b|Address\b';

        /**
         * Extract a value from $text matching one of $patterns.
         *
         * @param  string[] $patterns   PCRE patterns; capture group 1 is the raw value.
         * @param  bool     $boundary   When true, trim the captured value at the next
         *                              recognized MLS label (prevents field bleed).
         *                              Terminates at `\b` so colon/space presence after
         *                              the label word is not required.
         */
        $extract = function (array $patterns, bool $boundary = false) use ($text, $labelStop): ?string {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $text, $m)) {
                    $val = trim($m[1] ?? '');
                    if ($boundary && $val !== '') {
                        // Trim at the first occurrence of a known label word boundary.
                        // Using \b avoids needing a trailing separator char that may
                        // have been excluded by the capture char class.
                        if (preg_match('/^(.*?)(?:\s+(?:' . $labelStop . ')\b)/is', $val, $sm)) {
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

        // ─── Address ──────────────────────────────────────────────────────────
        if ($v = $extract(['/(?:Property\s+)?Address[\s:]+([^\|,\n]{5,80})/i'])) {
            $data['address'] = $v;
        }

        // ─── City ─────────────────────────────────────────────────────────────
        if ($v = $extract(['/City[\s:]+([A-Za-z\s\-\.]{2,50})(?:\s|,|$)/i'])) {
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
        if ($v = $extract(['/County[\s:]+([A-Za-z\s\-\.]{2,50})(?:\s|,|$)/i'])) {
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
        if ($v = $extract(['/(?:Tax\s+ID|Parcel\s+(?:ID|Number))[\s:]+([A-Za-z0-9\-\.]+)/i'])) {
            $data['tax_id'] = $v;
        }

        // ─── Tax Year ─────────────────────────────────────────────────────────
        if ($v = $extract(['/Tax\s+Year[\s:\*]+(\d{4})/i'])) {
            $data['tax_year'] = $v;
        }

        // ─── Annual Property Taxes ────────────────────────────────────────────
        if ($v = $extract([
            '/Taxes?\s*\(Annual\s+Amount\)[\s:]+\$?([\d,]+(?:\.\d{2})?)/i',
            '/Annual\s+(?:Property\s+)?Taxes?[\s:]+\$?([\d,]+(?:\.\d{2})?)/i',
        ])) {
            $data['annual_taxes'] = preg_replace('/[^\d.]/', '', $v);
        }

        // ─── Legal Description ────────────────────────────────────────────────
        if ($v = $extract(['/Legal\s+Description[\s:]+(.{5,500}?)(?=\s{2,}|[A-Z][a-z]+\s*[\s:\*]|$)/s'])) {
            $data['legal_description'] = trim($v);
        }

        // ─── Flood Zone Code ──────────────────────────────────────────────────
        // Tighten char class to [A-Za-z0-9\-\/] (no spaces) so multi-word label
        // names that follow (e.g. "Appliances:") are never captured by accident.
        if ($v = $extract(['/Flood\s+Zone\s+Code[\s:\*]+([A-Za-z0-9\-\/]{1,15})/i'])) {
            $data['flood_zone_code'] = MlsNormalizer::normalize('flood_zone_code', $v);
        }

        // ─── Flood Zone Date ──────────────────────────────────────────────────
        if ($v = $extract(['/Flood\s+Zone\s+Date[\s:]+(\d{1,2}\/\d{1,2}\/\d{2,4}|\d{4}-\d{2}-\d{2})/i'])) {
            $data['flood_zone_date'] = $v;
        }

        // ─── Flood Zone Panel ─────────────────────────────────────────────────
        if ($v = $extract(['/Flood\s+Zone\s+Panel[\s:]+([A-Za-z0-9\s\-]{1,30})/i'], true)) {
            $data['flood_zone_panel'] = $v;
        }

        // ─── Zoning ───────────────────────────────────────────────────────────
        if ($v = $extract(['/Zoning[\s:\*]+([A-Za-z0-9\-\/\s]{1,30})/i'], true)) {
            $data['zoning'] = $v;
        }

        // ─── Additional Parcels ───────────────────────────────────────────────
        // Capture limit 50 gives the boundary stop enough text to find "Total Number\b"
        // when this field immediately precedes "Total Number of Parcels:".
        if ($v = $extract(['/Additional\s+Parcels[\s:\*]+([^\|\n]{1,50})/i'], true)) {
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
        if ($v = $extract([
            '/Association\s+Y\/N[\s:]+([Yy]es|[Nn]o|[YyNn])\b/i',
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

        // "Association Fee Freq:", "Association Fee Frequency:"
        if ($v = $extract([
            '/Association\s+Fee\s+Freq(?:uency)?[\s:]+([^\|\n]{1,30})/i',
            '/HOA\s+Fee\s+Freq(?:uency)?[\s:]+([^\|\n]{1,30})/i',
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

        // ─── Heating ──────────────────────────────────────────────────────────
        if ($v = $extract(['/Heat(?:ing)?(?:\s+and\s+Fuel)?[\s:]+([^\|\n]{1,120})/i'], true)) {
            $data['heating'] = $v;
        }

        // ─── Interior Features ────────────────────────────────────────────────
        if ($v = $extract(['/Interior\s+Features?[\s:]+([^\|\n]{1,300})/i'], true)) {
            $data['interior_features'] = $v;
        }

        // ─── Appliances ───────────────────────────────────────────────────────
        // Boundary protection: stops at the next known label (e.g. Interior Features).
        if ($v = $extract(['/Appliances?(?:\s+Included)?[\s:]+([^\|\n]{1,300})/i'], true)) {
            $data['appliances'] = $v;
        }

        // ─── Rent Includes ────────────────────────────────────────────────────
        if ($v = $extract(['/Rent\s+Includes?[\s:]+([^\|\n]{1,200})/i'])) {
            $data['rent_includes'] = $v;
        }

        // ─── Waterfront ───────────────────────────────────────────────────────
        if ($v = $extract(['/Waterfront[\s:]+([^\|\n]{1,50})/i'], true)) {
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
        if ($v = $extract([
            '/(?:Public\s+)?Remarks?[\s:]+(.{10,1000}?)(?=\s{2,}|(?:[A-Z][a-z]+\s*:)|$)/s',
            '/Description[\s:]+(.{10,1000}?)(?=\s{2,}|(?:[A-Z][a-z]+\s*:)|$)/s',
        ])) {
            $data['description'] = trim($v);
        }

        // ─── Directions ───────────────────────────────────────────────────────
        if ($v = $extract(['/Directions?[\s:]+([^\|\n]{5,300})/i'])) {
            $data['directions'] = $v;
        }

        // ─── listing_type_hint derived from rental signals ────────────────────
        $isRental = isset($data['rental_rate_type'])
            || preg_match('/Rental\s+Rate\s+Type|Monthly\s+Rent|Rent\s+Price/i', $text)
            || preg_match('/\bfor\s+rent\b|\bfor\s+lease\b/i', $text);

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
