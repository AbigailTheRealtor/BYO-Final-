<?php

namespace App\Support;

class TenantServicesCatalog
{
    public static function residential(): array
    {
        return [
            '📢 Tenant Criteria Marketing & Promotion' => [
                'Create a branded flyer summarizing the Tenant\'s rental criteria',
                'Post the Tenant\'s rental criteria on Craigslist under the "Real Estate Wanted" section',
                'Share the Tenant\'s rental criteria on Nextdoor in Neighborhood or Community Groups',
                'Promote the Tenant\'s rental criteria on Facebook in Rental or Housing Groups',
                'Share the Tenant\'s rental criteria on Instagram using posts, stories, or reels',
                'Promote the Tenant\'s rental criteria on LinkedIn in Real Estate or Housing Groups',
                'Upload a TikTok video summarizing the Tenant\'s rental criteria',
                'Upload a YouTube video summarizing the Tenant\'s rental criteria',
                'Launch a mass email campaign promoting the Tenant\'s rental criteria',
                'Distribute branded postcards or flyers in the Tenant\'s preferred neighborhoods',
                'Launch hyperlocal digital ads targeting the Tenant\'s preferred rental areas',
            ],
            '🔍 Property Search, Alerts & Matching' => [
                'Send email alerts with new listings from the MLS that match the Tenant\'s rental criteria',
                'Search for off-market, pre-market, withdrawn, canceled, or expired properties that meet the Tenant\'s rental criteria',
                'Communicate with the Landlord\'s Agent, Landlord, or Property Manager to confirm availability, lease terms, and showing instructions',
                'Evaluate properties with the Tenant and provide insights on pricing, lease terms, and overall fit',
            ],
            '🏡 Property Showings & Virtual Tours' => [
                'Schedule and attend property showings with the Tenant',
                'Coordinate or conduct virtual showings via live video or pre-recorded walkthroughs',
                'Preview properties on behalf of the Tenant upon request',
                'Provide factual observations on property layout and condition',
            ],
            '📝 Tenant Application Support' => [
                'Provide the Tenant with application instructions or links to an online rental application platform',
                'Gather and organize required supporting documents (e.g., identification, income verification, reference letters)',
                'Submit complete and organized application packages to the Landlord\'s Agent, Landlord, or Property Manager for review',
                'Answer questions about the application process, screening timelines, and required documentation',
            ],
            '📃 Lease Preparation & Execution' => [
                'Review lease offers and assist the Tenant in preparing questions or requested changes',
                'Coordinate lease negotiation with the Landlord\'s Agent, Landlord, or Property Manager',
                'Assist with completing required lease disclosures and reviewing key lease terms',
                'Assist with in-person or electronic lease signing, including e-signature setup and secure delivery of executed lease documents, addenda, and disclosures to all parties',
            ],
            '🚚 Move-In Support & Coordination' => [
                'Coordinate move-in date and key handoff logistics with the Landlord\'s Agent, Landlord or Property Manager',
                'Confirm completion of any agreed-upon pre-move-in cleaning or repairs',
                'Provide a utility setup checklist and local provider resources',
                'Share a move-in checklist for documentation and property condition review',
                'Confirm required move-in payments and assist the Tenant with tracking amounts due, deadlines, and accepted payment methods',
            ],
            '💡 Leasing Strategy & Guidance' => [
                'Provide a Rental Market Analysis (RMA) with pricing insights based on comparable rentals, neighborhood trends, and current market conditions',
                'Advise on lease types and structures (e.g., month-to-month, annual, furnished, lease-option)',
                'Provide general guidance on Tenant rights and Landlord responsibilities under state law',
                'Provide general guidance on lease clauses, payment terms, and renewal options',
            ],
        ];
    }

