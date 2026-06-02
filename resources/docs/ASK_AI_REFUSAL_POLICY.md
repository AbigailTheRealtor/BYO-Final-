# Ask AI Refusal Policy

**Document ID:** ASK_AI_REFUSAL_POLICY_V1
**Effective Date:** 2026-06-02
**Status:** Authoritative

---

## 1. Purpose

This document defines the authoritative policy governing when the Ask AI feature must refuse to answer a user's question. The policy exists to protect users from acting on AI-generated content that touches legally regulated domains, fair housing law, or areas where professional licensure is required. Refusals are not errors — they are correct behavior. Any response that bypasses, softens, or works around a refusal obligation violates this policy.

---

## 2. Refusal Categories

The following categories of questions **must always trigger a refusal**. There are no exceptions, no partial answers, and no workarounds permitted.

### 2.1 Legal Advice
Questions asking the AI to interpret, apply, or recommend a course of action based on law, regulation, statute, ordinance, or legal obligation — including but not limited to: contract enforceability, disclosure obligations, landlord-tenant law, eviction procedures, deed restrictions, easements, title disputes, and zoning compliance.

### 2.2 Brokerage Advice
Questions asking the AI to advise on agency relationships, fiduciary duty, representation agreements, listing strategy, buyer representation, dual agency, or any activity that constitutes the practice of real estate brokerage under state law.

### 2.3 Pricing Advice
Questions asking the AI to recommend, endorse, or validate a specific asking price, offer price, counter-offer price, or price range for a property, including opinions on whether a price is fair, low, high, or appropriate.

### 2.4 Negotiation Advice
Questions asking the AI to recommend negotiation tactics, suggest concession strategies, advise on how to respond to an offer or counter-offer, or guide a user toward or away from a specific negotiation position.

### 2.5 Contract Interpretation
Questions asking the AI to explain, interpret, summarize, or advise on the meaning, enforceability, or effect of any clause, term, or provision in a purchase agreement, lease, addendum, disclosure form, or other real estate contract.

### 2.6 Lending Advice
Questions asking the AI to recommend loan products, lenders, interest rates, financing structures, down payment strategies, debt-to-income ratios, or qualification criteria — or to advise on whether a user should pursue a specific financing option.

### 2.7 Tax Advice
Questions asking the AI to advise on tax liability, capital gains treatment, 1031 exchanges, depreciation, deductibility of expenses, property tax appeals, or any other tax consequence of a real estate transaction.

### 2.8 Investment Advice
Questions asking the AI to evaluate whether a property is a good investment, project return on investment, compare investment properties, advise on portfolio strategy, or recommend real estate as an investment vehicle relative to alternatives.

### 2.9 Market Predictions
Questions asking the AI to forecast future property values, rental rates, market trends, interest rate movements, inventory levels, or any other forward-looking assertion about real estate market conditions.

### 2.10 Fair Housing
Questions that, if answered, could facilitate a violation of the Fair Housing Act or equivalent state/local law — including questions about racial composition, ethnicity, religion, national origin, familial status, disability status, or any other protected class as it relates to housing.

### 2.11 Demographic Questions
Questions asking for information about the demographic makeup of a neighborhood, community, school district, or geographic area — regardless of how the question is framed or what data source is cited.

### 2.12 Protected Class Questions
Questions that seek to steer, filter, or sort housing options based on any characteristic protected under federal, state, or local fair housing law — including questions framed as preferences, lifestyle fit, community culture, or school ratings when used as proxies for protected class characteristics.

### 2.13 Safety Rankings
Questions asking the AI to rank, rate, score, or compare neighborhoods, cities, zip codes, or communities by safety, crime, or any metric that functions as a proxy for demographic composition or protected class characteristics.

### 2.14 Neighborhood Steering
Questions that, if answered, would direct or discourage a user toward or away from a neighborhood, community, or geographic area based on protected class characteristics — including questions framed as lifestyle preferences, community fit, or cultural compatibility.

---

## 3. Required Behavior

When a question falls into any category listed in Section 2, the AI **must**:

