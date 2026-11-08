# Final acceptance results — 2026-11-08, Asia/Dhaka (UTC+06)

This records executed local development checks, not production/client approval. Source baseline was develop `8f7d9f19d99695930e473cb5708d917a1e8528b4`. Main was not modified. Commit/push identifiers are reported separately after actual delivery. All documents are synthetic; no live paid APIs, real borrower processing, purchases or deployment were performed.

## Executed checks

| Check | Actual outcome / evidence outside Git |
|---|---|
| Complete PostgreSQL backend suite | **140 passed, 1261 assertions, 37.699s**. `phase3-tests-final.log`; all original91tests retained plus final-domain and Bank timeout regressions. Test bootstrap verifies testing environment, PostgreSQL and actual database suffix `_test` before migrations. |
| Vue DOM/component integration | **25/25 passed**,3test files; `phase3-ui-final.log`. HTTP APIs mocked here, not mistaken for native transport or visual browser proof. |
| Production frontend | `npm run build` passed TypeScript/Vite, **1606modules**; `phase3-build-final.log`. Actual browser build assets scanned separately. |
| PDF.js | `npm run test:pdf` passed real PDF.js6.5.438render,1page704×911; `phase3-pdfjs-final.log`. |
| Actual native HTTP integration | **27/27 passed after final source and Horizon restart**; `phase3-http-smoke-final.log`. Real cookie/CSRF/auth/SMTP verification-reset-invite, PostgreSQL, Redis/Horizon default+bank queues, Python native bank/appraisal, exact private originals, real bankCSV/PDF/XLSX and appraisalPDF/XLSX, DSCR1.10/.95/LTV76.92, Broker/revoke boundaries. No external provider involved. |
| Formatting / source integrity | `vendor/bin/pint --test` and `git diff --check` passed. Additive migrations applied locally without resetting the application database; tests use a separate isolated database. |
| Dependency advisories | `composer audit --format=json`, `npm audit --json`, `pip-audit -r python-worker/requirements.txt -f json`: **0known advisories** in this execution, not a security guarantee. `phase3-composer-audit.json`, `phase3-npm-audit.json`, `phase3-python-audit.json`. |
| Secrets/private-data audit | Intended-source export and actual browser assets scanned by Gitleaks with redacted output: **0findings**. No private environment, runtime financial document, PDF/workbook/database/key artifact enters intended Git source. Tracked storage entries are only harmless `.gitignore` directory placeholders. Source fixtures and generated documents are explicitly fictional; generated PDFs/XLSX remain outside Git. |
| Operations/retention | Horizon running; `retention:review` and `bank:purge-transient` verified dry-run/no deletion. Approved retention remains false. Compose configuration syntax passed; Docker daemon unavailable, so container build/runtime was **not** verified. |

Commands: `PHASE3_ARTIFACT_DIR=/workspace/mortgagelens-build/phase3-artifacts php artisan test --compact`; `npm test`; `npm run build`; `npm run test:pdf`; `vendor/bin/pint --test`; `python3 tests/smoke_http.py`; the audit commands above. Full logs are under `/workspace/mortgagelens-build/`, never committed with private runtime files.

## Fourteen synthetic acceptance paths

Each named `PhaseThreeTest::cases()` scenario performs actual synthetic original upload, native Python parsing, recorded reviewer inputs and actual private PDF/XLSX export. Native mode always discloses manual/vision limitations. Photo observation semantics use a clearly labeled mock; separate contract tests verify actual inline image bytes and strict Responses requests.

| Scenario | Executed evidence / expected human-review boundary |
|---|---|
| Clean primary purchase | Separate synthetic contract and appraisal report matching330000prices, distinct original source ids, contract reconciliation question, actual PDF/XLSX and both deterministic ratios. Not a certified appraisal or complete live OCR/vision acceptance. |
| Second-home refinance | Separate loan occupancy and ownership/history/value-basis questions; no inferred program eligibility. |
|3leased/1vacant cash-out | Actual three lease originals with explicit unit/address matches, confirmed950×3 plus explicit vacant0,825market×4, payment3000:1.10/.95; unit-specific occupied lease and separate vacancy support/questions. |
| Fully vacant refinance | Vacancy permission and market-rent use/documentation remain separate review items, no automatic decline. |
| Expired/month-to-month | Actual expired synthetic lease; continuation/payment evidence requested, no assumed renewal. |
| Short-term rental | Separate STR evidence path, no invented annualization/normalization. |
| ADU comparison | Explicit ADU input and sufficient with/without-income scenarios, not eligibility. |
| Rural/agricultural/site | Reported site/use observations retain evidence; no invented acreage threshold or rule citation. |
| Photo/occupancy conflict | Clearly labeled mocked observation retains pictured/narrative uncertainty; unknown unit/date remain null; no verified current occupancy or fraud claim. |
| Revision/new lease | Actual version2lease upload and parent review, changed source/value proposals and earlier records preserved; no automatic confirmed-input replacement or finding resolution. Separate appraisal-value/revision tests cover changed/removed fields and shifted identity. |
| Missing/unreadable/conflict | Actual blank rendered page plus conflicting reported address; actionable source findings; unknown qualifying rent produces null DSCR, not0. |
| Open findings export | Real report generation remains available and includes open/provisional items. |
| Legitimate repeated photos | Actual synthetic embedded images, SHA/dHash duplicate candidates with legitimate-repeat explanation, never confirmed fraud. |
| Unsupported/manual | Different form/version recognized and manual limitation visible, never silently equivalent. |

