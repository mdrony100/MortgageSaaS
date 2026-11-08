# Phase 2 provider and private-worker setup

## Approval / no live calls in development acceptance

Default extraction is `BANK_EXTRACTOR=hybrid`: native PDF text/words/verified rotated-page coordinates first, normalizing only the supported explicit labeled/pipe-column layout; incomplete layouts/scans use bounded OpenAI vision only when separately approved/configured. Otherwise incomplete native evidence stays explicit, never fabricated. `native` and `openai` can be explicitly selected. Blank/scanned pages cannot silently count as complete native extraction. `tests/Support/BankFixtures.php` is an explicitly named TEST adapter, injected only by PHPUnit; no production fallback switches to it.

OpenAI and Ocrolus require explicit server-side approval flags, credentials, a configured organization budget AND a positive conservative per-call reservation before any submission. Defaults are disabled/zero. Do not enable them or submit real borrowers/paid calls without separate data-processing/cost approval. No keys, real borrowers or full provider payloads belong in fixtures/logs. Model IDs must be selected by an operator from current official model documentation; the default is unset. Test IDs beginning TEST are HTTP-mock labels, NOT live model IDs.

## Official documentation read for implementation (2026-11-08, Dhaka)

- Responses creation and `text.format`: https://developers.openai.com/api/reference/resources/responses/methods/create
- Structured output, refusals and incomplete responses: https://developers.openai.com/api/docs/guides/structured-outputs
- Native PDF / vision file inputs: https://developers.openai.com/api/docs/guides/file-inputs
- Base64 image inputs, detail and limitations: https://developers.openai.com/api/docs/guides/images-vision
- Retention and file expiry: https://developers.openai.com/api/docs/guides/your-data
- Ocrolus bearer-token use / 24-hour expiry: https://docs.ocrolus.com/docs/using-api-credentials
- **Actual current upload contract**: https://docs.ocrolus.com/reference/upload-document (`POST https://api.ocrolus.com/v1/book/upload`, multipart `upload` ORIGINAL bytes + `book_uuid`; parse `response.uploaded_docs[].uuid`, not a guessed v2 upload URL).
- Detect: https://docs.ocrolus.com/reference/doc-fraud-signals (`GET /v2/detect/uploaded_doc/{uuid}/signals`, `doc_analysis[].detect_status`, `form_analysis[].signals`).

The adapter uses documented polling, not an invented webhook signature. A dedicated preprovisioned Ocrolus Book must be bound to exactly one organization/loan file in `bank_vendor_books`; UUID uniqueness prevents sharing a Book between files. Book provisioning and token rotation are operator setup steps, NOT a fabricated Book-create endpoint. Set a short-lived `OCROLUS_ACCESS_TOKEN` server-side; renewal uses the documented OAuth flow outside this adapter. MoneyThumb has not been selected/configured; selecting an unsupported provider is explicitly Unavailable.

## Server configuration

