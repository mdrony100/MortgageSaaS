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

## Public marketing website

Server-rendered Blade pages (`resources/views/site`) with their own small Vite entry (`resources/css/site.css`, `resources/js/site.ts`); the dashboard Vue bundle is not loaded on public pages. Details, claim evidence and launch checklist: [docs/public-website.md](docs/public-website.md).

**Design system (redesign):** fluid type scale (hero ~37→58px, section headings ~29→42px, body 17→19px, buttons/nav 16–17px, captions ≥14px), 1280px content width with full-width white / light-teal sections, navy/teal palette. Homepage narrative: hero with a three-tab product preview → interactive four-step sample walkthrough → three feature stories (bank statements, document checks, appraisal & rental income with a local sample DSCR calculator) → team roles → "Built for your workflow" → customer reviews (approved only) → security → FAQ → final CTA. All interactivity is vanilla TypeScript in `resources/js/site.ts` (+ `resources/js/site/dscr.ts`), progressive (readable with JavaScript disabled) and respects `prefers-reduced-motion`. Photography is locally served, licensed Pexels images (sources in [docs/website-assets.md](docs/website-assets.md)).

**Customer reviews:** add only genuine, written-approved reviews to `config/website.php` → `testimonials` with `'approved' => true` (quote, name, role/company, optional approved photo). Until then production hides the reviews section and shows the role-based "Built for your workflow" section; non-production environments show clearly labelled placeholder cards for design review only.

**Rendered browser checks:** `tests/browser_website.py` (Playwright, not a project dependency — install it in a throwaway venv) checks every public page at 375/768/1280/1440px for horizontal overflow, 44px targets, ≥14px text, console errors and third-party requests, exercises tabs/walkthrough/calculator/FAQ/mobile menu, reduced motion and JavaScript-disabled rendering, and saves screenshots to `SCREENSHOT_DIR`.

**Public routes:** `/` (home), `/features`, `/how-it-works`, `/pricing`, `/security`, `/faq`, `/contact` (GET form, POST submit), `/privacy`, `/terms`, `/sitemap.xml`, `/robots.txt`. The authenticated dashboard moved from `/` to **`/dashboard`** (Fortify `home` and SPA redirects updated; all other app routes such as `/login`, `/files/{id}`, `/settings/*` are unchanged; `/platform` now redirects to the owner console at `/admin`). Signed-in visitors see **Open Dashboard** on the public site.

**Website settings** (`config/website.php`, env in `.env.example`): `WEBSITE_PRODUCT_NAME` (defaults to `APP_NAME`, currently MortgageLens), `WEBSITE_URL` (canonical base, defaults to `APP_URL`), `WEBSITE_INDEXING` (indexing only when also `APP_ENV=production`; everything else sends `noindex` meta + `X-Robots-Tag`, and `robots.txt` disallows all), `WEBSITE_CONTACT_EMAIL` / `WEBSITE_CONTACT_PHONE` (shown only when set), rate limits, legal approval flags. No analytics or trackers are included.

**Contact notification setup:** submissions are validated (name, work email, company, optional role, inquiry type, message; no attachments), rate limited per IP (`WEBSITE_INQUIRY_PER_MINUTE`/`_PER_DAY`), honeypot-protected and **saved before success is shown**. When `WEBSITE_INQUIRY_NOTIFY_EMAIL` is set, a queued job (`SendWebsiteInquiryNotification`, 3 tries) emails the inbox through the existing `MAIL_*` SMTP + Redis/Horizon queue; status is tracked per inquiry (`queued`, `sent`, `failed`, `dispatch_failed`, `not_configured`, `skipped_spam`). Failures are logged without content and never shown to visitors. **Pending:** production SMTP and the business inbox address are not configured; until then inquiries are only visible in the admin area. Locally, Mailpit receives the mail.

**Website Inquiries admin:** moved into the owner console at `/admin/inquiries` (API `/api/admin/inquiries`). The old `/platform/inquiries` URL redirects there. Access requires a verified platform admin (see below); organization roles, including owners/admins, receive 403. Filters by type/status/spam, statuses New/In Progress/Closed, internal notes, mark/unmark spam, and **Convert to organization invite**. All changes are audited.

