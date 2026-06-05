---
name: Seller schema typo — rental_restrictions_desription
description: The property_auctions table has a legacy misspelled column name that must be referenced exactly as-is in code.
---

# Seller Schema Typo: rental_restrictions_desription

## The rule
When reading the seller's rental restriction description text, use the column name `rental_restrictions_desription` (missing the second 'c' in "description"). Do NOT correct the spelling in code — correct it only via a DB migration that renames the column.

**The context key is spelled correctly:** `rental_restrictions_description`  
**The DB column is misspelled:** `rental_restrictions_desription`

## Why
This is a legacy typo baked into the `property_auctions` table schema. PHP reads the actual column name, so using the correctly-spelled name returns null. A comment was added to `AskAiContextBuilderService::extractFactualFields()` documenting this.

## How to apply
Any code that reads this field must use `rental_restrictions_desription` as the column key. When a migration renames the column, update the mapping in `extractFactualFields()` and remove the comment.
