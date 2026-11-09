# Public marketing website

Added after Phases 1–3 as an addition to the existing application. It does not change analysis, permissions or billing behavior.

## Architecture

| Item | Implementation |
|---|---|
| Rendering | Laravel Blade views in `resources/views/site` (real server-rendered HTML; works without JavaScript). Layout `site/layout.blade.php` is separate from the dashboard SPA shell `app.blade.php`. |
| Assets | Separate Vite entries `resources/css/site.css` (Tailwind 4, same palette as the dashboard: navy `#102840`, teal `#0f766e`) and `resources/js/site.ts` (~0.5 kB, mobile menu only). The dashboard bundle (Vue, PDF.js) is never loaded on public pages (asserted in tests). No images are required; the product preview is HTML/CSS with fictional data. |
| Routes | `routes/web.php` named `site.*` (pages) and `seo.*` (sitemap/robots), registered before the SPA catch-all. |
| Content/config | `config/website.php` + `App\Website\Site` (page metadata, FAQ, indexing rules, approved-plan filter). No CMS. |
| Dashboard move | SPA dashboard `/` → `/dashboard`; `fortify.home` = `/dashboard` (email verification now redirects to `/dashboard?verified=1`); Vue router `/` and unknown paths redirect to `/dashboard`; brand/back links updated. |
| SEO | Unique `<title>`/description per page, canonical from `WEBSITE_URL`, Open Graph + Twitter summary tags, `/sitemap.xml` (public pages only; legal pages only once approved), dynamic `/robots.txt` (static `public/robots.txt` removed). `SecurityHeaders` adds `X-Robots-Tag: noindex, nofollow` to every response except indexable public pages; indexable = `WEBSITE_INDEXING=true` **and** `APP_ENV=production`. Robots directives are not access control; app/API routes keep their existing authentication. |
| Accessibility | Skip link, landmark/labelled sections, `aria-current` nav, mobile menu button with `aria-expanded`/`aria-controls`, Escape-to-close with focus return, visible `:focus-visible` outlines, labelled form fields with `aria-invalid`/`aria-describedby` errors, sample tables with captions, `prefers-reduced-motion` support. |
| Contact | `PublicSiteController@submit`; `website_inquiries` + `website_inquiry_notes` tables (migration `2026_11_09_000100`); `SendWebsiteInquiryNotification` queued job → `WebsiteInquiryReceived` text mail. IP stored only as an HMAC hash. |
| Admin | `WebsiteInquiryController` under existing `auth:sanctum` + `verified` + `throttle:workspace` API group, checks `users.platform_admin` before loading any record (non-staff get 403 even for unknown IDs). Vue view `resources/js/views/WebsiteInquiries.vue` at `/platform/inquiries`, linked in the sidebar and Platform administration page for staff only. |

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

Not claimed anywhere: testimonials, customers, logos, accuracy or time-saving statistics, certifications (SOC 2, HIPAA, "bank-grade"), zero retention, guaranteed fraud detection, integrations beyond the above, prices, limits, trials.

## Launch checklist

See README "Public marketing website" for settings, notification setup, pricing publication and remaining blockers.
