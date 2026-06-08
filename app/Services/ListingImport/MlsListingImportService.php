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
     */
    private function parseFields(string $text): array
    {
        $data = [];

        // Helper: extract value after a label pattern
        $extract = function (array $patterns, string $stopPattern = '') use ($text): ?string {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $text, $m)) {
                    $val = trim($m[1] ?? '');
                    // Trim at common stop characters or next label
                    if ($stopPattern && preg_match('/^(.*?)(?:' . $stopPattern . ')/is', $val, $sm)) {
                        $val = trim($sm[1]);
                    }
                    if ($val !== '') {
                        return $val;
                    }
                }
            }
            return null;
        };

        // MLS Number
        if ($v = $extract(['/MLS\s*(?:#|Number|Num\.?|No\.?)[\s:]+([A-Za-z0-9\-]+)/i'])) {
            $data['mls_number'] = $v;
        }

        // Address
        if ($v = $extract(['/(?:Property\s+)?Address[\s:]+([^\|,\n]{5,80})/i'])) {
            $data['address'] = $v;
        }

        // City
        if ($v = $extract(['/City[\s:]+([A-Za-z\s\-\.]{2,50})(?:\s|,|$)/i'])) {
            $data['city'] = trim($v, ', ');
        }

        // State
        if ($v = $extract(['/\bState[\s:]+([A-Za-z]{2})\b/i'])) {
            $data['state'] = strtoupper($v);
        }

        // ZIP
        if ($v = $extract(['/\bZip(?:\s*Code)?[\s:]+(\d{5}(?:-\d{4})?)/i', '/\bPostal\s+Code[\s:]+(\d{5}(?:-\d{4})?)/i'])) {
            $data['zip'] = $v;
        }

        // County
        if ($v = $extract(['/County[\s:]+([A-Za-z\s\-\.]{2,50})(?:\s|,|$)/i'])) {
            $data['county'] = trim($v, ', ');
        }

        // Price / List Price
        if ($v = $extract(['/(?:List\s+Price|Price)[\s:]+\$?([\d,]+)/i'])) {
            $data['price'] = preg_replace('/[^\d.]/', '', $v);
        }

        // Monthly Rent / Rental Rate → also signals rental listing
        if ($v = $extract(['/(?:Monthly\s+Rent|Rent(?:al)?\s+Rate)[\s:]+\$?([\d,]+)/i'])) {
            $data['price'] = preg_replace('/[^\d.]/', '', $v);
            $data['rental_rate_type'] = 'monthly';
        }

        // Rental Rate Type (any mention signals rental)
        if ($v = $extract(['/Rental\s+Rate\s+Type[\s:]+([^\|\n]{1,50})/i'])) {
            $data['rental_rate_type'] = trim($v);
        }

        // Bedrooms
        if ($v = $extract(['/Bed(?:room)?s?[\s:]+(\d+(?:\.\d+)?)/i', '/Beds?[\s:]+(\d+)/i'])) {
            $data['bedrooms'] = $v;
        }

        // Bathrooms
        if ($v = $extract(['/Bath(?:room)?s?[\s:]+(\d+(?:\.\d+)?)/i', '/Baths?[\s:]+(\d+(?:\.\d+)?)/i'])) {
            $data['bathrooms'] = $v;
        }

        // Heated Sq Ft
        if ($v = $extract(['/(?:Heated|Living|Sq\.?\s*Ft\.?|Square\s+Feet)[\s:]+(\d[\d,]*)/i',
                           '/(\d[\d,]+)\s*(?:sq\.?\s*ft\.?|square\s+feet)/i'])) {
            $data['heated_sqft'] = preg_replace('/[^\d]/', '', $v);
        }

        // Lot Dimensions
        if ($v = $extract(['/Lot\s+(?:Dim(?:ension)?s?|Size\s+Dim)[\s:]+([^\|\n]{3,40})/i'])) {
            $data['lot_dimensions'] = $v;
        }

        // Lot Size Acres
        if ($v = $extract(['/Lot\s+(?:Acres|Acreage|Size\s+Acres?)[\s:]+(\d+(?:\.\d+)?)/i',
                           '/(\d+(?:\.\d+)?)\s*Acres?/i'])) {
            $data['lot_size_acres'] = $v;
        }

        // Lot Size Sqft
        if ($v = $extract(['/Lot\s+(?:Sq\.?\s*Ft\.?|Square\s+Feet)[\s:]+(\d[\d,]*)/i'])) {
            $data['lot_size_sqft'] = preg_replace('/[^\d]/', '', $v);
        }

        // Year Built
        if ($v = $extract(['/Year\s+Built[\s:]+(\d{4})/i', '/Built\s+(?:in\s+)?(\d{4})/i'])) {
            $data['year_built'] = $v;
        }

        // Pool
        if ($v = $extract(['/Pool[\s:]+([^\|\n]{1,30})/i'])) {
            $data['pool'] = $v;
        } elseif (preg_match('/\bPool\b/i', $text)) {
            $data['pool'] = 'Yes';
        }

        // Garage
        if ($v = $extract(['/Garage[\s:]+([^\|\n]{1,30})/i', '/Garage\s+(?:Spaces?|Dim)[\s:]+([^\|\n]{1,30})/i'])) {
            $data['garage'] = $v;
        }

        // Carport
        if ($v = $extract(['/Carport[\s:]+([^\|\n]{1,30})/i', '/Carport\s+Spaces?[\s:]+(\d+)/i'])) {
            $data['carport'] = $v;
        }

        // Furnished
        if ($v = $extract(['/Furnished[\s:]+([^\|\n]{1,20})/i'])) {
            $data['furnished'] = trim($v);
        }

        // Available Date
        if ($v = $extract(['/(?:Available|Avail\.?)\s*(?:Date)?[\s:]+(\d{1,2}\/\d{1,2}\/\d{2,4}|\d{4}-\d{2}-\d{2}|[A-Za-z]+ \d{1,2},?\s*\d{4})/i'])) {
            $data['available_date'] = $v;
        }

        // Application Fee
        if ($v = $extract(['/Application\s+Fee[\s:]+\$?([\d,]+(?:\.\d{2})?)/i'])) {
            $data['application_fee'] = preg_replace('/[^\d.]/', '', $v);
        }

        // Tax / Parcel ID
        if ($v = $extract(['/(?:Tax\s+ID|Parcel\s+ID|Parcel\s+Number)[\s:]+([A-Za-z0-9\-\.]+)/i'])) {
            $data['tax_id'] = $v;
        }

        // A/C
        if ($v = $extract(['/Air\s+Conditioning[\s:]+([^\|\n]{1,80})/i', '/A\/?C[\s:]+([^\|\n]{1,80})/i'])) {
            $data['air_conditioning'] = $v;
        }

        // Heating
        if ($v = $extract(['/Heat(?:ing)?[\s:]+([^\|\n]{1,80})/i'])) {
            $data['heating'] = $v;
        }

        // Interior Features
        if ($v = $extract(['/Interior\s+Features?[\s:]+([^\|\n]{1,200})/i'])) {
            $data['interior_features'] = $v;
        }

        // Appliances
        if ($v = $extract(['/Appliances?[\s:]+([^\|\n]{1,200})/i'])) {
            $data['appliances'] = $v;
        }

        // Rent Includes
        if ($v = $extract(['/Rent\s+Includes?[\s:]+([^\|\n]{1,200})/i'])) {
            $data['rent_includes'] = $v;
        }

        // Waterfront
        if ($v = $extract(['/Waterfront[\s:]+([^\|\n]{1,50})/i'])) {
            $data['waterfront'] = $v;
        }

        // Water Access
        if ($v = $extract(['/Water\s+Access[\s:]+([^\|\n]{1,50})/i'])) {
            $data['water_access'] = $v;
        }

        // Water View
        if ($v = $extract(['/Water\s+View[\s:]+([^\|\n]{1,50})/i'])) {
            $data['water_view'] = $v;
        }

        // Description / Remarks
        if ($v = $extract(['/(?:Public\s+)?Remarks?[\s:]+(.{10,1000}?)(?=\s{2,}|(?:[A-Z][a-z]+\s*:)|$)/s',
                           '/Description[\s:]+(.{10,1000}?)(?=\s{2,}|(?:[A-Z][a-z]+\s*:)|$)/s'])) {
            $data['description'] = trim($v);
        }

        // Directions
        if ($v = $extract(['/Directions?[\s:]+([^\|\n]{5,300})/i'])) {
            $data['directions'] = $v;
        }

        // listing_type_hint derived from rental signals
        $isRental = isset($data['rental_rate_type'])
            || preg_match('/Rental\s+Rate\s+Type|Monthly\s+Rent/i', $text)
            || preg_match('/\bfor\s+rent\b|\bfor\s+lease\b/i', $text);

        $data['listing_type_hint'] = $isRental ? 'rental' : 'sale';

        // Remove rental_rate_type from the user-facing data (it's a signal, not a user field)
        unset($data['rental_rate_type']);

        return $data;
    }

    // ─── SSRF guard ──────────────────────────────────────────────────────────

    /**
     * Returns true when the URL resolves to a private, loopback, or reserved IP
     * address that must not be fetched (SSRF prevention).
     *
     * Checks:
     *   - Loopback          127.0.0.0/8,  ::1
     *   - Link-local        169.254.0.0/16 (cloud metadata endpoints live here)
     *   - Private RFC-1918  10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16
     *   - Reserved          0.0.0.0/8, 240.0.0.0/4
     *   - Direct IPv4/IPv6  addresses embedded in the URL itself
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
            // Resolve hostname to IP.
            // gethostbyname() returns the input unchanged when DNS resolution fails.
            $resolved = gethostbyname($host);
            if ($resolved === $host || !filter_var($resolved, FILTER_VALIDATE_IP)) {
                // DNS failed — the subsequent HTTP call will time out / throw an
                // exception that we already handle; let it through.
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
