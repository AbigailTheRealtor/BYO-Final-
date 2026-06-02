# Listing-to-Offer Field Map

**Version date:** 2026-06-02  
**Audit scope:** All field names in this document were verified against the codebase on 2026-06-02.  
Field names are drawn exclusively from the four FieldMap source files (`SellerFieldMap`, `BuyerFieldMap`, `LandlordFieldMap`, `TenantFieldMap`) and confirmed against actual bid model `saveMeta()` calls in the four agent auction bid Livewire components.

---

## Storage Target Key

| Code | Meaning |
|------|---------|
| `listing_ref` | Value is read directly from the linked listing at render time; it is **not** written to the offer/bid record. |
| `offer_meta` | EAV meta key already present and actively written on the bid record (confirmed via `saveMeta()` call). |
| `new_offer_meta` | Field is semantically offer-side but currently has **no** storage on any bid/offer table; a new meta key would be required. |

---

## Flow Context

All four flows are **agent hiring auctions**: a client (seller, buyer, landlord, or tenant) posts a listing and agents submit bids proposing their services and compensation. The offer is the **agent's bid**, not a property purchase offer. As a result:

- Property, location, financial, and criteria fields from the listing are **context** shown to the agent but are not stored on the bid record (`listing_ref`).
- Services and broker compensation fields are the **substance** of the bid and are stored on the bid record.
- Agent profile fields (bio, marketing plan, credentials) are bid-only fields that appear in bid forms but are **absent from all four FieldMaps** — see Section 6 (Unmapped Existing Form Fields).

---

## Section 1 — Seller Agent Auction Flow

**Listing source:** `app/Exports/ListingFieldMaps/SellerFieldMap.php`  
**Bid storage:** `seller_agent_auction_bid_metas` (EAV) + native columns on `seller_agent_auction_bids`  
**Bid Livewire component:** `app/Http/Livewire/Seller/SellerAgentAuctionBid.php`

| Listing Field | Offer Field | Auto-Populated | Editable | Storage Target | Notes |
|---|---|---|---|---|---|
| **— Listing Details —** | | | | | |
| `service_type` | — | No | N/A | `listing_ref` | Administrative; determines which form sections appear. |
| `listing_status` | — | No | N/A | `listing_ref` | Operational status only. |
| `auction_type` | — | No | N/A | `listing_ref` | Listing type; shown as context. |
| `working_with_agent` | — | No | N/A | `listing_ref` | Client preference field. |
| `current_status` | — | No | N/A | `listing_ref` | Property occupancy/sale status. |
| `listing_date` | — | No | N/A | `listing_ref` | Operational date field. |
| `desired_agent_hire_date` | — | No | N/A | `listing_ref` | Client target date. |
| `expiration_date` | — | No | N/A | `listing_ref` | Auction operational field. |
| `auction_time` | — | No | N/A | `listing_ref` | Auction operational field. |
| **— Location —** | | | | | |
| `address` | — | No | N/A | `listing_ref` | Property address displayed for agent context. |
| `property_city` | — | No | N/A | `listing_ref` | |
| `property_county` | — | No | N/A | `listing_ref` | |
| `property_state` | — | No | N/A | `listing_ref` | |
| `property_zip` | — | No | N/A | `listing_ref` | |
| `cities` | — | No | N/A | `listing_ref` | Acceptable cities for buyer/criteria context. |
| `counties` | — | No | N/A | `listing_ref` | |
| `zipCodes` | — | No | N/A | `listing_ref` | |
| **— Property Details —** | | | | | |
| `property_type` | — | No | N/A | `listing_ref` | Passed to bid form as display context only. |
| `property_items` | — | No | N/A | `listing_ref` | `otherPairs` companion: `other_property_items`. |
| `leasing_space` | — | No | N/A | `listing_ref` | |
| `condition_prop` | — | No | N/A | `listing_ref` | `otherPairs` companion: `other_property_condition`. |
| `bedrooms` | — | No | N/A | `listing_ref` | `otherPairs` companion: `other_bedrooms`. |
| `bathrooms` | — | No | N/A | `listing_ref` | `otherPairs` companion: `other_bathrooms`. |
| `minimum_heated_square` | — | No | N/A | `listing_ref` | |
| `minimum_leaseable` | — | No | N/A | `listing_ref` | |
| `min_acreage` | — | No | N/A | `listing_ref` | |
| `total_acreage` | — | No | N/A | `listing_ref` | |
| `appliances` | — | No | N/A | `listing_ref` | `otherPairs` companion: `other_appliances`. |
| `property_criteria` | — | No | N/A | `listing_ref` | |
| `unit_size` | — | No | N/A | `listing_ref` | `otherPairs` companion: `unit_size_other`. |
| `preferance_details` | — | No | N/A | `listing_ref` | Typo in source retained as-is. |
| **— Income & Investment Metrics —** | | | | | |
| `minimum_annual_net_income` | — | No | N/A | `listing_ref` | |
| `minimum_cap_rate` | — | No | N/A | `listing_ref` | |
| `assets` | — | No | N/A | `listing_ref` | `otherPairs` companion: `assets_other`. |
| **— Additional Property Preferences —** | | | | | |
| `tenant_require` | — | No | N/A | `listing_ref` | |
| `carport_needed` | — | No | N/A | `listing_ref` | `otherPairs` companion: `other_carport_needed`. |
| `garage_needed` | — | No | N/A | `listing_ref` | `otherPairs` companion: `other_garage_needed`. |
| `garage_parking_spaces` | — | No | N/A | `listing_ref` | |
| `garage_parking_spaces_option` | — | No | N/A | `listing_ref` | `otherPairs` companion: `other_parking_space_wrapper`. |
| `pool_needed` | — | No | N/A | `listing_ref` | |
| `pool_type` | — | No | N/A | `listing_ref` | |
| `view_preference` | — | No | N/A | `listing_ref` | `otherPairs` companion: `other_preferences`. |
| `real_estate_purchase` | — | No | N/A | `listing_ref` | |
| `number_of_unit` | — | No | N/A | `listing_ref` | `otherPairs` companion: `number_of_unit_other`. |
| `unit_number` | — | No | N/A | `listing_ref` | |
| `unit_buildings` | — | No | N/A | `listing_ref` | |
| `leasing_55_plus` | — | No | N/A | `listing_ref` | |
| `non_negotiable_amenities` | — | No | N/A | `listing_ref` | `otherPairs` companion: `other_non_negotiable_amenities`. |
| **— Pets —** | | | | | |
| `pets` | — | No | N/A | `listing_ref` | |
| `number_of_pets` | — | No | N/A | `listing_ref` | |
| `breed_of_pets` | — | No | N/A | `listing_ref` | |
| `type_of_pets` | — | No | N/A | `listing_ref` | |
| `weight_of_pets` | — | No | N/A | `listing_ref` | |
| `number_occupant` | — | No | N/A | `listing_ref` | |
| **— Sale Terms —** | | | | | |
| `sale_provision` | — | No | N/A | `listing_ref` | `otherPairs` companion: `sale_provision_other`. |
| `sale_provision_assignment` | — | No | N/A | `listing_ref` | |
| `assignment_fee_type` | — | No | N/A | `listing_ref` | |
| `assignment_fee_amount` | — | No | N/A | `listing_ref` | |
| `buyer_sell_contract` | — | No | N/A | `listing_ref` | |
| **— Budget & Financing —** | | | | | |
| `maximum_budget` | — | No | N/A | `listing_ref` | |
| `offered_financing` | — | No | N/A | `listing_ref` | `otherPairs` companion: `other_financing`. |
| `cash_budget` | — | No | N/A | `listing_ref` | |
| `pre_approved` | — | No | N/A | `listing_ref` | |
| `pre_approval_amount` | — | No | N/A | `listing_ref` | |
| `purchase_price` | — | No | N/A | `listing_ref` | |
| `budget` | — | No | N/A | `listing_ref` | |
| `monthly_income` | — | No | N/A | `listing_ref` | |
| `credit_scroe_rating` | — | No | N/A | `listing_ref` | Typo in source retained as-is. |
| `prior_eviction` | — | No | N/A | `listing_ref` | |
| `eviction_explanation` | — | No | N/A | `listing_ref` | |
| `prior_felony` | — | No | N/A | `listing_ref` | |
| `prior_felony_explanation` | — | No | N/A | `listing_ref` | |
| **— Down Payment —** | | | | | |
| `down_payment_type` | — | No | N/A | `listing_ref` | |
| `down_payment_amount` | — | No | N/A | `listing_ref` | |
| **— Seller Financing —** | | | | | |
| `seller_financing_type` | — | No | N/A | `listing_ref` | |
| `seller_financing_amount` | — | No | N/A | `listing_ref` | |
| `interest_rate` | — | No | N/A | `listing_ref` | |
| `loan_duration` | — | No | N/A | `listing_ref` | |
| `prepayment_penalty` | — | No | N/A | `listing_ref` | |
| `prepayment_penalty_amount` | — | No | N/A | `listing_ref` | |
| `balloon_payment_amount` | — | No | N/A | `listing_ref` | |
| `balloon_payment_date` | — | No | N/A | `listing_ref` | |
| **— Assumable Loan —** | | | | | |
| `assumable_terms` | — | No | N/A | `listing_ref` | |
| `max_assumable_rate` | — | No | N/A | `listing_ref` | |
| `assumable_monthly_escrow` | — | No | N/A | `listing_ref` | |
| `assumable_loan_term_remaining` | — | No | N/A | `listing_ref` | |
| `assumable_loan_origination_date` | — | No | N/A | `listing_ref` | |
| `assumable_loan_servicer` | — | No | N/A | `listing_ref` | |
| `assumable_fee_type` | — | No | N/A | `listing_ref` | |
| `assumable_fee_amount` | — | No | N/A | `listing_ref` | |
| `assumable_occupancy_requirement` | — | No | N/A | `listing_ref` | `otherPairs` companion: `assumable_occupancy_other`. |
| **— Gap / Additional Payments —** | | | | | |
| `max_monthly_payment` | — | No | N/A | `listing_ref` | |
| `gap_payment_type` | — | No | N/A | `listing_ref` | |
| `gap_payment_amount` | — | No | N/A | `listing_ref` | |
| `additional_cash` | — | No | N/A | `listing_ref` | |
| **— Exchange / Trade —** | | | | | |
| `exchange_item` | — | No | N/A | `listing_ref` | `otherPairs` companion: `other_exchange_item`. |
| `exchange_item_value` | — | No | N/A | `listing_ref` | |
| `exchange_item_condition` | — | No | N/A | `listing_ref` | |
| `value_determination` | — | No | N/A | `listing_ref` | |
| `exchange_transfer_method` | — | No | N/A | `listing_ref` | |
| `exchange_liens_disclosure` | — | No | N/A | `listing_ref` | |
| `exchange_liens_details` | — | No | N/A | `listing_ref` | |
| `exchange_inspection_rights` | — | No | N/A | `listing_ref` | |
| **— Lease Option —** | | | | | |
| `lease_option_price` | — | No | N/A | `listing_ref` | |
| `lease_option_terms` | — | No | N/A | `listing_ref` | |
| `lease_option_duration` | — | No | N/A | `listing_ref` | |
| `lease_option_payment` | — | No | N/A | `listing_ref` | |
| `lease_option_conditions` | — | No | N/A | `listing_ref` | |
| `has_option_fee` | — | No | N/A | `listing_ref` | |
| `option_fee_amount` | — | No | N/A | `listing_ref` | |
| `seller_lease_option_fee_credit` | — | No | N/A | `listing_ref` | Seller-prefixed key; no corresponding bid field. |
| `seller_lease_option_fee_credit_percent` | — | No | N/A | `listing_ref` | |
| `seller_lease_option_maintenance` | — | No | N/A | `listing_ref` | |
| `seller_lease_option_extension_terms` | — | No | N/A | `listing_ref` | |
| **— Lease Purchase —** | | | | | |
| `lease_purchase_price` | — | No | N/A | `listing_ref` | |
| `lease_purchase_terms` | — | No | N/A | `listing_ref` | |
| `lease_purchase_duration` | — | No | N/A | `listing_ref` | |
| `lease_purchase_payment` | — | No | N/A | `listing_ref` | |
| `lease_purchase_conditions` | — | No | N/A | `listing_ref` | |
| `seller_lease_purchase_rent_credit` | — | No | N/A | `listing_ref` | |
| `seller_lease_purchase_rent_credit_type` | — | No | N/A | `listing_ref` | |
| `seller_lease_purchase_rent_credit_amount` | — | No | N/A | `listing_ref` | |
| `seller_lease_purchase_deposit` | — | No | N/A | `listing_ref` | |
| `seller_lease_purchase_maintenance` | — | No | N/A | `listing_ref` | |
| `seller_lease_purchase_extension_terms` | — | No | N/A | `listing_ref` | |
| `lease_purchase_option_fee` | — | No | N/A | `listing_ref` | |
| `lease_purchase_option_fee_amount` | — | No | N/A | `listing_ref` | |
| **— Cryptocurrency —** | | | | | |
| `cryptocurrency_type` | — | No | N/A | `listing_ref` | |
| `crypto_percentage` | — | No | N/A | `listing_ref` | |
| `cash_percentage_crypto` | — | No | N/A | `listing_ref` | |
| **— NFT —** | | | | | |
| `nft_description` | — | No | N/A | `listing_ref` | |
| `nft_percentage` | — | No | N/A | `listing_ref` | |
| `cash_percentage_nft` | — | No | N/A | `listing_ref` | |
| **— Lease Details —** | | | | | |
| `lease_for` | — | No | N/A | `listing_ref` | `otherPairs` companion: `other_lease_for`. |
| `lease_by` | — | No | N/A | `listing_ref` | |
| `lease_date` | — | No | N/A | `listing_ref` | |
| **— Services —** | | | | | |
| `services` | `services` | No | Yes | `offer_meta` | Stored as JSON array of selected service keys. |
| `custom_services` | `custom_services` | No | Yes | `new_offer_meta` | `saveMeta('custom_services')` call is commented out in bid components; no current bid storage. |
| `include_marketing_fee` | — | No | N/A | `listing_ref` | Client's budget figure; agents propose via `services` JSON. |
| `email_marketing_fee` | — | No | N/A | `listing_ref` | |
| `email_notifications_fee` | — | No | N/A | `listing_ref` | |
| `launch_ads` | — | No | N/A | `listing_ref` | |
| `launch_ads_fee` | — | No | N/A | `listing_ref` | |
| `market_groups` | — | No | N/A | `listing_ref` | |
| `market_groups_fee` | — | No | N/A | `listing_ref` | |
| `marketing_materials_fee` | — | No | N/A | `listing_ref` | |
| `mls_filter_fee` | — | No | N/A | `listing_ref` | |
| `off_market_search_fee` | — | No | N/A | `listing_ref` | |
| `promote_social` | — | No | N/A | `listing_ref` | |
| `promote_social_fee` | — | No | N/A | `listing_ref` | |
| `neighborhood_marketing_fee` | — | No | N/A | `listing_ref` | |
| `neighborhood_materials_fee` | — | No | N/A | `listing_ref` | |
| `flat_fee_services` | — | No | N/A | `listing_ref` | |
| `schedule_showings` | — | No | N/A | `listing_ref` | |
| `schedule_showings_fee` | — | No | N/A | `listing_ref` | |
| `number_of_showings_to_schedule` | — | No | N/A | `listing_ref` | |
| `attend_showings` | — | No | N/A | `listing_ref` | |
| `number_of_showings_to_attend` | — | No | N/A | `listing_ref` | |
| `attend_showings_fee` | — | No | N/A | `listing_ref` | |
| `provide_virtual_tours` | — | No | N/A | `listing_ref` | |
| `number_of_virtual_tours` | — | No | N/A | `listing_ref` | |
| `virtual_tours_fee` | — | No | N/A | `listing_ref` | |
| `assist_application` | — | No | N/A | `listing_ref` | |
| `assist_application_fee` | — | No | N/A | `listing_ref` | |
| `collect_documents` | — | No | N/A | `listing_ref` | |
| `collect_documents_fee` | — | No | N/A | `listing_ref` | |
| `submit_application` | — | No | N/A | `listing_ref` | |
| `submit_application_fee` | — | No | N/A | `listing_ref` | |
| `review_lease` | — | No | N/A | `listing_ref` | |
| `review_lease_fee` | — | No | N/A | `listing_ref` | |
| `provide_lease_form` | — | No | N/A | `listing_ref` | |
| `provide_lease_form_fee` | — | No | N/A | `listing_ref` | |
| `coordinate_signing` | — | No | N/A | `listing_ref` | |
| `coordinate_signing_fee` | — | No | N/A | `listing_ref` | |
| `prepare_application_fee` | — | No | N/A | `listing_ref` | |
| `move_in_inspection_fee` | — | No | N/A | `listing_ref` | |
| `moving_resources_fee` | — | No | N/A | `listing_ref` | |
| `short_term_housing_fee` | — | No | N/A | `listing_ref` | |
| `rental_rights_fee` | — | No | N/A | `listing_ref` | |
| `lease_advice_fee` | — | No | N/A | `listing_ref` | |
| `neighborhood_insights_fee` | — | No | N/A | `listing_ref` | |
| `list_criteria` | — | No | N/A | `listing_ref` | |
| `list_criteria_fee` | — | No | N/A | `listing_ref` | |
| `total_marketing_fee` | `total_marketing_fee` | No | Yes | `new_offer_meta` | Not confirmed in SellerAgentAuctionBid saveMeta; confirmed only in Tenant bid. |
| `total_flat_fee` | `total_flat_fee` | No | Yes | `new_offer_meta` | Same as above. |
| `staging_duration` | — | No | N/A | `listing_ref` | |
| `open_house_count` | — | No | N/A | `listing_ref` | |
| `virtual_showings_count` | — | No | N/A | `listing_ref` | |
| **— Broker Compensation & Agency Agreement —** | | | | | |
| `commission_structure` | `commission_structure` | No | Yes | `offer_meta` | Core bid field; saved in SellerAgentAuctionBid. |
| `commission_structure_type` | `commission_structure_type` | No | Yes | `offer_meta` | |
| `commission_structure_type_fee_flat` | `commission_structure_type_fee_flat` | No | Yes | `offer_meta` | |
| `commission_structure_type_fee_percentage` | `commission_structure_type_fee_percentage` | No | Yes | `offer_meta` | |
| `commission_structure_type_fee_flat_combo` | `commission_structure_type_fee_flat_combo` | No | Yes | `offer_meta` | |
| `commission_structure_type_fee_percentage_combo` | `commission_structure_type_fee_percentage_combo` | No | Yes | `offer_meta` | |
| `commission_structure_type_fee_other` | `commission_structure_type_fee_other` | No | Yes | `offer_meta` | |
| **— Additional Details —** | | | | | |
| `additional_details` | `additional_details` | No | Yes | `offer_meta` | Agent's own additional notes; stored in bid meta. |
| `video_link` | — | No | N/A | `listing_ref` | Listing's marketing video; not copied to bid. |
| **— Meeting Details —** | | | | | |
| `person_meeting` | — | No | N/A | `listing_ref` | Client scheduling contact. |
| `meeting_details_first_name` | — | No | N/A | `listing_ref` | |
| `meeting_details_last_name` | — | No | N/A | `listing_ref` | |
| `meeting_details_phone` | — | No | N/A | `listing_ref` | |
| `meeting_details_email` | — | No | N/A | `listing_ref` | |
| `meeting_details_meeting_date` | — | No | N/A | `listing_ref` | |
| `meeting_details_meeting_time` | — | No | N/A | `listing_ref` | |
| `meeting_details_time_zone` | — | No | N/A | `listing_ref` | |
| `meeting_details_instructions` | — | No | N/A | `listing_ref` | |
| `meeting_details_additional_details` | — | No | N/A | `listing_ref` | |
| `service_completion_date` | — | No | N/A | `listing_ref` | |
| `service_completion_time` | — | No | N/A | `listing_ref` | |
| `service_time_zone` | — | No | N/A | `listing_ref` | |
| **— Contact Information —** | | | | | |
| `first_name` | — | No | N/A | `listing_ref` | Client contact info. |
| `last_name` | — | No | N/A | `listing_ref` | |
| `phone_number` | — | No | N/A | `listing_ref` | |
| `email` | — | No | N/A | `listing_ref` | |