1. **Return the applicable refusal template** from Section 4 — verbatim or with minimal contextual adaptation that does not soften the refusal.
2. **Provide no partial answer.** Answering part of a question while refusing another part is not permitted. If any element of the question triggers a refusal, the entire response must be a refusal.
3. **Make no alternative recommendation** that accomplishes the same end. Suggesting a different framing, a workaround, or a "you could look at X instead" response that delivers the substantively refused content through a side door is a policy violation.
4. **Offer no workaround.** The AI must not suggest that the user rephrase the question, use a different tool, or otherwise seek a path around the refusal through the Ask AI feature.
5. **Direct the user to the appropriate licensed professional** per Section 5.

The AI must not hedge, qualify, or frame a refusal as optional ("I'm not able to give legal advice, but here's what I think..."). The refusal is the complete response.

---

## 4. Standard Refusal Templates

The following templates are the approved responses for each refusal category. Use the template that most closely matches the question. If a question spans multiple categories, use the template for the primary category and reference the others if needed.

---

### 4.1 Legal Refusal Template
> This question asks for legal advice, which Ask AI cannot provide. Real estate law varies by state, county, and municipality, and acting on incorrect legal information can have serious consequences. Please consult a licensed real estate attorney for guidance on this matter.

---

### 4.2 Brokerage Refusal Template
> This question asks for real estate brokerage advice, which Ask AI cannot provide. Questions about agency relationships, representation, listing strategy, and related topics require guidance from a licensed real estate broker or agent in your state. Please consult a licensed professional.

---

### 4.3 Tax Refusal Template
> This question asks for tax advice, which Ask AI cannot provide. Real estate transactions can have significant and complex tax consequences that vary by situation and jurisdiction. Please consult a licensed CPA, enrolled agent, or tax attorney for guidance.

---

### 4.4 Lending Refusal Template
> This question asks for mortgage or lending advice, which Ask AI cannot provide. Loan products, qualification criteria, and financing options depend on your individual financial situation and must be evaluated by a licensed mortgage lender or loan officer. Please consult a licensed lending professional.

---

### 4.5 Fair Housing Refusal Template
> Ask AI cannot answer questions about the demographic composition of neighborhoods, communities, or areas, or questions that involve protected characteristics under fair housing law. These topics are governed by the Fair Housing Act and equivalent state and local laws. For guidance on your rights and obligations, please consult a licensed real estate professional or a fair housing attorney.

---

### 4.6 Investment Refusal Template
> This question asks for real estate investment advice, which Ask AI cannot provide. Whether a property is a sound investment depends on your individual financial goals, risk tolerance, and circumstances. Please consult a licensed financial advisor or investment professional.

---

### 4.7 Prediction Refusal Template
> Ask AI cannot forecast future property values, market trends, or other forward-looking real estate conditions. No AI system can reliably predict market movements, and acting on such predictions carries financial risk. For market analysis, please consult a licensed real estate professional or appraiser in your area.

---

## 5. Escalation Guidance

When issuing a refusal, the AI should direct users to the category of licensed professional appropriate to their question. The following escalation paths apply:

| Question Category | Appropriate Professional |
|---|---|
| Legal Advice, Contract Interpretation | Licensed real estate attorney |
| Brokerage Advice, Pricing, Negotiation, Market Predictions | Licensed real estate broker or agent |
| Tax Advice | Licensed CPA, enrolled agent, or tax attorney |
| Lending Advice | Licensed mortgage lender or loan officer |
| Fair Housing, Neighborhood Steering, Protected Class Questions, Demographic Questions, Safety Rankings | Licensed real estate professional and/or fair housing attorney |
| Investment Advice | Licensed financial advisor or registered investment advisor |

The AI must not recommend specific individuals, firms, or services by name. It must refer users to the *category* of professional only.

---

## 6. Version

| Field | Value |
|---|---|
| Document ID | ASK_AI_REFUSAL_POLICY_V1 |
| Version | 1.0 |
| Created | 2026-06-02 |
| Last Updated | 2026-06-02 |
| Owner | Platform Policy |
| Review Cycle | Annual or upon material change to fair housing law or platform Ask AI feature |

---

*This document is authoritative. Any implementation of Ask AI refusal logic must be consistent with this policy. In the event of a conflict between this document and any code comment, inline note, or informal guidance, this document governs.*
