# Ask AI Live Modal — Diagnostic Trace (Task 2976)

## Listing: `/offer-listing/seller/view/121` (seller_agent_auctions id=121)

### Listing Context
- `user_id = 142` (Abigail Baschuk, `user_type=agent`)
- `description = null`
- `agent_default_profiles` rows for agent 142: **14 rows** — agent context loads fully
- No `accepted_bid_summary` record (no hired agent via bid flow; agent IS user 142)

---

## Failing Question 1: "Tell me about the agent"

### Root Cause
`OPENAI_API_KEY`, `OPENAI_MODEL`, and `OPENAI_PROMPT_VERSION` were set only as Replit
platform-level environment secrets. The `artisan serve` workflow process does not inherit
platform secrets — it only reads from `.env` via phpdotenv. These three keys were absent
from `.env`, so every OpenAI call from the workflow threw an API authentication error.

### Before Fix — Classifier Trace
```
question_type = agent_profile   (confidence=0.9)
contract_ready = YES  (required_sources: [] → always ready)
prompt_status  = prompt_ready
OpenAI call    = FAIL  (no api_key in workflow env → AuthenticationError)
final_status   = failed
modal_message  = "A response could not be generated right now. Please try again shortly."
```

### After Fix — Classifier Trace
```
question_type = agent_profile   (confidence=0.9)
contract_ready = YES
prompt_status  = prompt_ready
OpenAI call    = SUCCESS  (OPENAI_API_KEY now in .env → phpdotenv loads at startup)
final_status   = ready
modal_message  = agent bio / credentials from AgentDefaultProfile (14 rows available)
```

### Fix Applied
`.env` — appended `OPENAI_API_KEY`, `OPENAI_MODEL`, `OPENAI_PROMPT_VERSION`,
`ASK_AI_ENABLE_OPENAI_INTENT_NORMALIZATION`, `ASK_AI_ENABLE_DESCRIPTION_FALLBACK`.
Workflow restarted to pick up new values.

---

## Failing Question 2: "Tell me about how much it costs to own the property"

### Root Cause
The phrase "how much it costs" was absent from `AskAiQuestionClassifierService` listing_facts
KEYWORD_RULES. The classifier returned `unsupported`. With the intent normalizer also failing
(no OpenAI key), the pipeline reached the 1a-desc path, attempted an adapter call (also
fails), and returned `status='insufficient_context'` or `status='unsupported'`. The frontend
JavaScript renders `status='unsupported'` as "I may need the agent to confirm that."

### Before Fix — Classifier Trace
```
question_type = unsupported   (no cost-of-ownership keyword matched)
normalizer    = FAIL (no api_key → OpenAI call fails)
step_1a_desc  = MISS (description=null → block skipped)
final_status  = unsupported
modal_message = "I may need the agent to confirm that. Would you like me to send them your question?"
```

### After Fix — Classifier Trace
```
question_type = listing_facts   (confidence=0.9)
              ↑ matched "how much it costs" keyword (new)
step_1b       = no specific field key (cost spans multiple fields)
OpenAI call   = SUCCESS (full listing_facts context: taxes, HOA, CDD, etc.)
final_status  = ready
modal_message = synthesized cost summary from listing data
```

### Fix Applied
`AskAiQuestionClassifierService.php` — added cost-of-ownership keyword block to
`listing_facts` KEYWORD_RULES:
- `'how much it costs'`, `'cost to own'`, `'costs to own'`, `'cost of ownership'`,
  `'costs of ownership'`, `'ownership costs'`, `'carrying costs'`,
  `'what does it cost to own'`, `'costs of owning'`

---

## Config Verification (artisan tinker — mirrors workflow after restart)
```
OPENAI_API_KEY set:         YES
model:                      gpt-4o-2024-08-06
prompt_version:             property-dna-report-v1.0
normalization enabled:      YES  (ASK_AI_ENABLE_OPENAI_INTENT_NORMALIZATION=true)
desc fallback enabled:      YES  (ASK_AI_ENABLE_DESCRIPTION_FALLBACK=true)
```