---

## Section 2 — Buyer Agent Auction Flow

**Listing source:** `app/Exports/ListingFieldMaps/BuyerFieldMap.php`  
**Bid storage:** `buyer_agent_auction_bid_metas` (EAV) + native columns on `buyer_agent_auction_bids`  
**Bid Livewire component:** `app/Http/Livewire/Buyer/BuyerAgentAuctionBid.php`

| Listing Field | Offer Field | Auto-Populated | Editable | Storage Target | Notes |
|---|---|---|---|---|---|
| **— Listing Details —** | | | | | |
| `service_type` | — | No | N/A | `listing_ref` | |
| `listing_status` | — | No | N/A | `listing_ref` | |
| `auction_type` | — | No | N/A | `listing_ref` | |
| `working_with_agent` | — | No | N/A | `listing_ref` | |
| `listing_date` | — | No | N/A | `listing_ref` | |
| `desired_agent_hire_date` | — | No | N/A | `listing_ref` | |
| `expiration_date` | — | No | N/A | `listing_ref` | |
| `auction_time` | — | No | N/A | `listing_ref` | |
| **— Location —** | | | | | |
| `address` | — | No | N/A | `listing_ref` | |
| `cities` | — | No | N/A | `listing_ref` | `otherPairs` companion: none in BuyerFieldMap. |
| `counties` | — | No | N/A | `listing_ref` | |
| `state` | — | No | N/A | `listing_ref` | Note: key is `state`, not `property_state` (differs from Seller). |
| **— Property Details —** | | | | | |
| `property_type` | — | No | N/A | `listing_ref` | |
| `property_items` | — | No | N/A | `listing_ref` | `otherPairs` companion: `other_property_items`. |
| `leasing_space` | — | No | N/A | `listing_ref` | |
| `condition_prop` | — | No | N/A | `listing_ref` | `otherPairs` companion: `other_property_condition`. |
| `condition_prop_buyer` | — | No | N/A | `listing_ref` | Buyer-specific property condition field. `otherPairs` companion: `other_property_condition`. |
| `bedrooms` | — | No | N/A | `listing_ref` | `otherPairs` companion: `other_bedrooms`. |
| `bathrooms` | — | No | N/A | `listing_ref` | `otherPairs` companion: `other_bathrooms`. |
| `minimum_heated_square` | — | No | N/A | `listing_ref` | |
| `minimum_leaseable` | — | No | N/A | `listing_ref` | |
| `min_acreage` | — | No | N/A | `listing_ref` | |
| `total_acreage` | — | No | N/A | `listing_ref` | |
| `property_criteria` | — | No | N/A | `listing_ref` | |
| `unit_size` | — | No | N/A | `listing_ref` | `otherPairs` companion: `unit_size_other`. |
| `preferance_details` | — | No | N/A | `listing_ref` | |
| **— Income & Investment Metrics —** | | | | | |
| `minimum_annual_net_income` | — | No | N/A | `listing_ref` | |
| `minimum_cap_rate` | — | No | N/A | `listing_ref` | |
| `assets` | — | No | N/A | `listing_ref` | `otherPairs` companion: `assets_other`. |
| **— Additional Property Preferences —** | | | | | |
| `tenant_require` | — | No | N/A | `listing_ref` | |
| `carport_needed` | — | No | N/A | `listing_ref` | `otherPairs` companion: `other_carport_needed`. |
| `garage_needed` | — | No | N/A | `listing_ref` | `otherPairs` companion: `other_garage_needed`. |
| `garage_parking_spaces` | — | No | N/A | `listing_ref` | |
| `garage_parking_spaces_option` | — | No | N/A | `listing_ref` | `otherPairs` companion: `other_parking_space_wrapper`. |
| `pool_needed` | — | No | N/A | `listing_ref` | |
| `pool_type` | — | No | N/A | `listing_ref` | |
| `view_preference` | — | No | N/A | `listing_ref` | `otherPairs` companion: `other_preferences`. |
| `real_estate_purchase` | — | No | N/A | `listing_ref` | |
| `number_of_unit` | — | No | N/A | `listing_ref` | `otherPairs` companion: `number_of_unit_other`. |
| `number_of_unit_type` | — | No | N/A | `listing_ref` | `otherPairs` companion: `number_of_unit_type_other`. |
| `leasing_55_plus` | — | No | N/A | `listing_ref` | |
| `non_negotiable_amenities` | — | No | N/A | `listing_ref` | `otherPairs` companion: `other_non_negotiable_amenities`. |
| **— Pets —** | | | | | |
| `pets` | — | No | N/A | `listing_ref` | |
| `number_of_pets` | — | No | N/A | `listing_ref` | |
| `breed_of_pets` | — | No | N/A | `listing_ref` | |
| `type_of_pets` | — | No | N/A | `listing_ref` | |
| `weight_of_pets` | — | No | N/A | `listing_ref` | |
| `monthly_income` | — | No | N/A | `listing_ref` | Placed in Pets section in BuyerFieldMap. |
| `number_occupant` | — | No | N/A | `listing_ref` | |
| **— Sale Terms —** | | | | | |
| `sale_provision` | — | No | N/A | `listing_ref` | `otherPairs` companion: `sale_provision_other`. |
| `sale_provision_assignment` | — | No | N/A | `listing_ref` | |
| `assignment_fee_type` | — | No | N/A | `listing_ref` | |
| `assignment_fee_amount` | — | No | N/A | `listing_ref` | |
| `buyer_sell_contract` | — | No | N/A | `listing_ref` | |
| **— Additional Purchase Terms —** | | | | | |
| `earnest_money_type` | — | No | N/A | `listing_ref` | Buyer-specific purchase term. |
| `earnest_money_amount` | — | No | N/A | `listing_ref` | |
| `earnest_money_timing` | — | No | N/A | `listing_ref` | |
| `due_diligence_yn` | — | No | N/A | `listing_ref` | |
| `inspection_period_days` | — | No | N/A | `listing_ref` | `otherPairs` companion: `inspection_period_other`. |
| `inspection_period_other` | — | No | N/A | `listing_ref` | |
| `inspection_contingency_buyer` | — | No | N/A | `listing_ref` | |
| `appraisal_contingency_buyer` | — | No | N/A | `listing_ref` | |
| `appraisal_contingency_days` | — | No | N/A | `listing_ref` | |
| `financing_contingency_buyer` | — | No | N/A | `listing_ref` | |
| `financing_contingency_period` | — | No | N/A | `listing_ref` | |
| `home_sale_contingency` | — | No | N/A | `listing_ref` | |
| `home_sale_contingency_address` | — | No | N/A | `listing_ref` | |
| `home_sale_contingency_date` | — | No | N/A | `listing_ref` | |
| `home_sale_contingency_under_contract` | — | No | N/A | `listing_ref` | |
| `home_sale_contingency_details` | — | No | N/A | `listing_ref` | |
| `seller_contribution` | — | No | N/A | `listing_ref` | |
| `seller_contribution_details` | — | No | N/A | `listing_ref` | |
| `possession_preference` | — | No | N/A | `listing_ref` | `otherPairs` companion: `possession_preference_other`. |
| `possession_preference_other` | — | No | N/A | `listing_ref` | |
| `possession_details` | — | No | N/A | `listing_ref` | |
| `home_warranty_requested` | — | No | N/A | `listing_ref` | |
| `home_warranty_details` | — | No | N/A | `listing_ref` | |
| `as_is_purchase` | — | No | N/A | `listing_ref` | |
| `property_inclusions` | — | No | N/A | `listing_ref` | |
| `property_exclusions` | — | No | N/A | `listing_ref` | |
| `closing_cost_responsibility` | — | No | N/A | `listing_ref` | |
| `additional_purchase_terms` | — | No | N/A | `listing_ref` | |
| **— Budget & Financing —** | | | | | |
| `maximum_budget` | — | No | N/A | `listing_ref` | |
| `offered_financing` | — | No | N/A | `listing_ref` | `otherPairs` companion: `other_financing`. |
| `cash_budget` | — | No | N/A | `listing_ref` | |
| `pre_approved` | — | No | N/A | `listing_ref` | |
| `pre_approval_amount` | — | No | N/A | `listing_ref` | |
| `purchase_price` | — | No | N/A | `listing_ref` | |
| `budget` | — | No | N/A | `listing_ref` | |
| `credit_scroe_rating` | — | No | N/A | `listing_ref` | |
| `prior_eviction` | — | No | N/A | `listing_ref` | |
| `eviction_explanation` | — | No | N/A | `listing_ref` | |
| `prior_felony` | — | No | N/A | `listing_ref` | |
| `prior_felony_explanation` | — | No | N/A | `listing_ref` | |
| **— Down Payment —** | | | | | |
| `down_payment_type` | — | No | N/A | `listing_ref` | BuyerAgentAuctionBid declares this as a public property but no confirmed saveMeta call. |
| `down_payment_amount` | — | No | N/A | `listing_ref` | |
| **— Seller Financing —** | | | | | |
| `seller_financing_type` | — | No | N/A | `listing_ref` | Declared in bid component but not confirmed saved to meta. |
| `seller_financing_amount` | — | No | N/A | `listing_ref` | |
| `interest_rate` | — | No | N/A | `listing_ref` | |
| `loan_duration` | — | No | N/A | `listing_ref` | |
| `prepayment_penalty` | — | No | N/A | `listing_ref` | |
| `prepayment_penalty_amount` | — | No | N/A | `listing_ref` | |
| `balloon_payment` | — | No | N/A | `listing_ref` | |
| `balloon_payment_amount` | — | No | N/A | `listing_ref` | |
| `balloon_payment_date` | — | No | N/A | `listing_ref` | |
| `seller_amortization_type` | — | No | N/A | `listing_ref` | `otherPairs` companion: `seller_amortization_other`. |
| `seller_payment_frequency` | — | No | N/A | `listing_ref` | `otherPairs` companion: `seller_payment_frequency_other`. |
| `seller_late_fee_amount` | — | No | N/A | `listing_ref` | |
| **— Assumable Loan —** | | | | | |
| `assumable_terms` | — | No | N/A | `listing_ref` | |
| `max_assumable_rate` | — | No | N/A | `listing_ref` | |
| **— Gap / Additional Payments —** | | | | | |
| `max_monthly_payment` | — | No | N/A | `listing_ref` | |
| `gap_payment_type` | — | No | N/A | `listing_ref` | Declared in bid component; no confirmed saveMeta. |
| `gap_payment_amount` | — | No | N/A | `listing_ref` | |
| `additional_cash` | — | No | N/A | `listing_ref` | |
| **— Exchange / Trade —** | | | | | |
| `exchange_item` | — | No | N/A | `listing_ref` | `otherPairs` companion: `other_exchange_item`. |
| `exchange_item_value` | — | No | N/A | `listing_ref` | |
| `exchange_item_condition` | — | No | N/A | `listing_ref` | |
| `value_determination` | — | No | N/A | `listing_ref` | |
| `exchange_transfer_method` | — | No | N/A | `listing_ref` | |
| `exchange_liens` | — | No | N/A | `listing_ref` | Key differs from Seller (`exchange_liens_disclosure`). |
| `exchange_liens_details` | — | No | N/A | `listing_ref` | |
| `exchange_inspection_rights` | — | No | N/A | `listing_ref` | |
| **— Lease Option —** | | | | | |
| `interested_lease_option` | `interested_lease_option` | No | Yes | `offer_meta` | Saved in BuyerAgentAuctionBid: whether agent offers lease-option services. |
| `interested_lease_option_agreement` | `interested_lease_option_agreement` | No | Yes | `offer_meta` | |
| `lease_option_price` | — | No | N/A | `listing_ref` | |
| `lease_option_terms` | — | No | N/A | `listing_ref` | |
| `lease_option_duration` | — | No | N/A | `listing_ref` | |
| `lease_option_payment` | — | No | N/A | `listing_ref` | |
| `lease_option_conditions` | — | No | N/A | `listing_ref` | |
| `has_option_fee` | — | No | N/A | `listing_ref` | |
| `option_fee_amount` | — | No | N/A | `listing_ref` | |
| `lease_option_fee_credit` | — | No | N/A | `listing_ref` | |
| `lease_option_fee_credit_percentage` | — | No | N/A | `listing_ref` | |
| `lease_option_maintenance` | — | No | N/A | `listing_ref` | |
| `lease_option_extension_terms` | — | No | N/A | `listing_ref` | |
| `lease_option_consideration` | — | No | N/A | `listing_ref` | |
| **— Lease Purchase —** | | | | | |
| `lease_purchase_price` | — | No | N/A | `listing_ref` | |
| `lease_purchase_terms` | — | No | N/A | `listing_ref` | |
| `lease_purchase_duration` | — | No | N/A | `listing_ref` | |
| `lease_purchase_payment` | — | No | N/A | `listing_ref` | |
| `lease_purchase_conditions` | — | No | N/A | `listing_ref` | |
| `lease_purchase_rent_credit` | — | No | N/A | `listing_ref` | |
| `lease_purchase_rent_credit_amount` | — | No | N/A | `listing_ref` | |
| `lease_purchase_deposit` | — | No | N/A | `listing_ref` | |
| `lease_purchase_maintenance` | — | No | N/A | `listing_ref` | |
| `lease_purchase_extension_terms` | — | No | N/A | `listing_ref` | |
| `lease_purchase_option_fee` | — | No | N/A | `listing_ref` | |
| `lease_purchase_option_fee_amount` | — | No | N/A | `listing_ref` | |
| **— Cryptocurrency —** | | | | | |
| `cryptocurrency_type` | — | No | N/A | `listing_ref` | |
| `crypto_percentage` | — | No | N/A | `listing_ref` | |
| `cash_percentage_crypto` | — | No | N/A | `listing_ref` | |
| `crypto_transfer_timing` | — | No | N/A | `listing_ref` | `otherPairs` companion: `crypto_transfer_timing_other`. |
| `crypto_exchange_method` | — | No | N/A | `listing_ref` | |
| `crypto_custodian_wallet` | — | No | N/A | `listing_ref` | |
| `crypto_transaction_fees` | — | No | N/A | `listing_ref` | |
| **— NFT —** | | | | | |
| `nft_description` | — | No | N/A | `listing_ref` | |
| `nft_percentage` | — | No | N/A | `listing_ref` | |
| `cash_percentage_nft` | — | No | N/A | `listing_ref` | |
| `nft_valuation_method` | — | No | N/A | `listing_ref` | |
| `nft_transfer_method` | — | No | N/A | `listing_ref` | |
| `nft_gas_fees` | — | No | N/A | `listing_ref` | |
| **— Lease Details —** | | | | | |
| `lease_for` | — | No | N/A | `listing_ref` | `otherPairs` companion: `other_lease_for`. |
| `lease_by` | — | No | N/A | `listing_ref` | |
| `lease_date` | — | No | N/A | `listing_ref` | |
| **— Services —** | | | | | |
| `services` | `services` | No | Yes | `offer_meta` | JSON array of selected service keys. |
| `custom_services` | `custom_services` | No | Yes | `new_offer_meta` | Not stored in bid; commented-out saveMeta. |
| `include_marketing_fee` | — | No | N/A | `listing_ref` | |
| `email_marketing_fee` | — | No | N/A | `listing_ref` | |
| `email_notifications_fee` | — | No | N/A | `listing_ref` | |
| `launch_ads` | — | No | N/A | `listing_ref` | |
| `launch_ads_fee` | — | No | N/A | `listing_ref` | |
| `market_groups` | — | No | N/A | `listing_ref` | |
| `market_groups_fee` | — | No | N/A | `listing_ref` | |
| `marketing_materials_fee` | — | No | N/A | `listing_ref` | |
| `mls_filter_fee` | — | No | N/A | `listing_ref` | |
| `off_market_search_fee` | — | No | N/A | `listing_ref` | |
| `promote_social` | — | No | N/A | `listing_ref` | |
| `promote_social_fee` | — | No | N/A | `listing_ref` | |
| `list_criteria` | — | No | N/A | `listing_ref` | |
| `list_criteria_fee` | — | No | N/A | `listing_ref` | |
| `flat_fee_services` | — | No | N/A | `listing_ref` | |
| `schedule_showings` | — | No | N/A | `listing_ref` | |
| `schedule_showings_fee` | — | No | N/A | `listing_ref` | |
| `number_of_showings_to_schedule` | — | No | N/A | `listing_ref` | |
| `attend_showings` | — | No | N/A | `listing_ref` | |
| `number_of_showings_to_attend` | — | No | N/A | `listing_ref` | |
| `attend_showings_fee` | — | No | N/A | `listing_ref` | |
| `provide_virtual_tours` | — | No | N/A | `listing_ref` | |
| `number_of_virtual_tours` | — | No | N/A | `listing_ref` | |
| `virtual_tours_fee` | — | No | N/A | `listing_ref` | |
| `assist_application` | — | No | N/A | `listing_ref` | |
| `assist_application_fee` | — | No | N/A | `listing_ref` | |
| `collect_documents` | — | No | N/A | `listing_ref` | |
| `collect_documents_fee` | — | No | N/A | `listing_ref` | |
| `submit_application` | — | No | N/A | `listing_ref` | |
| `submit_application_fee` | — | No | N/A | `listing_ref` | |
| `review_lease` | — | No | N/A | `listing_ref` | |
| `review_lease_fee` | — | No | N/A | `listing_ref` | |
| `provide_lease_form` | — | No | N/A | `listing_ref` | |
| `provide_lease_form_fee` | — | No | N/A | `listing_ref` | |
| `coordinate_signing` | — | No | N/A | `listing_ref` | |
| `coordinate_signing_fee` | — | No | N/A | `listing_ref` | |
| `prepare_application_fee` | — | No | N/A | `listing_ref` | |
| `move_in_inspection_fee` | — | No | N/A | `listing_ref` | |
| `moving_resources_fee` | — | No | N/A | `listing_ref` | |
| `short_term_housing_fee` | — | No | N/A | `listing_ref` | |
| `rental_rights_fee` | — | No | N/A | `listing_ref` | |
| `lease_advice_fee` | — | No | N/A | `listing_ref` | |
| `neighborhood_insights_fee` | — | No | N/A | `listing_ref` | |
| `neighborhood_marketing_fee` | — | No | N/A | `listing_ref` | |
| `neighborhood_materials_fee` | — | No | N/A | `listing_ref` | |
| `total_marketing_fee` | `total_marketing_fee` | No | Yes | `offer_meta` | Confirmed in BuyerAgentAuctionBid (public property, computed). |
| `total_flat_fee` | `total_flat_fee` | No | Yes | `offer_meta` | Same. |
| `staging_duration` | — | No | N/A | `listing_ref` | |
| `open_house_count` | — | No | N/A | `listing_ref` | |
| `virtual_showings_count` | — | No | N/A | `listing_ref` | |
| **— Broker Compensation & Agency Agreement —** | | | | | |
| `commission_structure` | `commission_structure` | No | Yes | `offer_meta` | |
| `lease_fee_type` | `lease_fee_type` | No | Yes | `offer_meta` | Saved in BuyerAgentAuctionBid. |
| `lease_fee_flat` | `lease_fee_flat` | No | Yes | `offer_meta` | |
| `lease_fee_percentage` | `lease_fee_percentage` | No | Yes | `offer_meta` | |
| `lease_fee_months` | `lease_fee_months` | No | Yes | `new_offer_meta` | In FieldMap; not confirmed in bid saveMeta. |
| `lease_fee_percentage_monthly_rent` | `lease_fee_percentage_monthly_rent` | No | Yes | `offer_meta` | |
| `lease_fee_percentage_monthly_number` | `lease_fee_percentage_monthly_number` | No | Yes | `offer_meta` | |
| `lease_fee_flat_combo` | `lease_fee_flat_combo` | No | Yes | `offer_meta` | |
| `lease_fee_percentage_combo` | `lease_fee_percentage_combo` | No | Yes | `offer_meta` | |
| `lease_fee_flat_combo_net` | `lease_fee_flat_combo_net` | No | Yes | `offer_meta` | |
| `lease_fee_percentage_combo_net` | `lease_fee_percentage_combo_net` | No | Yes | `offer_meta` | |
| `lease_fee_percentage_net` | `lease_fee_percentage_net` | No | Yes | `offer_meta` | |
| `lease_fee_other` | `lease_fee_other` | No | Yes | `offer_meta` | |
| `purchase_fee_type` | `purchase_fee_type` | No | Yes | `offer_meta` | |
| `interested_purchase_fee_type` | `interested_purchase_fee_type` | No | Yes | `offer_meta` | |
| `purchase_fee_percentage` | `purchase_fee_percentage` | No | Yes | `offer_meta` | |
| `purchase_fee_flat` | `purchase_fee_flat` | No | Yes | `offer_meta` | |
| `purchase_fee_percentage_combo` | `purchase_fee_percentage_combo` | No | Yes | `offer_meta` | |
| `purchase_fee_flat_combo` | `purchase_fee_flat_combo` | No | Yes | `offer_meta` | |
| `purchase_fee_flat_exercised` | `purchase_fee_flat_exercised` | No | Yes | `new_offer_meta` | In BuyerFieldMap; not confirmed in bid saveMeta. |
| `purchase_pice_commercial` | `purchase_pice_commercial` | No | Yes | `new_offer_meta` | Typo in source retained. Not confirmed in bid saveMeta. |
| `purchase_fee_other` | `purchase_fee_other` | No | Yes | `offer_meta` | |
| `lease_option_fee_type` | `lease_option_fee_type` | No | Yes | `new_offer_meta` | In BuyerFieldMap; not confirmed in bid saveMeta. |
| `lease_option_fee_flat` | `lease_option_fee_flat` | No | Yes | `new_offer_meta` | |
| `lease_option_fee_flat_combo` | `lease_option_fee_flat_combo` | No | Yes | `new_offer_meta` | |
| `lease_option_fee_percentage` | `lease_option_fee_percentage` | No | Yes | `new_offer_meta` | |
| `lease_option_fee_percentage_combo` | `lease_option_fee_percentage_combo` | No | Yes | `new_offer_meta` | |
| `lease_option_fee_other` | `lease_option_fee_other` | No | Yes | `new_offer_meta` | |
| `protection_period` | `protection_period` | No | Yes | `offer_meta` | |
| `early_termination_fee_option` | `early_termination_fee_option` | No | Yes | `offer_meta` | |
| `lease_type` | `lease_type` | No | Yes | `offer_meta` | Internal type selector (`percent`/`flat`). |
| `lease_value` | `lease_value` | No | Yes | `offer_meta` | |
| `purchase_type` | `purchase_type` | No | Yes | `offer_meta` | |
| `purchase_value` | `purchase_value` | No | Yes | `offer_meta` | |
| `interested_lease_option_agreement` | `interested_lease_option_agreement` | No | Yes | `offer_meta` | Repeated from Lease Option section. |
| `retainer_fee_option` | `retainer_fee_option` | No | Yes | `offer_meta` | |
| `retainer_fee_application` | `retainer_fee_application` | No | Yes | `offer_meta` | |
| `agency_agreement_timeframe` | `agency_agreement_timeframe` | No | Yes | `offer_meta` | |
| `agency_agreement_custom` | `agency_agreement_custom` | No | Yes | `offer_meta` | |
| `brokerage_relationship` | `brokerage_relationship` | No | Yes | `offer_meta` | |
| **— Additional Details —** | | | | | |
| `additional_details` | `additional_details` | No | Yes | `offer_meta` | |
| `additional_details_broker` | `additional_details_broker` | No | Yes | `offer_meta` | |
| `video_link` | — | No | N/A | `listing_ref` | |
| **— Meeting Details —** | | | | | |
| `person_meeting` | — | No | N/A | `listing_ref` | |
| `meeting_details_first_name` | — | No | N/A | `listing_ref` | |
| `meeting_details_last_name` | — | No | N/A | `listing_ref` | |
| `meeting_details_phone` | — | No | N/A | `listing_ref` | |
| `meeting_details_email` | — | No | N/A | `listing_ref` | |
| `meeting_details_meeting_date` | — | No | N/A | `listing_ref` | |
| `meeting_details_meeting_time` | — | No | N/A | `listing_ref` | |
| `meeting_details_time_zone` | — | No | N/A | `listing_ref` | |
| `meeting_details_instructions` | — | No | N/A | `listing_ref` | |
| `meeting_details_additional_details` | — | No | N/A | `listing_ref` | |
| `service_completion_date` | — | No | N/A | `listing_ref` | |
| `service_completion_time` | — | No | N/A | `listing_ref` | |
| `service_time_zone` | — | No | N/A | `listing_ref` | |
| **— Contact Information —** | | | | | |
| `first_name` | — | No | N/A | `listing_ref` | |
| `last_name` | — | No | N/A | `listing_ref` | |
| `phone_number` | — | No | N/A | `listing_ref` | |
| `email` | — | No | N/A | `listing_ref` | |

