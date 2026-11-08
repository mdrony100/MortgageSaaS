# MortgageLens — integrated final-development release

Existing Laravel/PHP, Vue 3/TypeScript, PostgreSQL, Redis/Horizon, Python, PDF.js, private S3-compatible storage, OpenAI Responses, PhpSpreadsheet and PDF-report stack. All three modules now use actual pinned private ingestion, asynchronous review and versioned exports: Bank Statement Income & Integrity; Appraisal Review; Rental Income / DSCR–LTV. Appraisal vision has real pixel-input/schema/provider adapters, verified with HTTP mocks only; native labeled-layout/manual fallback is explicitly incomplete. Official-source guidelines remain draft until designated client approval. Billing is provider-neutral, disabled by default, with isolated local/testing fake flows. Plaid remains future. This is locally verified development, **not production approval, deployment or live paid-provider success**.

## Local setup (not deployment)

Use PHP 8.4 with PDO PostgreSQL, mbstring, DOM/XML, curl, zip, GD, bcmath, intl and pcntl; Composer, Node, PostgreSQL, Redis and Mailpit. Keep all environment files private and ignored.

```sh
composer install
npm ci
./bin/setup-env
python3 -m venv .venv-document-worker
.venv-document-worker/bin/pip install -r python-worker/requirements.txt
# Set BANK_PYTHON to this venv's absolute python path in private .env.
# Configure private PostgreSQL credentials; create mortgagelens and mortgagelens_test.
php artisan migrate
php artisan db:seed
npm run build
php -d upload_max_filesize=20M -d post_max_size=22M artisan serve --host=127.0.0.1 --port=8000
# Separate terminal; Redis must already be running:
php artisan horizon
# Also run local Mailpit SMTP1025/UI8025 and the Laravel scheduler.
php artisan schedule:work
```

Review URL: `http://127.0.0.1:8000` (choose one hostname consistently for cookie/CSRF authentication). Mailpit UI: `http://127.0.0.1:8025`. Register and verify through the local catcher; no fixed account/password, impersonation shortcut or platform-admin backdoor. `/horizon` requires a separately provisioned verified platform administrator even in local mode.

Docker Compose configuration is retained (`docker compose config --quiet`), not a claimed executed deployment. Container build/runtime acceptance still requires a working Docker daemon. The separate Python health service is not itself a queue consumer: Horizon launches the actual isolated document parser for pinned jobs.

Real documents require working ClamAV and current signatures. Missing/unavailable scanning is never clean. Explicitly fictional local/testing demo files may opt into `BANK_FICTIONAL_SCAN_BYPASS=true`; production cannot. Production parsing additionally requires the configured bubblewrap OS sandbox. See [provider/private-worker setup](docs/provider-setup.md).

## End-to-end Bank review

1. Create an authorized borrower/file; upload PDF/JPEG/PNG into private quarantine. Originals remain exact bytes with SHA-256; renders/manifest are separate artifacts.
2. Pin exact versions, personal/business workflow, 12/24 months, end month and immutable rule version. Queue actual analysis; watch status, scan/extraction/reconciliation issues and provider completeness. Retry/cancel only within bounded controls.
3. Verify account identity/designation and source coverage. Review/search/filter transactions, preserve original suggestions, correct source values with notes, bulk classify/include/exclude, and inspect the authenticated PDF.js source. Exact highlights are native-verified only.
4. Verify same-file account relationships, review proposed/ambiguous/split/combined transfers and explicit partial allocations. Select income accounts and confirm ownership/expense inputs with versioned reason + source notes; preview/recalculate new immutable snapshots.
5. Review NSF/OD, restricted sustainability/integrity, finding dispositions/history and deliberately public conditions/explanations. Income is not mortgage approval; indicators never automatically decline or redefine income.
6. Download real preliminary XLSX/CSV/PDF. Finalization requires approved configured completeness gates. Broker resources/templates contain only issued final income/monthly summaries and deliberate public conditions/explanations; internal decisions, policies, risk evidence, reports and other uploaders' originals stay forbidden.

