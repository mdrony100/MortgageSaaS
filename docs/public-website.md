# Public marketing website

Added after Phases 1–3 as an addition to the existing application. It does not change analysis, permissions or billing behavior.

## Architecture

| Item | Implementation |
|---|---|
| Rendering | Laravel Blade views in `resources/views/site` (real server-rendered HTML; works without JavaScript). Layout `site/layout.blade.php` is separate from the dashboard SPA shell `app.blade.php`. |
| Assets | Separate Vite entries `resources/css/site.css` (Tailwind 4, same palette as the dashboard: navy `#102840`, teal `#0f766e`) and `resources/js/site.ts` (~7 kB / ~2.8 kB gzip: mobile menu, ARIA tabs, walkthrough, sample DSCR calculator, reveal-on-entry, review carousel). The dashboard bundle (Vue, PDF.js) is never loaded on public pages (asserted in tests). Product visuals are HTML/CSS illustrations with fictional data; two licensed photos are served locally from `public/images/site` (AVIF/WebP/JPEG, 640/1200 px) — see [website-assets.md](website-assets.md). |
| Routes | `routes/web.php` named `site.*` (pages) and `seo.*` (sitemap/robots), registered before the SPA catch-all. |
| Content/config | `config/website.php` + `App\Website\Site` (page metadata, FAQ, indexing rules, approved-plan filter). No CMS. |
| Dashboard move | SPA dashboard `/` → `/dashboard`; `fortify.home` = `/dashboard` (email verification now redirects to `/dashboard?verified=1`); Vue router `/` and unknown paths redirect to `/dashboard`; brand/back links updated. |
| SEO | Unique `<title>`/description per page, canonical from `WEBSITE_URL`, Open Graph + Twitter summary tags, `/sitemap.xml` (public pages only; legal pages only once approved), dynamic `/robots.txt` (static `public/robots.txt` removed). `SecurityHeaders` adds `X-Robots-Tag: noindex, nofollow` to every response except indexable public pages; indexable = `WEBSITE_INDEXING=true` **and** `APP_ENV=production`. Robots directives are not access control; app/API routes keep their existing authentication. |
| Accessibility | Skip link, landmark/labelled sections, `aria-current` nav, mobile menu button with `aria-expanded`/`aria-controls`, Escape-to-close with focus return, visible `:focus-visible` outlines, labelled form fields with `aria-invalid`/`aria-describedby` errors, sample tables with captions, `prefers-reduced-motion` support. |
| Contact | `PublicSiteController@submit`; `website_inquiries` + `website_inquiry_notes` tables (migration `2026_11_09_000100`); `SendWebsiteInquiryNotification` queued job → `WebsiteInquiryReceived` text mail. IP stored only as an HMAC hash. |
| Admin | `WebsiteInquiryController` under existing `auth:sanctum` + `verified` + `throttle:workspace` API group, checks `users.platform_admin` before loading any record (non-staff get 403 even for unknown IDs). Vue view `resources/js/views/WebsiteInquiries.vue` at `/platform/inquiries`, linked in the sidebar and Platform administration page for staff only. |

## Redesign (visual, interactive, plain English)

| Area | Implementation |
|---|---|
| Hero | Two columns; headline "Understand mortgage documents. Review every detail with confidence."; CTAs Request a Demo (`/contact?type=demo`) and See How It Works (scrolls to `#walkthrough`, selects and focuses step 1). Browser-framed preview with three WAI-ARIA tabs (Bank Statements, Document Checks, Appraisal & Rental Income; arrow/Home/End keys) and a visible "Sample data" label. |
| Walkthrough | `partials/home/walkthrough.blade.php`, labelled "Interactive sample walkthrough": four steps as vertical tabs, Previous/Next, "Step n of 4" live status and animated progress bar. No autoplay, no uploads, no network or AI calls. Mobile stacks the preview beneath the step controls. |
| Feature stories | Bank statements, document checks and appraisal & rental income, alternating image/text, each linking to `/features#bank-statements`, `#document-integrity`, `#appraisal-review`. Findings are labelled "Potential issue" / "Needs review" — never "fraud confirmed". |
| Sample DSCR calculator | `resources/js/site/dscr.ts`: input parsed to integer cents (no floating point), ratio rounded HALF_UP to 2 decimals with BigInt — the same display convention as `App\Appraisal\Calculator` (`BigDecimal::dividedBy(…, 2, RoundingMode::HalfUp)`). Validates empty, non-numeric, negative, >2 decimals, ≥$10M and zero payment with inline `aria-live` errors. Labelled "Illustration only…"; no thresholds, approvals or eligibility. Runs entirely in the page. |
| Reviews | `Site::reviews()`: only `config('website.testimonials')` entries with `approved => true`; otherwise placeholders in non-production and nothing in production (feature-tested). |
| Progressive enhancement | An inline head script adds `html.js` (removed again after 4 s if `site.ts` never initialises), so inactive tab panels are hidden only when scripting works. Without JavaScript all tab/walkthrough panels render stacked; FAQ uses native `details/summary`. Reveal animation hides sections only after `IntersectionObserver` is set up and never under `prefers-reduced-motion`, which also disables all transitions/animations. |