---

## Section 3 — Landlord Agent Auction Flow

**Listing source:** `app/Exports/ListingFieldMaps/LandlordFieldMap.php`  
**Bid storage:** `landlord_agent_auction_bid_metas` (EAV) + native columns on `landlord_agent_auction_bids`  
**Bid Livewire component:** `app/Http/Livewire/Landlord/LandlordAgentAuctionBid.php`

| Listing Field | Offer Field | Auto-Populated | Editable | Storage Target | Notes |
|---|---|---|---|---|---|
| **— Listing Details —** | | | | | |
| `service_type` | — | No | N/A | `listing_ref` | |
| `listing_status` | — | No | N/A | `listing_ref` | |
| `auction_type` | — | No | N/A | `listing_ref` | |
| `working_with_agent` | — | No | N/A | `listing_ref` | |
| `listing_date` | — | No | N/A | `listing_ref` | |
| `desired_agent_hire_date` | — | No | N/A | `listing_ref` | |
| `expiration_date` | — | No | N/A | `listing_ref` | |
| `auction_time` | — | No | N/A | `listing_ref` | |
| `agent_bid_visibility` | — | No | N/A | `listing_ref` | Controls whether bids are visible to other agents. |
| `meeting_Preference` | — | No | N/A | `listing_ref` | Note: mixed-case key from FieldMap. |
| **— Location —** | | | | | |
| `address` | — | No | N/A | `listing_ref` | |
| `property_city` | — | No | N/A | `listing_ref` | |
| `property_county` | — | No | N/A | `listing_ref` | |
| `property_state` | — | No | N/A | `listing_ref` | |
| `property_zip` | — | No | N/A | `listing_ref` | |
| `cities` | — | No | N/A | `listing_ref` | |
| `counties` | — | No | N/A | `listing_ref` | |
| `zipCodes` | — | No | N/A | `listing_ref` | |
| **— Property Details —** | | | | | |
| `property_type` | — | No | N/A | `listing_ref` | |
| `property_items` | — | No | N/A | `listing_ref` | `otherPairs` companion: `other_property_items`. |
| `leasing_space` | — | No | N/A | `listing_ref` | |
| `condition_prop` | — | No | N/A | `listing_ref` | `otherPairs` companion: `other_property_condition`. |
| `bedrooms` | — | No | N/A | `listing_ref` | `otherPairs` companion: `other_bedrooms`. |
| `bathrooms` | — | No | N/A | `listing_ref` | `otherPairs` companion: `other_bathrooms`. |
| `minimum_heated_square` | — | No | N/A | `listing_ref` | |
| `minimum_leaseable` | — | No | N/A | `listing_ref` | |
| `total_square_feet` | — | No | N/A | `listing_ref` | |
| `sqft_heated_source` | — | No | N/A | `listing_ref` | |
| `min_acreage` | — | No | N/A | `listing_ref` | |
| `total_acreage` | — | No | N/A | `listing_ref` | |
| `appliances` | — | No | N/A | `listing_ref` | `otherPairs` companion: `appliances_other`. |
| `property_criteria` | — | No | N/A | `listing_ref` | |
| `unit_size` | — | No | N/A | `listing_ref` | `otherPairs` companion: `unit_size_other`. |
| `preferance_details` | — | No | N/A | `listing_ref` | |
| **— Income & Investment Metrics —** | | | | | |
| `minimum_annual_net_income` | — | No | N/A | `listing_ref` | |
| `minimum_cap_rate` | — | No | N/A | `listing_ref` | |
| `assets` | — | No | N/A | `listing_ref` | `otherPairs` companion: `assets_other`. |
| **— Leasing Terms —** | | | | | |
| `occupant_status` | — | No | N/A | `listing_ref` | |
| `occupant_types` | — | No | N/A | `listing_ref` | |
| `occupant_types_tenant` | — | No | N/A | `listing_ref` | |
| `occupant_tenant` | — | No | N/A | `listing_ref` | |
| `leasing_space_property` | — | No | N/A | `listing_ref` | |
| `leasing_spaces` | — | No | N/A | `listing_ref` | |
| `desired_rental_amount` | — | No | N/A | `listing_ref` | |
| `desired_rental_amount_tenant` | — | No | N/A | `listing_ref` | |
| `lease_amount_frequency` | — | No | N/A | `listing_ref` | |
| `desired_lease_length` | — | No | N/A | `listing_ref` | |
| `custom_lease_term` | — | No | N/A | `listing_ref` | `otherPairs` companion: `other_lease_term`. |
| `terms_of_lease` | — | No | N/A | `listing_ref` | |
| `rent_includes` | — | No | N/A | `listing_ref` | `otherPairs` companion: `other_rent_include`. |
| `tenant_pays` | — | No | N/A | `listing_ref` | `otherPairs` companion: `tenant_pays_other`. |
| `owner_pays` | — | No | N/A | `listing_ref` | `otherPairs` companion: `owner_pays_other`. |
| `restrictions` | — | No | N/A | `listing_ref` | |
| `maintenance_by` | — | No | N/A | `listing_ref` | |
| `maintenance_handler` | — | No | N/A | `listing_ref` | |
| `maintenance_response_time` | — | No | N/A | `listing_ref` | |
| **— Commercial Property Details —** | | | | | |
| `building_hours` | — | No | N/A | `listing_ref` | |
| `access_24_7` | — | No | N/A | `listing_ref` | |
| `bathroom_facilities` | — | No | N/A | `listing_ref` | |
| `room_size` | — | No | N/A | `listing_ref` | |
| `zoning_allows` | — | No | N/A | `listing_ref` | |
| `space_features` | — | No | N/A | `listing_ref` | |
| `neighboring_tenants` | — | No | N/A | `listing_ref` | |
| `shared_amenities` | — | No | N/A | `listing_ref` | |
| `common_areas_access` | — | No | N/A | `listing_ref` | |
| `common_areas_cleaning` | — | No | N/A | `listing_ref` | |
| `utilities` | — | No | N/A | `listing_ref` | |
| **— Storage —** | | | | | |
| `storage_space` | — | No | N/A | `listing_ref` | |
| `included_storage_space_res_both` | — | No | N/A | `listing_ref` | |
| `storage_space_res_both` | — | No | N/A | `listing_ref` | |
| `included_storage_space_res_single` | — | No | N/A | `listing_ref` | |
| `storage_space_res_single` | — | No | N/A | `listing_ref` | |
| `included_storage_space_com_entire` | — | No | N/A | `listing_ref` | |
| `storage_space_com_entire` | — | No | N/A | `listing_ref` | |
| `included_storage_space_com_single` | — | No | N/A | `listing_ref` | |
| `storage_space_com_single` | — | No | N/A | `listing_ref` | |
| **— Additional Property Preferences —** | | | | | |
| `tenant_require` | — | No | N/A | `listing_ref` | |
| `carport_needed` | — | No | N/A | `listing_ref` | `otherPairs` companion: `other_carport_needed`. |
| `garage_needed` | — | No | N/A | `listing_ref` | `otherPairs` companion: `other_garage_needed`. |
| `garage_parking_spaces` | — | No | N/A | `listing_ref` | |
| `garage_parking_spaces_option` | — | No | N/A | `listing_ref` | `otherPairs` companion: `other_parking_space_wrapper`. |
| `pool_needed` | — | No | N/A | `listing_ref` | |
| `pool_type` | — | No | N/A | `listing_ref` | |
| `view_preference` | — | No | N/A | `listing_ref` | `otherPairs` companion: `other_preferences`. |
| `real_estate_purchase` | — | No | N/A | `listing_ref` | |
| `number_of_unit` | — | No | N/A | `listing_ref` | `otherPairs` companion: `number_of_unit_other`. |
| `leasing_55_plus` | — | No | N/A | `listing_ref` | |
| `guests_allowed` | — | No | N/A | `listing_ref` | |
| `custom_enhancement` | — | No | N/A | `listing_ref` | Listing-side amenity field. Note: bid components also use `custom_enhancement` as a separate bid-side field (see Section 6). |
| `non_negotiable_amenities` | — | No | N/A | `listing_ref` | `otherPairs` companion: `other_non_negotiable_amenities`. |
| **— Pets —** | | | | | |
| `pets` | — | No | N/A | `listing_ref` | |
| `number_of_pets` | — | No | N/A | `listing_ref` | |
| `has_breed_restrictions` | — | No | N/A | `listing_ref` | |
| `breed_restrictions` | — | No | N/A | `listing_ref` | |
| `service_animal` | — | No | N/A | `listing_ref` | |
| `support_animal` | — | No | N/A | `listing_ref` | |
| `breed_of_pets` | — | No | N/A | `listing_ref` | |
| `type_of_pets` | — | No | N/A | `listing_ref` | |
| `weight_of_pets` | — | No | N/A | `listing_ref` | |
| `monthly_income` | — | No | N/A | `listing_ref` | |
| `number_occupant` | — | No | N/A | `listing_ref` | |
| **— Sale Terms —** | | | | | |
| `sale_provision` | — | No | N/A | `listing_ref` | `otherPairs` companion: `sale_provision_other`. |
| `sale_provision_assignment` | — | No | N/A | `listing_ref` | |
| `assignment_fee_type` | — | No | N/A | `listing_ref` | |
| `assignment_fee_amount` | — | No | N/A | `listing_ref` | |
| `buyer_sell_contract` | — | No | N/A | `listing_ref` | |
| `interested_in_selling` | `interested_in_selling` | No | Yes | `offer_meta` | Landlord bid component saves this; agent proposes whether they'll handle a sale. |
| `interested_in_selling_type` | `interested_in_selling_type` | No | Yes | `offer_meta` | |
| **— Budget & Financing —** | | | | | |
| `maximum_budget` | — | No | N/A | `listing_ref` | |
| `offered_financing` | — | No | N/A | `listing_ref` | `otherPairs` companion: `other_financing`. |
| `cash_budget` | — | No | N/A | `listing_ref` | |
| `pre_approved` | — | No | N/A | `listing_ref` | |
| `pre_approval_amount` | — | No | N/A | `listing_ref` | |
| `purchase_price` | — | No | N/A | `listing_ref` | |
| `budget` | — | No | N/A | `listing_ref` | |
| `credit_scroe_rating` | — | No | N/A | `listing_ref` | |
| `prior_eviction` | — | No | N/A | `listing_ref` | |
| `eviction_explanation` | — | No | N/A | `listing_ref` | |
| `prior_felony` | — | No | N/A | `listing_ref` | |
| `prior_felony_explanation` | — | No | N/A | `listing_ref` | |
| **— Down Payment —** | | | | | |
| `down_payment_type` | — | No | N/A | `listing_ref` | |
| `down_payment_amount` | — | No | N/A | `listing_ref` | |
| **— Seller Financing —** | | | | | |
| `seller_financing_type` | — | No | N/A | `listing_ref` | |
| `seller_financing_amount` | — | No | N/A | `listing_ref` | |
| `interest_rate` | — | No | N/A | `listing_ref` | |
| `loan_duration` | — | No | N/A | `listing_ref` | |
| `prepayment_penalty` | — | No | N/A | `listing_ref` | |
| `prepayment_penalty_amount` | — | No | N/A | `listing_ref` | |
| `balloon_payment_amount` | — | No | N/A | `listing_ref` | |
| `balloon_payment_date` | — | No | N/A | `listing_ref` | |
| **— Assumable Loan —** | | | | | |
| `assumable_terms` | — | No | N/A | `listing_ref` | |
| `max_assumable_rate` | — | No | N/A | `listing_ref` | |
| **— Gap / Additional Payments —** | | | | | |
| `max_monthly_payment` | — | No | N/A | `listing_ref` | |
| `gap_payment_type` | — | No | N/A | `listing_ref` | |
| `gap_payment_amount` | — | No | N/A | `listing_ref` | |
| `additional_cash` | — | No | N/A | `listing_ref` | |
| **— Exchange / Trade —** | | | | | |
| `exchange_item` | — | No | N/A | `listing_ref` | `otherPairs` companion: `other_exchange_item`. |
| `exchange_item_value` | — | No | N/A | `listing_ref` | |
| `exchange_item_condition` | — | No | N/A | `listing_ref` | |
| `value_determination` | — | No | N/A | `listing_ref` | |
| **— Lease Option —** | | | | | |
| `interested_lease_option_agreement` | `interested_lease_option_agreement` | No | Yes | `offer_meta` | Saved in LandlordAgentAuctionBid. |
| `lease_option_price` | — | No | N/A | `listing_ref` | |
| `lease_option_terms` | — | No | N/A | `listing_ref` | |
| `lease_option_duration` | — | No | N/A | `listing_ref` | |
| `lease_option_payment` | — | No | N/A | `listing_ref` | |
| `lease_option_conditions` | — | No | N/A | `listing_ref` | |
| `has_option_fee` | — | No | N/A | `listing_ref` | |
| `option_fee_amount` | — | No | N/A | `listing_ref` | |
| **— Lease Purchase —** | | | | | |
| `lease_purchase_price` | — | No | N/A | `listing_ref` | |
| `lease_purchase_terms` | — | No | N/A | `listing_ref` | |
| `lease_purchase_duration` | — | No | N/A | `listing_ref` | |
| `lease_purchase_payment` | — | No | N/A | `listing_ref` | |
| `lease_purchase_conditions` | — | No | N/A | `listing_ref` | |
| `lease_purchase_option_fee` | — | No | N/A | `listing_ref` | |
| `lease_purchase_option_fee_amount` | — | No | N/A | `listing_ref` | |
| **— Cryptocurrency —** | | | | | |
| `cryptocurrency_type` | — | No | N/A | `listing_ref` | |
| `crypto_percentage` | — | No | N/A | `listing_ref` | |
| `cash_percentage_crypto` | — | No | N/A | `listing_ref` | |
| **— NFT —** | | | | | |
| `nft_description` | — | No | N/A | `listing_ref` | |
| `nft_percentage` | — | No | N/A | `listing_ref` | |
| `cash_percentage_nft` | — | No | N/A | `listing_ref` | |
| **— Lease Details —** | | | | | |
| `lease_for` | — | No | N/A | `listing_ref` | `otherPairs` companion: `other_lease_for`. |
| `lease_by` | — | No | N/A | `listing_ref` | |
| `lease_date` | — | No | N/A | `listing_ref` | |
| **— Services —** | | | | | |
| `services` | `services` | No | Yes | `offer_meta` | JSON array of selected service keys. |
| `custom_services` | `custom_services` | No | Yes | `new_offer_meta` | Not saved in bid; commented-out saveMeta. |
| `include_marketing_fee` | — | No | N/A | `listing_ref` | |
| `email_marketing_fee` | — | No | N/A | `listing_ref` | |
| `email_notifications_fee` | — | No | N/A | `listing_ref` | |
| `launch_ads` | — | No | N/A | `listing_ref` | |
| `launch_ads_fee` | — | No | N/A | `listing_ref` | |
| `market_groups` | — | No | N/A | `listing_ref` | |
| `market_groups_fee` | — | No | N/A | `listing_ref` | |
| `marketing_materials_fee` | — | No | N/A | `listing_ref` | |
| `mls_filter_fee` | — | No | N/A | `listing_ref` | |
| `off_market_search_fee` | — | No | N/A | `listing_ref` | |
| `promote_social` | — | No | N/A | `listing_ref` | |
| `promote_social_fee` | — | No | N/A | `listing_ref` | |
| `list_criteria` | — | No | N/A | `listing_ref` | |
| `list_criteria_fee` | — | No | N/A | `listing_ref` | |
| `flat_fee_services` | — | No | N/A | `listing_ref` | |
| `schedule_showings` | — | No | N/A | `listing_ref` | |
| `schedule_showings_fee` | — | No | N/A | `listing_ref` | |
| `number_of_showings_to_schedule` | — | No | N/A | `listing_ref` | |
| `attend_showings` | — | No | N/A | `listing_ref` | |
| `number_of_showings_to_attend` | — | No | N/A | `listing_ref` | |
| `attend_showings_fee` | — | No | N/A | `listing_ref` | |
| `provide_virtual_tours` | — | No | N/A | `listing_ref` | |
| `number_of_virtual_tours` | — | No | N/A | `listing_ref` | |
| `virtual_tours_fee` | — | No | N/A | `listing_ref` | |
| `assist_application` | — | No | N/A | `listing_ref` | |
| `assist_application_fee` | — | No | N/A | `listing_ref` | |
| `collect_documents` | — | No | N/A | `listing_ref` | |
| `collect_documents_fee` | — | No | N/A | `listing_ref` | |
| `submit_application` | — | No | N/A | `listing_ref` | |
| `submit_application_fee` | — | No | N/A | `listing_ref` | |
| `review_lease` | — | No | N/A | `listing_ref` | |
| `review_lease_fee` | — | No | N/A | `listing_ref` | |
| `provide_lease_form` | — | No | N/A | `listing_ref` | |
| `provide_lease_form_fee` | — | No | N/A | `listing_ref` | |
| `coordinate_signing` | — | No | N/A | `listing_ref` | |
| `coordinate_signing_fee` | — | No | N/A | `listing_ref` | |
| `prepare_application_fee` | — | No | N/A | `listing_ref` | |
| `move_in_inspection_fee` | — | No | N/A | `listing_ref` | |
| `moving_resources_fee` | — | No | N/A | `listing_ref` | |
| `short_term_housing_fee` | — | No | N/A | `listing_ref` | |
| `rental_rights_fee` | — | No | N/A | `listing_ref` | |
| `lease_advice_fee` | — | No | N/A | `listing_ref` | |
| `neighborhood_insights_fee` | — | No | N/A | `listing_ref` | |
| `neighborhood_marketing_fee` | — | No | N/A | `listing_ref` | |
| `neighborhood_materials_fee` | — | No | N/A | `listing_ref` | |
| `total_marketing_fee` | `total_marketing_fee` | No | Yes | `new_offer_meta` | In FieldMap; not confirmed in LandlordAgentAuctionBid saveMeta. |
| `total_flat_fee` | `total_flat_fee` | No | Yes | `new_offer_meta` | Same. |
| `staging_duration` | — | No | N/A | `listing_ref` | |
| `open_house_count` | — | No | N/A | `listing_ref` | |
| `virtual_showings_count` | — | No | N/A | `listing_ref` | |
| `photo_enhancements` | `photo_enhancements` | No | Yes | `offer_meta` | Confirmed in LandlordAgentAuctionBid saveMeta. |
| **— Broker Compensation & Agency Agreement —** | | | | | |
| `commission_structure` | `commission_structure` | No | N/A | `listing_ref` | LandlordFieldMap commission_structure is not saved in LandlordAgentAuctionBid (no explicit saveMeta call confirmed). |
| `lease_fee_type` | `lease_fee_type` | No | Yes | `offer_meta` | Confirmed in LandlordAgentAuctionBid. |
| `lease_fee_flat` | `lease_fee_flat` | No | Yes | `offer_meta` | |
| `lease_fee_flat_type` | `lease_fee_flat_type` | No | Yes | `new_offer_meta` | In LandlordFieldMap; not confirmed in bid saveMeta. |
| `lease_fee_percentage` | `lease_fee_percentage` | No | Yes | `offer_meta` | |
| `lease_fee_months` | `lease_fee_months` | No | Yes | `new_offer_meta` | |
| `lease_fee_percentage_monthly_rent` | `lease_fee_percentage_monthly_rent` | No | Yes | `offer_meta` | |
| `lease_fee_flat_combo` | `lease_fee_flat_combo` | No | Yes | `offer_meta` | |
| `lease_fee_percentage_combo` | `lease_fee_percentage_combo` | No | Yes | `offer_meta` | |
| `lease_fee_other` | `lease_fee_other` | No | Yes | `offer_meta` | |
| `purchase_fee_type` | `purchase_fee_type` | No | Yes | `offer_meta` | Confirmed in LandlordAgentAuctionBid. |
| `purchase_fee_percentage` | `purchase_fee_percentage` | No | Yes | `offer_meta` | |
| `purchase_fee_flat` | `purchase_fee_flat` | No | Yes | `offer_meta` | |
| `purchase_fee_flat_type` | `purchase_fee_flat_type` | No | Yes | `new_offer_meta` | |
| `purchase_fee_percentage_combo` | `purchase_fee_percentage_combo` | No | Yes | `offer_meta` | |
| `purchase_fee_flat_combo` | `purchase_fee_flat_combo` | No | Yes | `offer_meta` | |
| `purchase_fee_other` | `purchase_fee_other` | No | Yes | `offer_meta` | |
| `purchase_fee_net_aggregate` | `purchase_fee_net_aggregate` | No | Yes | `offer_meta` | Confirmed. |
| `purchase_fee_other_commercial` | `purchase_fee_other_commercial` | No | Yes | `offer_meta` | Confirmed. |
| `purchase_fee_flat_commercial` | `purchase_fee_flat_commercial` | No | Yes | `offer_meta` | Confirmed. |
| `purchase_fee_gross_rent` | `purchase_fee_gross_rent` | No | Yes | `offer_meta` | Confirmed. |
| `purchase_fee_monthly_percentage` | `purchase_fee_monthly_percentage` | No | Yes | `offer_meta` | Confirmed. |
| `purchase_fee_months` | `purchase_fee_months` | No | Yes | `offer_meta` | Confirmed. |
| `purchase_fee_purchase_price` | `purchase_fee_purchase_price` | No | Yes | `offer_meta` | Confirmed. |
| `purchase_fee_rental_period` | `purchase_fee_rental_period` | No | Yes | `offer_meta` | Confirmed. |
| `lease_option_fee_type` | `lease_option_fee_type` | No | Yes | `new_offer_meta` | In LandlordFieldMap; not confirmed in bid saveMeta. |
| `lease_option_fee_flat` | `lease_option_fee_flat` | No | Yes | `new_offer_meta` | |
| `lease_option_fee_percentage` | `lease_option_fee_percentage` | No | Yes | `new_offer_meta` | |
| `lease_option_fee_other` | `lease_option_fee_other` | No | Yes | `new_offer_meta` | |
| `landlord_broker_flate_fee` | `landlord_broker_flate_fee` | No | Yes | `offer_meta` | Confirmed. (Typo in source: "flate" retained.) |
| `landlord_broker_percentage_price` | `landlord_broker_percentage_price` | No | Yes | `offer_meta` | Confirmed. |
| `landlord_broker_dollar_price` | `landlord_broker_dollar_price` | No | Yes | `offer_meta` | Confirmed. |
| `landlord_broker_purchase_price` | `landlord_broker_purchase_price` | No | Yes | `offer_meta` | Confirmed. |
| `landlord_broker_other` | `landlord_broker_other` | No | Yes | `offer_meta` | Confirmed. |
| `renewal_fee_type` | `renewal_fee_type` | No | Yes | `offer_meta` | Confirmed. |
| `renewal_fee_percentage` | `renewal_fee_percentage` | No | Yes | `offer_meta` | Confirmed. |
| `renewal_fee_first_month` | `renewal_fee_first_month` | No | Yes | `offer_meta` | Confirmed. |
| `renewal_fee_flat_free` | `renewal_fee_flat_free` | No | Yes | `offer_meta` | Confirmed. (Typo "free" vs "fee" retained from source.) |
| `renewal_fee_lease_value` | `renewal_fee_lease_value` | No | Yes | `offer_meta` | Confirmed. |
| `renewal_fee_no_of_months` | `renewal_fee_no_of_months` | No | Yes | `offer_meta` | Confirmed. |
| `renewal_fee_sales_tax_first_month` | `renewal_fee_sales_tax_first_month` | No | Yes | `offer_meta` | Confirmed. |
| `renewal_fee_sales_tax_flat_fee` | `renewal_fee_sales_tax_flat_fee` | No | Yes | `offer_meta` | Confirmed. |
| `renewal_fee_sales_tax_lease_value` | `renewal_fee_sales_tax_lease_value` | No | Yes | `offer_meta` | Confirmed. |
| `renewal_fee_custom` | `renewal_fee_custom` | No | Yes | `offer_meta` | Confirmed. |
| `sales_tax_option_flat` | `sales_tax_option_flat` | No | Yes | `offer_meta` | Confirmed. |
| `sales_tax_option_gross` | `sales_tax_option_gross` | No | Yes | `offer_meta` | Confirmed. |
| `sales_tax_option_monthly` | `sales_tax_option_monthly` | No | Yes | `offer_meta` | Confirmed. |
| `net_aggregate_rent` | `net_aggregate_rent` | No | Yes | `new_offer_meta` | In LandlordFieldMap; not confirmed in bid saveMeta. |
| `month_percentage_rent` | `month_percentage_rent` | No | Yes | `new_offer_meta` | |
| `no_of_months` | `no_of_months` | No | Yes | `new_offer_meta` | |
| `flat_fee` | `flat_fee` | No | Yes | `new_offer_meta` | |
| `gross_percentage_rent` | `gross_percentage_rent` | No | Yes | `new_offer_meta` | |
| `split_payment_due` | `split_payment_due` | No | Yes | `offer_meta` | Confirmed. `otherPairs` companion: `split_payment_due_other`. |
| `broker_fee_timing` | `broker_fee_timing` | No | Yes | `offer_meta` | Confirmed. `otherPairs` companion: `broker_fee_timing_other`. |
| `broker_fee_days_after_lease` | `broker_fee_days_after_lease` | No | Yes | `offer_meta` | Confirmed. |
| `broker_fee_days_after_rent` | `broker_fee_days_after_rent` | No | Yes | `offer_meta` | Confirmed. |
| `broker_fee_days_from_rent` | `broker_fee_days_from_rent` | No | Yes | `offer_meta` | Confirmed. |
| `broker_fee_days_after_due_event` | `broker_fee_days_after_due_event` | No | Yes | `offer_meta` | Confirmed. |
| `tenant_broker_commission_structure` | `tenant_broker_commission_structure` | No | Yes | `offer_meta` | Confirmed. |
| `tenant_broker_commission_percentage` | `tenant_broker_commission_percentage` | No | Yes | `new_offer_meta` | In LandlordFieldMap; not confirmed in bid saveMeta. |
| `tenant_broker_fee_structure` | `tenant_broker_fee_structure` | No | Yes | `offer_meta` | Confirmed. |
| `tenant_broker_percentage` | `tenant_broker_percentage` | No | Yes | `offer_meta` | Confirmed. |
| `tenant_broker_flat_fee` | `tenant_broker_flat_fee` | No | Yes | `offer_meta` | Confirmed. |
| `tenant_broker_first_month_rent` | `tenant_broker_first_month_rent` | No | Yes | `offer_meta` | Confirmed. |
| `tenant_broker_gross_lease` | `tenant_broker_gross_lease` | No | Yes | `offer_meta` | Confirmed. |
| `tenant_broker_other` | `tenant_broker_other` | No | Yes | `offer_meta` | Confirmed. |
| `expansion_commission_type` | `expansion_commission_type` | No | Yes | `new_offer_meta` | In LandlordFieldMap; not confirmed in bid saveMeta. |
| `expansion_commission_percentage` | `expansion_commission_percentage` | No | Yes | `offer_meta` | Confirmed. |
| `expansion_gross_percentage` | `expansion_gross_percentage` | No | Yes | `new_offer_meta` | |
| `expansion_first_month_percentage` | `expansion_first_month_percentage` | No | Yes | `new_offer_meta` | |
| `expansion_flat_fee` | `expansion_flat_fee` | No | Yes | `new_offer_meta` | |
| `expansion_custom_commission` | `expansion_custom_commission` | No | Yes | `new_offer_meta` | |
| `protection_period` | `protection_period` | No | Yes | `offer_meta` | Confirmed. |
| `early_termination_fee_option` | `early_termination_fee_option` | No | Yes | `offer_meta` | Confirmed. |
| `lease_type` | `lease_type` | No | Yes | `offer_meta` | Confirmed. |
| `lease_value` | `lease_value` | No | Yes | `offer_meta` | Confirmed. |
| `purchase_type` | `purchase_type` | No | Yes | `offer_meta` | Confirmed. |
| `purchase_value` | `purchase_value` | No | Yes | `offer_meta` | Confirmed. |
| `retainer_fee_option` | `retainer_fee_option` | No | Yes | `new_offer_meta` | In LandlordFieldMap; not confirmed in LandlordAgentAuctionBid saveMeta. |
| `retainer_fee_application` | `retainer_fee_application` | No | Yes | `new_offer_meta` | Same. |
| `agency_agreement_timeframe` | `agency_agreement_timeframe` | No | Yes | `offer_meta` | Confirmed. |
| `agency_agreement_custom` | `agency_agreement_custom` | No | Yes | `offer_meta` | Confirmed. |
| `brokerage_relationship` | `brokerage_relationship` | No | Yes | `new_offer_meta` | In LandlordFieldMap; not confirmed in LandlordAgentAuctionBid saveMeta. |
| **— Property Management —** | | | | | |
| `interested_in_property_management` | `interested_in_property_management` | No | Yes | `offer_meta` | Confirmed. Agent proposes PM services. |
| `interested_in_property_management_fee` | `interested_in_property_management_fee` | No | Yes | `offer_meta` | Confirmed. |
| `interested_in_property_management_fee_flate_free` | `interested_in_property_management_fee_flate_free` | No | Yes | `offer_meta` | Confirmed. (Typo "flate_free" retained from source.) |
| `interested_in_property_management_fee_gross_lease` | `interested_in_property_management_fee_gross_lease` | No | Yes | `offer_meta` | Confirmed. |
| `interested_in_property_management_fee_rental_periord` | `interested_in_property_management_fee_rental_periord` | No | Yes | `offer_meta` | Confirmed. (Typo "periord" retained.) |
| `interested_in_property_management_fee_other` | `interested_in_property_management_fee_other` | No | Yes | `offer_meta` | Confirmed. |
| **— Additional Details —** | | | | | |
| `additional_details` | `additional_details` | No | Yes | `offer_meta` | Confirmed. |
| `additional_details_broker` | `additional_details_broker` | No | Yes | `new_offer_meta` | In LandlordFieldMap; not confirmed in LandlordAgentAuctionBid saveMeta. |
| `video_link` | — | No | N/A | `listing_ref` | |
| **— Meeting Details —** | | | | | |
| `person_meeting` | — | No | N/A | `listing_ref` | |
| `meeting_details_first_name` | — | No | N/A | `listing_ref` | |
| `meeting_details_last_name` | — | No | N/A | `listing_ref` | |
| `meeting_details_phone` | — | No | N/A | `listing_ref` | |
| `meeting_details_email` | — | No | N/A | `listing_ref` | |
| `meeting_details_meeting_date` | — | No | N/A | `listing_ref` | |
| `meeting_details_meeting_time` | — | No | N/A | `listing_ref` | |
| `meeting_details_time_zone` | — | No | N/A | `listing_ref` | |
| `meeting_details_instructions` | — | No | N/A | `listing_ref` | |
| `meeting_details_additional_details` | — | No | N/A | `listing_ref` | |
| `service_completion_date` | — | No | N/A | `listing_ref` | |
| `service_completion_time` | — | No | N/A | `listing_ref` | |
| `service_time_zone` | — | No | N/A | `listing_ref` | |
| **— Contact Information —** | | | | | |
| `first_name` | — | No | N/A | `listing_ref` | |
| `last_name` | — | No | N/A | `listing_ref` | |
| `phone_number` | — | No | N/A | `listing_ref` | |
| `email` | — | No | N/A | `listing_ref` | |

