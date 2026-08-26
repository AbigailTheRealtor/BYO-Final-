<?php

namespace App\Support\Listing;

/**
 * Option vocabularies the canonical Seller Sale Terms tab offers.
 *
 * Extracted from the tab's own @php block so that code which must VALIDATE an
 * answer reads the same list the tab OFFERED. MLS Quick Import needs exactly
 * that: it rejects a financing value outside the declared vocabulary before
 * storing it, and until now it did so against a hand-copied list of its own —
 * which is the duplication this whole change removes.
 *
 * The names are the stored values. They are matched verbatim elsewhere in the
 * product, so the spellings are load-bearing: "Seller Financing" not "Seller
 * Finance", "Exchange/Trade" not "Trade", "Non-Fungible Token (NFT)" in full.
 * Renaming one silently orphans every listing already stored under the old
 * spelling.
 */
final class SellerSaleTermsOptions
{
    /**
     * Financing / consideration methods a seller may say they will accept.
     *
     * @return list<array{name: string, description: string}>
     */
    public static function financing(): array
    {
        return [
        ['name' => 'Assumable', 'description' => 'Allows an existing mortgage to be assumed by a Buyer, subject to lender approval.'],
        ['name' => 'Cash', 'description' => 'Purchase is completed without financing, with the full price paid in cash.'],
        ['name' => 'Conventional', 'description' => 'Uses a traditional mortgage that meets standard underwriting guidelines.'],
        ['name' => 'FHA', 'description' => 'Uses a loan backed by the Federal Housing Administration.'],
        ['name' => 'Jumbo', 'description' => 'Uses a loan that exceeds conforming loan limits.'],
        ['name' => 'VA', 'description' => 'Uses a VA-backed loan available to eligible veterans and active-duty service members.'],
        ['name' => 'No-Doc', 'description' => 'Uses a loan requiring limited or no income documentation.'],
        ['name' => 'Non-QM', 'description' => 'Uses a Non-Qualified Mortgage that allows alternative income verification methods.'],
        ['name' => 'USDA', 'description' => 'Uses a USDA-backed loan for eligible rural properties and qualifying buyers.'],
        ['name' => 'Cryptocurrency', 'description' => 'Uses digital currency (e.g., Bitcoin or Ethereum) as full or partial consideration.'],
        ['name' => 'Exchange/Trade', 'description' => 'Includes another asset as part of the purchase consideration in a trade.'],
        ['name' => 'Lease Option', 'description' => 'Allows the property to be leased with an option to purchase later under pre-agreed terms.'],
        ['name' => 'Lease Purchase', 'description' => 'Allows the property to be leased now with a commitment to purchase later.'],
        ['name' => 'Non-Fungible Token (NFT)', 'description' => 'Uses a verified digital asset as full or partial consideration, subject to Seller approval.'],
        ['name' => 'Seller Financing', 'description' => 'Purchase price is financed in whole or in part directly by the Seller.'],
        ['name' => 'Other', 'description' => 'Uses an alternative financing or consideration method not listed above.'],
        ];
    }

    /**
     * Just the stored values, for validation.
     *
     * @return list<string>
     */
    public static function financingNames(): array
    {
        return array_column(self::financing(), 'name');
    }
}
