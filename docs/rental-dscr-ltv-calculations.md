# Rental, DSCR and LTV calculation contract

Backend owner: `app/Appraisal/Calculator.php`; values enter through a scoped reviewer-confirmed immutable input API, not AI financial formulas. Money uses integer minor units and Brick decimal division. Source, explicit rent/value basis, confirmation, included units, formula, assumptions/missing items and input version are recorded.

## Unknown / zero / selected rent
Each unit has inspection occupancy, current occupancy, lease status and rent method independently. Market rent and selected qualifying rent are independent confirmed choices; no higher/lower/market/cap/renewal/ADU choice is automatic. Null/unconfirmed/no-source is unknown, **not0**. A selected0 requires explicit source/basis/confirmation and produces0.00DSCR if denominator is known/positive. Unknown qualifying rent withholds qualifyingDSCR without suppressing known marketDSCR.

## Denominator
- `payment.mode=total`: only the confirmed user-entered total is used; all components are ignored, never added twice.
- `components`: payment (PIorIO), taxes, insurance, association and other must each have confirmed value/source. Explicit0 represents a confirmed nonapplicable expense; missing is not0.
- Denominator must be positive. A zero/missing denominator withholds ratios and explains the missing input.
- An interest-only flag does not establish program treatment: reviewer-confirmed payment definition is required.

## DSCR and precision
`Market Rent DSCR = sum(confirmed included unit market rent) / confirmed monthly payment`.
`Qualifying DSCR = sum(explicitly selected confirmed qualifying rent) / confirmed monthly payment`.

Acceptance: payment3000.00; market3300.00→**1.10**; qualifying2850.00→**0.95**. Store numerator/denominator in minor units and exact rational, plus12-decimal representation and2-decimal display. HALF_UP2DP is a documented display convention while guideline/rounding policy is pending. Only a separately approved exact `HALF_UP_2DP` rule is treated as approved; approval of an unrelated Fannie checklist is not rounding approval.

Sufficient explicitly selected ADU inputs produce with/without-income scenarios, including a confirmed0ADU selection. These are comparisons, not ADU/property/investor eligibility. STR methods are never automatically normalized or mixed with long-term rent.

## LTV and overlays
`LTV% = confirmed loan amount / positive reviewer-selected qualifying value basis ×100`.
Appraised value is shown separately. There is no universal purchase-price/appraisal/lower-of assumption. Missing/nonpositive/unconfirmed basis withholdsLTV.

An overlay is used only from the frozen approved checklist with explicit approved flag, source, version, calculation order and maximum percent. Supported order `confirmed_value_times_max_percent`: value-basis minor units × approved percent/100, round down to a minor unit; show maximum loan and requested-minus-maximum difference. No approved overlay exists by default. Otherwise generate a verification item, never invent a limit or loan decision. Test overlay values are TEST-only simulations, not lender policy.

## Snapshot and change semantics
Every confirmed financial-input save appends input+calculation snapshots atomically; no in-place replacement. Relevant input changes automatically recalculate. Extracted fact confirmations, new leases and revised documents remain proposals and do not silently replace a separately confirmed financial-input selection. Reviewer explicitly confirms the updated selection; the new input then produces a new snapshot. Earlier decisions/calculations/reports remain immutable.

All outputs remain review/provisional and can include missing policy/evidence. No automatic approval, rejection, ineligibility, condition clearance or underwriting sign-off. PDF/XLSX use the same frozen values; workbook trusted numeric formulas are recalculated and compared against backend displayed results, with literal imported text and correct cached values.
