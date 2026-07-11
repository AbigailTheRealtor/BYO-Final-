<?php

namespace App\Http\Livewire\OfferListing\Concerns;

use App\Services\Pets\PetFeeNormalizer;

/**
 * #2 Part B — canonical pet-fee hydration + persistence for the Landlord offer-listing
 * create and edit components.
 *
 * Shared so the two components cannot drift apart, which is exactly how #15 (the garage
 * gate) and #14 (the property-style reveal) came to be broken on one side only.
 *
 * The five retired legacy fee fields (pet_deposit_amount, pet_monthly_fee, pet_rent,
 * pet_fee, pet_deposit_fee_rent) are READ here and never written. saveMeta() is an
 * upsert, so a key we do not write keeps its stored historical value.
 */
trait HasCanonicalPetFee
{
    /**
     * Seed the canonical pet-fee inputs when opening an existing record.
     *
     * A legacy-only record has no pet_fee_type, so we derive a meaningful canonical view
     * from its stored legacy values — otherwise the redesigned form would open blank and
     * a save would look like the landlord had cleared their fee. This only seeds the
     * FORM; it mutates nothing in storage.
     *
     * Must be called AFTER the legacy pet properties have been hydrated.
     */
    protected function hydrateCanonicalPetFee($auction): void
    {
        $this->pet_fee_type   = $auction->get->pet_fee_type ?? '';
        $this->pet_fee_amount = $auction->get->pet_fee_amount ?? '';
        $this->pet_fee_other  = $auction->get->pet_fee_other ?? '';

        // Canonical values already present → they win outright.
        if (trim((string) $this->pet_fee_type) !== '') {
            return;
        }

        $normalized = (new PetFeeNormalizer())->normalize([
            PetFeeNormalizer::KEY_TYPE   => $this->pet_fee_type,
            PetFeeNormalizer::KEY_AMOUNT => $this->pet_fee_amount,
            PetFeeNormalizer::KEY_OTHER  => $this->pet_fee_other,
            'pet_deposit_amount'         => $this->pet_deposit_amount,
            'pet_monthly_fee'            => $this->pet_monthly_fee,
            'pet_rent'                   => $this->pet_rent,
            'pet_fee'                    => $this->pet_fee,
            'pet_deposit_fee_rent'       => $this->pet_deposit_fee_rent,
        ]);

        if ($normalized['type'] === null) {
            return;
        }

        $this->pet_fee_type   = $normalized['type'];
        $this->pet_fee_amount = $normalized['amount'] !== null ? (string) $normalized['amount'] : '';
        $this->pet_fee_other  = $normalized['other_text'] ?? '';
    }

    /**
     * The canonical pet-fee values actually persisted.
     *
     *   No Pet Fee → amount and explanatory text are CLEARED in the submitted values.
     *   Other      → explanatory text is kept; the amount is optional.
     *   otherwise  → the amount is kept; no explanatory text applies.
     *
     * @return array{0: string, 1: string} [amount, other_text]
     */
    protected function canonicalPetFeeValues(): array
    {
        $type = trim((string) $this->pet_fee_type);

        if ($type === '' || $type === PetFeeNormalizer::TYPE_NONE) {
            return ['', ''];
        }

        $amount = $this->stripCommas((string) $this->pet_fee_amount);

        if ($type === PetFeeNormalizer::TYPE_OTHER) {
            return [$amount, trim((string) $this->pet_fee_other)];
        }

        return [$amount, ''];
    }
}
