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
    |   tooltip          – short sentence explaining why the AI needs this answer
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
            'placeholder'     => 'Enter WFH needs (e.g., Need a dedicated private office, Work remotely 5 days a week, Not applicable)',
            'tooltip'         => 'Helps the AI understand whether the tenant needs a dedicated workspace when evaluating listings.',
            'category'        => 'Lifestyle & Priorities',
            'commercial_only' => false,
            'aliases'         => ['work from home', 'remote work', 'home office needs'],
        ],
        [
            'key'             => 'faq_q2',
            'label'           => 'What matters most to you in day-to-day living — quiet, walkability, community, outdoor access?',
            'placeholder'     => 'Enter lifestyle priorities (e.g., Very important to be near parks and walking trails, Love a walkable neighborhood, Prefer quiet and private surroundings)',
            'tooltip'         => 'Gives the AI context to match lifestyle priorities to specific property features and locations.',
            'category'        => 'Lifestyle & Priorities',
            'commercial_only' => false,
            'aliases'         => ['lifestyle priorities', 'daily living preferences', 'what matters most'],
        ],
        [
            'key'             => 'faq_q3',
            'label'           => 'How would you describe your ideal neighborhood vibe?',
            'placeholder'     => 'Enter neighborhood vibe (e.g., Quiet residential family-friendly area, Trendy walkable urban neighborhood, Rural and very private setting)',
            'tooltip'         => 'Lets the AI understand the community feel the tenant is looking for when comparing neighborhoods.',
            'category'        => 'Lifestyle & Priorities',
            'commercial_only' => false,
            'aliases'         => ['ideal neighborhood', 'neighborhood vibe', 'community feel'],
        ],
        [
            'key'             => 'faq_q4',
            'label'           => 'Are you sensitive to noise from neighbors, traffic, or nearby businesses?',
            'placeholder'     => 'Enter noise sensitivity (e.g., Very sensitive — busy street or ground floor would be a problem, Moderate tolerance, Not a concern at all)',
            'tooltip'         => 'Helps the AI factor noise sensitivity into property assessments and landlord screening.',
            'category'        => 'Lifestyle & Priorities',
            'commercial_only' => false,
            'aliases'         => ['noise sensitivity', 'noise tolerance', 'quiet neighborhood'],
        ],
        [
            'key'             => 'faq_q5',
            'label'           => 'Which amenity matters most to you — in-unit laundry, parking, outdoor space, storage, or something else?',
            'placeholder'     => 'Enter top amenity priority (e.g., In-unit washer/dryer is a must, Covered parking is essential, Large yard for the dogs)',
            'tooltip'         => 'Lets the AI identify the single most important amenity so it can prioritize it in property comparisons.',
            'category'        => 'Lifestyle & Priorities',
            'commercial_only' => false,
            'aliases'         => ['amenity priority', 'must-have amenity', 'top feature'],
        ],
        [
            'key'             => 'faq_q6',
            'label'           => 'How important is outdoor space to you, and what would you ideally have?',
            'placeholder'     => 'Enter outdoor space needs (e.g., Need a fenced yard for the dogs, Covered patio for morning coffee would be great, Not particularly important)',
            'tooltip'         => 'Helps the AI evaluate whether a property\'s outdoor space meets the tenant\'s needs.',
            'category'        => 'Lifestyle & Priorities',
            'commercial_only' => false,
            'aliases'         => ['outdoor space importance', 'yard preference', 'patio balcony needs'],
        ],

        // ── Category 2: Pet Details ───────────────────────────────────────────
        [
            'key'             => 'faq_q7',
            'label'           => 'If you have pets, what are their breed(s), size, and any special space or yard needs?',
            'placeholder'     => 'Enter pet details (e.g., 2 dogs — 60lb Lab mix and 15lb Chihuahua, Fenced yard required, All pets fully vaccinated)',
            'tooltip'         => 'Lets the AI accurately communicate pet information to landlords screening for pets.',
            'category'        => 'Pet Details',
            'commercial_only' => false,
            'aliases'         => ['pet details', 'pet breed size', 'pet routines'],
        ],
        [
            'key'             => 'faq_q8',
            'label'           => 'Are you willing to pay a pet deposit or monthly pet rent if required?',
            'placeholder'     => 'Enter pet fee flexibility (e.g., Yes — happy to pay a reasonable deposit and pet rent, Prefer to avoid extra pet fees if possible)',
            'tooltip'         => 'Helps the AI set accurate expectations around pet-related fees before a tenant applies.',
            'category'        => 'Pet Details',
            'commercial_only' => false,
            'aliases'         => ['pet deposit', 'pet fee flexibility', 'pet rent willingness'],
        ],

        // ── Category 3: Flexibility & Lease Intent ────────────────────────────
        [
            'key'             => 'faq_q9',
            'label'           => 'Are you flexible on lease length if a great property came along with different terms?',
            'placeholder'     => 'Enter lease flexibility (e.g., Prefer 1 year but open to 2-year for the right place, Firm on 6 months only, Open to any term length)',
            'tooltip'         => 'Lets the AI match the tenant to listings where the lease term is flexible or aligns with their needs.',
            'category'        => 'Flexibility & Lease Intent',
            'commercial_only' => false,
            'aliases'         => ['lease flexibility', 'lease length flexibility', 'term negotiation'],
        ],
        [
            'key'             => 'faq_q10',
            'label'           => 'Would you consider a furnished unit, and is that a preference or a dealbreaker?',
            'placeholder'     => 'Enter furnished preference (e.g., Strongly prefer furnished — relocating from another state, Unfurnished only, Open either way)',
            'tooltip'         => 'Helps the AI filter for furnished or unfurnished options based on the tenant\'s situation.',
            'category'        => 'Flexibility & Lease Intent',
            'commercial_only' => false,
            'aliases'         => ['furnished preference', 'furnished rental', 'furnished or unfurnished'],
        ],
        [
            'key'             => 'faq_q11',
            'label'           => 'How firm is your move-in timeline? Is there any flexibility?',
            'placeholder'     => 'Enter move-in flexibility (e.g., Firm — starting new job June 1, Flexible by 2–3 weeks, Available anytime within a 30-day window)',
            'tooltip'         => 'Lets the AI flag listings with availability windows that align with the tenant\'s move-in date.',
            'category'        => 'Flexibility & Lease Intent',
            'commercial_only' => false,
            'aliases'         => ['move-in flexibility', 'start date flexibility', 'move-in timing'],
        ],
        [
            'key'             => 'faq_q12',
            'label'           => 'Is there any chance you\'d need to break the lease early — job relocation, life changes, etc.?',
            'placeholder'     => 'Enter early termination risk (e.g., Possibly — job may relocate me within 12 months, Unlikely but can\'t rule it out, Fully committed to the full term)',
            'tooltip'         => 'Helps the AI surface any early termination considerations landlords should be aware of.',
            'category'        => 'Flexibility & Lease Intent',
            'commercial_only' => false,
            'aliases'         => ['early termination', 'break lease risk', 'relocation possibility'],
        ],
        [
            'key'             => 'faq_q13',
            'label'           => 'Would you consider a longer lease term in exchange for a rent reduction or locked-in rate?',
            'placeholder'     => 'Enter long-term lease interest (e.g., Yes — very interested in a 2-year lease with a reduced rate, Prefer a shorter term, Open to multi-year if the savings are meaningful)',
            'tooltip'         => 'Lets the AI identify listings where a longer lease term may benefit both the tenant and landlord.',
            'category'        => 'Flexibility & Lease Intent',
            'commercial_only' => false,
            'aliases'         => ['longer lease discount', 'extended lease interest', 'multi-year lease'],
        ],

        // ── Category 4: Background & Motivation ───────────────────────────────
        [
            'key'             => 'faq_q14',
            'label'           => 'What\'s driving your rental search right now?',
            'placeholder'     => 'Enter search motivation (e.g., Current lease ending soon, Relocating for a new job, First time renting on my own)',
            'tooltip'         => 'Gives the AI context to understand the urgency and background behind the tenant\'s search.',
            'category'        => 'Background & Motivation',
            'commercial_only' => false,
            'aliases'         => ['rental motivation', 'why renting now', 'reason for searching'],
        ],
        [
            'key'             => 'faq_q15',
            'label'           => 'How long was your most recent tenancy, and why are you moving?',
            'placeholder'     => 'Enter rental history (e.g., 3 years at my current place, Landlord is selling so I must move, Relocating out of the area)',
            'tooltip'         => 'Helps the AI establish rental history context landlords commonly consider during screening.',
            'category'        => 'Background & Motivation',
            'commercial_only' => false,
            'aliases'         => ['rental history', 'previous tenancy length', 'reason for moving'],
        ],
        [
            'key'             => 'faq_q16',
            'label'           => 'Are you looking for a short-term solution or a long-term home?',
            'placeholder'     => 'Enter housing intent (e.g., Long-term — want to settle in for at least 3 years, Bridge rental while our new home is being built, Short-term solution only)',
            'tooltip'         => 'Lets the AI match this tenant to listings suited for long-term versus bridge rentals.',
            'category'        => 'Background & Motivation',
            'commercial_only' => false,
            'aliases'         => ['long-term intent', 'housing goals', 'short-term or long-term'],
        ],
        [
            'key'             => 'faq_q17',
            'label'           => 'Do you have a landlord or employer reference available?',
            'placeholder'     => 'Enter reference availability (e.g., Yes — previous landlord and employer both available, Reference letters ready to share, Can provide references on request)',
            'tooltip'         => 'Helps the AI let landlords know references are available, which can strengthen the tenant\'s application.',
            'category'        => 'Background & Motivation',
            'commercial_only' => false,
            'aliases'         => ['references available', 'landlord reference', 'employer reference'],
        ],
        [
            'key'             => 'faq_q18',
            'label'           => 'What is the source of your income (employment, self-employment, retirement, other)?',
            'placeholder'     => 'Enter income source (e.g., Salaried W-2 employee, Self-employed — 2 years of tax returns available, Retired with steady pension income)',
            'tooltip'         => 'Gives the AI context to address income verification questions landlords may have.',
            'category'        => 'Background & Motivation',
            'commercial_only' => false,
            'aliases'         => ['income source', 'employment type', 'how income is earned'],
        ],

        // ── Category 5: Communication & Preferences ───────────────────────────
        [
            'key'             => 'faq_q19',
            'label'           => 'How do you prefer to communicate with a landlord — text, email, phone call?',
            'placeholder'     => 'Enter communication preference (e.g., Prefer text for quick questions and email for formal matters, Phone call works best for me, Whatever is easiest for the landlord)',
            'tooltip'         => 'Lets the AI set expectations about communication style with potential landlords.',
            'category'        => 'Communication & Preferences',
            'commercial_only' => false,
            'aliases'         => ['communication preference', 'contact method', 'landlord communication style'],
        ],
        [
            'key'             => 'faq_q20',
            'label'           => 'What\'s your biggest concern or hesitation in this rental search?',
            'placeholder'     => 'Enter biggest concern (e.g., Worried about approval with self-employment income, Concerned about pet acceptance, Timing of the move feels tight)',
            'tooltip'         => 'Helps the AI proactively address the tenant\'s top concern in listing matches and landlord discussions.',
            'category'        => 'Communication & Preferences',
            'commercial_only' => false,
            'aliases'         => ['biggest concern', 'rental hesitation', 'top worry'],
        ],

        // ── Category 6: Commercial – Business Use ─────────────────────────────
        [
            'key'             => 'faq_q21',
            'label'           => 'What type of business will be operating from this space?',
            'placeholder'     => 'Enter business type (e.g., Medical office, Retail boutique, Tech startup office, Food service operation)',
            'tooltip'         => 'Lets the AI communicate the tenant\'s business type to landlords screening for compatible uses.',
            'category'        => 'Commercial – Business Use',
            'commercial_only' => true,
            'aliases'         => ['business type', 'type of business', 'intended business use'],
        ],
        [
            'key'             => 'faq_q22',
            'label'           => 'Do you expect customer or client foot traffic at this location?',
            'placeholder'     => 'Enter foot traffic expectation (e.g., Yes — 50 to 100 customers per day, Appointment-only with low foot traffic, Employees only no customer visits)',
            'tooltip'         => 'Helps the AI address parking, signage, and traffic questions relevant to the business\'s customer volume.',
            'category'        => 'Commercial – Business Use',
            'commercial_only' => true,
            'aliases'         => ['customer foot traffic', 'client visits', 'retail foot traffic'],
        ],
        [
            'key'             => 'faq_q23',
            'label'           => 'Do you have any special equipment or power requirements?',
            'placeholder'     => 'Enter power or equipment needs (e.g., Need 3-phase 200A power for CNC equipment, Heavy compressor and machinery usage, Standard office power is sufficient)',
            'tooltip'         => 'Lets the AI accurately answer landlord questions about electrical demands before a site visit.',
            'category'        => 'Commercial – Business Use',
            'commercial_only' => true,
            'aliases'         => ['equipment needs', 'power requirements', '3-phase power'],
        ],
        [
            'key'             => 'faq_q24',
            'label'           => 'Do you require exterior building signage?',
            'placeholder'     => 'Enter signage needs (e.g., Need monument sign and suite signage, Suite directory listing is sufficient, No signage needed)',
            'tooltip'         => 'Helps the AI communicate signage needs to landlords when evaluating space compatibility.',
            'category'        => 'Commercial – Business Use',
            'commercial_only' => true,
            'aliases'         => ['exterior signage', 'building sign', 'business signage needs'],
        ],
        [
            'key'             => 'faq_q25',
            'label'           => 'Will you need to modify or build out the space?',
            'placeholder'     => 'Enter buildout needs (e.g., Need to add private offices and a reception area, Minor cosmetic changes only, Prefer a turnkey ready space)',
            'tooltip'         => 'Lets the AI address buildout questions and tenant improvement expectations with prospective landlords.',
            'category'        => 'Commercial – Business Use',
            'commercial_only' => true,
            'aliases'         => ['buildout needs', 'space modification', 'tenant improvement'],
        ],
        [
            'key'             => 'faq_q26',
            'label'           => 'What are your expected hours of operation?',
            'placeholder'     => 'Enter operating hours (e.g., Mon–Fri 9am to 6pm, Seven days a week 8am to 10pm, 24/7 operation)',
            'tooltip'         => 'Helps the AI factor operating hours into lease compatibility and neighbor impact discussions.',
            'category'        => 'Commercial – Business Use',
            'commercial_only' => true,
            'aliases'         => ['hours of operation', 'business hours', 'operating schedule'],
        ],
        [
            'key'             => 'faq_q27',
            'label'           => 'Are you flexible on commercial lease term length and structure (NNN, gross, modified gross)?',
            'placeholder'     => 'Enter commercial lease flexibility (e.g., Prefer 3-year modified gross lease, Open to NNN for the right space, Need 5-year minimum term for lender approval)',
            'tooltip'         => 'Lets the AI accurately represent the tenant\'s commercial lease preferences when matching spaces.',
            'category'        => 'Commercial – Business Use',
            'commercial_only' => true,
            'aliases'         => ['commercial lease flexibility', 'NNN gross lease preference', 'lease structure'],
        ],

    ],

];