**Pricing publication steps:** pricing is **contact-only** because no client-approved prices exist (`config('billing.catalog')` is empty and the legacy `provisional_monthly_price` is never shown). To publish: (1) obtain written approval of plan names, price labels and included items; (2) add them to `config/website.php` → `pricing.plans` with `'approved' => true`; (3) set `WEBSITE_PRICING_PUBLISHED=true`; (4) separately configure the approved billing catalog/provider before any plan is sold online — the public page never offers checkout.

**Missing legal/business content:** Privacy Policy and Terms render clearly labelled **DRAFT** placeholders (noindexed, excluded from the sitemap) until approved text is added as `resources/views/site/legal-approved/{privacy,terms}.blade.php` and `WEBSITE_LEGAL_APPROVED=true`. Legal entity, address, jurisdiction, retention periods, public contact details and prices are not supplied and were not invented.

**Remaining public-launch blockers:** approved legal text and company identity; genuine approved customer reviews (optional — section stays hidden without them); approved brand assets and product photography choice; approved contact details and notification inbox with production SMTP; pricing decision; production domain/HTTPS and `WEBSITE_URL`; enabling `WEBSITE_INDEXING` in production only; manual acceptance on real devices/browsers and assistive technology (automated headless Chrome checks only so far); optional social share image and brand assets approval; retention period for stored inquiries.

## Owner console (`/admin`)

A separate platform-owner back office with its own URL, bundle and dark navy/indigo design: Overview, Analytics, Revenue, Approvals, Organizations, Users, Plans & billing, Website inquiries, Reports (CSV/XLSX), Audit log and System status. Customers never see it. Platform admins get a small **Owner console** link in the customer sidebar. Full details, including the safeguards for **Login as customer** support sessions: [docs/owner-console.md](docs/owner-console.md).

- **Access:** verified + `users.platform_admin` + not disabled, plus confirmed 2FA when `OWNER_CONSOLE_REQUIRE_2FA=true` (default on in production). The UI can never grant the flag. Use `php artisan mortgage:platform-admin someone@example.com` (or `--revoke`); both are audited.
- **New env:** `OWNER_CONSOLE_REQUIRE_2FA`, `PLATFORM_SIGNUP_APPROVAL` (false), `IMPERSONATION_ENABLED` (on only in local/testing by default; set it explicitly in production), `IMPERSONATION_MINUTES` (30), `IMPERSONATION_ALLOW_DOCUMENT_DOWNLOAD` (false), `APP_VERSION`.
- **Updating an existing server** (operator steps, not automated deployment):

```bash
git pull origin develop
composer install --no-dev --optimize-autoloader
php artisan migrate --force          # adds owner-console columns/tables
npm ci && npm run build
php artisan config:cache && php artisan route:cache   # only if you already cache them
php artisan horizon:terminate        # Supervisor restarts Horizon with the new code
php artisan mortgage:platform-admin admin@your-domain.example
```

The new admin then signs in, turns on two-factor authentication under **My account**, and opens `/admin`.

## Handoff

[All 25 requirements](docs/final-phase-checklist.md) · [executed acceptance results](docs/acceptance-test-results.md) · [architecture](docs/architecture.md) · [permissions](docs/permissions-matrix.md) · [owner console](docs/owner-console.md) · [appraisal review](docs/appraisal-review.md) · [DSCR/LTV](docs/rental-dscr-ltv-calculations.md) · [guideline approval](docs/guideline-checklist-approval.md) · [provider/privacy configuration](docs/provider-configuration.md) · [report mapping](docs/report-mapping.md) · [retention/backups](docs/retention-and-backups.md) · [release readiness](docs/release-readiness.md) · [open client decisions](docs/open-decisions.md) · [future deployment runbook](docs/deployment-runbook.md).

Historical Bank references: [Phase 2 checklist](docs/phase-2-checklist.md), [calculation specification](docs/calculation-specification.md), [Excel mapping](docs/excel-mapping.md). Actual supplied report/workbook matching and approved anonymized sample acceptance remain pending; no client template was fabricated.

No real borrower documents, credentials or private client workbooks are committed. No AWS resources, live paid-provider submissions, production deployment or main-branch changes are part of local acceptance. No approval/decline, automatic ineligibility, lender condition clearance, underwriting sign-off or fraud/regulatory guarantees.
