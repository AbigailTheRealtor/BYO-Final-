<?php

namespace App\Services\AskAi;

/**
 * AskAiQuestionClassifierService — Deterministic Question Classifier
 *
 * GOVERNANCE BLOCK:
 * ==================================================================================
 * ROLE: Deterministic keyword-based classifier for Ask AI user questions.
 * Accepts a plain question string and returns a structured classification
 * (question_type, confidence, reason) using keyword matching only.
 *
 * This service MUST NEVER:
 *   - Call any external LLM, language model, or external HTTP service.
 *   - Execute any database write (save, update, create, delete).
 *   - Infer, estimate, or invent values for missing data.
 *   - Generate any AI answer text or call OpenAI.
 *   - Create routes, controllers, Blade views, or database migrations.
 *   - Rank, sort, or recommend any listing, offer, buyer, or agent.
 *   - Reference or infer protected class characteristics.
 * ==================================================================================
 */
class AskAiQuestionClassifierService
{
    /**
     * Keyword rule map for each question type.
     *
     * Each entry is an array of keyword strings. A match is detected when any
     * keyword appears in the lowercased question (case-insensitive substring match).
     *
     * Order matters: types are evaluated top-to-bottom; the first match wins.
     * 'prohibited' is checked before all other types to ensure hard refusals fire first.
     * 'compatibility_signals' is checked before 'buyer_tenant_match' so that
     * score-specific phrases (e.g. "match score") are not swallowed by buyer/tenant terms.
     * 'unsupported' is never listed here — it is the fallback when nothing matches.
     */
    private const KEYWORD_RULES = [
        'prohibited' => [
            'race',
            'racial',
            'race of',
            'religion',
            'religious',
            'nationality',
            'national origin',
            'ethnic',
            'ethnicity',
            'familial status',
            'disability',
            'handicap',
            'sex offender',
            'gender identity',
            'sexual orientation',
            'protected class',
            'fair housing',
            'discrimination',
            'discriminate',
            'neighborhood demographic',
            'neighborhood demographics',
            'diverse neighborhood',
            'neighborhood diversity',
            'demographics',
            'who lives there',
            'type of people',
            'kind of people',
            'school district',
            'school district quality',
            'school quality',
            'best school',
            'good school',
            'best schools',
            'worst schools',
            'schools near',
            'crime statistics',
            'crime rate',
            'crime in',
            'criminal activity',
            'how safe is',
            'is it safe',
            'safe neighborhood',
            'safe area',
            'dangerous',
            'gang',
            'good for kids',
            'good for families',
            'kid friendly',
            'child friendly',
            'families with children',
        ],

        // -----------------------------------------------------------------------
        // listing_facts — Deterministic factual lookup for listing fields and
        // seller/landlord FAQ answers. Routes before buyer_tenant_match so that
        // structural listing questions (bedrooms, price, lease length) are answered
        // from listing data rather than incorrectly trapped as match criteria.
        // -----------------------------------------------------------------------
        'listing_facts' => [
            // Bedrooms / Bathrooms
            'how many bedrooms',
            'how many bathrooms',
            'number of bedrooms',
            'number of bathrooms',
            'bedrooms does',
            'bathrooms does',
            'bedroom count',
            'bathroom count',
            'bedrooms',
            'bathrooms',
            // Asking price / sale price
            'asking price',
            'starting price',
            'list price',
            'listed price',
            'buy now price',
            'sale price',
            'how much does it cost',
            'what is the price',
            'what is the cost',
            'purchase price',
            // Rent amount
            'monthly rent',
            'rent amount',
            'rental price',
            'how much is the rent',
            'how much does it rent',
            'what is the rent',
            'rent per month',
            // Lease length — note: bare 'lease length' is intentionally excluded here
            // so 'desired lease length' and 'preferred lease length' in buyer_tenant_match
            // are not swallowed. 'what is the lease', 'lease term', 'how long is the lease'
            // cover the factual case without conflicting with match-criteria phrases.
            'lease term',
            'what is the lease',
            'how long is the lease',
            'length of lease',
            'lease duration',
            // Pets
            'pets allowed',
            'pet policy',
            'allow pets',
            'allows pets',
            'are pets allowed',
            'pet restrictions',
            'pet deposit',
            'is the property pet',
            // HOA
            'hoa fee',
            'monthly hoa',
            'homeowners association fee',
            'homeowners association',
            'association fee',
            'is there an hoa',
            'does it have an hoa',
            'hoa amount',
            'hoa cost',
            'hoa',
            // Pool
            'is there a pool',
            'does it have a pool',
            'pool included',
            'has a pool',
            'have a pool',
            // Parking / Garage
            'parking spaces',
            'parking space',
            'how many parking',
            'garage spaces',
            'how many garages',
            'how many garage',
            'parking available',
            'garage',
            'is there a garage',
            'does it have a garage',
            'does the property have a garage',
            'attached garage',
            'detached garage',
            'garage parking',
            // Appliances
            'appliances included',
            'what appliances',
            'which appliances',
            'appliances come with',
            'appliances are included',
            'does it include appliances',
            // Utilities
            'utilities included',
            'what utilities',
            'which utilities',
            'utilities are included',
            'who pays utilities',
            'does it include utilities',
            'what is included in rent',
            // Showing instructions
            'showing instructions',
            'how to schedule a showing',
            'schedule a showing',
            'how do i view this',
            'how do i tour',
            // Square footage
            'square feet',
            'square footage',
            'sq ft',
            'sqft',
            'heated sqft',
            'how big is the',
            'how large is the',
            'size of the property',
            'size of the home',
            'size of the unit',
            // Year built / age
            'year built',
            'when was it built',
            'when was the home built',
            'when was this home built',
            'when was the property built',
            'when was this property built',
            'how old is the home',
            'how old is the property',
            'how old is this home',
            'age of the home',
            'age of the property',
            // Roof / HVAC / mechanical
            'roof age',
            'roof condition',
            'when was the roof',
            'how old is the roof',
            'hvac',
            'air conditioning',
            'heating system',
            'cooling system',
            'water heater',
            // Flood zone
            'flood zone',
            'is it in a flood zone',
            'in a flood zone',
            // Availability / move-in date for the listing
            'available date',
            'when is it available',
            'when does it become available',
            'available for rent',
            // Move-in date / timeframe — factual retrieval phrases only.
            // Bare 'move-in date' / 'move in date' stay in buyer_tenant_match so that
            // compatibility-framed questions ("does the move-in date work for this tenant?")
            // are not swallowed here.
            'what is the move-in date',
            'when is the move-in date',
            'what is the move in date',
            'when is the move in date',
            'move-in timeframe',
            'move in timeframe',
            'move-in schedule',
            'available move-in',
            // Buyer / tenant preferred area
            'preferred areas',
            'preferred neighborhoods',
            'preferred cities',
            'preferred locations',
            'where does the buyer want to live',
            'location preferences',
            'location preference',
            // Move-in timeframe synonyms
            'when do they want to move',
            'when can they move',
            'target move date',
            'desired move date',
            'move-in timeline',
            'move in timeline',
            // Condo / additional fees
            'condo fee',
            // Smoking
            'smoking policy',
            'smoking allowed',
            'is smoking allowed',
            // Subletting
            'subletting allowed',
            'can i sublet',
            // Closing date
            'closing date',
            'preferred closing',
            // Lot size / acreage
            'lot size',
            'acreage',
            'how many acres',
            // Unit count
            'number of units',
            'how many units',
            // MLS
            'mls number',
            'mls id',
        ],

        'compatibility_signals' => [
            'compatibility',
            'compatible',
            'compatibility score',
            'match score',
            'score breakdown',
            'how strong is the match',
            'financial compatibility',
            'financial match',
            'physical match',
            'terms match',
            'location match',
            'compatibility warning',
            'compatibility signal',
            'compatibility highlight',
            'how compatible',
        ],

        'property_standout' => [
            'stand out',
            'stands out',
            'what makes this',
            'what makes it',
            'what makes the property',
            'makes this property',
            'unique feature',
            'special about',
            'what is special',
            'best feature',
            'best features',
            'highlight',
            'highlights',
            'selling point',
            'selling points',
            'notable',
            'key feature',
            'top feature',
            'most impressive',
            'most appealing feature',
            'distinguish',
            'differentiator',
            'standout',
            'strength',
            'strengths',
            'benefit',
            'benefits',
            'good about this listing',
            'what is good about',
        ],

        'suited_audience' => [
            'suited for',
            'suitable for',
            'ideal for',
            'ideal buyer',
            'ideal tenant',
            'who would',
            'who would want',
            'who is this for',
            'who is this property for',
            'who is this listing for',
            'target audience',
            'target buyer',
            'target renter',
            'who should',
            'best fit for',
            'good fit for',
            'right buyer',
            'right renter',
            'right tenant',
            'who might',
            'appeal to',
            'type of buyer',
            'type of tenant',
            'what kind of buyer',
            'what kind of tenant',
            'lifestyle',
            'who is this good for',
            'good for who',
            'who would like this',
            'best suited for',
            'who would enjoy',
        ],

        'buyer_tenant_match' => [
            'good match for',
            'right match',
            'match for a buyer',
            'match for a tenant',
            'match for buyer',
            'match for tenant',
            'fit for the buyer',
            'fit for the tenant',
            'fit for buyer',
            'fit for tenant',
            'buyer fit',
            'tenant fit',
            'buyer match',
            'tenant match',
            'how well does',
            'how well do',
            'would a buyer',
            'would a tenant',
            'aligned with',
            'align with',
            'meet the buyer',
            'meet the tenant',
            'does this tenant have pets',
            'what does this tenant want',
            'what does this buyer want',
            'rent budget',
            'rental budget',
            'within budget',
            'purchase budget',
            'desired lease length',
            'preferred lease length',
            'move-in date',
            'move in date',
            'amenities required',
            'parking requirement',
        ],

        'missing_data' => [
            'what is missing',
            "what's missing",
            'missing data',
            'missing information',
            'missing from this listing',
            'missing field',
            'missing fields',
            'incomplete',
            'not filled',
            'not filled in',
            'not provided',
            'what data is missing',
            'no information',
            'lacking',
            'absent',
            'gaps in',
            'gap in',
            'what needs to be added',
            'what needs to be filled',
            'how complete is this',
            'what should be added',
            'do we know',
            'is there information about',
            'is income listed',
            'is credit score listed',
            'is pet information listed',
            'is budget listed',
            'is parking listed',
            'is lease length listed',
        ],

        'marketing_angles' => [
            'how to market',
            'marketing angle',
            'marketing strategy',
            'marketing approach',
            'best way to market',
            'how should i market',
            'how would you market',
            'how should this be marketed',
            'advertise this',
            'promote this listing',
            'listing pitch',
            'best pitch',
            'positioning for this',
            'listing description',
            'write a description',
            'draft a description',
            'property description',
            'tagline',
            'ad copy',
            'marketing idea',
            'marketing ideas',
            'listing description ideas',
            'ad ideas',
            'selling points',
        ],

        'educational' => [
            'what is a ',
            'what is an ',
            'what is escrow',
            'what is cap rate',
            'what is earnest',
            'what is closing',
            'what is a mortgage',
            'what is contingency',
            'how does escrow',
            'how does closing',
            'how does the appraisal',
            'how does a mortgage',
            'how does ',
            'how do ',
            'explain ',
            'define ',
            'definition',
            'overview',
            'introduction to',
            'teach me',
            'help me understand',
            'in general',
            'generally speaking',
            'real estate term',
            'real estate process',
            'closing cost',
            'closing costs',
            'escrow',
            'contingency',
            'earnest money',
            'mortgage',
            'appraisal',
            'inspection',
            'title insurance',
            'cap rate',
            'cash flow',
            'auction process',
            'bidding process',
            'platform process',
            'how does this platform',
        ],
    ];