    public static function commercial(): array
    {
        return [
            '📢 Tenant Criteria Marketing & Promotion' => [
                'Create a branded flyer summarizing the Tenant\'s leasing criteria',
                'Post the Tenant\'s leasing criteria on Craigslist under the "Office/Commercial" or "Retail" section',
                'Promote the Tenant\'s leasing criteria on Facebook in Commercial Leasing or Business Groups',
                'Share the Tenant\'s leasing criteria on Instagram using posts, stories, or reels',
                'Promote the Tenant\'s leasing criteria on LinkedIn in Professional, Real Estate, or Commercial Investment Groups',
                'Upload a TikTok video summarizing the Tenant\'s leasing criteria',
                'Upload a YouTube video summarizing the Tenant\'s leasing criteria',
                'Launch a mass email campaign promoting the Tenant\'s leasing criteria',
                'Distribute branded postcards or flyers in the Tenant\'s preferred neighborhoods',
                'Launch hyperlocal digital ads targeting the Tenant\'s preferred leasing areas',
            ],
            '🔍 Property Search, Alerts & Matching' => [
                'Send listing alerts from real estate platforms that match the Tenant\'s leasing criteria',
                'Search for off-market, pre-market, withdrawn, canceled, or expired properties that meet the Tenant\'s rental criteria',
                'Communicate with the Landlord\'s Agent, Landlord, or Property Manager to confirm availability, lease terms, and showing instructions',
                'Evaluate properties for layout efficiency, building specs, logistics, zoning fit, and operational alignment',
            ],
            '🏢 Property Showings & Virtual Tours' => [
                'Schedule and attend property tours with the Tenant',
                'Coordinate or conduct virtual showings via live video or pre-recorded walkthroughs',
                'Preview properties on behalf of the Tenant upon request',
                'Provide factual notes on layout, access, parking, visibility, and other operational considerations',
            ],
            '📝 Tenant Application Support' => [
                'Provide the Tenant with application instructions or links to online platforms',
                'Gather and organize required supporting documents (e.g., business licenses, financials, references)',
                'Submit complete and organized application packages to the Landlord\'s Agent, Landlord, or Property Manager',
            ],
            '📃 Lease Preparation, LOI & Execution' => [
                'Draft or assist with preparing a Letter of Intent (LOI) summarizing the Tenant\'s business needs and proposed terms',
                'Assist with negotiating rent, CAM, lease term, TI allowance, exclusivity clauses, renewal options, and other provisions (as permitted under the agency agreement)',
                'Coordinate with the Landlord\'s Agent, Landlord or Property Manager to finalize lease terms',
                'Review lease drafts and coordinate revisions through appropriate channels',
                'Assist with in-person or electronic lease signing, including e-signature setup and secure delivery of executed lease documents, addenda, and disclosures to all parties',
                'Track required deposits, rent commencement, and key lease dates to ensure move-in readiness',
            ],
            '🚚 Move-In Support & Coordination' => [
                'Coordinate move-in date and key handoff logistics with the Landlord, Landlord\'s Agent, or Property Manager',
                'Confirm completion of any agreed-upon pre-move-in repairs, cleaning, or buildout',
                'Provide a utility setup checklist and local provider resources',
                'Share a move-in checklist for documentation and property condition review',
                'Confirm required move-in payments and assist the Tenant with tracking amounts due, deadlines, and accepted payment methods',
            ],
            '💡 Leasing Strategy & Guidance' => [
                'Provide a Comparative Lease Market Analysis (CLMA) with pricing insights, comps, and vacancy trends',
                'Advise on lease types and structures (e.g., NNN, Modified Gross, Full Service) with general explanations of differences',
                'Provide general guidance on Tenant rights and Landlord responsibilities under commercial leasing law',
                'Provide general guidance on lease clauses, escalation terms, and space usage considerations',
            ],
        ];
    }

    public static function forPropertyType(string $propertyType): array
    {
        return $propertyType === 'Commercial Property' 
            ? self::commercial() 
            : self::residential();
    }

    /**
     * Canonicalize a string for matching purposes
     * Normalizes quotes and whitespace without changing display text
     */
    private static function canon(string $s): string
    {
        $s = trim($s);
        $s = str_replace(["\xe2\x80\x98", "\xe2\x80\x99", "\xe2\x80\x9c", "\xe2\x80\x9d"], ["'", "'", '"', '"'], $s);
        $s = preg_replace('/\s+/', ' ', $s);
        return $s;
    }

    /**
     * Build a services snapshot with checked status for each service
     * Uses canonicalized matching to handle quote differences
     */
    public static function buildSnapshot(array $selectedServices, string $propertyType): array
    {
        $catalog = self::forPropertyType($propertyType);
        $snapshot = [];

        $selectedCanon = array_map(fn($x) => self::canon($x), $selectedServices);

        foreach ($catalog as $section => $items) {
            $sectionData = [
                'section' => $section,
                'items' => [],
            ];

            foreach ($items as $item) {
                $checked = in_array(self::canon($item), $selectedCanon, true);
                $sectionData['items'][] = [
                    'text' => $item,
                    'checked' => $checked,
                ];
            }

            $snapshot[] = $sectionData;
        }

        return $snapshot;
    }

    public static function getCheckedServicesInOrder(array $snapshot): array
    {
        $result = [];
        
        foreach ($snapshot as $section) {
            $sectionName = $section['section'] ?? '';
            $checkedItems = [];
            
            foreach ($section['items'] ?? [] as $item) {
                if (!empty($item['checked'])) {
                    $checkedItems[] = $item['text'] ?? '';
                }
            }
            
            if (!empty($checkedItems)) {
                $result[$sectionName] = $checkedItems;
            }
        }
        
        return $result;
    }
}