---

## Section 4 — Tenant Agent Auction Flow

**Listing source:** `app/Exports/ListingFieldMaps/TenantFieldMap.php`  
**Bid storage:** `tenant_agent_auction_bid_metas` (EAV) + native columns on `tenant_agent_auction_bids`  
**Bid Livewire component:** `app/Http/Livewire/Tenant/TenantAgentAuctionBid.php`

| Listing Field | Offer Field | Auto-Populated | Editable | Storage Target | Notes |
|---|---|---|---|---|---|
| **— Listing Details —** | | | | | |
| `service_type` | — | No | N/A | `listing_ref` | |
| `listing_status` | — | No | N/A | `listing_ref` | |
| `auction_type` | — | No | N/A | `listing_ref` | |
| `working_with_agent` | — | No | N/A | `listing_ref` | |
| `current_status` | — | No | N/A | `listing_ref` | |
| `listing_date` | — | No | N/A | `listing_ref` | |
| `desired_agent_hire_date` | — | No | N/A | `listing_ref` | |
| `expiration_date` | — | No | N/A | `listing_ref` | |
| `auction_time` | — | No | N/A | `listing_ref` | |
| `agent_bid_visibility` | — | No | N/A | `listing_ref` | |
| `meeting_Preference` | — | No | N/A | `listing_ref` | |
| **— Location —** | | | | | |
| `address` | — | No | N/A | `listing_ref` | |
| `property_city` | — | No | N/A | `listing_ref` | |
| `property_county` | — | No | N/A | `listing_ref` | |
| `property_state` | — | No | N/A | `listing_ref` | |
| `property_zip` | — | No | N/A | `listing_ref` | |
| `cities` | — | No | N/A | `listing_ref` | |
| `counties` | — | No | N/A | `listing_ref` | |
| `zipCodes` | — | No | N/A | `listing_ref` | |
| **— Property Details —** | | | | | |
| `property_type` | — | No | N/A | `listing_ref` | |
| `property_items` | — | No | N/A | `listing_ref` | `otherPairs` companion: `other_property_items`. |
| `leasing_space` | — | No | N/A | `listing_ref` | |
| `leasing_spaces_tenant` | — | No | N/A | `listing_ref` | Tenant-specific variant. |
| `condition_prop` | — | No | N/A | `listing_ref` | `otherPairs` companion: `other_property_condition`. |
| `condition_prop_buyer` | — | No | N/A | `listing_ref` | |
| `business_type` | — | No | N/A | `listing_ref` | `otherPairs` companion: `other_business_type`. |
| `business_type_selected` | — | No | N/A | `listing_ref` | |
| `bedrooms` | — | No | N/A | `listing_ref` | `otherPairs` companion: `other_bedrooms`. |
| `bathrooms` | — | No | N/A | `listing_ref` | `otherPairs` companion: `other_bathrooms`. |
| `minimum_heated_square` | — | No | N/A | `listing_ref` | |
| `minimum_leaseable` | — | No | N/A | `listing_ref` | |
| `total_square_feet` | — | No | N/A | `listing_ref` | |
| `sqft_heated_source` | — | No | N/A | `listing_ref` | |
| `min_acreage` | — | No | N/A | `listing_ref` | |
| `total_acreage` | — | No | N/A | `listing_ref` | |
| `appliances` | — | No | N/A | `listing_ref` | `otherPairs` companion: `other_appliances`. |
| `property_criteria` | — | No | N/A | `listing_ref` | |
| `unit_size` | — | No | N/A | `listing_ref` | `otherPairs` companion: `unit_size_other`. |
| `number_of_unit` | — | No | N/A | `listing_ref` | |
| `number_of_unit_type` | — | No | N/A | `listing_ref` | |
| `unit_number` | — | No | N/A | `listing_ref` | |
| `unit_buildings` | — | No | N/A | `listing_ref` | |
| `unit_type_configurations` | — | No | N/A | `listing_ref` | |
| `preferance_details` | — | No | N/A | `listing_ref` | |
| **— Income & Investment Metrics —** | | | | | |
| `minimum_annual_net_income` | — | No | N/A | `listing_ref` | |
| `minimum_cap_rate` | — | No | N/A | `listing_ref` | |
| `assets` | — | No | N/A | `listing_ref` | `otherPairs` companion: `assets_other`. |
| **— Leasing Terms —** | | | | | |
| `occupant_status` | — | No | N/A | `listing_ref` | |
| `occupant_tenant` | — | No | N/A | `listing_ref` | |
| `leasing_spaces` | — | No | N/A | `listing_ref` | |
| `desired_rental_amount` | — | No | N/A | `listing_ref` | |
| `lease_amount_frequency` | — | No | N/A | `listing_ref` | |
| `desired_lease_length` | — | No | N/A | `listing_ref` | |
| `custom_lease_term` | — | No | N/A | `listing_ref` | `otherPairs` companion: `other_lease_term`. |
| `terms_of_lease` | — | No | N/A | `listing_ref` | |
| `rent_includes` | — | No | N/A | `listing_ref` | `otherPairs` companion: `other_rent_include`. |
| `tenant_pays` | — | No | N/A | `listing_ref` | `otherPairs` companion: `other_tenant_pays`. |
| `owner_pays` | — | No | N/A | `listing_ref` | `otherPairs` companions: `owner_pays_other`, `other_owner_pays`. |
| `restrictions` | — | No | N/A | `listing_ref` | |
| `maintenance_by` | — | No | N/A | `listing_ref` | |
| `maintenance_response_time` | — | No | N/A | `listing_ref` | |
| `occupied_until` | — | No | N/A | `listing_ref` | |
| `occupancy_status` | — | No | N/A | `listing_ref` | |
| `target_closing_date` | — | No | N/A | `listing_ref` | |
| **— Commercial Property Details —** | | | | | |
| `building_hours` | — | No | N/A | `listing_ref` | |
| `access_24_7` | — | No | N/A | `listing_ref` | |
| `bathroom_facilities` | — | No | N/A | `listing_ref` | |
| `room_size` | — | No | N/A | `listing_ref` | |
| `zoning_allows` | — | No | N/A | `listing_ref` | |
| `space_features` | — | No | N/A | `listing_ref` | |
| `neighboring_tenants` | — | No | N/A | `listing_ref` | |
| `shared_amenities` | — | No | N/A | `listing_ref` | |
| `common_areas_access` | — | No | N/A | `listing_ref` | |
| `common_areas_cleaning` | — | No | N/A | `listing_ref` | |
| `utilities` | — | No | N/A | `listing_ref` | |
| **— Storage —** | | | | | |
| `storage_space` | — | No | N/A | `listing_ref` | |
| `included_storage_space_res_both` | — | No | N/A | `listing_ref` | |
| `storage_space_res_both` | — | No | N/A | `listing_ref` | |
| `included_storage_space_res_single` | — | No | N/A | `listing_ref` | |
| `storage_space_res_single` | — | No | N/A | `listing_ref` | |
| `included_storage_space_com_entire` | — | No | N/A | `listing_ref` | |
| `storage_space_com_entire` | — | No | N/A | `listing_ref` | |
| `included_storage_space_com_single` | — | No | N/A | `listing_ref` | |
| `storage_space_com_single` | — | No | N/A | `listing_ref` | |
| **— Additional Property Preferences —** | | | | | |
| `tenant_require` | — | No | N/A | `listing_ref` | Labeled "Furnishings Needed" in TenantFieldMap. |
| `carport_needed` | — | No | N/A | `listing_ref` | `otherPairs` companion: `other_carport_needed`. |
| `garage_needed` | — | No | N/A | `listing_ref` | `otherPairs` companion: `other_garage_needed`. |
| `garage_parking_spaces` | — | No | N/A | `listing_ref` | |
| `garage_parking_spaces_option` | — | No | N/A | `listing_ref` | `otherPairs` companion: `other_parking_space_wrapper`. |
| `garage_parking_spaces_option_buyer` | — | No | N/A | `listing_ref` | Tenant-specific buyer parking variant. |
| `pool_needed` | — | No | N/A | `listing_ref` | |
| `pool_type` | — | No | N/A | `listing_ref` | |
| `view_preference` | — | No | N/A | `listing_ref` | `otherPairs` companion: `other_preferences`. |
| `real_estate_purchase` | — | No | N/A | `listing_ref` | |
| `leasing_55_plus` | — | No | N/A | `listing_ref` | |
| `non_negotiable_amenities` | — | No | N/A | `listing_ref` | `otherPairs` companion: `other_non_negotiable_amenities`. |
| `guests_allowed` | — | No | N/A | `listing_ref` | |
| `custom_enhancement` | — | No | N/A | `listing_ref` | Listing-side field. |
| **— Pets & Animals —** | | | | | |
| `pets` | — | No | N/A | `listing_ref` | |
| `number_of_pets` | — | No | N/A | `listing_ref` | |
| `breed_of_pets` | — | No | N/A | `listing_ref` | |
| `type_of_pets` | — | No | N/A | `listing_ref` | |
| `weight_of_pets` | — | No | N/A | `listing_ref` | |
| `service_animal` | — | No | N/A | `listing_ref` | |
| `other_services_enabled` | — | No | N/A | `listing_ref` | Listed in TenantFieldMap Pets section. |
| `support_animal` | — | No | N/A | `listing_ref` | |
| `emotional_support_animal` | — | No | N/A | `listing_ref` | |
| `has_breed_restrictions` | — | No | N/A | `listing_ref` | |
| `breed_restrictions` | — | No | N/A | `listing_ref` | |
| **— Screening —** | | | | | |
| `screening_concerns` | — | No | N/A | `listing_ref` | |
| `screening_concerns_explanation` | — | No | N/A | `listing_ref` | |
| `credit_scroe_rating` | — | No | N/A | `listing_ref` | |
| `monthly_income` | — | No | N/A | `listing_ref` | |
| `number_occupant` | — | No | N/A | `listing_ref` | |
| `prior_eviction` | — | No | N/A | `listing_ref` | |
| `eviction_explanation` | — | No | N/A | `listing_ref` | |
| `prior_felony` | — | No | N/A | `listing_ref` | |
| `prior_felony_explanation` | — | No | N/A | `listing_ref` | |
| **— Sale Terms —** | | | | | |
| `sale_provision` | — | No | N/A | `listing_ref` | `otherPairs` companion: `sale_provision_other`. |
| `sale_provision_assignment` | — | No | N/A | `listing_ref` | |
| `assignment_fee_type` | — | No | N/A | `listing_ref` | |
| `assignment_fee_amount` | — | No | N/A | `listing_ref` | |
| **— Budget & Financing —** | | | | | |
| `maximum_budget` | — | No | N/A | `listing_ref` | |
| `offered_financing` | — | No | N/A | `listing_ref` | `otherPairs` companion: `other_financing`. |
| `cash_budget` | — | No | N/A | `listing_ref` | |
| `pre_approved` | — | No | N/A | `listing_ref` | |
| `pre_approval_amount` | — | No | N/A | `listing_ref` | |
| `purchase_price` | — | No | N/A | `listing_ref` | |
| `budget` | — | No | N/A | `listing_ref` | |
| **— Down Payment —** | | | | | |
| `down_payment_type` | — | No | N/A | `listing_ref` | |
| `down_payment_amount` | — | No | N/A | `listing_ref` | |
| **— Seller Financing —** | | | | | |
| `seller_financing_type` | — | No | N/A | `listing_ref` | |
| `seller_financing_amount` | — | No | N/A | `listing_ref` | |
| `seller_down_payment_amount` | — | No | N/A | `listing_ref` | Tenant-flow-specific key. |
| `interest_rate` | — | No | N/A | `listing_ref` | |
| `loan_duration` | — | No | N/A | `listing_ref` | |
| `seller_amortization_type` | — | No | N/A | `listing_ref` | `otherPairs` companion: `seller_amortization_other`. |
| `seller_payment_frequency` | — | No | N/A | `listing_ref` | `otherPairs` companion: `seller_payment_frequency_other`. |
| `seller_late_fee_amount` | — | No | N/A | `listing_ref` | |
| `seller_late_fee_type` | — | No | N/A | `listing_ref` | |
| `prepayment_penalty` | — | No | N/A | `listing_ref` | |
| `prepayment_penalty_amount` | — | No | N/A | `listing_ref` | |
| `balloon_payment` | — | No | N/A | `listing_ref` | |
| `balloon_payment_amount` | — | No | N/A | `listing_ref` | |
| `balloon_payment_date` | — | No | N/A | `listing_ref` | |
| `seller_broker_leasing_fee` | — | No | N/A | `listing_ref` | |
| `seller_leasing_fee_type` | — | No | N/A | `listing_ref` | Listing side; TenantFieldMap Seller Financing section. |
| `seller_leasing_gross` | — | No | N/A | `listing_ref` | |
| `seller_leasing_each_rental` | — | No | N/A | `listing_ref` | |
| `seller_leasing_gross_flat_combo` | — | No | N/A | `listing_ref` | |
| `seller_leasing_gross_flat_net_combo` | — | No | N/A | `listing_ref` | |
| `seller_leasing_gross_month_rent` | — | No | N/A | `listing_ref` | |
| `seller_leasing_gross_no_of_months` | — | No | N/A | `listing_ref` | |
| `seller_leasing_gross_percentage_combo` | — | No | N/A | `listing_ref` | |
| `seller_leasing_gross_percentage_net_combo` | — | No | N/A | `listing_ref` | |
| `seller_leasing_gross_percentage_no_of_months` | — | No | N/A | `listing_ref` | |
| `seller_leasing_gross_purchase_fee_flat_amount` | — | No | N/A | `listing_ref` | |
| `seller_leasing_gross_purchase_fee_other` | — | No | N/A | `listing_ref` | |
| `seller_leasing_gross_rental` | — | No | N/A | `listing_ref` | |
| `seller_leasing_gross_ross_percentage_rent` | — | No | N/A | `listing_ref` | Typo in source ("ross"). |
| `seller_leasing_gross_sales_tax_first_month` | — | No | N/A | `listing_ref` | |
| `seller_leasing_gross_sales_tax_flat_free_gross` | — | No | N/A | `listing_ref` | |
| `seller_leasing_gross_sales_tax_option_gross` | — | No | N/A | `listing_ref` | |
| `seller_leasing_gross_other` | — | No | N/A | `listing_ref` | |
| **— Assumable Loan —** | | | | | |
| `assumable_terms` | — | No | N/A | `listing_ref` | |
| `assumable_loan_type` | — | No | N/A | `listing_ref` | Tenant-flow-specific key. |
| `max_assumable_rate` | — | No | N/A | `listing_ref` | |
| `assumable_fee_type` | — | No | N/A | `listing_ref` | |
| `assumable_fee_amount` | — | No | N/A | `listing_ref` | |
| `assumable_monthly_escrow` | — | No | N/A | `listing_ref` | |
| `assumable_loan_term_remaining` | — | No | N/A | `listing_ref` | |
| `assumable_loan_origination_date` | — | No | N/A | `listing_ref` | |
| `assumable_loan_servicer` | — | No | N/A | `listing_ref` | |
| `assumable_occupancy_requirement` | — | No | N/A | `listing_ref` | `otherPairs` companion: `assumable_occupancy_other`. |
| **— Gap / Additional Payments —** | | | | | |
| `max_monthly_payment` | — | No | N/A | `listing_ref` | |
| `gap_payment_type` | — | No | N/A | `listing_ref` | |
| `gap_payment_amount` | — | No | N/A | `listing_ref` | |
| `additional_cash` | — | No | N/A | `listing_ref` | |
| **— Exchange / Trade —** | | | | | |
| `exchange_item` | — | No | N/A | `listing_ref` | `otherPairs` companion: `other_exchange_item`. |
| `exchange_item_value` | — | No | N/A | `listing_ref` | |
| `exchange_item_condition` | — | No | N/A | `listing_ref` | |
| `value_determination` | — | No | N/A | `listing_ref` | |
| `exchange_transfer_method` | — | No | N/A | `listing_ref` | |
| `exchange_liens` | — | No | N/A | `listing_ref` | |
| `exchange_liens_details` | — | No | N/A | `listing_ref` | |
| `exchange_inspection_rights` | — | No | N/A | `listing_ref` | |
| **— Lease Option —** | | | | | |
| `interested_lease_option` | `interested_lease_option` | No | Yes | `new_offer_meta` | In TenantFieldMap; not confirmed in TenantAgentAuctionBid saveMeta. |
| `interested_lease_option_agreement` | `interested_lease_option_agreement` | No | Yes | `offer_meta` | Confirmed in TenantAgentAuctionBid. |
| `lease_option_price` | — | No | N/A | `listing_ref` | |
| `lease_option_terms` | — | No | N/A | `listing_ref` | |
| `lease_option_duration` | — | No | N/A | `listing_ref` | |
| `lease_option_payment` | — | No | N/A | `listing_ref` | |
| `lease_option_conditions` | — | No | N/A | `listing_ref` | |
| `has_option_fee` | — | No | N/A | `listing_ref` | |
| `option_fee_amount` | — | No | N/A | `listing_ref` | |
| `lease_option_fee_credit` | — | No | N/A | `listing_ref` | |
| `lease_option_fee_credit_percentage` | — | No | N/A | `listing_ref` | |
| `lease_option_maintenance` | — | No | N/A | `listing_ref` | |
| `lease_option_extension_terms` | — | No | N/A | `listing_ref` | |
| `lease_option_consideration` | — | No | N/A | `listing_ref` | |
| **— Lease Purchase —** | | | | | |
| `lease_purchase_price` | — | No | N/A | `listing_ref` | |
| `lease_purchase_terms` | — | No | N/A | `listing_ref` | |
| `lease_purchase_duration` | — | No | N/A | `listing_ref` | |
| `lease_purchase_payment` | — | No | N/A | `listing_ref` | |
| `lease_purchase_conditions` | — | No | N/A | `listing_ref` | |
| `lease_purchase_rent_credit` | — | No | N/A | `listing_ref` | |
| `lease_purchase_rent_credit_amount` | — | No | N/A | `listing_ref` | |
| `lease_purchase_rent_credit_amount_type` | — | No | N/A | `listing_ref` | Tenant-flow-specific key. |
| `lease_purchase_deposit` | — | No | N/A | `listing_ref` | |
| `lease_purchase_maintenance` | — | No | N/A | `listing_ref` | |
| `lease_purchase_extension_terms` | — | No | N/A | `listing_ref` | |
| `lease_purchase_option_fee` | — | No | N/A | `listing_ref` | |
| `lease_purchase_option_fee_amount` | — | No | N/A | `listing_ref` | |
| **— Cryptocurrency —** | | | | | |
| `cryptocurrency_type` | — | No | N/A | `listing_ref` | |
| `crypto_percentage` | — | No | N/A | `listing_ref` | |
| `cash_percentage_crypto` | — | No | N/A | `listing_ref` | |
| `crypto_transfer_timing` | — | No | N/A | `listing_ref` | `otherPairs` companion: `crypto_transfer_timing_other`. |
| `crypto_exchange_method` | — | No | N/A | `listing_ref` | |
| `crypto_custodian_wallet` | — | No | N/A | `listing_ref` | |
| `crypto_transaction_fees` | — | No | N/A | `listing_ref` | |
| **— NFT —** | | | | | |
| `nft_description` | — | No | N/A | `listing_ref` | |
| `nft_percentage` | — | No | N/A | `listing_ref` | |
| `cash_percentage_nft` | — | No | N/A | `listing_ref` | |
| `nft_valuation_method` | — | No | N/A | `listing_ref` | |
| `nft_transfer_method` | — | No | N/A | `listing_ref` | |
| `nft_gas_fees` | — | No | N/A | `listing_ref` | |
| **— Lease Details —** | | | | | |
| `lease_for` | — | No | N/A | `listing_ref` | `otherPairs` companion: `other_lease_for`. |
| `lease_by` | — | No | N/A | `listing_ref` | |
| `lease_date` | — | No | N/A | `listing_ref` | |
| **— Services —** | | | | | |
| `services` | `services` | No | Yes | `offer_meta` | JSON array. Confirmed in TenantAgentAuctionBid. |
| `custom_services` | `custom_services` | No | Yes | `new_offer_meta` | Commented-out saveMeta in TenantAgentAuctionBid. |
| `include_marketing_fee` | — | No | N/A | `listing_ref` | |
| `email_marketing_fee` | — | No | N/A | `listing_ref` | |
| `email_notifications_fee` | — | No | N/A | `listing_ref` | |
| `launch_ads` | — | No | N/A | `listing_ref` | |
| `launch_ads_fee` | — | No | N/A | `listing_ref` | |
| `market_groups` | — | No | N/A | `listing_ref` | |
| `market_groups_fee` | — | No | N/A | `listing_ref` | |
| `marketing_materials_fee` | — | No | N/A | `listing_ref` | |
| `mls_filter_fee` | — | No | N/A | `listing_ref` | |
| `off_market_search_fee` | — | No | N/A | `listing_ref` | |
| `promote_social` | — | No | N/A | `listing_ref` | |
| `promote_social_fee` | — | No | N/A | `listing_ref` | |
| `list_criteria` | — | No | N/A | `listing_ref` | |
| `list_criteria_fee` | — | No | N/A | `listing_ref` | |
| `flat_fee_services` | — | No | N/A | `listing_ref` | |
| `schedule_showings` | — | No | N/A | `listing_ref` | |
| `schedule_showings_fee` | — | No | N/A | `listing_ref` | |
| `number_of_showings_to_schedule` | — | No | N/A | `listing_ref` | |
| `attend_showings` | — | No | N/A | `listing_ref` | |
| `number_of_showings_to_attend` | — | No | N/A | `listing_ref` | |
| `attend_showings_fee` | — | No | N/A | `listing_ref` | |
| `provide_virtual_tours` | — | No | N/A | `listing_ref` | |
| `number_of_virtual_tours` | — | No | N/A | `listing_ref` | |
| `virtual_tours_fee` | — | No | N/A | `listing_ref` | |
| `assist_application` | — | No | N/A | `listing_ref` | |
| `assist_application_fee` | — | No | N/A | `listing_ref` | |
| `collect_documents` | — | No | N/A | `listing_ref` | |
| `collect_documents_fee` | — | No | N/A | `listing_ref` | |
| `submit_application` | — | No | N/A | `listing_ref` | |
| `submit_application_fee` | — | No | N/A | `listing_ref` | |
| `review_lease` | — | No | N/A | `listing_ref` | |
| `review_lease_fee` | — | No | N/A | `listing_ref` | |
| `provide_lease_form` | — | No | N/A | `listing_ref` | |
| `provide_lease_form_fee` | — | No | N/A | `listing_ref` | |
| `coordinate_signing` | — | No | N/A | `listing_ref` | |
| `coordinate_signing_fee` | — | No | N/A | `listing_ref` | |
| `prepare_application_fee` | — | No | N/A | `listing_ref` | |
| `move_in_inspection_fee` | — | No | N/A | `listing_ref` | |
| `moving_resources_fee` | — | No | N/A | `listing_ref` | |
| `short_term_housing_fee` | — | No | N/A | `listing_ref` | |
| `rental_rights_fee` | — | No | N/A | `listing_ref` | |
| `lease_advice_fee` | — | No | N/A | `listing_ref` | |
| `neighborhood_insights_fee` | — | No | N/A | `listing_ref` | |
| `neighborhood_marketing_fee` | — | No | N/A | `listing_ref` | |
| `neighborhood_materials_fee` | — | No | N/A | `listing_ref` | |
| `total_marketing_fee` | `total_marketing_fee` | No | Yes | `offer_meta` | Confirmed in TenantAgentAuctionBid saveMeta. |
| `total_flat_fee` | `total_flat_fee` | No | Yes | `offer_meta` | Confirmed in TenantAgentAuctionBid saveMeta. |
| `staging_duration` | — | No | N/A | `listing_ref` | |
| `open_house_count` | — | No | N/A | `listing_ref` | |
| `virtual_showings_count` | — | No | N/A | `listing_ref` | |
| `photo_enhancements` | `photo_enhancements` | No | Yes | `new_offer_meta` | In TenantFieldMap Services; not confirmed in TenantAgentAuctionBid saveMeta. |
| **— Broker Compensation & Agency Agreement —** | | | | | |
| `commission_structure` | `commission_structure` | No | Yes | `offer_meta` | Confirmed in TenantAgentAuctionBid. |
| `commission_structure_type` | `commission_structure_type` | No | Yes | `new_offer_meta` | In TenantFieldMap; not confirmed in bid saveMeta. |
| `commission_structure_type_fee_flat` | `commission_structure_type_fee_flat` | No | Yes | `new_offer_meta` | |
| `commission_structure_type_fee_flat_combo` | `commission_structure_type_fee_flat_combo` | No | Yes | `new_offer_meta` | |
| `commission_structure_type_fee_percentage` | `commission_structure_type_fee_percentage` | No | Yes | `new_offer_meta` | |
| `commission_structure_type_fee_percentage_combo` | `commission_structure_type_fee_percentage_combo` | No | Yes | `new_offer_meta` | |
| `commission_structure_type_fee_other` | `commission_structure_type_fee_other` | No | Yes | `new_offer_meta` | |
| `lease_fee_type` | `lease_fee_type` | No | Yes | `offer_meta` | Confirmed. |
| `lease_fee_flat` | `lease_fee_flat` | No | Yes | `offer_meta` | Confirmed. |
| `lease_fee_percentage` | `lease_fee_percentage` | No | Yes | `offer_meta` | Confirmed. |
| `lease_fee_months` | `lease_fee_months` | No | Yes | `new_offer_meta` | |
| `lease_fee_percentage_monthly_rent` | `lease_fee_percentage_monthly_rent` | No | Yes | `offer_meta` | Confirmed. |
| `lease_fee_percentage_monthly_number` | `lease_fee_percentage_monthly_number` | No | Yes | `offer_meta` | Confirmed. |
| `lease_fee_flat_combo` | `lease_fee_flat_combo` | No | Yes | `offer_meta` | Confirmed. |
| `lease_fee_percentage_combo` | `lease_fee_percentage_combo` | No | Yes | `offer_meta` | Confirmed. |
| `lease_fee_flat_combo_net` | `lease_fee_flat_combo_net` | No | Yes | `offer_meta` | Confirmed. |
| `lease_fee_percentage_combo_net` | `lease_fee_percentage_combo_net` | No | Yes | `offer_meta` | Confirmed. |
| `lease_fee_percentage_net` | `lease_fee_percentage_net` | No | Yes | `offer_meta` | Confirmed. |
| `lease_fee_flat_type` | `lease_fee_flat_type` | No | Yes | `offer_meta` | Confirmed in TenantAgentAuctionBid. |
| `lease_fee_other` | `lease_fee_other` | No | Yes | `offer_meta` | Confirmed. |
| `purchase_fee_type` | `purchase_fee_type` | No | Yes | `offer_meta` | Confirmed. |
| `interested_purchase_fee_type` | `interested_purchase_fee_type` | No | Yes | `offer_meta` | Confirmed. |
| `purchase_fee_percentage` | `purchase_fee_percentage` | No | Yes | `offer_meta` | Confirmed. |
| `purchase_fee_flat` | `purchase_fee_flat` | No | Yes | `offer_meta` | Confirmed. |
| `purchase_fee_flat_type` | `purchase_fee_flat_type` | No | Yes | `offer_meta` | Confirmed in TenantAgentAuctionBid. |
| `purchase_fee_percentage_combo` | `purchase_fee_percentage_combo` | No | Yes | `offer_meta` | Confirmed. |
| `purchase_fee_flat_combo` | `purchase_fee_flat_combo` | No | Yes | `offer_meta` | Confirmed. |
| `purchase_fee_other` | `purchase_fee_other` | No | Yes | `offer_meta` | Confirmed. |
| `purchase_fee_flat_commercial` | `purchase_fee_flat_commercial` | No | Yes | `new_offer_meta` | In TenantFieldMap; not confirmed in bid saveMeta. |
| `purchase_fee_other_commercial` | `purchase_fee_other_commercial` | No | Yes | `new_offer_meta` | |
| `purchase_fee_net_aggregate` | `purchase_fee_net_aggregate` | No | Yes | `new_offer_meta` | |
| `purchase_fee_gross_rent` | `purchase_fee_gross_rent` | No | Yes | `new_offer_meta` | |
| `purchase_fee_monthly_percentage` | `purchase_fee_monthly_percentage` | No | Yes | `new_offer_meta` | |
| `purchase_fee_months` | `purchase_fee_months` | No | Yes | `new_offer_meta` | |
| `purchase_fee_purchase_price` | `purchase_fee_purchase_price` | No | Yes | `new_offer_meta` | |
| `purchase_fee_rental_period` | `purchase_fee_rental_period` | No | Yes | `new_offer_meta` | |
| `lease_option_fee_type` | `lease_option_fee_type` | No | Yes | `new_offer_meta` | |
| `lease_option_fee_flat` | `lease_option_fee_flat` | No | Yes | `new_offer_meta` | |
| `lease_option_fee_flat_combo` | `lease_option_fee_flat_combo` | No | Yes | `new_offer_meta` | |
| `lease_option_fee_percentage` | `lease_option_fee_percentage` | No | Yes | `new_offer_meta` | |
| `lease_option_fee_percentage_combo` | `lease_option_fee_percentage_combo` | No | Yes | `new_offer_meta` | |
| `lease_option_fee_other` | `lease_option_fee_other` | No | Yes | `new_offer_meta` | |
| `landlord_broker_flate_fee` | `landlord_broker_flate_fee` | No | Yes | `new_offer_meta` | In TenantFieldMap; not confirmed in TenantAgentAuctionBid. |
| `landlord_broker_flate_fee_type` | `landlord_broker_flate_fee_type` | No | Yes | `new_offer_meta` | Tenant-only variant. |
| `landlord_broker_percentage_price` | `landlord_broker_percentage_price` | No | Yes | `new_offer_meta` | |
| `landlord_broker_dollar_price` | `landlord_broker_dollar_price` | No | Yes | `new_offer_meta` | |
| `landlord_broker_purchase_price` | `landlord_broker_purchase_price` | No | Yes | `new_offer_meta` | |
| `landlord_broker_other` | `landlord_broker_other` | No | Yes | `new_offer_meta` | |
| `tenant_broker_commission_structure` | `tenant_broker_commission_structure` | No | Yes | `new_offer_meta` | In TenantFieldMap; not confirmed in bid saveMeta. |
| `tenant_broker_fee_structure` | `tenant_broker_fee_structure` | No | Yes | `new_offer_meta` | |
| `tenant_broker_percentage` | `tenant_broker_percentage` | No | Yes | `new_offer_meta` | |
| `tenant_broker_flat_fee` | `tenant_broker_flat_fee` | No | Yes | `new_offer_meta` | |
| `tenant_broker_first_month_rent` | `tenant_broker_first_month_rent` | No | Yes | `new_offer_meta` | |
| `tenant_broker_gross_lease` | `tenant_broker_gross_lease` | No | Yes | `new_offer_meta` | |
| `tenant_broker_other` | `tenant_broker_other` | No | Yes | `new_offer_meta` | |
| `expansion_commission_percentage` | `expansion_commission_percentage` | No | Yes | `new_offer_meta` | In TenantFieldMap; not confirmed in bid saveMeta. |
| `renewal_fee_type` | `renewal_fee_type` | No | Yes | `new_offer_meta` | |
| `renewal_fee_percentage` | `renewal_fee_percentage` | No | Yes | `new_offer_meta` | |
| `renewal_fee_first_month` | `renewal_fee_first_month` | No | Yes | `new_offer_meta` | |
| `renewal_fee_flat_free` | `renewal_fee_flat_free` | No | Yes | `new_offer_meta` | |
| `renewal_fee_lease_value` | `renewal_fee_lease_value` | No | Yes | `new_offer_meta` | |
| `renewal_fee_no_of_months` | `renewal_fee_no_of_months` | No | Yes | `new_offer_meta` | |
| `broker_fee_timing` | `broker_fee_timing` | No | Yes | `offer_meta` | Confirmed in TenantAgentAuctionBid. `otherPairs` companion: `broker_fee_timing_other`. |
| `broker_fee_days_after_lease` | `broker_fee_days_after_lease` | No | Yes | `offer_meta` | Confirmed. |
| `broker_fee_days_after_rent` | `broker_fee_days_after_rent` | No | Yes | `offer_meta` | Confirmed. |
| `broker_fee_days_from_rent` | `broker_fee_days_from_rent` | No | Yes | `offer_meta` | Confirmed. |
| `protection_period` | `protection_period` | No | Yes | `offer_meta` | Confirmed. |
| `early_termination_fee_option` | `early_termination_fee_option` | No | Yes | `offer_meta` | Confirmed. |
| `lease_type` | `lease_type` | No | Yes | `offer_meta` | Confirmed. |
| `lease_value` | `lease_value` | No | Yes | `offer_meta` | Confirmed. |
| `purchase_type` | `purchase_type` | No | Yes | `offer_meta` | Confirmed. |
| `purchase_value` | `purchase_value` | No | Yes | `offer_meta` | Confirmed. |
| `retainer_fee_option` | `retainer_fee_option` | No | Yes | `offer_meta` | Confirmed in TenantAgentAuctionBid. |
| `retainer_fee_application` | `retainer_fee_application` | No | Yes | `offer_meta` | Confirmed. |
| `agency_agreement_timeframe` | `agency_agreement_timeframe` | No | Yes | `offer_meta` | Confirmed. |
| `agency_agreement_custom` | `agency_agreement_custom` | No | Yes | `offer_meta` | Confirmed. |
| `brokerage_relationship` | `brokerage_relationship` | No | Yes | `offer_meta` | Confirmed. |
| `additional_details_broker` | `additional_details_broker` | No | Yes | `offer_meta` | Confirmed. |
| **— Additional Details —** | | | | | |
| `additional_details` | `additional_details` | No | Yes | `offer_meta` | Confirmed. |
| `video_link` | — | No | N/A | `listing_ref` | |
| **— Meeting Details —** | | | | | |
| `person_meeting` | — | No | N/A | `listing_ref` | |
| `meeting_details_first_name` | — | No | N/A | `listing_ref` | |
| `meeting_details_last_name` | — | No | N/A | `listing_ref` | |
| `meeting_details_phone` | — | No | N/A | `listing_ref` | |
| `meeting_details_email` | — | No | N/A | `listing_ref` | |
| `meeting_details_meeting_date` | — | No | N/A | `listing_ref` | |
| `meeting_details_meeting_time` | — | No | N/A | `listing_ref` | |
| `meeting_details_time_zone` | — | No | N/A | `listing_ref` | |
| `meeting_details_instructions` | — | No | N/A | `listing_ref` | |
| `meeting_details_additional_details` | — | No | N/A | `listing_ref` | |
| `service_completion_date` | — | No | N/A | `listing_ref` | |
| `service_completion_time` | — | No | N/A | `listing_ref` | |
| `service_time_zone` | — | No | N/A | `listing_ref` | |
| **— Contact Information —** | | | | | |
| `first_name` | — | No | N/A | `listing_ref` | |
| `last_name` | — | No | N/A | `listing_ref` | |
| `phone_number` | — | No | N/A | `listing_ref` | |
| `email` | — | No | N/A | `listing_ref` | |