    /**
     * Classify a plain user question into one of the nine approved question types.
     *
     * Output contract — always returns exactly these three keys:
     *   question_type  string  — one of the nine approved types, or 'unsupported'
     *   confidence     float   — value between 0.0 and 1.0 (rule-based, not probabilistic)
     *   reason         string  — human-readable explanation of why this type was selected
     *
     * @param  string $question  The raw user question string.
     * @return array{question_type: string, confidence: float, reason: string}
     */
    public function classify(string $question): array
    {
        $lower = mb_strtolower(trim($question));

        if ($lower === '') {
            return [
                'question_type' => 'unsupported',
                'confidence'    => 0.0,
                'reason'        => 'Empty question string provided; no classification possible.',
            ];
        }

        foreach (self::KEYWORD_RULES as $type => $keywords) {
            $matched = $this->findFirstMatch($lower, $keywords);

            if ($matched !== null) {
                return [
                    'question_type' => $type,
                    'confidence'    => $this->confidenceFor($type),
                    'reason'        => "Matched keyword rule for '{$type}': \"{$matched}\".",
                ];
            }
        }

        return [
            'question_type' => 'unsupported',
            'confidence'    => 0.0,
            'reason'        => 'No keyword rule matched; question does not map to any approved type.',
        ];
    }

    /**
     * Return the first keyword from the list that appears in the haystack, or null.
     */
    private function findFirstMatch(string $haystack, array $keywords): ?string
    {
        foreach ($keywords as $keyword) {
            if (str_contains($haystack, mb_strtolower($keyword))) {
                return $keyword;
            }
        }

        return null;
    }

    /**
     * Return a deterministic confidence value for each question type.
     *
     * Confidence reflects how reliably the keyword rule identifies the type,
     * not a probabilistic model score. 'prohibited' gets 1.0 because it is a
     * hard governance refusal and must never be downgraded. 'unsupported' always
     * returns 0.0 and is handled directly in classify().
     */
    private function confidenceFor(string $type): float
    {
        return match ($type) {
            'prohibited'            => 1.0,
            'listing_facts'         => 0.90,
            'compatibility_signals' => 0.85,
            'property_standout'     => 0.85,
            'suited_audience'       => 0.80,
            'buyer_tenant_match'    => 0.80,
            'missing_data'          => 0.80,
            'marketing_angles'      => 0.75,
            'educational'           => 0.70,
            default                 => 0.0,
        };
    }
}