### Additional claims introduced by the redesign

| Claim | Evidence |
|---|---|
| "Each file is checked for malware before anyone can open it." / FAQ "must pass a malware scan" | `DocumentController@content` returns 423 unless scan verdict `Clean`; pipelines refuse non-clean versions. |
| "Your company’s files stay private." / "Private file access" | `Services\PrivateStorage`, `Services\Access::visible` (organization + file grant), `DocumentController@content` authorization. |
| "Share files while keeping access permissions in place." / "Sharing a file never raises anyone’s role." | `FileShare` grants never change `memberships.role`; `LoanFilePolicy` checks role AND grant. |
| "See who changed what, and when." / history "can’t be edited later" | Append-only triggers (`2026_11_08_000200_protect_evidence`) on `audit_events` and evidence tables; `Audit::record`. |
| "Two-factor sign-in … code from an authenticator app" | Fortify two-factor authentication (TOTP) features in `config/fortify.php`, events audited in `AppServiceProvider`. |
| Brokers: "limited view", "restricted findings hidden", "can’t change results", can upload | `LoanFilePolicy::restricted` (owner/admin/underwriter only), `::review` via `Access::reviewer` (owner/admin/underwriter), `::upload` includes broker; broker serializers in `Services\Present`; broker report audience in `Bank\Reports\ReportService`. Brokers are **not** described as able to share (only admins/owners, or the creating underwriter, can share). |
| Processors "upload documents, add notes" | `LoanFilePolicy::update` and `::upload` include processor. |
| Reviewers correct values, change deposit classification, confirm/reject transfers, reason recorded, original kept | `BankController` review/override endpoints with `reason_code`; overrides append-only. |
| Export formats (bank XLSX/CSV/PDF, appraisal PDF/Excel), versioned | `Bank\Reports\Export`, `Appraisal\Reports` (`['pdf','xlsx']`), report version counters. |
| Uploads PDF/PNG/JPG up to 20 MB; forms 1004/1025/1007/216; 1–4 units | `DocumentController::save`; appraisal form allowlist and unit scope (see table above). |
| Sample visuals and figures | All fictional (e.g. "Sample Borrower LLC", "Sample Bank", $3,300 ÷ $3,000 = 1.10), visibly labelled "Sample data" / "Fictional". Team names (Alex R., Jordan M., Sam K., Taylor P.) are fictional and marked as such. |

## Claim verification

Every product statement on the public pages was checked against the code. Statements that could not be verified were removed or reworded.

