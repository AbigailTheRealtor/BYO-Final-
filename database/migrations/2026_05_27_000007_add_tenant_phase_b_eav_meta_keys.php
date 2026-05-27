<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Property DNA Phase B — Tenant Tier 1 EAV Meta Keys (Documentation Record)
 *
 * Storage table: tenant_agent_auction_metas (EAV — key/value rows)
 * No schema column changes are needed. This migration serves as the authoritative
 * documentation record for the Phase B Tenant key set.
 *
 * PRE-FLIGHT COLLISION CHECK RESULTS (verified against TenantOfferListing.php
 * and TenantOfferListingEdit.php before implementation):
 *
 *   commute_destination_zip — No collision. SAFE.
 *   max_commute_minutes     — No collision. SAFE.
 *   commute_mode            — No collision. SAFE.
 *   credit_score_range      — No collision. Note: `credit_scroe_rating` (legacy
 *                             multi-select, typo in key name) is a separate,
 *                             unrelated key. `credit_score_range` is new. SAFE.
 *
 * Phase B Tenant Tier 1 keys added (T-01 and T-06):
 *   - commute_destination_zip  (T-01) String — commute origin ZIP for drive-time
 *   - max_commute_minutes      (T-01) Integer — max acceptable commute in minutes
 *   - commute_mode             (T-01) String — Drive / Transit / Walk / Bike / Remote
 *   - credit_score_range       (T-06) String — Excellent 750+ / Good 700-749 /
 *                                               Fair 650-699 / Below 650 /
 *                                               Prefer not to disclose
 *
 * Out of scope for Phase B (deferred to Phase D):
 *   - rental_purpose (T-02), move_in_budget_upfront (T-03),
 *     move_in_date_earliest / move_in_date_latest (T-04),
 *     accessibility_requirements (T-05), smoking_preference (T-07)
 *
 * Special access control note (T-06):
 *   credit_score_range must never gate listing access. It is a voluntary
 *   disclosure field used for agent matching assistance only.
 */
class AddTenantPhaseBEavMetaKeys extends Migration
{
    public function up(): void
    {
        // EAV table — no column schema changes required.
        // New meta keys are stored as rows (meta_key / meta_value) in
        // tenant_agent_auction_metas. This migration documents the
        // Phase B key set and confirms the collision check result above.
    }

    public function down(): void
    {
        // EAV rows are not schema objects; no rollback action needed.
        // To remove Phase B data rows, a separate data migration would be required.
    }
}
