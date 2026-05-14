<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Tenant Criteria AI / Chatbot Knowledge Base Questions
    |--------------------------------------------------------------------------
    |
    | Residential base questions are shown for all listings.
    | Commercial-only questions are shown when property_type is
    | 'Commercial Property'.
    |
    | Each entry has:
    |   key              – stable snake_case identifier used as the form field name
    |   label            – human-readable question text
    |   placeholder      – per-question textarea placeholder text
    |   category         – display grouping heading
    |   commercial_only  – true only for commercial tenant questions
    |   aliases          – alternative phrasings for future chatbot NLP matching
    |
    */

    'questions' => [

        // ── Category 1: Lifestyle & Priorities ───────────────────────────────
        [
            'key'             => 'faq_q1',
            'label'           => 'Do you work from home? If so, what does your ideal home setup look like?',
            'placeholder'     => 'Enter WFH needs (e.g., need a dedicated private office; work remotely 5 days a week; not applicable)',
            'category'        => 'Lifestyle & Priorities',
            'commercial_only' => false,
            'aliases'         => ['work from home', 'remote work', 'home office needs'],
        ],
        [
            'key'             => 'faq_q2',
            'label'           => 'What matters most to you in day-to-day living — quiet, walkability, community, outdoor access?',
            'placeholder'     => 'Enter lifestyle priorities (e.g., very important to be near parks and walking trails; love walkable neighborhood; prefer quiet and private)',
            'category'        => 'Lifestyle & Priorities',
            'commercial_only' => false,
            'aliases'         => ['lifestyle priorities', 'daily living preferences', 'what matters most'],
        ],
        [
            'key'             => 'faq_q3',
            'label'           => 'How would you describe your ideal neighborhood vibe?',
            'placeholder'     => 'Enter neighborhood vibe (e.g., quiet residential, family-friendly; trendy and walkable urban area; rural and private)',
            'category'        => 'Lifestyle & Priorities',
            'commercial_only' => false,
            'aliases'         => ['ideal neighborhood', 'neighborhood vibe', 'community feel'],
        ],
        [
            'key'             => 'faq_q4',
            'label'           => 'Are you sensitive to noise from neighbors, traffic, or nearby businesses?',
            'placeholder'     => 'Enter noise sensitivity (e.g., very sensitive — ground floor or busy street would be a problem; moderate tolerance; not a concern)',
            'category'        => 'Lifestyle & Priorities',
            'commercial_only' => false,
            'aliases'         => ['noise sensitivity', 'noise tolerance', 'quiet neighborhood'],
        ],
        [
            'key'             => 'faq_q5',
            'label'           => 'Which amenity matters most to you — in-unit laundry, parking, outdoor space, storage, or something else?',
            'placeholder'     => 'Enter top amenity priority (e.g., in-unit washer/dryer is a must; covered parking essential; large yard for dogs)',
            'category'        => 'Lifestyle & Priorities',
            'commercial_only' => false,
            'aliases'         => ['amenity priority', 'must-have amenity', 'top feature'],
        ],
        [
            'key'             => 'faq_q6',
            'label'           => 'How important is outdoor space to you, and what would you ideally have?',
            'placeholder'     => 'Enter outdoor space needs (e.g., need a fenced yard for dogs; patio for morning coffee is a plus; not important)',
            'category'        => 'Lifestyle & Priorities',
            'commercial_only' => false,
            'aliases'         => ['outdoor space importance', 'yard preference', 'patio balcony needs'],
        ],

        // ── Category 2: Pet Details ───────────────────────────────────────────
        [
            'key'             => 'faq_q7',
            'label'           => 'If you have pets, what are their breed(s), size, and any special space or yard needs?',
            'placeholder'     => 'Enter pet details (e.g., 2 dogs — 60lb Lab mix and 15lb Chihuahua; need fenced yard; fully vaccinated)',
            'category'        => 'Pet Details',
            'commercial_only' => false,
            'aliases'         => ['pet details', 'pet breed size', 'pet routines'],
        ],
        [
            'key'             => 'faq_q8',
            'label'           => 'Are you willing to pay a pet deposit or monthly pet rent if required?',
            'placeholder'     => 'Enter pet fee flexibility (e.g., yes — happy to pay reasonable pet deposit and monthly pet rent; prefer no pet fees)',
            'category'        => 'Pet Details',
            'commercial_only' => false,
            'aliases'         => ['pet deposit', 'pet fee flexibility', 'pet rent willingness'],
        ],

        // ── Category 3: Flexibility & Lease Intent ────────────────────────────
        [
            'key'             => 'faq_q9',
            'label'           => 'Are you flexible on lease length if a great property came along with different terms?',
            'placeholder'     => 'Enter lease flexibility (e.g., prefer 1 year but open to 2-year for the right place; firm on 6 months only)',
            'category'        => 'Flexibility & Lease Intent',
            'commercial_only' => false,
            'aliases'         => ['lease flexibility', 'lease length flexibility', 'term negotiation'],
        ],
        [
            'key'             => 'faq_q10',
            'label'           => 'Would you consider a furnished unit, and is that a preference or a dealbreaker?',
            'placeholder'     => 'Enter furnished preference (e.g., strongly prefer furnished — moving from another state; unfurnished only; open either way)',
            'category'        => 'Flexibility & Lease Intent',
            'commercial_only' => false,
            'aliases'         => ['furnished preference', 'furnished rental', 'furnished or unfurnished'],
        ],
        [
            'key'             => 'faq_q11',
            'label'           => 'How firm is your move-in timeline? Is there any flexibility?',
            'placeholder'     => 'Enter move-in flexibility (e.g., firm — starting new job June 1; flexible by 2–3 weeks; available anytime in 30-day window)',
            'category'        => 'Flexibility & Lease Intent',
            'commercial_only' => false,
            'aliases'         => ['move-in flexibility', 'start date flexibility', 'move-in timing'],
        ],
        [
            'key'             => 'faq_q12',
            'label'           => 'Is there any chance you\'d need to break the lease early — job relocation, life changes, etc.?',
            'placeholder'     => 'Enter early termination risk (e.g., possibly — job may relocate me in 12 months; unlikely; fully committed to full term)',
            'category'        => 'Flexibility & Lease Intent',
            'commercial_only' => false,
            'aliases'         => ['early termination', 'break lease risk', 'relocation possibility'],
        ],
        [
            'key'             => 'faq_q13',
            'label'           => 'Would you consider a longer lease term in exchange for a rent reduction or locked-in rate?',
            'placeholder'     => 'Enter long-term lease interest (e.g., yes — very interested in 2-year lease with reduced rate; prefer shorter term)',
            'category'        => 'Flexibility & Lease Intent',
            'commercial_only' => false,
            'aliases'         => ['longer lease discount', 'extended lease interest', 'multi-year lease'],
        ],

        // ── Category 4: Background & Motivation ───────────────────────────────
        [
            'key'             => 'faq_q14',
            'label'           => 'What\'s driving your rental search right now?',
            'placeholder'     => 'Enter search motivation (e.g., current lease ending; relocating for new job; escaping bad landlord situation; first time renting)',
            'category'        => 'Background & Motivation',
            'commercial_only' => false,
            'aliases'         => ['rental motivation', 'why renting now', 'reason for searching'],
        ],
        [
            'key'             => 'faq_q15',
            'label'           => 'How long was your most recent tenancy, and why are you moving?',
            'placeholder'     => 'Enter rental history (e.g., 3 years at current place; landlord selling the property; relocating out of area)',
            'category'        => 'Background & Motivation',
            'commercial_only' => false,
            'aliases'         => ['rental history', 'previous tenancy length', 'reason for moving'],
        ],
        [
            'key'             => 'faq_q16',
            'label'           => 'Are you looking for a short-term solution or a long-term home?',
            'placeholder'     => 'Enter housing intent (e.g., long-term — want to settle in for at least 3 years; bridge rental while building home)',
            'category'        => 'Background & Motivation',
            'commercial_only' => false,
            'aliases'         => ['long-term intent', 'housing goals', 'short-term or long-term'],
        ],
        [
            'key'             => 'faq_q17',
            'label'           => 'Do you have a landlord or employer reference available?',
            'placeholder'     => 'Enter reference availability (e.g., yes — previous landlord and employer available; have reference letters ready)',
            'category'        => 'Background & Motivation',
            'commercial_only' => false,
            'aliases'         => ['references available', 'landlord reference', 'employer reference'],
        ],
        [
            'key'             => 'faq_q18',
            'label'           => 'What is the source of your income (employment, self-employment, retirement, other)?',
            'placeholder'     => 'Enter income source (e.g., salaried W-2 employee; self-employed business owner — 2 yrs tax returns available; retired with pension)',
            'category'        => 'Background & Motivation',
            'commercial_only' => false,
            'aliases'         => ['income source', 'employment type', 'how income is earned'],
        ],

        // ── Category 5: Communication & Preferences ───────────────────────────
        [
            'key'             => 'faq_q19',
            'label'           => 'How do you prefer to communicate with a landlord — text, email, phone call?',
            'placeholder'     => 'Enter communication preference (e.g., prefer text for quick questions, email for formal matters; phone call works best)',
            'category'        => 'Communication & Preferences',
            'commercial_only' => false,
            'aliases'         => ['communication preference', 'contact method', 'landlord communication style'],
        ],
        [
            'key'             => 'faq_q20',
            'label'           => 'What\'s your biggest concern or hesitation in this rental search?',
            'placeholder'     => 'Enter biggest concern (e.g., worried about getting approved with self-employment income; concerned about pet acceptance; timing)',
            'category'        => 'Communication & Preferences',
            'commercial_only' => false,
            'aliases'         => ['biggest concern', 'rental hesitation', 'top worry'],
        ],

        // ── Category 6: Commercial – Business Use ─────────────────────────────
        [
            'key'             => 'faq_q21',
            'label'           => 'What type of business will be operating from this space?',
            'placeholder'     => 'Enter business type (e.g., medical office; retail boutique; tech startup office; food service)',
            'category'        => 'Commercial – Business Use',
            'commercial_only' => true,
            'aliases'         => ['business type', 'type of business', 'intended business use'],
        ],
        [
            'key'             => 'faq_q22',
            'label'           => 'Do you expect customer or client foot traffic at this location?',
            'placeholder'     => 'Enter foot traffic expectation (e.g., yes — 50–100 customers per day; appointment-only, low foot traffic; employees only)',
            'category'        => 'Commercial – Business Use',
            'commercial_only' => true,
            'aliases'         => ['customer foot traffic', 'client visits', 'retail foot traffic'],
        ],
        [
            'key'             => 'faq_q23',
            'label'           => 'Do you have any special equipment or power requirements?',
            'placeholder'     => 'Enter power or equipment needs (e.g., need 3-phase 200A power for CNC equipment; heavy compressor usage; standard office power)',
            'category'        => 'Commercial – Business Use',
            'commercial_only' => true,
            'aliases'         => ['equipment needs', 'power requirements', '3-phase power'],
        ],
        [
            'key'             => 'faq_q24',
            'label'           => 'Do you require exterior building signage?',
            'placeholder'     => 'Enter signage needs (e.g., need monument sign and suite signage; suite directory listing sufficient; no signage needed)',
            'category'        => 'Commercial – Business Use',
            'commercial_only' => true,
            'aliases'         => ['exterior signage', 'building sign', 'business signage needs'],
        ],
        [
            'key'             => 'faq_q25',
            'label'           => 'Will you need to modify or build out the space?',
            'placeholder'     => 'Enter buildout needs (e.g., need to add 3 private offices and a reception area; minor changes only; prefer turnkey)',
            'category'        => 'Commercial – Business Use',
            'commercial_only' => true,
            'aliases'         => ['buildout needs', 'space modification', 'tenant improvement'],
        ],
        [
            'key'             => 'faq_q26',
            'label'           => 'What are your expected hours of operation?',
            'placeholder'     => 'Enter operating hours (e.g., Mon–Fri 9am–6pm; 7 days a week 8am–10pm; 24/7 operation)',
            'category'        => 'Commercial – Business Use',
            'commercial_only' => true,
            'aliases'         => ['hours of operation', 'business hours', 'operating schedule'],
        ],
        [
            'key'             => 'faq_q27',
            'label'           => 'Are you flexible on commercial lease term length and structure (NNN, gross, modified gross)?',
            'placeholder'     => 'Enter commercial lease flexibility (e.g., prefer 3-year modified gross; open to NNN for right space; need 5-year minimum for lender)',
            'category'        => 'Commercial – Business Use',
            'commercial_only' => true,
            'aliases'         => ['commercial lease flexibility', 'NNN gross lease preference', 'lease structure'],
        ],

    ],

];