---

## Section 5 — Cross-Flow Summary Lists

### 5A. Reusable Offer Terms

Meta keys that share the **same key name** on both the listing and confirmed offer/bid side and can be directly read or copied without renaming. These appear in at least one FieldMap AND in a confirmed `saveMeta()` call in the corresponding bid Livewire component.

| Meta Key | Appears In Flows | Notes |
|---|---|---|
| `services` | All four | Stored as JSON array; content differs from listing's fee budget fields. |
| `additional_details` | All four | Agent's own free-text notes. |
| `commission_structure` | Seller, Tenant | Core bid field in Seller and Tenant flows. |
| `purchase_fee_type` | Seller, Buyer, Landlord | Core compensation bid field. |
| `purchase_fee_flat` | Seller, Buyer, Landlord | |
| `purchase_fee_percentage` | Seller, Buyer, Landlord | |
| `purchase_fee_percentage_combo` | Seller, Buyer, Landlord | |
| `purchase_fee_flat_combo` | Seller, Buyer, Landlord | |
| `purchase_fee_other` | Seller, Buyer, Landlord | |
| `lease_fee_type` | Buyer, Landlord, Tenant | |
| `lease_fee_flat` | Buyer, Landlord, Tenant | |
| `lease_fee_percentage` | Buyer, Landlord, Tenant | |
| `lease_fee_percentage_monthly_rent` | Buyer, Landlord, Tenant | |
| `lease_fee_percentage_monthly_number` | Buyer, Tenant | |
| `lease_fee_flat_combo` | Buyer, Landlord, Tenant | |
| `lease_fee_percentage_combo` | Buyer, Landlord, Tenant | |
| `lease_fee_flat_combo_net` | Buyer, Tenant | |
| `lease_fee_percentage_combo_net` | Buyer, Tenant | |
| `lease_fee_percentage_net` | Buyer, Tenant | |
| `lease_fee_other` | Buyer, Landlord, Tenant | |
| `interested_lease_option_agreement` | Seller, Buyer, Landlord, Tenant | |
| `interested_lease_option` | Buyer | |
| `lease_type` | Seller, Buyer, Landlord, Tenant | Internal type selector. |
| `lease_value` | Seller, Buyer, Landlord, Tenant | |
| `purchase_type` | Seller, Buyer, Landlord, Tenant | |
| `purchase_value` | Seller, Buyer, Landlord, Tenant | |
| `protection_period` | Seller, Buyer, Landlord, Tenant | |
| `early_termination_fee_option` | Seller, Buyer, Landlord, Tenant | |
| `agency_agreement_timeframe` | Seller, Buyer, Landlord, Tenant | |
| `agency_agreement_custom` | Seller, Buyer, Landlord, Tenant | |
| `brokerage_relationship` | Seller, Buyer, Tenant | |
| `additional_details_broker` | Buyer, Tenant | |
| `retainer_fee_option` | Seller, Buyer, Tenant | |
| `retainer_fee_application` | Seller, Buyer, Tenant | |
| `total_marketing_fee` | Buyer, Tenant | |
| `total_flat_fee` | Buyer, Tenant | |
| `photo_enhancements` | Seller, Landlord | |
| `purchase_fee_net_aggregate` | Landlord | |
| `purchase_fee_gross_rent` | Landlord | |
| `purchase_fee_monthly_percentage` | Landlord | |
| `purchase_fee_months` | Landlord | |
| `purchase_fee_purchase_price` | Landlord | |
| `purchase_fee_rental_period` | Landlord | |
| `purchase_fee_flat_commercial` | Landlord | |
| `purchase_fee_other_commercial` | Landlord | |
| `landlord_broker_flate_fee` | Landlord | |
| `landlord_broker_percentage_price` | Landlord | |
| `landlord_broker_dollar_price` | Landlord | |
| `landlord_broker_purchase_price` | Landlord | |
| `landlord_broker_other` | Landlord | |
| `renewal_fee_type` | Landlord | |
| `renewal_fee_percentage` | Landlord | |
| `renewal_fee_first_month` | Landlord | |
| `renewal_fee_flat_free` | Landlord | |
| `renewal_fee_lease_value` | Landlord | |
| `renewal_fee_no_of_months` | Landlord | |
| `renewal_fee_sales_tax_first_month` | Landlord | |
| `renewal_fee_sales_tax_flat_fee` | Landlord | |
| `renewal_fee_sales_tax_lease_value` | Landlord | |
| `renewal_fee_custom` | Landlord | |
| `sales_tax_option_flat` | Landlord | |
| `sales_tax_option_gross` | Landlord | |
| `sales_tax_option_monthly` | Landlord | |
| `split_payment_due` | Landlord | `otherPairs` companion: `split_payment_due_other`. |
| `broker_fee_timing` | Landlord, Tenant | `otherPairs` companion: `broker_fee_timing_other`. |
| `broker_fee_days_after_lease` | Landlord, Tenant | |
| `broker_fee_days_after_rent` | Landlord, Tenant | |
| `broker_fee_days_from_rent` | Landlord, Tenant | |
| `broker_fee_days_after_due_event` | Landlord | |
| `tenant_broker_commission_structure` | Landlord | |
| `tenant_broker_fee_structure` | Landlord | |
| `tenant_broker_percentage` | Landlord | |
| `tenant_broker_flat_fee` | Landlord | |
| `tenant_broker_first_month_rent` | Landlord | |
| `tenant_broker_gross_lease` | Landlord | |
| `tenant_broker_other` | Landlord | |
| `expansion_commission_percentage` | Landlord | |
| `interested_in_selling` | Landlord | Bid-side field; agent proposes sale services. |
| `interested_in_selling_type` | Landlord | |
| `interested_in_property_management` | Landlord | |
| `interested_in_property_management_fee` | Landlord | |
| `interested_in_property_management_fee_gross_lease` | Landlord | |
| `interested_in_property_management_fee_rental_periord` | Landlord | |
| `interested_in_property_management_fee_flate_free` | Landlord | |
| `interested_in_property_management_fee_other` | Landlord | |
| `lease_fee_flat_type` | Tenant | Tenant-specific type selector. |
| `purchase_fee_flat_type` | Tenant | |
| `interested_purchase_fee_type` | Buyer, Tenant | |
| `commission_structure_type` | Seller | |
| `commission_structure_type_fee_flat` | Seller | |
| `commission_structure_type_fee_flat_combo` | Seller | |
| `commission_structure_type_fee_percentage` | Seller | |
| `commission_structure_type_fee_percentage_combo` | Seller | |
| `commission_structure_type_fee_other` | Seller | |