## Additional security/finance/provider regression proof

Unit/address ambiguity, conflicting sources and exact native-word provenance; raw parcel/legal and decimal strings preserved; invalid/nested/negative/float inputs rejected; positive denominator/value basis; unknown versus confirmed0; total-only versus components; explicit IO definition; rational/decimal/display precision; approved-demo overlay/rounding gates; immutable rule/input/calculation/report lineage and retirement; no automatic eligibility decisions.

Cross-organization/source/parent/record isolation; specific shared Underwriter notes/confirmations/calcs/dispositions/exports/audit; required substantive resolution reason; revoked originals/pages/images/reports/polling/queued work; conservative Broker projections/no overrides/no internal fraud-sustainability export; generic authenticated notification/revocation. File-route switches clear stale notes/sources/calculations and ignore stale responses; ordinary shared notes/audit/original intake no longer depend on the historical sample screen.

Responses actual-pixel request/schema/coverage/store:false, malicious document instructions, refusal/429/transport/invalid schema, shared budget reservation/cache and bounded document/vision continuation, exact derivative nonoverwrite. Unknown provider outcome never blindly resubmits. Billing fake HMAC/timestamp/signature/org mapping/event identity/idempotency/stale replay/lifecycle, no redirect entitlement grants, operational usage retry quotas. Late Bank/Appraisal failure callbacks preserve successful terminal results and correctly finish cancelled usage.

All existing Bank12/24month, personal/business source cross-check, transfer double-count prevention/allocations, NSF/OD event counting, sustainability/income separation, restricted fraud and selected-period workbook tests remain in the full suite. Actual provider accuracy/concurrency/load and production-host acceptance are not inferred from mocks.

## Actual report/artifact inspection

Private files: `/workspace/mortgagelens-build/phase3-artifacts/fictional-review.pdf` and `.xlsx`; `long-text-review.pdf` and `.xlsx`; `artifact-verification.json`; normal rendered PNG pages and LibreOffice print PDFs in `xlsx-render/`.

- Final ordinary PDF **19pages**, long-note PDF **13pages**; actual property/loan/results, findings, source/version tail and long-note tail inspected through `pdftoppm` plus image Read. No clipping seen on inspected pages; extracted word bounding boxes outside page:0. This is document inspection, not a claim that every browser/device passed visual acceptance.
- Both actual XLSX files have all9requested tabs. XML and PhpSpreadsheet checks verify trusted formula caches **1.10/0.95/76.92**, numeric0.00formats, backend consistency, literal `=HYPERLINK` imported text, no external workbook links and full long-note sentinel. Printed formula diagnostics are now included in the sheet print area.
- LibreOffice headless normal document conversion produced **29 / 23pages**. Actual checked-formula page, first page and wrapped long-note ending visually inspected; figures and end sentinel visible, no clipping seen. The Java-helper warning did not prevent successful Calc/PDF conversion.
- Versioned issued bytes/hashes are independently tested: later source/input/note/renderer changes do not regenerate or overwrite old reports; current authorization and integrity checks apply on download.
- These are implemented internal review reports, not a fabricated supplied client workbook/template. Only the two attachment PNGs existed and both were GitHub installation screenshots, not report specifications.

## Observed local resource measurements

| Synthetic native workload | Pipeline wall | PHP process-lifetime peak | Actual Python process observations |
|---|---|---|---|
|2pages / separate appraisal+contract originals | 0.330s | 88,084,480bytes | maxRSS124,264KiB; parser wall0.0356s + 0.0306s |
|8page synthetic embedded-image report | 0.397s | 190,844,928bytes | maxRSS238,140KiB; parser wall0.206s; CPU user0.254715s/system0.035819s |

`metrics.json` and `eight-page-metrics.json` contain actual values. PHP peaks include framework/test/worker lifetime, not isolated incremental per-run allocation. Measurements use tiny fictional local native inputs and fictional scan acknowledgement, exclude live model latency, queue wait/report generation and real scanner costs, and **are not production capacity, load, accuracy or sizing guarantees**.

## Release status / unperformed checks

Implemented and locally verified: surrounding three-module workflows, deterministic finance, private reports, collaboration, neutral billing and security boundaries. Provider/schema/vision observations, simulated approvals/overlays and signed fake billing are fixture/provider-mocked only. **Live sandbox verified: none.** Official public Fannie pages were read on2026-11-08: B4-1.2-01effective2025-09-03 and B3-3.8-02effective2026-09-02; minimal developer content remains draft.

Waiting for client guideline/reviewer/investor/payment/IO/rounding/LTV/rent policies; actual supplied client specifications/workbooks and approved samples; live model/privacy/budget/vendor scope/credentials; selected billing provider and approved pricing/cancel/refund/access/usage policy; production scanner/signatures/S3/Brevo/sandbox/backup-restore/host acceptance; licensing, browser/accessibility/security/load and future deployment approval. Appraisal specialist forensics stays disabled/unclaimed. Known desktop loopback restriction was not bypassed; **full browser visual acceptance and Docker runtime were not performed**. Production readiness: **not approved**. See release-readiness, open-decisions and future deployment runbook.
