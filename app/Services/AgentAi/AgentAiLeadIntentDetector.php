<?php

namespace App\Services\AgentAi;

use App\Models\AgentAiChatLead;

/**
 * AgentAiLeadIntentDetector
 *
 * Deterministic phrase-pattern intent classifier.
 * Maps user question text to a lead type and a scoring signal.
 *
 * GOVERNANCE:
 *  - No AI/OpenAI calls — all classification is rule-based.
 *  - Never modifies any table other than agent_ai_chat_messages (detected_intent).
 *  - Results are used for lead scoring; notification thresholds rely only on
 *    this class (not on model-generated classifications).
 */
class AgentAiLeadIntentDetector
{
    /**
     * Signal names that map to the canonical scoring keys in config/ask_ai.php.
     */
    public const SIGNAL_PROPERTY_QUESTION      = 'property_question';
    public const SIGNAL_FINANCIAL_QUESTION     = 'financial_question';
    public const SIGNAL_SHOWING_REQUEST        = 'showing_request';
    public const SIGNAL_OFFER_QUESTION         = 'offer_question';
    public const SIGNAL_SUBMIT_OFFER_INTENT    = 'submit_offer_intent';
    public const SIGNAL_CONSULTATION_REQUEST   = 'consultation_request';
    public const SIGNAL_ESCALATION_REQUESTED   = 'human_escalation_requested';
    public const SIGNAL_CALLBACK_REQUEST       = 'callback_request';
    public const SIGNAL_PHONE_PROVIDED         = 'phone_provided';
    public const SIGNAL_EMAIL_PROVIDED         = 'email_provided';

    /**
     * High-intent signals that trigger lead capture for anonymous visitors.
     */
    public const HIGH_INTENT_SIGNALS = [
        self::SIGNAL_SHOWING_REQUEST,
        self::SIGNAL_SUBMIT_OFFER_INTENT,
        self::SIGNAL_CONSULTATION_REQUEST,
        self::SIGNAL_ESCALATION_REQUESTED,
        self::SIGNAL_CALLBACK_REQUEST,
    ];

    /**
     * Phrase patterns → signal.  Evaluated top-to-bottom; first match wins.
     * All patterns are case-insensitive substring checks.
     */
    private const SIGNAL_PATTERNS = [
        self::SIGNAL_SUBMIT_OFFER_INTENT => [
            'submit an offer',
            'put in an offer',
            'make an offer',
            'write an offer',
            'place an offer',
            'ready to offer',
            'want to buy',
            'interested in purchasing',
            'ready to purchase',
        ],
        self::SIGNAL_SHOWING_REQUEST => [
            'schedule a showing',
            'book a showing',
            'set up a showing',
            'request a tour',
            'book a tour',
            'schedule a tour',
            'see the property',
            'view the property',
            'arrange a visit',
            'walk through',
            'visit the home',
            'can i see it',
        ],
        self::SIGNAL_CALLBACK_REQUEST => [
            'call me back',
            'give me a call',
            'reach me at',
            'my number is',
            'best number',
            'call me at',
            'phone me',
            'text me',
        ],
        self::SIGNAL_CONSULTATION_REQUEST => [
            'schedule a consultation',
            'book a consultation',
            'set up a meeting',
            'talk to the agent',
            'speak with the agent',
            'contact the agent',
            'reach out to the agent',
            'get in touch with',
            'home value',
            'what is my home worth',
            'representation',
            'represent me',
            'work with you',
            'hire you',
            'hire this agent',
        ],
        self::SIGNAL_OFFER_QUESTION => [
            'how do i make an offer',
            'offer process',
            'offer price',
            'asking price',
            'negotiate',
            'counter offer',
            'accepted offer',
            'contingency',
            'contingencies',
            'inspection contingency',
            'financing contingency',
        ],
        self::SIGNAL_FINANCIAL_QUESTION => [
            'mortgage',
            'interest rate',
            'down payment',
            'financing',
            'pre-approved',
            'pre-qualification',
            'loan',
            'monthly payment',
            'hoa fee',
            'hoa dues',
            'tax',
            'price per',
            'cost of',
            'closing cost',
        ],
        self::SIGNAL_PROPERTY_QUESTION => [
            'bedroom',
            'bathroom',
            'square feet',
            'sqft',
            'sq ft',
            'garage',
            'yard',
            'pool',
            'school district',
            'neighborhood',
            'built in',
            'year built',
            'renovated',
            'updated',
            'appliances',
            'parking',
            'pet',
            'lease term',
            'available',
            'when can',
        ],
    ];

    /**
     * Lead-type phrase patterns → lead type.  Evaluated after signal detection.
     */
    private const LEAD_TYPE_PATTERNS = [
        AgentAiChatLead::LEAD_TYPE_BUYER => [
            'buy',
            'buyer',
            'purchase',
            'buying a home',
            'looking to buy',
        ],
        AgentAiChatLead::LEAD_TYPE_SELLER => [
            'sell',
            'seller',
            'listing my home',
            'sell my house',
            'sell my property',
        ],
        AgentAiChatLead::LEAD_TYPE_LANDLORD => [
            'landlord',
            'rental property',
            'manage my property',
            'property management',
        ],
        AgentAiChatLead::LEAD_TYPE_TENANT => [
            'rent',
            'tenant',
            'renting',
            'looking to rent',
            'lease apartment',
            'lease home',
        ],
        AgentAiChatLead::LEAD_TYPE_INVESTOR => [
            'invest',
            'investor',
            'investment property',
            'flip',
            'cap rate',
            'roi',
            'rental income',
        ],
        AgentAiChatLead::LEAD_TYPE_REFERRAL => [
            'referral',
            'referred',
            'my friend',
            'my family',
            'someone i know',
        ],
    ];

    /**
     * Detect the primary scoring signal from a user question.
     *
     * Returns one of the SIGNAL_* constants, or null if none matched.
     */
    public function detectSignal(string $question): ?string
    {
        $lower = strtolower($question);

        foreach (self::SIGNAL_PATTERNS as $signal => $phrases) {
            foreach ($phrases as $phrase) {
                if (str_contains($lower, $phrase)) {
                    return $signal;
                }
            }
        }

        return null;
    }

    /**
     * Detect the lead type from a user question.
     *
     * Returns one of AgentAiChatLead::LEAD_TYPES or null.
     */
    public function detectLeadType(string $question): ?string
    {
        $lower = strtolower($question);

        foreach (self::LEAD_TYPE_PATTERNS as $type => $phrases) {
            foreach ($phrases as $phrase) {
                if (str_contains($lower, $phrase)) {
                    return $type;
                }
            }
        }

        return null;
    }

    /**
     * Returns true if the detected signal is a high-intent signal that should
     * trigger contact-info capture for anonymous visitors.
     */
    public function isHighIntent(?string $signal): bool
    {
        if ($signal === null) {
            return false;
        }

        return in_array($signal, self::HIGH_INTENT_SIGNALS, true);
    }

    /**
     * Check whether a string looks like a phone number.
     * Loose check: 7+ digits optionally separated by common delimiters.
     */
    public function containsPhone(string $text): bool
    {
        return (bool) preg_match('/\b[\d\s\-\.\(\)]{7,}\b/', $text);
    }

    /**
     * Check whether a string contains an email address.
     */
    public function containsEmail(string $text): bool
    {
        return (bool) preg_match('/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/', $text);
    }
}
