# Phase 3 — not operational in this release

Real appraisal/lease extraction, photo review, comparable reasoning, approved investor checks, DSCR/LTV calculations, revision comparison and generated reports remain disabled. Phase 1 persists drafts and displays fictional examples only.

- Appraisal form is independently modeled as `1004`, `1025`, `1007` or `216`; units must be 1–4 and occupancy is separately owner-occupied / second-home / investment. Do not infer occupancy from form.
- Lease and market rent sources must be separately evidenced; resolve rent selection/adjustment/vacancy policy with the investor.
- DSCR example A: rent **3300.00** / PITIA **3000.00** = **1.10**. Example B: **2850.00** / **3000.00** = **0.95**. They remain fixed fixtures regardless of saved draft inputs. No live calculator or underwriting eligibility claim exists.
- Real LTV and DSCR require decimal arithmetic, input validation, zero-denominator/error handling, approved denominator/numerator policy and exact source lineage.
- Findings must have severity, human status, evidence coordinates and append-only history. Open findings do not block **sample** report preview; real report-release policy requires approval.
- Photo and revision workflows must retain every original and explicitly identify observation vs model suggestion vs human override. Never represent a rendered/normalized derivative as the original forensic evidence.
- Guidelines are fictional drafts, tenant/version-scoped, not invented Fannie Mae or investor-approved rules.

Release gates: approved lender rules and full scenario edge cases, form/unit/occupancy matrix, lease evidence/expiry/renewals, DSCR zero and rounding cases, LTV boundaries, revision/source consistency, role-scoped report/export leak tests, accessibility/browser/device acceptance, load/retry/failure recovery, security/pentest, backup/restore/retention and operational runbooks.

## Phase 2 handoff

Bank processing now lives under `app/Bank`, normalized `bank_*` tables and `BankWorkspace.vue`; do not repurpose its income/risk rules for appraisal or DSCR. Existing Phase 3 tab state/screens and fictional fixtures were preserved. Reuse tenant/file/role policy patterns, immutable originals, separate suggestions/decisions/snapshots and Broker-safe publication principles when Phase 3 is explicitly authorized. Plaid remains a future adapter.
