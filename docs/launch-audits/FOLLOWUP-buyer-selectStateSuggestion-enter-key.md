# Follow-up: Buyer `selectStateSuggestion()` Enter-key argument asymmetry

**Status:** Open — deliberately NOT fixed in commit `897b03474`
(`fix(offer-listing): restore county/state fallback controls`).

## Summary
In the Buyer offer-listing property-preferences fallback UI, the "Acceptable State"
input binds:

```blade
wire:keydown.enter.prevent="selectStateSuggestion"
```

which invokes the method with **no argument**. The Buyer component method has **no
default parameter**:

- `app/Http/Livewire/OfferListing/Buyer/BuyerOfferListing.php:1341`
  `public function selectStateSuggestion($suggestion)`

Pressing **Enter** in the state field (e.g. to accept a highlighted suggestion) can
therefore raise an `ArgumentCountError` (500).

The Tenant equivalent is safe — it defaults the param and resolves the highlighted
index itself:

- `app/Http/Livewire/OfferListing/Tenant/TenantOfferListing.php:2288`
  ```php
  public function selectStateSuggestion($suggestion = null)
  {
      if ($suggestion === null && $this->highlightedStateIndex >= 0) {
          $suggestion = $this->stateSuggestions[$this->highlightedStateIndex];
      }
      ...
  }
  ```

## Why it was left alone
- **Pre-existing**, not a restore regression: the Buyer Blade + method are byte-for-byte
  identical to the pre-9B original (verified against `5083246bb~1`). Workstream 2's
  scope was a faithful restore of the 9B county/state fallback UI only.
- Fixing it means editing a **component method**, which is outside the "restore the UI
  bindings" scope and would deviate from the verified original.

## Scope note (role symmetry)
The Buyer property-preferences partial is shared into the Buyer, Tenant, Landlord and
Seller parent listing components. When fixing, check `selectStateSuggestion()` in **all**
components that back this partial:
- `OfferListing/Buyer/BuyerOfferListing.php` + `BuyerOfferListingEdit.php`
- `OfferListing/Landlord/LandlordOfferListing.php` + `...Edit.php`
- `OfferListing/Seller/SellerOfferListing.php` + `...Edit.php`
(Tenant/`TenantOfferListing` already has the `= null` default.)

## Suggested fix (future commit)
Give the Buyer (and Landlord/Seller) `selectStateSuggestion()` a `= null` default and the
same highlighted-index resolution the Tenant version uses. Mirror the pattern already
present in `selectCountySuggestion($suggestion = null)`.