Missing essential policy/evidence withholds the figure or permits an explicitly provisional observed-only figure under the selected profile; missing months are not silently zero and never change the 12/24 denominator. Development examples are not lender policies. Actual client workbook matching is pending: the implemented XLSX is visibly marked FALLBACK with backend-checked selected-period totals and detail overflow sheets.

Phase 1 sample bank analysis remains only an explicitly separate fictional demo toggle. It is not upload processing. Historical appraisal/DSCR sample previews remain separately labeled; ordinary uploads use the actual API-connected review workflow.

## Verification

Create private `.env.testing` using `DB_DATABASE=mortgagelens_test`, `APP_ENV=testing`, `QUEUE_CONNECTION=sync`, `MAIL_MAILER=array`, `CACHE_STORE=array`, `SESSION_DRIVER=array` and the actual parser venv. Never point tests at real data. PostgreSQL is required, not SQLite. The test bootstrap refuses non-testing/non-PostgreSQL connections and actual database names not ending `_test` before migrations.

```sh
php artisan test
npm test
npm run test:pdf
npm run build
vendor/bin/pint --test
composer audit
npm audit
# Actual local HTTP/cookie/CSRF + PostgreSQL + Redis/Horizon + Python + exports + SMTP:
python3 tests/smoke_http.py
php artisan bank:purge-transient
```

Synthetic fixtures/TEST extraction and HTTP-mocked provider contracts are labeled and do not measure OCR accuracy or prove live integrations. Native processing and the real HTTP queue/export smoke are separately verified. See [final executed acceptance record](docs/acceptance-test-results.md) and the historical [Phase 2 record](docs/phase2-verification.md).

## Appraisal / rental review and client demo

1. Create an appraisal or DSCR file and upload the relevant original appraisals, schedules, leases and supporting evidence. Select exact original versions, context and classifications; start a review. Native mode does not fabricate vision results.
2. Review coverage, evidence/pages/photos, classification, conflicts and proposed lease-unit matches. Confirm facts separately from AI suggestions. Keep loan/inspection/current occupancy and lease status distinct.
3. Record source/basis/confirmation for each selected rent, monthly payment, requested loan and chosen positive value basis. Decimal-safe backend snapshots show both DSCRs, LTV and missing/provisional items; no loan decision is issued.
4. Resolve/follow-up/reopen with reasons, preserve revisions and prior confirmed inputs, issue actual private PDF/XLSX even with open findings, and share/revoke using existing conservative roles.
5. Organization settings show usage, entitlement and subscription state. No unapproved prices are advertised; no live payment provider is selected. Redirects never grant paid access.

For an entirely fictional local walkthrough, `php artisan mortgage:dev-account` saves generated credentials privately, and `php artisan mortgage:fictional-review-documents` saves eight explicitly fictional native-layout PDFs privately. Neither command runs analysis or calls external providers. Follow [the three-module client walkthrough](docs/client-demo-walkthrough.md); never enable demo scan bypass for real files.

## Handoff

[All 25 requirements](docs/final-phase-checklist.md) · [executed acceptance results](docs/acceptance-test-results.md) · [architecture](docs/architecture.md) · [permissions](docs/permissions-matrix.md) · [appraisal review](docs/appraisal-review.md) · [DSCR/LTV](docs/rental-dscr-ltv-calculations.md) · [guideline approval](docs/guideline-checklist-approval.md) · [provider/privacy configuration](docs/provider-configuration.md) · [report mapping](docs/report-mapping.md) · [retention/backups](docs/retention-and-backups.md) · [release readiness](docs/release-readiness.md) · [open client decisions](docs/open-decisions.md) · [future deployment runbook](docs/deployment-runbook.md).

Historical Bank references: [Phase 2 checklist](docs/phase-2-checklist.md), [calculation specification](docs/calculation-specification.md), [Excel mapping](docs/excel-mapping.md). Actual supplied report/workbook matching and approved anonymized sample acceptance remain pending; no client template was fabricated.

No real borrower documents, credentials or private client workbooks are committed. No AWS resources, live paid-provider submissions, production deployment or main-branch changes are part of local acceptance. No approval/decline, automatic ineligibility, lender condition clearance, underwriting sign-off or fraud/regulatory guarantees.