---

### 5B. Listing-Only Fields

Fields that are administrative, operational, or property-description fields that must **never** appear on offer/bid forms. These are displayed as read-only context to agents but are not stored on bid records.

**Administrative / Operational:**
`service_type`, `listing_status`, `auction_type`, `working_with_agent`, `current_status`, `listing_date`, `desired_agent_hire_date`, `expiration_date`, `auction_time`, `agent_bid_visibility`, `meeting_Preference`

**Location:**
`address`, `property_city`, `property_county`, `property_state`, `property_zip`, `cities`, `counties`, `zipCodes`, `state`

**Property Attributes:**
`property_type`, `property_items`, `leasing_space`, `leasing_spaces_tenant`, `condition_prop`, `condition_prop_buyer`, `bedrooms`, `bathrooms`, `minimum_heated_square`, `minimum_leaseable`, `total_square_feet`, `sqft_heated_source`, `min_acreage`, `total_acreage`, `appliances`, `property_criteria`, `unit_size`, `preferance_details`, `number_of_unit`, `number_of_unit_type`, `unit_number`, `unit_buildings`, `unit_type_configurations`, `business_type`, `business_type_selected`

**Income & Investment:**
`minimum_annual_net_income`, `minimum_cap_rate`, `assets`

**Leasing Terms (Landlord/Tenant listings):**
`occupant_status`, `occupant_types`, `occupant_types_tenant`, `occupant_tenant`, `leasing_space_property`, `leasing_spaces`, `desired_rental_amount`, `desired_rental_amount_tenant`, `lease_amount_frequency`, `desired_lease_length`, `custom_lease_term`, `terms_of_lease`, `rent_includes`, `tenant_pays`, `owner_pays`, `restrictions`, `maintenance_by`, `maintenance_handler`, `maintenance_response_time`, `occupied_until`, `occupancy_status`, `target_closing_date`

