<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Tenant Criteria AI / Chatbot Knowledge Base Questions
    |--------------------------------------------------------------------------
    |
    | Questions 1-70 are shown for all listings (residential base).
    | Questions 71-93 are shown only when the listing property_type is
    | 'Commercial Property'.
    |
    | Each entry has:
    |   key      – stable snake_case identifier used as the form field name
    |   label    – human-readable question text
    |   category – display grouping heading
    |   commercial_only – true only for questions 71-93
    |   aliases  – alternative phrasings for future chatbot NLP matching
    |
    */

    'questions' => [

        // ── Category 1: General Background ───────────────────────────────────
        [
            'key'             => 'faq_q1',
            'label'           => 'Can you briefly describe who will be living in the rental property?',
            'category'        => 'General Background',
            'commercial_only' => false,
            'aliases'         => ['who lives there', 'occupants overview', 'household description'],
        ],
        [
            'key'             => 'faq_q2',
            'label'           => 'Why are you looking to rent at this time?',
            'category'        => 'General Background',
            'commercial_only' => false,
            'aliases'         => ['reason for renting', 'why renting', 'motivation to rent'],
        ],
        [
            'key'             => 'faq_q3',
            'label'           => 'How long have you been searching for a rental property?',
            'category'        => 'General Background',
            'commercial_only' => false,
            'aliases'         => ['search duration', 'how long looking', 'time searching'],
        ],
        [
            'key'             => 'faq_q4',
            'label'           => 'Have you rented before? If so, how long was your most recent tenancy?',
            'category'        => 'General Background',
            'commercial_only' => false,
            'aliases'         => ['rental history', 'previous tenancy', 'prior rental'],
        ],
        [
            'key'             => 'faq_q5',
            'label'           => 'Are you currently renting? If yes, why are you looking to move?',
            'category'        => 'General Background',
            'commercial_only' => false,
            'aliases'         => ['reason to move', 'current rental situation', 'why moving'],
        ],

        // ── Category 2: Budget & Finances ─────────────────────────────────────
        [
            'key'             => 'faq_q6',
            'label'           => 'What is your total monthly gross household income?',
            'category'        => 'Budget & Finances',
            'commercial_only' => false,
            'aliases'         => ['gross income', 'monthly income', 'household income amount'],
        ],
        [
            'key'             => 'faq_q7',
            'label'           => 'What is your maximum monthly rent budget?',
            'category'        => 'Budget & Finances',
            'commercial_only' => false,
            'aliases'         => ['max rent', 'rent budget', 'maximum monthly payment'],
        ],
        [
            'key'             => 'faq_q8',
            'label'           => 'Do you have funds available for a security deposit?',
            'category'        => 'Budget & Finances',
            'commercial_only' => false,
            'aliases'         => ['security deposit', 'deposit funds', 'can afford deposit'],
        ],
        [
            'key'             => 'faq_q9',
            'label'           => 'What is your approximate credit score range?',
            'category'        => 'Budget & Finances',
            'commercial_only' => false,
            'aliases'         => ['credit score', 'credit rating', 'FICO score'],
        ],
        [
            'key'             => 'faq_q10',
            'label'           => 'Do you have any outstanding debt obligations that may affect your ability to pay rent?',
            'category'        => 'Budget & Finances',
            'commercial_only' => false,
            'aliases'         => ['debt obligations', 'existing debt', 'financial obligations'],
        ],
        [
            'key'             => 'faq_q11',
            'label'           => 'Can you provide references from a previous landlord or employer upon request?',
            'category'        => 'Budget & Finances',
            'commercial_only' => false,
            'aliases'         => ['references available', 'landlord reference', 'employer reference'],
        ],
        [
            'key'             => 'faq_q12',
            'label'           => 'What is the source of your income (employment, self-employment, retirement, etc.)?',
            'category'        => 'Budget & Finances',
            'commercial_only' => false,
            'aliases'         => ['income source', 'employment status', 'how do you earn income'],
        ],

        // ── Category 3: Property Preferences ─────────────────────────────────
        [
            'key'             => 'faq_q13',
            'label'           => 'What type of property are you looking for (apartment, house, condo, townhome, etc.)?',
            'category'        => 'Property Preferences',
            'commercial_only' => false,
            'aliases'         => ['property type preference', 'type of home', 'housing type'],
        ],
        [
            'key'             => 'faq_q14',
            'label'           => 'How many bedrooms do you require at minimum?',
            'category'        => 'Property Preferences',
            'commercial_only' => false,
            'aliases'         => ['minimum bedrooms', 'bedroom requirement', 'number of bedrooms needed'],
        ],
        [
            'key'             => 'faq_q15',
            'label'           => 'How many bathrooms do you require at minimum?',
            'category'        => 'Property Preferences',
            'commercial_only' => false,
            'aliases'         => ['minimum bathrooms', 'bathroom requirement', 'number of bathrooms needed'],
        ],
        [
            'key'             => 'faq_q16',
            'label'           => 'What is the minimum square footage you need?',
            'category'        => 'Property Preferences',
            'commercial_only' => false,
            'aliases'         => ['minimum sqft', 'square footage requirement', 'size requirement'],
        ],
        [
            'key'             => 'faq_q17',
            'label'           => 'Do you have any specific property condition requirements (e.g., updated kitchen, new flooring)?',
            'category'        => 'Property Preferences',
            'commercial_only' => false,
            'aliases'         => ['property condition', 'renovation requirement', 'update preference'],
        ],
        [
            'key'             => 'faq_q18',
            'label'           => 'Do you need a garage, carport, or dedicated parking space?',
            'category'        => 'Property Preferences',
            'commercial_only' => false,
            'aliases'         => ['parking requirement', 'garage needed', 'carport preference'],
        ],
        [
            'key'             => 'faq_q19',
            'label'           => 'Is outdoor space (yard, patio, balcony) important to you?',
            'category'        => 'Property Preferences',
            'commercial_only' => false,
            'aliases'         => ['outdoor space', 'yard preference', 'patio balcony'],
        ],
        [
            'key'             => 'faq_q20',
            'label'           => 'Are there any accessibility features you require (e.g., wheelchair ramp, single story)?',
            'category'        => 'Property Preferences',
            'commercial_only' => false,
            'aliases'         => ['accessibility', 'disability accommodation', 'ADA requirements'],
        ],

        // ── Category 4: Location Preferences ──────────────────────────────────
        [
            'key'             => 'faq_q21',
            'label'           => 'What cities or neighborhoods are you most interested in?',
            'category'        => 'Location Preferences',
            'commercial_only' => false,
            'aliases'         => ['preferred city', 'neighborhood preference', 'location preference'],
        ],
        [
            'key'             => 'faq_q22',
            'label'           => 'How important is proximity to your workplace or school?',
            'category'        => 'Location Preferences',
            'commercial_only' => false,
            'aliases'         => ['commute importance', 'proximity to work', 'distance to school'],
        ],
        [
            'key'             => 'faq_q23',
            'label'           => 'Are there specific school districts you need to be within?',
            'category'        => 'Location Preferences',
            'commercial_only' => false,
            'aliases'         => ['school district', 'specific schools', 'school zone'],
        ],
        [
            'key'             => 'faq_q24',
            'label'           => 'How far are you willing to commute to work or school?',
            'category'        => 'Location Preferences',
            'commercial_only' => false,
            'aliases'         => ['commute distance', 'travel time', 'max commute'],
        ],
        [
            'key'             => 'faq_q25',
            'label'           => 'Is access to public transportation important to you?',
            'category'        => 'Location Preferences',
            'commercial_only' => false,
            'aliases'         => ['public transit', 'bus stop', 'train access'],
        ],
        [
            'key'             => 'faq_q26',
            'label'           => 'Are there any areas or neighborhoods you want to avoid?',
            'category'        => 'Location Preferences',
            'commercial_only' => false,
            'aliases'         => ['areas to avoid', 'excluded neighborhoods', 'unwanted locations'],
        ],
        [
            'key'             => 'faq_q27',
            'label'           => 'How important is being near restaurants, shops, or entertainment?',
            'category'        => 'Location Preferences',
            'commercial_only' => false,
            'aliases'         => ['nearby amenities', 'walkability', 'entertainment proximity'],
        ],

        // ── Category 5: Lease Terms ────────────────────────────────────────────
        [
            'key'             => 'faq_q28',
            'label'           => 'What lease length are you looking for (month-to-month, 6 months, 1 year, etc.)?',
            'category'        => 'Lease Terms',
            'commercial_only' => false,
            'aliases'         => ['lease duration', 'contract length', 'term preference'],
        ],
        [
            'key'             => 'faq_q29',
            'label'           => 'When is your ideal move-in date?',
            'category'        => 'Lease Terms',
            'commercial_only' => false,
            'aliases'         => ['move in date', 'start date', 'desired move-in'],
        ],
        [
            'key'             => 'faq_q30',
            'label'           => 'Are you flexible on the move-in date?',
            'category'        => 'Lease Terms',
            'commercial_only' => false,
            'aliases'         => ['flexible start date', 'move-in flexibility', 'can you wait'],
        ],
        [
            'key'             => 'faq_q31',
            'label'           => 'Would you consider a longer lease term in exchange for reduced rent?',
            'category'        => 'Lease Terms',
            'commercial_only' => false,
            'aliases'         => ['longer lease for lower rent', 'extended term discount', 'lease negotiation'],
        ],
        [
            'key'             => 'faq_q32',
            'label'           => 'Do you have any conditions for early termination of the lease?',
            'category'        => 'Lease Terms',
            'commercial_only' => false,
            'aliases'         => ['early termination', 'break clause', 'exit clause'],
        ],
        [
            'key'             => 'faq_q33',
            'label'           => 'Are you willing to sign an automatic renewal clause?',
            'category'        => 'Lease Terms',
            'commercial_only' => false,
            'aliases'         => ['auto renewal', 'lease renewal', 'contract extension'],
        ],
        [
            'key'             => 'faq_q34',
            'label'           => 'What utilities are you expecting to have included in the rent?',
            'category'        => 'Lease Terms',
            'commercial_only' => false,
            'aliases'         => ['utilities included', 'bills included', 'what is covered'],
        ],

        // ── Category 6: Lifestyle & Habits ────────────────────────────────────
        [
            'key'             => 'faq_q35',
            'label'           => 'Do you smoke or use tobacco products?',
            'category'        => 'Lifestyle & Habits',
            'commercial_only' => false,
            'aliases'         => ['smoker', 'tobacco use', 'smoking habits'],
        ],
        [
            'key'             => 'faq_q36',
            'label'           => 'Do you work from home? If so, how often?',
            'category'        => 'Lifestyle & Habits',
            'commercial_only' => false,
            'aliases'         => ['work from home', 'remote work', 'home office'],
        ],
        [
            'key'             => 'faq_q37',
            'label'           => 'Do you frequently have overnight guests?',
            'category'        => 'Lifestyle & Habits',
            'commercial_only' => false,
            'aliases'         => ['overnight guests', 'visitors staying', 'guest frequency'],
        ],
        [
            'key'             => 'faq_q38',
            'label'           => 'Do you play musical instruments or have hobbies that may create noise?',
            'category'        => 'Lifestyle & Habits',
            'commercial_only' => false,
            'aliases'         => ['noise level', 'musical instruments', 'loud hobbies'],
        ],
        [
            'key'             => 'faq_q39',
            'label'           => 'Do you host social gatherings or parties at home?',
            'category'        => 'Lifestyle & Habits',
            'commercial_only' => false,
            'aliases'         => ['parties at home', 'social gatherings', 'entertain guests'],
        ],
        [
            'key'             => 'faq_q40',
            'label'           => 'Are you a quiet/early riser or do you tend to keep late hours?',
            'category'        => 'Lifestyle & Habits',
            'commercial_only' => false,
            'aliases'         => ['sleep schedule', 'night owl', 'quiet hours'],
        ],
        [
            'key'             => 'faq_q41',
            'label'           => 'Do you cook frequently? Are there any special cooking needs (e.g., gas stove preferred)?',
            'category'        => 'Lifestyle & Habits',
            'commercial_only' => false,
            'aliases'         => ['cooking habits', 'kitchen use', 'gas stove preference'],
        ],
        [
            'key'             => 'faq_q42',
            'label'           => 'Are there any hobbies or activities that require special accommodations (e.g., art studio, gym, storage)?',
            'category'        => 'Lifestyle & Habits',
            'commercial_only' => false,
            'aliases'         => ['hobby accommodations', 'special activity space', 'storage needs'],
        ],

        // ── Category 7: Pets ──────────────────────────────────────────────────
        [
            'key'             => 'faq_q43',
            'label'           => 'Do you have pets? If so, how many?',
            'category'        => 'Pets',
            'commercial_only' => false,
            'aliases'         => ['pet owner', 'number of pets', 'have animals'],
        ],
        [
            'key'             => 'faq_q44',
            'label'           => 'What type and breed of pet(s) do you have?',
            'category'        => 'Pets',
            'commercial_only' => false,
            'aliases'         => ['pet breed', 'type of animal', 'dog breed cat breed'],
        ],
        [
            'key'             => 'faq_q45',
            'label'           => 'What is the approximate weight of your pet(s)?',
            'category'        => 'Pets',
            'commercial_only' => false,
            'aliases'         => ['pet weight', 'how heavy is your pet', 'animal size'],
        ],
        [
            'key'             => 'faq_q46',
            'label'           => 'Are your pets up-to-date on vaccinations?',
            'category'        => 'Pets',
            'commercial_only' => false,
            'aliases'         => ['pet vaccinations', 'pet health', 'animal vaccinated'],
        ],
        [
            'key'             => 'faq_q47',
            'label'           => 'Are you willing to pay a pet deposit or additional pet rent?',
            'category'        => 'Pets',
            'commercial_only' => false,
            'aliases'         => ['pet deposit', 'pet fee', 'additional pet rent'],
        ],

        // ── Category 8: Move-In Details ────────────────────────────────────────
        [
            'key'             => 'faq_q48',
            'label'           => 'Do you have large furniture or appliances that will need to be moved in?',
            'category'        => 'Move-In Details',
            'commercial_only' => false,
            'aliases'         => ['large furniture', 'move in items', 'appliances moving'],
        ],
        [
            'key'             => 'faq_q49',
            'label'           => 'Do you plan to bring your own washer/dryer?',
            'category'        => 'Move-In Details',
            'commercial_only' => false,
            'aliases'         => ['own washer dryer', 'bring laundry appliances', 'washer dryer hookup'],
        ],
        [
            'key'             => 'faq_q50',
            'label'           => 'Do you require a storage unit or additional storage space?',
            'category'        => 'Move-In Details',
            'commercial_only' => false,
            'aliases'         => ['storage unit', 'extra storage', 'storage requirement'],
        ],
        [
            'key'             => 'faq_q51',
            'label'           => 'Will you be using a moving company or doing a self-move?',
            'category'        => 'Move-In Details',
            'commercial_only' => false,
            'aliases'         => ['moving company', 'self move', 'how are you moving'],
        ],
        [
            'key'             => 'faq_q52',
            'label'           => 'Are there any specific move-in conditions or preparations you would request of the landlord?',
            'category'        => 'Move-In Details',
            'commercial_only' => false,
            'aliases'         => ['move in requests', 'landlord preparation', 'move in conditions'],
        ],
        [
            'key'             => 'faq_q53',
            'label'           => 'Will you require parking for multiple vehicles during move-in?',
            'category'        => 'Move-In Details',
            'commercial_only' => false,
            'aliases'         => ['multiple vehicle parking', 'move in parking', 'parking needs during move'],
        ],

        // ── Category 9: Occupants ──────────────────────────────────────────────
        [
            'key'             => 'faq_q54',
            'label'           => 'How many people in total will be living in the property?',
            'category'        => 'Occupants',
            'commercial_only' => false,
            'aliases'         => ['number of occupants', 'total residents', 'how many people'],
        ],
        [
            'key'             => 'faq_q55',
            'label'           => 'Will any minors be living in the property?',
            'category'        => 'Occupants',
            'commercial_only' => false,
            'aliases'         => ['children living there', 'minors in home', 'kids in property'],
        ],
        [
            'key'             => 'faq_q56',
            'label'           => 'Will there be any co-signers or guarantors on the lease?',
            'category'        => 'Occupants',
            'commercial_only' => false,
            'aliases'         => ['co-signer', 'guarantor', 'lease co-applicant'],
        ],
        [
            'key'             => 'faq_q57',
            'label'           => 'Are all intended occupants listed on the lease application?',
            'category'        => 'Occupants',
            'commercial_only' => false,
            'aliases'         => ['all occupants listed', 'co-tenants', 'lease applicants'],
        ],
        [
            'key'             => 'faq_q58',
            'label'           => 'Are any of the intended occupants related to each other?',
            'category'        => 'Occupants',
            'commercial_only' => false,
            'aliases'         => ['family members', 'related occupants', 'roommate relationship'],
        ],

        // ── Category 10: Maintenance & Responsibilities ───────────────────────
        [
            'key'             => 'faq_q59',
            'label'           => 'Are you comfortable handling minor maintenance tasks (e.g., changing light bulbs, air filters)?',
            'category'        => 'Maintenance & Responsibilities',
            'commercial_only' => false,
            'aliases'         => ['minor maintenance', 'tenant responsibilities', 'self-maintenance'],
        ],
        [
            'key'             => 'faq_q60',
            'label'           => 'Are you willing to maintain the lawn or landscaping if required?',
            'category'        => 'Maintenance & Responsibilities',
            'commercial_only' => false,
            'aliases'         => ['lawn maintenance', 'landscaping upkeep', 'yard care'],
        ],
        [
            'key'             => 'faq_q61',
            'label'           => 'How do you prefer to report maintenance issues to a landlord?',
            'category'        => 'Maintenance & Responsibilities',
            'commercial_only' => false,
            'aliases'         => ['report maintenance', 'maintenance communication', 'how to contact landlord'],
        ],
        [
            'key'             => 'faq_q62',
            'label'           => 'Have you ever caused significant damage to a rental property? If so, please explain.',
            'category'        => 'Maintenance & Responsibilities',
            'commercial_only' => false,
            'aliases'         => ['property damage history', 'past damage', 'rental damage'],
        ],
        [
            'key'             => 'faq_q63',
            'label'           => 'Are you willing to purchase renters insurance?',
            'category'        => 'Maintenance & Responsibilities',
            'commercial_only' => false,
            'aliases'         => ['renters insurance', 'tenant insurance', 'insurance coverage'],
        ],

        // ── Category 11: Amenities & Features Needed ──────────────────────────
        [
            'key'             => 'faq_q64',
            'label'           => 'Is in-unit laundry a requirement for you?',
            'category'        => 'Amenities & Features Needed',
            'commercial_only' => false,
            'aliases'         => ['in-unit laundry', 'washer dryer in unit', 'laundry in unit'],
        ],
        [
            'key'             => 'faq_q65',
            'label'           => 'Do you require a pool or community amenities?',
            'category'        => 'Amenities & Features Needed',
            'commercial_only' => false,
            'aliases'         => ['pool required', 'community amenities', 'pool access'],
        ],
        [
            'key'             => 'faq_q66',
            'label'           => 'Is high-speed internet infrastructure important to you?',
            'category'        => 'Amenities & Features Needed',
            'commercial_only' => false,
            'aliases'         => ['internet speed', 'fiber internet', 'high-speed internet'],
        ],
        [
            'key'             => 'faq_q67',
            'label'           => 'Do you need a home with central air conditioning and heating?',
            'category'        => 'Amenities & Features Needed',
            'commercial_only' => false,
            'aliases'         => ['central AC', 'heating and cooling', 'HVAC requirement'],
        ],
        [
            'key'             => 'faq_q68',
            'label'           => 'Is a fenced yard important to you?',
            'category'        => 'Amenities & Features Needed',
            'commercial_only' => false,
            'aliases'         => ['fenced yard', 'enclosed yard', 'fence requirement'],
        ],
        [
            'key'             => 'faq_q69',
            'label'           => 'Do you require elevator access or specific building features?',
            'category'        => 'Amenities & Features Needed',
            'commercial_only' => false,
            'aliases'         => ['elevator access', 'building features', 'floor preference'],
        ],
        [
            'key'             => 'faq_q70',
            'label'           => 'Are there any other amenities or property features that are non-negotiable for you?',
            'category'        => 'Amenities & Features Needed',
            'commercial_only' => false,
            'aliases'         => ['non-negotiable amenities', 'must-have features', 'required property features'],
        ],

        // ── Category 12: Commercial – Business Information (Q71–78) ───────────
        [
            'key'             => 'faq_q71',
            'label'           => 'What type of business will be operating from this space?',
            'category'        => 'Commercial – Business Information',
            'commercial_only' => true,
            'aliases'         => ['business type', 'type of business', 'business category'],
        ],
        [
            'key'             => 'faq_q72',
            'label'           => 'What is the legal structure of your business (LLC, corporation, sole proprietor, etc.)?',
            'category'        => 'Commercial – Business Information',
            'commercial_only' => true,
            'aliases'         => ['business structure', 'legal entity', 'LLC corporation'],
        ],
        [
            'key'             => 'faq_q73',
            'label'           => 'How long has your business been in operation?',
            'category'        => 'Commercial – Business Information',
            'commercial_only' => true,
            'aliases'         => ['business age', 'years in business', 'business history'],
        ],
        [
            'key'             => 'faq_q74',
            'label'           => 'How many employees will regularly work from this space?',
            'category'        => 'Commercial – Business Information',
            'commercial_only' => true,
            'aliases'         => ['number of employees', 'staff count', 'workers on site'],
        ],
        [
            'key'             => 'faq_q75',
            'label'           => 'Will you have customer or client foot traffic at the location?',
            'category'        => 'Commercial – Business Information',
            'commercial_only' => true,
            'aliases'         => ['customer foot traffic', 'client visits', 'retail foot traffic'],
        ],
        [
            'key'             => 'faq_q76',
            'label'           => 'Are there any special licenses or permits required for your business operations?',
            'category'        => 'Commercial – Business Information',
            'commercial_only' => true,
            'aliases'         => ['business license', 'permits required', 'regulatory approvals'],
        ],
        [
            'key'             => 'faq_q77',
            'label'           => 'Do you require signage on the exterior of the building?',
            'category'        => 'Commercial – Business Information',
            'commercial_only' => true,
            'aliases'         => ['exterior signage', 'building sign', 'business signage'],
        ],
        [
            'key'             => 'faq_q78',
            'label'           => 'Can you provide business financial statements or proof of revenue?',
            'category'        => 'Commercial – Business Information',
            'commercial_only' => true,
            'aliases'         => ['financial statements', 'business revenue proof', 'business financials'],
        ],

        // ── Category 13: Commercial – Space Requirements (Q79–85) ─────────────
        [
            'key'             => 'faq_q79',
            'label'           => 'What is the minimum net leasable square footage you require?',
            'category'        => 'Commercial – Space Requirements',
            'commercial_only' => true,
            'aliases'         => ['minimum commercial sqft', 'net leasable area', 'office size requirement'],
        ],
        [
            'key'             => 'faq_q80',
            'label'           => 'What specific layout or configuration do you need (open floor plan, private offices, warehouse, etc.)?',
            'category'        => 'Commercial – Space Requirements',
            'commercial_only' => true,
            'aliases'         => ['space layout', 'floor plan configuration', 'office layout'],
        ],
        [
            'key'             => 'faq_q81',
            'label'           => 'Do you require loading docks, drive-in access, or freight elevators?',
            'category'        => 'Commercial – Space Requirements',
            'commercial_only' => true,
            'aliases'         => ['loading dock', 'freight elevator', 'drive-in access'],
        ],
        [
            'key'             => 'faq_q82',
            'label'           => 'Do you need 3-phase power or other specialized electrical capacity?',
            'category'        => 'Commercial – Space Requirements',
            'commercial_only' => true,
            'aliases'         => ['3-phase power', 'specialized electrical', 'power capacity'],
        ],
        [
            'key'             => 'faq_q83',
            'label'           => 'Do you need a commercial kitchen or food preparation area?',
            'category'        => 'Commercial – Space Requirements',
            'commercial_only' => true,
            'aliases'         => ['commercial kitchen', 'food prep area', 'restaurant kitchen'],
        ],
        [
            'key'             => 'faq_q84',
            'label'           => 'How many parking spaces do you require for employees and customers?',
            'category'        => 'Commercial – Space Requirements',
            'commercial_only' => true,
            'aliases'         => ['commercial parking', 'employee parking', 'parking spaces needed'],
        ],
        [
            'key'             => 'faq_q85',
            'label'           => 'Do you require ADA-compliant restrooms or accessible facilities?',
            'category'        => 'Commercial – Space Requirements',
            'commercial_only' => true,
            'aliases'         => ['ADA compliance', 'accessible restrooms', 'disability accessibility commercial'],
        ],

        // ── Category 14: Commercial – Lease Terms (Q86–93) ───────────────────
        [
            'key'             => 'faq_q86',
            'label'           => 'What type of commercial lease are you seeking (NNN, gross, modified gross)?',
            'category'        => 'Commercial – Lease Terms',
            'commercial_only' => true,
            'aliases'         => ['NNN lease', 'triple net', 'gross lease type'],
        ],
        [
            'key'             => 'faq_q87',
            'label'           => 'What is your ideal commercial lease term length?',
            'category'        => 'Commercial – Lease Terms',
            'commercial_only' => true,
            'aliases'         => ['commercial lease length', 'business lease term', 'commercial contract duration'],
        ],
        [
            'key'             => 'faq_q88',
            'label'           => 'Do you require tenant improvement (TI) allowance from the landlord?',
            'category'        => 'Commercial – Lease Terms',
            'commercial_only' => true,
            'aliases'         => ['TI allowance', 'tenant improvement', 'build-out allowance'],
        ],
        [
            'key'             => 'faq_q89',
            'label'           => 'Are you looking for renewal options or expansion rights in the lease?',
            'category'        => 'Commercial – Lease Terms',
            'commercial_only' => true,
            'aliases'         => ['renewal option', 'expansion rights', 'lease extension option'],
        ],
        [
            'key'             => 'faq_q90',
            'label'           => 'Do you require a personal guarantee or are you seeking a corporate-only lease?',
            'category'        => 'Commercial – Lease Terms',
            'commercial_only' => true,
            'aliases'         => ['personal guarantee', 'corporate lease', 'business-only guarantee'],
        ],
        [
            'key'             => 'faq_q91',
            'label'           => 'Will you need to sublease or assign the lease in the future?',
            'category'        => 'Commercial – Lease Terms',
            'commercial_only' => true,
            'aliases'         => ['sublease rights', 'lease assignment', 'ability to sublease'],
        ],
        [
            'key'             => 'faq_q92',
            'label'           => 'What is your maximum monthly base rent budget for the commercial space?',
            'category'        => 'Commercial – Lease Terms',
            'commercial_only' => true,
            'aliases'         => ['commercial rent budget', 'max base rent', 'monthly rent limit commercial'],
        ],
        [
            'key'             => 'faq_q93',
            'label'           => 'Are there any other commercial lease conditions or requirements you would like the landlord to know?',
            'category'        => 'Commercial – Lease Terms',
            'commercial_only' => true,
            'aliases'         => ['other commercial conditions', 'additional lease requirements', 'extra commercial terms'],
        ],
    ],
];
