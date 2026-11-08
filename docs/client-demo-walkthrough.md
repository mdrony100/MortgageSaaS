# Concise client walkthrough — all three modules

## Safe local preparation
Use README local setup with PostgreSQL, Redis/Horizon, configured Python venv, Mailpit and production frontend build. No deployment is required or performed. Keep external vision/provider adapters disabled for this native walkthrough. Real files require working malware scanning/current signatures; fictional scan acknowledgement is local/testing-only.

Optional commands:
```sh
php artisan mortgage:dev-account
php artisan mortgage:fictional-review-documents
```
The first prints the location of a private generated-credential file, never a fixed password; keep it private. The second prints a private0700directory containing eight0600 PDFs. Every PDF says **FICTIONAL DEVELOPMENT DOCUMENT**, explicitly not a Fannie form, actual appraisal, executed lease or actual bank statement. No supplied client template is represented. Neither command runs analysis or an external API. Use only these fictional inputs in explicitly marked fictional/demo files; acknowledge their fictional status on upload. Historical “sample analysis” is a different, separately labeled action, not processing.

## 1 — Bank Statement Income & Integrity
Create a fictional business bank file; upload `FICTIONAL-bank-one-month.pdf`. Pin that exact original, business workflow,12months ending January2025 and a visibly unapproved example policy (if none exists, use “Fill clearly unconfirmed draft structure,” name it FICTIONAL, select example status and save). Start real native processing through Horizon. Inspect original/coverage/source identity, transaction, opening/closing reconciliation and missing11months. Confirm ownership100% and example expense50% with a recorded fictional reason. The observed-only provisional numerator500 divided by12 is41.67 under that **example**, not a lender-approved policy; missing months are not silently zero. Review income separately from internal sustainability/fraud/NSF; demonstrate fallback workbook/PDF/CSV, with final publication gates still enforced. A Broker never receives internal risk evidence or overrides.

## 2 — Appraisal Review
Create a fictional appraisal file; upload `FICTIONAL-primary-purchase.pdf`. Choose primary/purchase/1unit and exact original/version/classification, then start ordinary analysis. Show queued/running/review status, page coverage, source/extracted facts and uncertain/manual fields. Native fallback does not pretend to interpret photographs or certify a signature/appraisal. Confirm an address or fact with a reason, show the separate original and confirmation, and add a note/finding follow-up. Show the minimal official-source **draft** checklist and unavailable live vision status; do not approve production policy as a developer. Download real reports **with open findings**, showing evidence, provisional status and frozen versions.

## 3 — Rental income / DSCR–LTV
Create a fictional DSCR file; upload `FICTIONAL-four-unit-appraisal.pdf` and leases for units1–3. Select investment/cash-out/4units, exact originals and classifications. Inspect explicit address/unit evidence and confirm each lease match. Leave inspection occupancy unknown unless separately confirmed; set current occupancy occupied for1–3 and explicitly vacant for4 only as a fictional reviewer decision. Lease absence alone never establishes vacancy.

Record source/basis/reviewer confirmation: market rent825 per unit; qualifying rent950 for each of1–3 and an explicit confirmed0 for4; total-only monthly payment3000. These are recorded demonstration choices, **not** automatic higher-rent selection or approved program treatment. Market3300/3000=**1.10**; qualifying2850/3000=**0.95**. Show separate partial-vacancy/program-permission, market-use and documentation items, lease-versus-market discrepancy, and pending rounding policy. Enter loan250000 and reviewer-chosen qualifying basis325000, keeping reported appraisal330000 separate: **LTV76.92%**. No limit/eligibility is inferred.

Upload the fictional revised appraisal and unit3 amendment using original-version relationships; start a child review. Show value330000→340000 and rent950→1000 as proposals, old sources/reports/decisions retained. Confirm updated calculator inputs explicitly to obtain a new snapshot; findings do not auto-resolve. Optional ADU scenarios require explicit sufficient inputs, not automatic rent inclusion.

## Collaboration, billing and finish
Share with a specific same-organization authorized reviewer; demonstrate notes, confirmations, calculations, dispositions/audit and reports. Revoke access and show originals/derived pages/reports/polling denied. Sharing never upgrades a Broker role. Organization settings show existing entitlements/usage and billing state. With no selected live provider, checkout is unavailable; test-only fake lifecycle/webhook demonstrations do not charge money or operate in production. No paid entitlement follows a success redirect.

Close with release-readiness/open-decisions: client specifications/templates, designated guideline approval, investor rent/IO/rounding/LTV policy, live model/privacy/budget approval, live billing/pricing/policies, scanner/vendor/storage/email credentials, approved sample/browser/accessibility/security/load and future deployment/backup acceptance. Native HTTP/DOM tests are not full visual browser acceptance; no lender acceptance or fraud accuracy guarantee.
