<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Property DNA Phase B — Landlord Tier 1 EAV Meta Keys (Documentation Record)
 *
 * Storage table: landlord_agent_auction_metas (EAV — key/value rows)
 * No schema column changes are needed. This migration serves as the authoritative
 * documentation record for the Phase B Landlord key set.
 *
 * PRE-FLIGHT COLLISION CHECK RESULTS (verified against LandlordOfferListing.php
 * and LandlordOfferListingEdit.php before implementation):
 *
 *   pet_policy            — COLLISION: already wired as property, load, and saveMeta
 *                           in both Landlord Livewire files. EXCLUDED from Phase B.
 *                           The existing Blade control in lease-terms.blade.php is
 *                           untouched. Phase B adds only the four sub-fields below.
 *
 *   available_date        — No collision. The existing key `lease_available_date` is
 *                           a separate, unrelated key. `available_date` is new. SAFE.
 *
 *   pet_max_weight_lbs    — No collision. SAFE.
 *   pet_species_allowed   — No collision. SAFE.
 *   pet_deposit_amount    — No collision. Note: `pet_deposit_fee_rent` is a separate
 *                           existing key. `pet_deposit_amount` is new. SAFE.
 *   pet_monthly_fee       — No collision. SAFE.
 *
 * Phase B Landlord Tier 1 keys added (L-02 and L-03 sub-fields):
 *   - available_date       (L-02) Date — property available date for move-in matching
 *   - pet_max_weight_lbs   (L-03) Integer — max allowable pet weight in pounds
 *   - pet_species_allowed  (L-03) JSON array — allowed species (Dog, Cat, Bird, etc.)
 *   - pet_deposit_amount   (L-03) Decimal — one-time pet deposit amount
 *   - pet_monthly_fee      (L-03) Decimal — monthly recurring pet fee
 *
 * Out of scope for Phase B (deferred to Phase D):
 *   - year_built (L-01), smoking_policy (L-04), security_deposit_amount (L-05),
 *     min_income_requirement (L-06), subletting_policy (L-07)
 */
class AddLandlordPhaseBEavMetaKeys extends Migration
{
    public function up(): void
    {
        // EAV table — no column schema changes required.
        // New meta keys are stored as rows (meta_key / meta_value) in
        // landlord_agent_auction_metas. This migration documents the
        // Phase B key set and confirms the collision check result above.
    }

    public function down(): void
    {
        // EAV rows are not schema objects; no rollback action needed.
        // To remove Phase B data rows, a separate data migration would be required.
    }
}
