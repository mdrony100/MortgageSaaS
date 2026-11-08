# Architecture and security boundaries — current integrated implementation

## Retained foundation
Laravel/PHP + Vue3/TypeScript; PostgreSQL; Redis/Horizon; Python/PyMuPDF/Pillow; OpenAI Responses; PDF.js; private local/S3-compatible storage; Brick decimal arithmetic; PhpSpreadsheet; Dompdf; Laravel SMTP/Brevo-compatible delivery. No rebuild, second original-document system, separate login system, production deployment or Plaid implementation.

Same-origin browser→encrypted session/CSRF/Sanctum/verified email/rate limits→current org membership+file/document policy→allowlisted APIs→PostgreSQL/private storage. No browser bearer tokens/credentials. Organization selection in localStorage is not authorization. Platform metadata/Horizon roles do not bypass customer-file permissions. Fortify retains verification/reset/TOTP/recovery; password reset clears sessions.

## One original/source/version/access system
`Document` is logical; upload/replacement appends immutable `DocumentVersion`, SHA, MIME, exact original bytes, uploader and original name. Existing quarantine, private adapter, scan-verdict resolver (`bank_scans`), authenticated original stream, `Access`, policies, file shares and audits are reused for all modules. No public/signed-public source URLs. Every request reauthorizes; private/no-store cannot retract information already copied.

Python produces private rendered page/image artifacts and native text/word regions, never replaces originals. Source references use original version id+page+excerpt+verified normalized region when uniquely located, otherwise explicit unknown region. AI coordinates are not accepted as exact. Repeated/revised sources retain relationships. Resource/image coverage limits are declared, not silently complete.

## Bank workflow preserved
`ProcessBankRun` on bank queue pins exact versions/policy/12or24month period, validates current rights and original hash, appends scan verdict, parses isolated native pages, uses native/approved Responses extraction, reconciles, preserves classifications/corrections, matches verified same-file transfers/allocations, calculates income separately from internal NSF/sustainability/integrity, and issues independently frozen internal/Broker reports. Only final public income/monthly summaries and deliberately public conditions/explanations enter Broker resources. Original access is uploader-version-specific for Brokers.

## Appraisal/rental workflow
`review_runs` pins organization/file/requester/original version hashes/context/checklist/parent/idempotency. Composite foreign keys and immutable-pin triggers prevent cross-tenant/run lineage changes. `review_records` is an append-only domain evidence/decision ledger, not another document or authorization system. Immutable kinds include fields, page/image references, classification, lease proposals/matches, photo observations, findings/dispositions, rental inputs/comparisons, calculation snapshots, revision differences and report versions/artifacts. Mutable run status/provider-call bookkeeping is kept separate.

`ProcessAppraisal` rechecks current reviewer rights, existing quarantine/scanner and source hash. Python receives a credential-cleared environment. Strict schema/coverage validation, canonical field vocabulary, adjacent-page context and bounded prior fields accompany actual target rendered pixels. Every page is accounted for; four new vision batches per execution yield an explicit queued continuation; prior encrypted completed responses are replayed without a new submission/reservation. Unknown paid-provider outcomes block automatic resubmission. Native labeled-layout extraction is honest limited/manual support, not fictional vision success.

Document classification/form/version identities stay per original; legitimate mixed schedules are not mislabeled cross-document inconsistencies.

Exact image SHA and64-bit dHash identify duplicate **candidates**, not fraud. Per-image dimensions/coverage and candidate/resource caps protect processing; limits remain visible. The model provides unverified evidence observations, never permissions, destinations, financial formulas, legal/occupancy/safety certification or eligibility. Bank specialist forensics is explicitly disabled/unclaimed for appraisal.

## Review and deterministic finance
Original suggestions never change. Confirmations, notes, disposition reasons and explicit unit matches append actor/time records. Lease conflicts/amendments remain visible; uncertain matching never assigns a unit automatically. Inspection/current/loan occupancy and lease status are separate.

Confirmed input API creates a new immutable input and calculation snapshot in the same scoped run transaction. Unknown≠confirmed0; total-only and components are mutually exclusive; positive denominator/value basis; explicit qualifying-rent basis/source and IO definition. Exact rational and decimal display values are backend-owned. No approval/decline/eligibility output. Revision proposals do not silently replace confirmed inputs or resolve findings.

## Guideline and report versions
Minimal official-source Fannie draft contains URL/section/retrieval/effective dates, applicability/evidence/wording. Only designated client reviewer approval is production approval. Demo simulation is local/testing+fictional-only. Retirement appends lineage and prevents fresh use; frozen prior runs/reports remain unchanged. Specific baseline findings require applicable approved rule+human-confirmed source. Non-QM/DSCR is never automatically disqualified.

Report issuance freezes content and renders actual PDF/XLSX, writes versioned private bytes through the existing storage adapter, records SHA/byte quota, and serves only authenticated authorized artifacts. Later notes/calculations/renderer changes do not overwrite prior report bytes. Missing/corrupt artifact requires explicit new issuance, not silent historical regeneration. Open findings do not block appraisal reports.

## Billing/operations
Existing `SubscriptionBilling` contract is reused by unavailable and isolated fake adapters. Client-approved catalog is empty by default; legacy provisional prices are not offers. Signed timestamp/HMAC fake webhooks require stored request/org reference and unique immutable event identity; stale/conflicting events are rejected. Redirects never grant entitlements. Fake paid-plan transitions cannot operate in production. Operation counters are idempotent per pinned run; configurable counts are not financial charges.

Provider usage contains identifiers/status/token counts/operator-approved reservations, not document text or credentials. Logs/exceptions redact financial workflow details. Optional review-ready email is generic authenticated link only, no document/financial text/attachments; unknown delivery is not blindly retried. Auth/invite/reset SMTP remains established.

Retention inventory is dry-run; deletion requires explicit command flag and approved policy, and authoritative originals/reports have no purge implementation. Future backup/provider-copy/region/legal-hold/restore policy remains a release gate. See runbook; no target infrastructure provisioned.

Historical Phase1/2 completion documents describe their original snapshots; current source and final checklist supersede obsolete Phase3 placeholders.
