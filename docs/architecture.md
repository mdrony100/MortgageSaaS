# Architecture and security boundaries

## Request flow

Browser → same-origin Laravel web stack (encrypted session cookies, CSRF, Sanctum `auth:sanctum`, verified email, rate limits) → organization membership + Laravel file/document policy → allowlisted inputs → PostgreSQL/private storage. There are no bearer tokens in localStorage; Pinia stores only current user/membership state. LocalStorage contains only the chosen organization id, which is never trusted for authorization.

Fortify handles login/logout, registration, signed verification, SMTP reset, profile/password updates, password-confirmed TOTP setup/confirmation/recovery, challenge and single-use recovery replacement. Pending second-factor login does not create an authenticated session. Password reset clears database sessions. Passwords require 12 characters, letters and numbers. Login and TOTP are throttled. UI form submissions supply Laravel's XSRF cookie header.

No policy `before()` grants platform admins access to customer files. Every file view first requires current membership and then creator/share/explicit admin-wide access. Sharing does not alter the role. Resource identifiers alone provide no access. PostgreSQL composite foreign keys prevent tenant-mismatched borrowers, files, documents, shares and analysis rows. Server predicates, not frontend filtering, establish isolation.

## Application surfaces

- Vue Router: workspace dashboard, loan file, organization, account/security, guidelines, provisional plans/usage, metadata-only platform administration and help.
- Three module tabs share source/version viewer, notes, activity and role-scoped reports.
- `Access` and Laravel policies centralize grants; `Present` allowlists Broker/processor/viewer snapshots. Internal notes/audits and reviewer-history APIs are independently gated. Search only searches already-accessible loan names; no global finding search or restricted notification payload exists.
- `DataPanel` renders plain text; source text is never interpreted as instructions or HTML. The only `v-html` is Fortify-generated authenticator QR SVG, never document content.
- Responsive navy/light/teal UI, semantic inputs, focus rings, skip link, labeled dialogs with keyboard focus trap and Escape, loading/error/empty states. Visual/browser and assistive-technology acceptance remain unverified.

## Original evidence

`Document` is logical; every upload/replacement appends a `DocumentVersion`. Opaque storage keys never reach browser JSON. Checksum, MIME, byte count, uploader and original name are preserved. Local and S3-compatible adapters are private. The authenticated stream endpoint re-evaluates access on every preview/download; no signed public URLs. `Cache-Control: private, no-store` protects revocation for subsequent requests, not information already viewed/copied.

Validation is synchronous before acceptance. Allowed PDF/PNG/JPEG, max 20 MiB, explicit fictional acknowledgement, actual `finfo` MIME, extension agreement. Scanner interface reports not configured / unknown cleanliness. These accepted fictional samples remain under `quarantine/`; generated fixtures use a separate namespace. Never claim a malware-clean result. Infected/failed statuses cannot stream. Before real ingestion add a separate append-only scan-verdict record/release resolver; do not mutate immutable original evidence.

PostgreSQL triggers reject UPDATE/DELETE on document versions, audits, source evidence, classification suggestions, reviewer overrides, analysis snapshots and report snapshots. Laravel also prevents model mutation of originals/audits. File archive is reversible and never deletes originals. Retention/purge require a future legally approved path.

Rendering/normalization/OCR must produce derivatives linked to original version ids in a separate namespace, never replace source bytes or metadata. Any fraud-forensics adapter must see original bytes before transformations.

## Background jobs

Only `LoadSampleAnalysis` is a queued analysis job. It runs solely on explicitly fictional files, uses a tenant/file-scoped hashed idempotency key and UUID run id, a row lock/transaction, 3 bounded attempts, 60-second timeout, backoff and failed state. Repeat delivery of a completed run is a no-op, preserving single snapshots/reports. Demo output is code-defined, not derived from uploads. Original fixture source PDF is generated locally, marked fictional and preserved once.

Stages are reserved in configuration: upload_validation (currently synchronous operational validation), document_classification, extraction, reconciliation, transaction_classification, transfer_matching, income_calculation, integrity_analysis, appraisal_analysis and report_generation. Except validation and sample fixtures these stages are disabled, not simulated as successful real processing. Provider failures must never create a completed real run.

Future extraction evidence → suggestions → human overrides → deterministic calculation snapshot → approved ruleset/version → role-scoped report snapshot. Evidence, proposed classifications, reviewer decisions and report payloads are separate append-only records. Sample decisions do not recalculate income or modify fixtures; the UI/history says so.

Redis powers queues/Horizon. Python service is an isolated health-only boundary with no GPU, worker consumer or document access. Never execute document instructions. No OpenAI/Plaid/extraction/fraud HTTP calls exist in Phase 1. Billing has disabled adapter/status only. SMTP and private filesystem adapters are operational locally; Brevo and S3 compatibility are configuration boundaries, not verified external integrations.

## Deployment boundary

Compose is for local review, not production. No deployment performed. Future work: HTTPS, hardened production web server, `APP_DEBUG=false`, secure session cookies, secret manager, least-privilege DB/storage/worker IAM, S3 encryption/versioning/object locks, antivirus/sandboxing, CSP tuning, backups/restores, observability/alerts, retention, incident response, pentest, load/accessibility tests and approved financial-model acceptance.