- `BANK_PYTHON=/absolute/path/to/venv/bin/python`; install pinned `python-worker/requirements.txt`.
- `BANK_CLAMAV=clamscan`: provision current virus signatures before real ingestion. Missing binary/signatures, errors and infection yield Unavailable/ScanFailed/Rejected, never Clean. Scan verdicts append separately from immutable versions.
- `BANK_FICTIONAL_SCAN_BYPASS=true` is permitted ONLY when APP_ENV is local/testing, file is an explicitly fictional demo, AND original upload acknowledged fictional. Production cannot use it. No claim of actual antivirus certification.
- Production requires `BANK_PARSER_SANDBOX=bwrap` and working bubblewrap user/network namespaces. The wrapper exposes read-only Python/runtime libraries, parser code and one original, plus one writable derivative output directory; no app configuration or other borrower files. Local parser has OS CPU/address-space/file/descriptors limits and disables socket creation, scripts/launches/attachments and URL following. Python-only restrictions are NOT claimed to replace the production OS sandbox. Container/sandbox execution must be verified on the release host; the fictional production-mode parser was verified inside actual bubblewrap namespaces on this box; Docker daemon/container execution remains unavailable. This is not a security certification of another release host.
- `BANK_EXTRACTOR=openai`, `BANK_OPENAI_APPROVED=true`, `OPENAI_API_KEY`, `BANK_OPENAI_MODEL`, optional `BANK_OPENAI_SECONDARY_MODEL`, `BANK_OPENAI_RESERVATION_MICRO_USD`.
- `BANK_INTEGRITY_PROVIDER=ocrolus`, `BANK_INTEGRITY_APPROVED=true`, `OCROLUS_ACCESS_TOKEN`, `BANK_OCROLUS_RESERVATION_MICRO_USD` and a tenant/file Book binding.
- Configure `bank_limits` organization lifetime reserved budget and per-run limit in integer micro-USD. No default budget. Reservations are conservative configured ceilings, not invented prices or claims of measured actual billing. Validate reservation bounds against the selected model/page/token caps and commercial Detect agreement before approval. Recorded token/page usage is available per run/provider call. Unknown timeout/submission outcomes hold their reservation and require operator review; the application will not blindly repeat them. 429 retries reuse the same reservation, bounded queue attempts/backoff. An atomic compare-and-swap claims each external submission before transport. Shared run locks serialize processing/poll jobs. Secondary interpretation is separately reserved for malformed output only; refusals never trigger a second model. Budgets never auto-increase.

## Privacy / retention

OpenAI uses foreground Responses, inline rendered-page data, strict schema, `store:false`, no tools/background mode/remote URLs/Files API. Thus no separately uploaded OpenAI File objects need deletion. `store:false` **does not guarantee zero retention**: ordinary abuse-monitoring retention can be up to 30 days and image/file exceptions apply. Obtain contractual retention/data-residency approval as appropriate.

Full Responses payloads are never persisted or logged. Minimal normalized replay extraction is Laravel-encrypted, expires after seven days via daily `bank:purge-transient`, and cannot silently trigger duplicate paid replay after expiry. Usage, call IDs/statuses and immutable normalized financial evidence remain for audit under the eventual approved customer retention policy. Stale local parser scratch directories expire after 24 hours; originals are never purged by this command. Ocrolus may retain originals/visualizations until deletion under its agreement; an approved vendor retention/deletion process is a release prerequisite. This code does not invent or automatically invoke a deletion API.

Use private filesystem `private` or private S3-compatible disk with server-side encryption (default AES256, configurable for compatible vendors), least-privilege bucket policy and encrypted storage/DB backups. No AWS resources are provisioned. Local/fake storage tests do not verify AWS or infrastructure encryption. Structured account IDs retain masked suffix only; long identifier-like sequences in excerpts/reference text are masked. Original exact bytes necessarily contain source information and must remain access-controlled and encrypted at infrastructure level.

## Live verification pending

No live OpenAI, Detect, AWS or paid OCR call is required by automated fixtures. Approval, keys, dedicated Books, budgets, approved sample statements, actual client workbook and lender-approved policy/evidence/finalization rules remain required for release. Disabled/pending/failed specialist checks are incomplete, never “No fraud detected”.

## Verified local parser dependency/security notes

Pinned PyMuPDF1.26.7 and Pillow13.0.0; Pillow was upgraded after the advisory audit. `pip-audit -r python-worker/requirements.txt` reports no known advisories at verification time (not a guarantee about every bundled native library). Review PyMuPDF/MuPDF AGPL/commercial licensing obligations before production distribution/service use; no license was purchased. Subprocess environments are allowlisted and do not inherit application credentials. Manifests are bounded private files, not financial stdout/log payloads. API/queue errors omit SQL/source/provider payloads; static operator-safe processing errors remain actionable.

`BANK_TRANSFER_WINDOW_DAYS` is a pinned candidate heuristic, not lender eligibility. Matching has explicit comparison/candidate resource caps and raises a review finding rather than declaring unsearched transfers absent. Provider retention/legal terms, signature updates and release-host infrastructure still require acceptance.