**Commercial Property Details:**
`building_hours`, `access_24_7`, `bathroom_facilities`, `room_size`, `zoning_allows`, `space_features`, `neighboring_tenants`, `shared_amenities`, `common_areas_access`, `common_areas_cleaning`, `utilities`

**Storage:**
`storage_space`, `included_storage_space_res_both`, `storage_space_res_both`, `included_storage_space_res_single`, `storage_space_res_single`, `included_storage_space_com_entire`, `storage_space_com_entire`, `included_storage_space_com_single`, `storage_space_com_single`

**Property Preferences:**
`tenant_require`, `carport_needed`, `garage_needed`, `garage_parking_spaces`, `garage_parking_spaces_option`, `garage_parking_spaces_option_buyer`, `pool_needed`, `pool_type`, `view_preference`, `real_estate_purchase`, `leasing_55_plus`, `guests_allowed`, `non_negotiable_amenities`

**Pets / Screening:**
`pets`, `number_of_pets`, `breed_of_pets`, `type_of_pets`, `weight_of_pets`, `number_occupant`, `service_animal`, `support_animal`, `emotional_support_animal`, `has_breed_restrictions`, `breed_restrictions`, `screening_concerns`, `screening_concerns_explanation`, `credit_scroe_rating`, `monthly_income`, `prior_eviction`, `eviction_explanation`, `prior_felony`, `prior_felony_explanation`

**Buyer Financial / Sale Terms:**
`sale_provision`, `sale_provision_assignment`, `assignment_fee_type`, `assignment_fee_amount`, `buyer_sell_contract`, `maximum_budget`, `offered_financing`, `cash_budget`, `pre_approved`, `pre_approval_amount`, `purchase_price`, `budget`, `down_payment_type`, `down_payment_amount`, `seller_financing_type`, `seller_financing_amount`, `interest_rate`, `loan_duration`, `prepayment_penalty`, `prepayment_penalty_amount`, `balloon_payment`, `balloon_payment_amount`, `balloon_payment_date`, `seller_amortization_type`, `seller_payment_frequency`, `seller_late_fee_amount`, `seller_late_fee_type`, `seller_down_payment_amount`, `assumable_terms`, `assumable_loan_type`, `max_assumable_rate`, `assumable_fee_type`, `assumable_fee_amount`, `assumable_monthly_escrow`, `assumable_loan_term_remaining`, `assumable_loan_origination_date`, `assumable_loan_servicer`, `assumable_occupancy_requirement`, `max_monthly_payment`, `gap_payment_type`, `gap_payment_amount`, `additional_cash`

**Buyer Additional Purchase Terms:**
`earnest_money_type`, `earnest_money_amount`, `earnest_money_timing`, `due_diligence_yn`, `inspection_period_days`, `inspection_period_other`, `inspection_contingency_buyer`, `appraisal_contingency_buyer`, `appraisal_contingency_days`, `financing_contingency_buyer`, `financing_contingency_period`, `home_sale_contingency`, `home_sale_contingency_address`, `home_sale_contingency_date`, `home_sale_contingency_under_contract`, `home_sale_contingency_details`, `seller_contribution`, `seller_contribution_details`, `possession_preference`, `possession_preference_other`, `possession_details`, `home_warranty_requested`, `home_warranty_details`, `as_is_purchase`, `property_inclusions`, `property_exclusions`, `closing_cost_responsibility`, `additional_purchase_terms`

**Exchange / Trade / Alternative Finance:**
`exchange_item`, `exchange_item_value`, `exchange_item_condition`, `value_determination`, `exchange_transfer_method`, `exchange_liens`, `exchange_liens_disclosure`, `exchange_liens_details`, `exchange_inspection_rights`, `cryptocurrency_type`, `crypto_percentage`, `cash_percentage_crypto`, `crypto_transfer_timing`, `crypto_exchange_method`, `crypto_custodian_wallet`, `crypto_transaction_fees`, `nft_description`, `nft_percentage`, `cash_percentage_nft`, `nft_valuation_method`, `nft_transfer_method`, `nft_gas_fees`

