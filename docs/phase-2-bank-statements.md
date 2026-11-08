# Phase 2 — not operational in this release

## Approved scope to implement later

Document classification/extraction for 12/24-month personal and business accounts; coverage gaps and duplicates; initial request for **two business bank statements**; account ownership and multiple accounts. Preserve original forensics before OCR/rendering. Record page/region/source-version provenance for every extracted field and transaction.

Deterministic decimal income calculations, business expense ratios, transfer tracing (never double count internal transfers), reconciliation and classifier suggestions separate from human overrides. No AI output directly changes qualifying income. Version investor/ruleset policies within an organization and snapshot calculations with inputs/exclusions/reviewer reasons.

NSF events, fees and overdraft days are separate measures; one event may have more than one fee. Confirm event-vs-fee conventions, date tolerance, linked accounts and reversal treatment. Sustainability concentration/reserves/trends require agreed thresholds. Integrity/fraud indicators are not proof of fraud and require source-linked human review. Evaluate Ocrolus Detect or MoneyThumb Thumbprint on approved document types; measure acceptance before enabling any vendor.

## Workbook mapping expectation

**No workbook existed in the source repository.** PhpSpreadsheet is installed and locked, but there is no approved template and no financial workbook exporter in Phase 1. Do not invent compatibility with a lender workbook.

Proposed mapping for approval:

| Sheet | Proposed mapping |
|---|---|
| Controls | explicit 12/24 selector, org/file/run/ruleset/template versions, currency, expense method and reviewer approval |
| Monthly Income | **24 dedicated monthly rows** with period/source link; the 12-month selector includes only the approved 12 periods, never silently truncates 24 into 12 or changes denominator |
| Deposits | transaction/source-version/page/row, amount and provenance; preserve all rows |
| Overflow | deposits beyond visible template row capacity, joined to period totals and original source; never discard excess lines |
| Exclusions | excluded transfers/non-income/returns with reason, reviewer, counterpart linkage and exact decimal amount |
| Reconciliation | statement totals vs extracted totals vs eligible/excluded totals, gaps/duplicates/error states |
| NSF & Overdraft | event ids distinct from fee ids and overdraft-day metrics; explicit approved convention |
| Audit | calculation inputs, suggestions/overrides, immutable snapshot reference and export permissions |

Acceptance: known 12/24-month fixtures with exact totals, selector boundary/denominator cases, duplicate/overflow preservation, rounding/reversals/fees/transfer tolerances, cross-tenant denial, Broker redaction in previews and exports, original hashes unchanged, retry idempotency, vendor latency/security/accuracy gates. Real financial documents and vendors must not be enabled until approved.
