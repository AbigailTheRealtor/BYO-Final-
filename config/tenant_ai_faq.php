<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Tenant Criteria AI / Chatbot Knowledge Base Questions
    |--------------------------------------------------------------------------
    |
    | Two-axis architecture (docs/ask-ai-kb-replacement-spec.md Part A): each
    | knowledge base = 'universal' + the group matching the rental type, resolved
    | via 'gating'. Tenant listings are 'Residential Property' or
    | 'Commercial Property'. Residential questions never leak into Commercial.
    |
    | Audience: landlords / leasing agents asking about the applicant. There is no
    | subject property — AI Insights educate about the applicant's stated criteria
    | (Tenant DNA + pre-screening + criteria + Match).
    |
    | PRIVACY (Part J / C-B): sensitive applicant fields (income source, references,
    | prior conduct, co-signer, readiness) are redacted for non-owner/unauthorized
    | viewers by AskAiViewerAuthorizationService. The AI never frames any answer as an
    | approve/deny recommendation and never references protected-class characteristics.
    |
    | Entry shape: key => [label, placeholder, tooltip, category_type, source].
    | Compatible with offer-listing/shared/ai-questions-input.blade.php
    |
    */

    'gating' => [
        'Residential Property' => ['universal', 'residential'],
        'Commercial Property'  => ['universal', 'commercial'],
    ],

    'groups' => [

        // =====================================================================
        // UNIVERSAL — both rental types
        // =====================================================================
        'universal' => [
            'Applicant Background' => [
                'faq_q14' => [
                    'label'         => 'What\'s driving the applicant\'s rental search?',
                    'placeholder'   => 'Enter search motivation (e.g., Current lease ending soon, Relocating for a new job, First time renting independently)',
                    'tooltip'       => 'Gives the AI factual context for the applicant\'s search; never an approval recommendation.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'faq_q20' => [
                    'label'         => 'What\'s the applicant\'s biggest concern in this search?',
                    'placeholder'   => 'Enter biggest concern (e.g., Approval with self-employment income, Pet acceptance, Tight move timing)',
                    'tooltip'       => 'Lets the AI restate the applicant\'s top concern factually.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'faq_q12' => [
                    'label'         => 'Is there any chance the applicant would need to break the lease early?',
                    'placeholder'   => 'Enter early-termination risk (e.g., Job may relocate within 12 months, Unlikely but possible, Committed to full term)',
                    'tooltip'       => 'Factual disclosure; never an approval recommendation.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'faq_q17' => [
                    'label'         => 'Does the applicant have landlord or employer references available?',
                    'placeholder'   => 'Enter reference availability (e.g., Previous landlord and employer available, Letters ready, References on request)',
                    'tooltip'       => 'Lets the AI note that references are available; it never shares reference contents.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
            ],
            'Applicant Insights' => [
                'tenant_background_disclosed' => [
                    'label'         => 'What has this applicant disclosed about their rental background?',
                    'placeholder'   => 'Optional — the AI summarizes the applicant\'s disclosed rental background',
                    'tooltip'       => 'Factual summary only; never an approval recommendation and never references protected classes.',
                    'category_type' => 'insight',
                    'source'        => 'TenantDNA+Match',
                ],
                'tenant_rental_needs' => [
                    'label'         => 'What rental needs and uses has the applicant described?',
                    'placeholder'   => 'Optional — the AI describes the applicant\'s stated rental needs and uses',
                    'tooltip'       => 'Describes needs/uses only — never demographics or "type of person."',
                    'category_type' => 'insight',
                    'source'        => 'TenantDNA+Match',
                ],
            ],
        ],

        // =====================================================================
        // RESIDENTIAL RENTAL
        // =====================================================================
        'residential' => [
            'Residential Applicant' => [
                'faq_q18' => [
                    'label'         => 'What is the source and stability of the applicant\'s income?',
                    'placeholder'   => 'Enter income source (e.g., Salaried W-2, Self-employed 2 years of returns available, Retired with pension)',
                    'tooltip'       => 'Factual disclosure of income source; never an approval recommendation. (Sensitive — redacted for unauthorized viewers.)',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'faq_q13' => [
                    'label'         => 'Would the applicant consider a longer lease for a locked-in/reduced rate?',
                    'placeholder'   => 'Enter long-term interest (e.g., Interested in a 2-year lease at a reduced rate, Prefer shorter term, Open to multi-year)',
                    'tooltip'       => 'Factual stance; the AI gives no negotiation coaching.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'faq_q10' => [
                    'label'         => 'Does the applicant prefer furnished or unfurnished?',
                    'placeholder'   => 'Enter furnished preference (e.g., Strongly prefer furnished — relocating, Unfurnished only, Open either way)',
                    'tooltip'       => 'Helps the AI convey furnishing preference (a native gap).',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'faq_q8' => [
                    'label'         => 'Is the applicant willing to pay a pet deposit or pet rent if required?',
                    'placeholder'   => 'Enter pet fee flexibility (e.g., Happy to pay a reasonable deposit and pet rent, Prefer to avoid extra pet fees)',
                    'tooltip'       => 'Helps the AI set expectations around pet-related fees.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'faq_q15' => [
                    'label'         => 'How long was the most recent tenancy, and why is the applicant moving?',
                    'placeholder'   => 'Enter rental history (e.g., 3 years at current place, Landlord is selling, Relocating out of the area)',
                    'tooltip'       => 'Factual rental-history context; never an approval recommendation. (Sensitive — redacted for unauthorized viewers.)',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'faq_q9' => [
                    'label'         => 'How flexible is the applicant on lease length?',
                    'placeholder'   => 'Enter lease flexibility (e.g., Prefer 1 year but open to 2-year, Firm on 6 months, Open to any term)',
                    'tooltip'       => 'Lets the AI convey the applicant\'s lease-length flexibility.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'tenant_cosigner' => [
                    'label'         => 'Is a co-signer or guarantor available if needed?',
                    'placeholder'   => 'Enter co-signer availability (e.g., Parent can co-sign if required, Guarantor available, Not needed)',
                    'tooltip'       => 'Factual disclosure; never an approval recommendation. (Sensitive — redacted for unauthorized viewers.)',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'tenant_application_readiness' => [
                    'label'         => 'How soon is the applicant ready to apply and provide documentation?',
                    'placeholder'   => 'Enter readiness (e.g., Ready to apply now with documents in hand, Can provide pay stubs within 24 hours)',
                    'tooltip'       => 'Factual disclosure of readiness. (Sensitive — redacted for unauthorized viewers.)',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'tenant_prior_conduct' => [
                    'label'         => 'Has the applicant disclosed any prior rental conduct (late payments, notices)?',
                    'placeholder'   => 'Enter disclosed conduct (e.g., No late payments or notices, One late payment in 2022 since resolved)',
                    'tooltip'       => 'Factual disclosure only; never an approval recommendation, no protected-class inference. (Sensitive — redacted for unauthorized viewers.)',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
            ],
        ],

        // =====================================================================
        // COMMERCIAL RENTAL
        // =====================================================================
        'commercial' => [
            'Commercial Applicant' => [
                'faq_q22' => [
                    'label'         => 'Does the applicant expect customer/client foot traffic, and how much?',
                    'placeholder'   => 'Enter foot-traffic expectation (e.g., 50–100 customers per day, Appointment-only low traffic, Employees only)',
                    'tooltip'       => 'Helps the AI address parking, signage, and traffic questions for the applicant\'s use.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'faq_q23' => [
                    'label'         => 'Does the applicant have special equipment or power requirements?',
                    'placeholder'   => 'Enter power/equipment needs (e.g., 3-phase 200A for CNC equipment, Heavy machinery, Standard office power)',
                    'tooltip'       => 'Lets the AI answer electrical-demand questions before a site visit.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'faq_q26' => [
                    'label'         => 'What are the applicant\'s expected hours of operation?',
                    'placeholder'   => 'Enter operating hours (e.g., Mon–Fri 9am–6pm, Seven days 8am–10pm, 24/7 operation)',
                    'tooltip'       => 'Helps the AI factor operating hours into lease-compatibility discussions.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'tenant_commercial_parking' => [
                    'label'         => 'What are the applicant\'s parking needs for staff and customers?',
                    'placeholder'   => 'Enter parking needs (e.g., 6 staff spaces plus customer turnover, 2 reserved spaces, Minimal parking needed)',
                    'tooltip'       => 'Helps the AI answer parking-needs questions for the applicant\'s business.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
            ],
            'Applicant Insights' => [
                'tenant_business_profile' => [
                    'label'         => 'What business use and operating profile has this applicant disclosed?',
                    'placeholder'   => 'Optional — the AI describes the applicant\'s stated business use and operating profile',
                    'tooltip'       => 'Factual, neutral description of the disclosed business use.',
                    'category_type' => 'insight',
                    'source'        => 'TenantDNA+Match',
                ],
            ],
        ],

    ],

];
