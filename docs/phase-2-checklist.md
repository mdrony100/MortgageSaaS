# Phase 2 — 23-section requirement-to-implementation acceptance map

Checked means implementable local source/workflow is delivered with listed automated evidence, NOT live commercial/provider/production acceptance. Unavailable client inputs and release gates remain explicit below and in [limitations](phase2-limitations.md). Full23-section user request is authoritative. Existing develop baseline `373801baf95360662a7786168d6aede95a527c53`; no client workbook/requirements PDF exists in accessible attachments (two screenshots only). No real documents/keys committed.

## [x] 1. Repository workflow

Existing develop baseline373801b inspected; original source preserved, normal develop-only commit/push required. No main/deploy/rebuild/force.

Evidence/boundary: AGENTS.md; final verification record and remote SHA proof.

## [x] 2. Stack/scope

Existing Laravel/Vue/PostgreSQL/Redis-Horizon/Python/PDF.js/private storage continued; BankWorkspace separated from explicit demo. Phase3 draft/screens retained; Plaid future.

Evidence/boundary: PhaseOne regression, native HTTP smoke; no full Appraisal implementation.

## [x] 3. Unresolved policy

Immutable versioned draft/example/approved rule profiles, deterministic eligibility, source provenance and explicit provisional/withheld results.

Evidence/boundary: RuleProfile, CalculateIncome; approval/reference and essential input tests. Client approval still pending.

## [x] 4. Asynchronous pipeline

Pinned original versions/hashes and source inventory; stages, retries/backoff/timeouts/cancel, shared queue locks, append-only artifacts, atomic provider submissions.

Evidence/boundary: Pipeline, ProcessBankRun, PollBankIntegrity; retry/cancel/pinning/idempotency/budget tests + actual Redis smoke.

## [x] 5. Secure ingestion

Actual MIME/size, quarantine/scanner states, exact original SHA256, private derived artifacts; bounded native parser and production OS sandbox.

Evidence/boundary: Scanner/DocumentParser; real PDF/image/encrypted/malformed/bubblewrap tests. Actual ClamAV/signature acceptance pending.

## [x] 6. Extraction strategy

Native supported layouts first, approved vision fallback, strict schema/prompt version, bounded page groups, null uncertainty, usage/budgets and explicit failed/refused/unknown outcomes.

Evidence/boundary: NativeExtractor/HybridExtractor/OpenAIExtractor; official-doc HTTP contracts and actual native fixtures. Live OpenAI pending.

## [x] 7. Extraction schema

Statement/account/period/totals/fees and transaction dates/amounts/running balance/reference/source/version/page/status; masked IDs and native-verified regions only.

Evidence/boundary: ExtractionSchema, Ledger; identifier/date and coordinate contract tests. Foreign currencies withheld, no conversion.

## [x] 8. Reconciliation

Exact totals/fees/running balances, continuity/coverage, overlap/duplicates/date/page sequence/account indicators; provenance artifact dedup retains legitimate repeats.

Evidence/boundary: Reconcile; real duplicate/reordered PDF + financial fixture tests. Indicators do not establish fraud.

## [x] 9. Classification/review

Separate suggestion/category/eligibility; search/filter/source, bulk include/exclude, accumulated source correction, reasoned overrides, impact preview/new calculation.

Evidence/boundary: ReviewState/BankController/BankWorkspace; original-suggestion and cumulative-correction tests.

## [x] 10. Transfers

Same-file verified relationship and evidence; bounded one-to-one candidates, explicit ambiguous/split/combined partial allocations, corrected-capacity limits and double-count prevention.

Evidence/boundary: TransferEngine + reviewer decisions; partial/split/combined/circular/over-allocation tests.

## [x] 11. Personal workflow

Only personal selected deposits count; two recent contiguous verified business months corroborate only their actual window. Missing/outside support explicit.

Evidence/boundary: CalculateIncome personal cross-check fixtures; no supporting business deposit addition.

## [x] 12. Business workflow

Selected accounts/lookback, eligible categories, expense method and ownership defined per profile; input confirmation requires actor/reason/source notes.

Evidence/boundary: Business12/24, account selection and original-policy/financial-input review tests. Approved lender inputs pending.