**Lease Option / Purchase (listing terms):**
`lease_option_price`, `lease_option_terms`, `lease_option_duration`, `lease_option_payment`, `lease_option_conditions`, `has_option_fee`, `option_fee_amount`, `lease_option_fee_credit`, `lease_option_fee_credit_percentage`, `lease_option_maintenance`, `lease_option_extension_terms`, `lease_option_consideration`, `seller_lease_option_fee_credit`, `seller_lease_option_fee_credit_percent`, `seller_lease_option_maintenance`, `seller_lease_option_extension_terms`, `lease_purchase_price`, `lease_purchase_terms`, `lease_purchase_duration`, `lease_purchase_payment`, `lease_purchase_conditions`, `lease_purchase_rent_credit`, `lease_purchase_rent_credit_amount`, `lease_purchase_rent_credit_amount_type`, `lease_purchase_deposit`, `lease_purchase_maintenance`, `lease_purchase_extension_terms`, `lease_purchase_option_fee`, `lease_purchase_option_fee_amount`, `seller_lease_purchase_rent_credit`, `seller_lease_purchase_rent_credit_type`, `seller_lease_purchase_rent_credit_amount`, `seller_lease_purchase_deposit`, `seller_lease_purchase_maintenance`, `seller_lease_purchase_extension_terms`

**Seller Financing Detail (Tenant listing):**
`seller_broker_leasing_fee`, `seller_leasing_fee_type`, `seller_leasing_gross`, `seller_leasing_each_rental`, `seller_leasing_gross_flat_combo`, `seller_leasing_gross_flat_net_combo`, `seller_leasing_gross_month_rent`, `seller_leasing_gross_no_of_months`, `seller_leasing_gross_percentage_combo`, `seller_leasing_gross_percentage_net_combo`, `seller_leasing_gross_percentage_no_of_months`, `seller_leasing_gross_purchase_fee_flat_amount`, `seller_leasing_gross_purchase_fee_other`, `seller_leasing_gross_rental`, `seller_leasing_gross_ross_percentage_rent`, `seller_leasing_gross_sales_tax_first_month`, `seller_leasing_gross_sales_tax_flat_free_gross`, `seller_leasing_gross_sales_tax_option_gross`, `seller_leasing_gross_other`

**Service Fee Budget Fields (listing specifies client budget, not bid storage):**
`include_marketing_fee`, `email_marketing_fee`, `email_notifications_fee`, `launch_ads`, `launch_ads_fee`, `market_groups`, `market_groups_fee`, `marketing_materials_fee`, `mls_filter_fee`, `off_market_search_fee`, `promote_social`, `promote_social_fee`, `neighborhood_marketing_fee`, `neighborhood_materials_fee`, `flat_fee_services`, `schedule_showings`, `schedule_showings_fee`, `number_of_showings_to_schedule`, `attend_showings`, `attend_showings_fee`, `number_of_showings_to_attend`, `provide_virtual_tours`, `virtual_tours_fee`, `number_of_virtual_tours`, `assist_application`, `assist_application_fee`, `collect_documents`, `collect_documents_fee`, `submit_application`, `submit_application_fee`, `review_lease`, `review_lease_fee`, `provide_lease_form`, `provide_lease_form_fee`, `coordinate_signing`, `coordinate_signing_fee`, `prepare_application_fee`, `move_in_inspection_fee`, `moving_resources_fee`, `short_term_housing_fee`, `rental_rights_fee`, `lease_advice_fee`, `neighborhood_insights_fee`, `list_criteria`, `list_criteria_fee`, `staging_duration`, `open_house_count`, `virtual_showings_count`

**Meeting Details:**
`person_meeting`, `meeting_details_first_name`, `meeting_details_last_name`, `meeting_details_phone`, `meeting_details_email`, `meeting_details_meeting_date`, `meeting_details_meeting_time`, `meeting_details_time_zone`, `meeting_details_instructions`, `meeting_details_additional_details`, `service_completion_date`, `service_completion_time`, `service_time_zone`

**Contact Information (client-side):**
`first_name`, `last_name`, `phone_number`, `email`

**Listing Videos:**
`video_link` (all flows)

---

### 5C. Fields Requiring New Offer Storage

Fields that appear in a FieldMap under the Broker Compensation or Services sections (making them semantically offer-relevant), but have **no confirmed `saveMeta()` call** in any current bid Livewire component. Implementing these requires new EAV meta keys on the relevant bid table.

**Seller Flow:**
- `total_marketing_fee`, `total_flat_fee`
- `custom_services`

**Buyer Flow:**
- `custom_services`
- `lease_option_fee_type`, `lease_option_fee_flat`, `lease_option_fee_flat_combo`, `lease_option_fee_percentage`, `lease_option_fee_percentage_combo`, `lease_option_fee_other`
- `purchase_fee_flat_exercised`, `purchase_pice_commercial`
- `lease_fee_months`

**Landlord Flow:**
- `custom_services`
- `total_marketing_fee`, `total_flat_fee`
- `lease_fee_flat_type`, `lease_fee_months`
- `lease_option_fee_type`, `lease_option_fee_flat`, `lease_option_fee_percentage`, `lease_option_fee_other`
- `net_aggregate_rent`, `month_percentage_rent`, `no_of_months`, `flat_fee`, `gross_percentage_rent`
- `tenant_broker_commission_percentage`
- `expansion_commission_type`, `expansion_gross_percentage`, `expansion_first_month_percentage`, `expansion_flat_fee`, `expansion_custom_commission`
- `retainer_fee_option`, `retainer_fee_application`
- `brokerage_relationship`
- `additional_details_broker`
- `purchase_fee_flat_type`

**Tenant Flow:**
- `custom_services`
- `photo_enhancements`
- `commission_structure_type`, `commission_structure_type_fee_flat`, `commission_structure_type_fee_flat_combo`, `commission_structure_type_fee_percentage`, `commission_structure_type_fee_percentage_combo`, `commission_structure_type_fee_other`
- `lease_fee_months`
- `lease_option_fee_type`, `lease_option_fee_flat`, `lease_option_fee_flat_combo`, `lease_option_fee_percentage`, `lease_option_fee_percentage_combo`, `lease_option_fee_other`
- `landlord_broker_flate_fee`, `landlord_broker_flate_fee_type`, `landlord_broker_percentage_price`, `landlord_broker_dollar_price`, `landlord_broker_purchase_price`, `landlord_broker_other`
- `tenant_broker_commission_structure`, `tenant_broker_fee_structure`, `tenant_broker_percentage`, `tenant_broker_flat_fee`, `tenant_broker_first_month_rent`, `tenant_broker_gross_lease`, `tenant_broker_other`
- `expansion_commission_percentage`
- `renewal_fee_type`, `renewal_fee_percentage`, `renewal_fee_first_month`, `renewal_fee_flat_free`, `renewal_fee_lease_value`, `renewal_fee_no_of_months`
- `purchase_fee_flat_commercial`, `purchase_fee_other_commercial`, `purchase_fee_net_aggregate`, `purchase_fee_gross_rent`, `purchase_fee_monthly_percentage`, `purchase_fee_months`, `purchase_fee_purchase_price`, `purchase_fee_rental_period`
- `interested_lease_option`

---

### 5D. Fields Already Supported by Counter Structures

Fields that participate in the existing counter-offer negotiation flows via the bid counter Livewire components (`BuyerAgentAuctionBidCounter`, `LandlordAgentAuctionBidCounter`, `TenantAgentAuctionBidCounter`) and the `accepted_bid_summaries` / `counter_bid_metas` EAV system.

The counter components allow the client and agent to negotiate on compensation and service terms. The following field categories are handled in counter flows:

| Category | Representative Fields | Counter Component |
|---|---|---|
| Lease fee compensation | `lease_fee_type`, `lease_fee_flat`, `lease_fee_percentage`, `lease_fee_flat_combo`, `lease_fee_percentage_combo` | Buyer, Landlord, Tenant counter |
| Purchase fee compensation | `purchase_fee_type`, `purchase_fee_flat`, `purchase_fee_percentage` | Buyer, Landlord counter |
| Commission structure | `commission_structure`, `commission_structure_type`, `commission_structure_type_fee_*` | Seller counter (implied) |
| Agency agreement | `protection_period`, `agency_agreement_timeframe`, `agency_agreement_custom` | All four counter components |
| Early termination / retainer | `early_termination_fee_option`, `retainer_fee_option`, `retainer_fee_application` | Buyer, Tenant counter |
| Landlord broker fees | `landlord_broker_flate_fee`, `landlord_broker_percentage_price`, `renewal_fee_*` | Landlord counter |
| Property management | `interested_in_property_management`, `interested_in_property_management_fee` | Landlord counter |
| Tenant broker fees | `tenant_broker_commission_structure`, `tenant_broker_fee_structure`, `tenant_broker_percentage` | Landlord counter |
| Services | `services` (JSON), `other_services` | All four counter components |
| Summary record | `accepted_bid_summaries` table | All four flows; stores accepted state for PDF generation |

Counter-bid meta is stored in `counter_bid_metas` (EAV). The `accepted_bid_summaries` table stores the finalized agreed terms and is the source of truth for accepted bid PDFs. PDF cache invalidation is tied to any change in `accepted_bid_summaries` rows.

---

### 5E. Potential Future Offer Fields

Field names that are conceptually useful on the offer/bid side but do **not currently exist** anywhere in the codebase — not in any FieldMap, not in any `saveMeta()` call, and not in any migration. These are **candidates only**, not confirmed entries.

| Candidate Key | Rationale |
|---|---|
| `offer_move_in_date` | Tenant/buyer could specify a desired move-in date as part of their agent search. |
| `offer_security_deposit` | Tenant could specify their maximum acceptable security deposit as an offer term. |
| `offer_closing_date` | Buyer agent engagement offers could include a desired closing timeline target. |
| `offer_contingency_waiver` | Buyer-side offer could indicate willingness to waive contingencies for agent compensation negotiation. |
| `offer_earnest_money` | A standardized offer-side earnest money commitment amount. |
| `offer_inspection_period` | Offer-side version of the buyer's preferred inspection window. |
| `offer_occupancy_date` | Agent could propose a target occupancy date as part of their service scope. |
| `agent_success_fee` | A performance-based fee not currently modeled; separate from flat/percentage commission. |
| `offer_marketing_budget` | Explicit marketing spend commitment from the agent (distinct from the listing's fee budget fields). |
| `offer_listing_price_recommendation` | Seller agent bid could formally include a recommended listing price. |

---

## Section 6 — Unmapped Existing Form Fields

Fields found in bid Livewire component `public` properties and `saveMeta()` calls that are **absent from all four FieldMap files**. These fields exist in the codebase and are written to bid meta tables but are not represented in `SellerFieldMap`, `BuyerFieldMap`, `LandlordFieldMap`, or `TenantFieldMap`. They should not be added to the main mapping tables until they are added to the appropriate FieldMap.

### From `app/Http/Livewire/Seller/SellerAgentAuctionBid.php`

| Bid Meta Key | Notes |
|---|---|
| `bio` | Agent biography; bid-only profile field. |
| `why_hire_you` | Agent pitch field; bid-only. |
| `what_sets_you_apart` | Agent differentiator; bid-only. |
| `marketing_plan` | Agent's proposed marketing narrative; bid-only. |
| `reviews_links` | JSON array of review URLs; bid-only. |
| `website_link` | Agent website; bid-only. |
| `social_media` | JSON array of platform/URL pairs; bid-only. |
| `year_licensed` | Agent licensing year; bid-only. |
| `nar_id` | NAR membership ID; bid-only. |
| `nominal` | Nominal fee flag/value; bid-only. |
| `other_services` | JSON array of other services; companion to `services`. |
| `other_services_enabled` | Toggle for other services; bid-only. |
| `photo_enhancements` | JSON array; bid-only in Seller flow (absent from SellerFieldMap). |
| `custom_enhancement` | Agent-proposed custom enhancement text; bid-only. |
| `early_termination_fee_amount` | Amount associated with `early_termination_fee_option`; unlisted in FieldMaps. |
| `retainer_fee_amount` | Amount associated with `retainer_fee_option`; unlisted in FieldMaps. |
| `retained_deposits` | Retained deposit field; bid-only. |
| `referral_fee_percent` | Referral percentage; used when listing was created by another agent. |
| `presentation_link` | Link to agent's presentation deck; bid-only. |
| `video_upload` | Agent's uploaded video; bid-only. |
| `business_card` | Path to uploaded business card image; bid-only. |
| `business_card_link` | Link to agent's business card; bid-only. |
| `seller_leasing_fee_type` | Agent proposes seller leasing fee; exists in bid but only in TenantFieldMap (cross-flow key). |
| `seller_leasing_gross`, `seller_leasing_each_rental`, `seller_leasing_gross_rental`, `seller_leasing_gross_month_rent`, `seller_leasing_gross_no_of_months`, `seller_leasing_gross_flat_combo`, `seller_leasing_gross_percentage_combo`, `seller_leasing_gross_flat_net_combo`, `seller_leasing_gross_percentage_net_combo`, `sales_tax_option_gross`, `seller_leasing_gross_sales_tax_first_month`, `seller_leasing_gross_sales_tax_option_gross`, `seller_leasing_gross_sales_tax_flat_free_gross`, `seller_leasing_gross_purchase_fee_flat_amount`, `seller_leasing_gross_purchase_fee_other`, `seller_leasing_gross_other` | Seller bid leasing compensation fields; present in TenantFieldMap listing side but not in SellerFieldMap. |
| `additional_details_broker` | Broker-specific additional notes; absent from SellerFieldMap (present in Buyer and Landlord FieldMaps). |

### From `app/Http/Livewire/Buyer/BuyerAgentAuctionBid.php`

| Bid Meta Key | Notes |
|---|---|
| `bio` | Agent profile; bid-only. |
| `why_hire_you` | Bid-only. |
| `what_sets_you_apart` | Bid-only. |
| `marketing_plan` | Bid-only. |
| `reviews_links` | Bid-only. |
| `website_link` | Bid-only. |
| `social_media` | Bid-only. |
| `year_licensed` | Bid-only. |
| `nar_id` | Bid-only. |
| `other_services` | Bid-only companion to `services`. |
| `other_services_enabled` | Bid-only. |
| `early_termination_fee_amount` | Unlisted in BuyerFieldMap. |
| `retainer_fee_amount` | Unlisted in BuyerFieldMap. |
| `referral_fee_percent` | Bid-only referral percentage. |
| `presentation_link` | Bid-only. |
| `video_upload` | Bid-only. |
| `business_card` | Bid-only. |
| `business_card_link` | Bid-only. |
| `promo_materials_link` | Promotional materials link; bid-only. |
| `promoMaterials` | JSON array of promotional materials; bid-only. |
| `purchase_fee_flat_type` | Type selector for flat fee (e.g., `$`/`%`); bid-only. |

### From `app/Http/Livewire/Landlord/LandlordAgentAuctionBid.php`

| Bid Meta Key | Notes |
|---|---|
| `bio` | Bid-only. |
| `why_hire_you` | Bid-only. |
| `what_sets_you_apart` | Bid-only. |
| `marketing_plan` | Bid-only. |
| `reviews_links` | Bid-only. |
| `website_link` | Bid-only. |
| `social_media` | Bid-only. |
| `other_services` | Bid-only. |
| `other_services_enabled` | Bid-only. |
| `photo_enhancements` | Confirmed in saveMeta; in LandlordFieldMap Services section (covered in Section 3 table). |
| `custom_enhancement` | Bid-only agent enhancement; separate from listing `custom_enhancement`. |
| `early_termination_fee_amount` | Unlisted in LandlordFieldMap. |
| `broker_fee_timing_other` | `otherPairs` companion saved to meta; unlisted in FieldMap as standalone. |
| `split_payment_due_other` | `otherPairs` companion saved to meta; unlisted in FieldMap as standalone. |
| `sales_tax_option_gross` | Saved in Landlord bid; in LandlordFieldMap (covered in Section 3). |

### From `app/Http/Livewire/Tenant/TenantAgentAuctionBid.php`

| Bid Meta Key | Notes |
|---|---|
| `bio` | Bid-only. |
| `why_hire_you` | Bid-only. |
| `what_sets_you_apart` | Bid-only. |
| `marketing_plan` | Bid-only. |
| `reviews_links` | Bid-only. |
| `website_link` | Bid-only. |
| `social_media` | Bid-only. |
| `year_licensed` | Bid-only. |
| `nar_id` | Bid-only. |
| `other_services` | Bid-only. |
| `other_services_enabled` | Bid-only. |
| `referral_fee_percent` | Bid-only referral percentage. |
| `early_termination_fee_amount` | Unlisted in TenantFieldMap. |
| `retainer_fee_amount` | Unlisted in TenantFieldMap. |
| `presentation_link` | Bid-only. |
| `video_upload` | Bid-only. |
| `business_card` | Bid-only. |
| `business_card_link` | Bid-only. |
| `promo_materials_link` | Bid-only. |
| `promoMaterials` | Bid-only. |
| `broker_fee_timing_other` | `otherPairs` companion saved to meta; unlisted standalone. |
| `broker_fee_days_after_due_event` | In LandlordFieldMap but absent from TenantFieldMap; bid saves it. |
| `first_name`, `last_name`, `phone`, `email` | Agent contact details saved to meta in TenantAgentAuctionBid; unlisted in TenantFieldMap (covered by Contact Information on the listing side). |
| `brokerage`, `license_no` | Agent credential fields saved to meta in TenantAgentAuctionBid; bid-only. |
| `total_marketing_fee` | Confirmed in TenantAgentAuctionBid; also confirmed in BuyerAgentAuctionBid as public property. In TenantFieldMap Services section (covered in Section 4). |
| `total_flat_fee` | Same. |
