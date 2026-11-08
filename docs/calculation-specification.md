# Deterministic calculation specification v1

Money enters through validated signed decimal strings (at most two currency places), becomes bounded integer minor units, and uses Brick Math for percentages/division. No float enters the backend income engine. Limits are 100 MiB originals, 600 pages and 50,000 transaction rows per run, with 20 MiB/120 pages per original. USD only.

For selected workflow/accounts and exact 12/24 month range:
1. Resolve latest immutable classification/eligibility decisions while preserving original extraction/suggestions.
2. Verify account identity, statement full-day coverage, overlaps and reconciliation independently from category.
3. Sum observed credits in the selected period; exclude ineligible/unresolved credits. All numerator rows retain transaction, statement, version, account, date/page and decision IDs.
4. Subtract confirmed selected-account incoming transfer allocations once, never more than either source/destination. Proposed/ambiguous selected-account movements withhold the figure. Business-to-personal source corroboration is distinct and does not add business receipts or automatically exclude actual eligible owner distributions.
5. Business: eligible minor units × ownership% × remaining-income% /10000. Personal follows its own profile (no expense/ownership formula is assumed).
6. Divide adjusted minor units by 100 and by the exact selected month count. Exact rational numerator/denominator and exact intermediate adjusted minor units are persisted; a 12-place decimal representation is labeled as a representation. HALF_UP display rounding is performed directly from the original rational expression to avoid double rounding.

Snapshots are append-only, content-hashed and bind pinned original hashes/versions, selected accounts/months, rule/version/policy, decisions/allocations, coverage, missing inputs/assumptions, original/corrected evidence, exact inputs/intermediates and displayed result. Recalculation never rewrites an issued report. New uploads mark freshness false and require a new pinned run for finalization. Old authorized report versions remain tied to old snapshots.

Reconciliation: opening + all transaction credits − all transaction debits = closing. Reported debit comparison adds separately excluded fees only when the source explicitly says fees were not included. Unknown fee convention is unresolved, never a double subtraction. Running balances use original row provenance. Invalid dates/unreadable amounts do not become invented digits or silently final income.

Personal owner-distribution credits: require verified related business accounts, two adjacent most-recent actual business statement periods, reconciled USD ledger and confirmed outgoing allocations. Each deposit is supported/unsupported/outside-window. Two recent months never verify the whole personal lookback. Missing source evidence remains provisional/withheld according to confirmed policy.

NSF/OD: explicit non-fee event rows are counted by account/date/type/reference; fees are separate amounts and never automatically a second event. Fee-only event counts are not invented. Negative days are null without reviewer-verified complete, reconciled ledger coverage; otherwise derive end-of-day account-days from opening balance and all daily postings, deduplicating overlapping dates. Multiple accounts count separately. Sporadic/closing balances alone cannot establish negative days.

Sustainability: net cash flow = all inflows − all outflows; owner distributions are a subset of outflows, NOT subtracted again. Remaining liquidity = observed closing balance; lowest balance is observed, coverage explicit. Repeated tight liquidity uses only configured thresholds. These metrics never change qualifying income and never automatically decline a mortgage.

## Review reproducibility and metric scope

Canonical recursive JSON hashing ignores object-key order while preserving ordered lists. Snapshot inputs include accumulated source correction IDs, statement/account/transfer reviews, reasoned financial-input review ID, original rule/profile values and effective numeric inputs. Reports remain attached to their issued immutable snapshot. Run inventory at queue time distinguishes pre-existing deliberately unselected documents from later new uploads; neither changes pinned processing inputs.

NSF exported totals are confined to explicitly selected income accounts and the selected12/24 date interval. Supporting-account NSF is separate actual-coverage evidence. Negative account-days reconstruct the complete verified source ledger, then count only the selected interval; one-cent tolerance cannot fabricate negative days. Sustainability retains actual business review periods, currency, source/match evidence and current dispositions separately from immutable metric observations; income is unaffected. Configured circular/repeat/cash/liquidity findings refresh after reviewer decisions, not just before accounts/transfers can be confirmed.