## [x] 13. Deterministic calculations

Integer minor units + exact rational arithmetic, fixed12/24 denominator, source/review IDs, canonical immutable snapshot and display rounding/hash.

Evidence/boundary: Money/CalculateIncome;8333.33 demonstration,24-month denominator, missing period, policy reproducibility and freshness tests.

## [x] 14. NSF/OD

Events vs fees, configurable count algorithms, monthly/cumulative scoped totals, lowest observed balance; negative account-days only complete reviewer-verified exact ledger.

Evidence/boundary: RiskMetrics; fee/event, mode, currency, insufficient daily data, selected-period and supporting-account tests. Review conditions, never decline.

## [x] 15. Sustainability

Actual business period/currency coverage, inflow/outflow, owner transfers and remaining liquidity without double subtraction; restricted observations/dispositions.

Evidence/boundary: RiskMetrics/FindingRefresh; owner-flow and circular refresh tests. Thresholds need policy approval; no income mutation.

## [x] 16. Integrity/provider

Independent internal arithmetic/page/metadata/font/account/cash/circular indicators and concrete documented Ocrolus original-upload/poll mapping; disabled/unavailable remains incomplete.

Evidence/boundary: InternalIntegrity/OcrolusDetect; official upload/signals HTTP contracts, Pending/Complete/timeout tests. Live vendor/Book/token/retention approval pending.

## [x] 17. Governance

Findings preserve original evidence and append reviewed/request/resolved/rejected/escalated/reopened history, assignee, standardized reason/version; Other requires notes.

Evidence/boundary: Finding/reason/history tests; deliberate public condition lifecycle. Income finalization is not mortgage approval.

## [x] 18. Broker permissions

Role+tenant+file boundaries; final-only separate bank resources/templates/exports, no risk/policy/overrides/internal versions. Originals limited to Broker-own specific clean versions.

Evidence/boundary: Broker API/legacy/audit/export/source per-version tests, cross-tenant/revocation; no sharing elevation.

## [x] 19. Exports

Real XLSX/CSV/PDF, preliminary/final/internal/public versions; backend-checked selected-period formulas/denominators/caches/detail overflow, CSV injection defense.

Evidence/boundary: Export/ReportService +12/24 workbook/PDF/CSV fixtures and actual HTTP downloads. Client workbook absent: explicitly marked FALLBACK; template matching pending.

## [x] 20. Privacy/configuration

Server-only secrets, generic source/SQL/provider errors, masked identifiers, private local/S3 abstraction, budget reservations, encrypted7-day replay cache +24h scratch cleanup.

Evidence/boundary: Fake S3, payload redaction, production bypass denial and atomic claim tests; dependency/secrets review. store:false is not zero retention; real AWS/provider agreement pending.

## [x] 21. Frontend

Real upload/coverage/progress/retries/source/review/transfers/rules/calculation/metrics/conditions/history/download; explicit uncertainty/provider labels.

Evidence/boundary: Connected Vue DOM tests + production TypeScript/Vite build + actual PDF.js render. Visual desktop acceptance blocked by known platform firewall; no bypass.

## [x] 22. Acceptance fixtures

Synthetic/anonymized-by-construction code only; full native HTTP path and TEST extraction end-to-end, provider mocked contracts distinctly labeled.

Evidence/boundary: PostgreSQL91 tests/731 assertions; Vue16 tests; native HTTP22 checks. No measured real borrower accuracy or live success claim.

## [x] 23. Documentation/handoff

README, architecture/data model, permissions, rules/calc/Excel/provider/fixtures/limitations, superseded Phase2 plan, Phase3 handoff and actual verification record.

Evidence/boundary: This23-section map and phase2-verification.md; commit/normal push remote proof recorded in handoff/checkpoint after execution.

## Release acceptance still open

Actual workbook + approved policy/thresholds/evidence/finalization gates; approved live sample documents/data-processing and billing permission; OpenAI key/model/reservation and Ocrolus commercial access/token/dedicated tenant-file Book; real ClamAV/signatures; real S3 and target-host/container acceptance; licensing/retention approval; browser visual/accessibility acceptance. These do not block unrelated delivered local implementation. No live success is claimed.