| Marketing claim | Evidence |
|---|---|
| 12 or 24 months of statements | `BankController@start` validates `months` in `[12, 24]`; `FileController` draft inputs `statement_months` in `[12, 24]`; `Bank\CalculateIncome` builds the month window. |
| Upload PDF, PNG, JPG up to 20 MB | `DocumentController::save` (`mimes:pdf,png,jpg,jpeg`, `max:20480`, content/extension agreement). |
| Native parser first; optional AI extraction only when configured and approved | `Bank\Providers\HybridExtractor` (native first; OpenAI only if `bank.openai.approved` + key + model); `review.vision_approved` for appraisal; both default false. |
| Classify deposits and review exclusions | `config('bank.categories')` (payroll, owner distribution, own transfer, loan proceeds, refund, ...), review endpoints in `BankController`. |
| Potential transfers / duplicate counting | `Bank\TransferEngine`; `CalculateIncome` blocks on unresolved transfer candidates and "Overlapping/duplicate coverage". |
| Income using configured rules | Versioned `bank_rules` with approval (`BankController@rule`), `Bank\RuleProfile`, deterministic `CalculateIncome`. |
| NSF and overdraft review | `Bank\RiskMetrics`, NSF/OD rows in `Bank\Reports\Export`. |
| Source references | Page words/regions in `bank_pages`; source viewer `DocumentViewer.vue`; findings carry `document_version_id` + page. |
| Excel/CSV/PDF bank exports; PDF/Excel appraisal reports | `Bank\Reports\Export` (xlsx/csv/pdf), `Appraisal\Reports` (pdf/xlsx); routes `.../reports/{id}/{format}`. |
| Reconciliation discrepancies | `Bank\Reconcile` (reported vs calculated credits/debits, tolerance in `RuleProfile`). |
| Potential missing/reordered/duplicate pages | `Bank\Pipeline` findings `missing_or_reordered_pages`, `duplicate_page`. |
| Consistency indicators (PDF structure, font variation) — not proof | `Bank\Pipeline` `native_font_variation`, structural indicator findings, wording "not proof of tampering/alteration". |
| Findings with evidence and recorded decisions/reasons | `BankController@finding/review` with `reason_code` (`config('bank.reason_codes')`); appraisal resolve/follow-up/reopen with notes (`AppraisalController@action`). |
| Specialist fraud provider | **Not advertised.** `OcrolusDetect` adapter exists but `BANK_INTEGRITY_PROVIDER=disabled` by default and no live credentials; listed only as an open decision. |
| Forms 1004, 1025, 1007, 216; 1–4 units; out-of-scope flagged | `Appraisal\Pipeline` form allowlist and `scope_limitation` findings for unit count/property type; `FileController` `units` 1–4. |
| Compare appraisal with supporting documents (leases, contract) | `Appraisal\LeaseMatcher`, source rent comparison and purchase-contract fixtures in `PhaseThreeTest`. |
| Potentially reused photos via fingerprints | Embedded-image SHA/dHash duplicate candidates (`review.max_duplicate_candidates`, Phase 3 pipeline). |
| Findings and revisions tracked | Review records/revisions in `App\Appraisal\Store`, `review_records`. |
| Market vs reviewer-confirmed qualifying rent; DSCR from confirmed inputs; provisional when missing | `Appraisal\Calculator` (formula "confirmed monthly rent / confirmed monthly payment", null → provisional), per-unit confirmation inputs. |
| Roles owner/admin/underwriter/processor/broker | Role lists in `OrganizationController`/policies; `LoanFilePolicy`. |
| Sharing never elevates role; file-level access | `FileShare`, `Services\Access`, `LoanFilePolicy` (role AND grant), Phase 1 tests. |
| Broker-safe reports/views | `Bank\Reports\ReportService` separate `audience=broker` report; Broker serializers (`Services\Present`). |
| Append-only audit/evidence | Migration `2026_11_08_000200_protect_evidence` triggers on `audit_events`, `document_versions`, `source_evidence`, overrides, snapshots. |
| Audit of sign-ins, uploads, shares, reviewer decisions, report downloads | `Audit::record` actions `auth.login`, `document.uploaded`, `file.shared`, `bank.review_recorded`, `reviewer.override`, `bank.report_downloaded`, `appraisal.report_downloaded`. |
| Private storage, served only through authenticated permission checks | `Services\PrivateStorage` refuses public/served disks; `DocumentController@content` authorizes and checks scan verdict. |
| Quarantine until malware scan is clean; unavailable ≠ clean | `DocumentController` quarantine keys, `content()` 423 unless `Clean`; `Bank\Scanner` returns `Unavailable`; pipelines refuse non-clean. |
| Two-factor, email verification, rate-limited sign-in | Fortify features (`config/fortify.php`), `login` limiter in `FortifyServiceProvider`. |
| Encryption: TLS deployment-dependent; cached AI extraction encrypted; at-rest depends on provider | `encrypt()` in `OpenAIExtractor`/`VisionExtractor`; no app-level file encryption → not claimed. |
| Retention not yet published; cleanup review-only | `config('review.retention.approved') = false`; purge commands default to dry run (`docs/retention-and-backups.md`). |
| External processor disclosure (OpenAI when enabled) | `OpenAIExtractor`/`VisionExtractor` send page images when approved. Site never says documents never leave the platform. |
| Usage allowances depend on plan | `plans.file_limit/member_limit/storage_limit_bytes` enforced by `Services\Entitlements`. Optional integrations per plan: **not** claimed (no such config). |
| Contact for pricing | `config('billing.catalog') = []`; billing provider disabled/fake only; `website.pricing.published=false`. |
| Plaid planned | `PlaidImport` bound to `DisabledProvider`; no Connect Bank CTA (tested). |
| No lending decisions | Product scope throughout (`docs/open-decisions.md`), footer, How It Works and FAQ. |

Not claimed anywhere: customer reviews (none approved; placeholders never render in production), customers, logos, accuracy or time-saving statistics, certifications (SOC 2, HIPAA, "bank-grade"), zero retention, guaranteed fraud detection, integrations beyond the above, prices, limits, trials.

## Launch checklist

See README "Public marketing website" for settings, notification setup, pricing publication and remaining blockers.
