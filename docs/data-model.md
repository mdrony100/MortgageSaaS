# Data model

PostgreSQL is required. All financial `amount` columns are `decimal(18,2)`, plan prices `decimal(10,2)`; JSON fixture amounts are explicit decimal **strings**, not binary floating-point money. Real calculations do not exist in Phase 1.

| Area | Tables | Purpose |
|---|---|---|
| Identity | users, password_reset_tokens, sessions | Fortify credentials/verification, encrypted TOTP secret/recovery, platform flag; private fields hidden |
| Tenancy | organizations, memberships, invitations | Unique org/user membership, role, explicit wide grant; hashed 7-day invitation token bound to verified email, one acceptance, revocation |
| Plans | plans + organization plan/status | Provisional pricing and enforced file/member/storage limits; no checkout |
| Work | borrowers, loan_files, file_shares | Tenant-scoped borrower relation, creator, draft inputs/archive, unique per-user file share and revocation |
| Evidence | documents, document_versions | Logical grouping, immutable version number, opaque disk/key, actual MIME/bytes/checksum/original name, scan/validation state |
| Collaboration | notes, audit_events | Shared/internal notes, append-only safe audit; review activity API exposes only allowed actions |
| Processing | analysis_runs, analysis_snapshots, rulesets | UUID run, idempotency key, stage/status/attempts/error, separate immutable fictional snapshot, tenant-scoped versioned draft rules |
| Bank future boundary | bank_accounts, statement_periods, transactions, transfer_matches, income_calculations, nsf_events, sustainability_metrics, fraud_findings | Typed Eloquent boundaries with tenant/file/run references, decimal amount and extensible JSON; **no real extraction/calculations populated** |
| Appraisal/rental future | appraisals, appraisal_observations, leases, dscr_scenarios | Typed boundary records; Phase 1 fixtures are stored in sample snapshots, not real analytical results |
| Provenance | source_evidence, classification_suggestions, reviewer_overrides, report_snapshots | Separate immutable evidence/suggestions/reasoned reviewer decisions/sample report marker; run+external key uniqueness |
| Framework | jobs, job_batches, failed_jobs, cache, cache_locks | Framework queue/cache infrastructure; Redis used at runtime |

Core tenant relations use composite foreign keys `(id, organization_id)` and `(organization_id,user_id)`; an application validation bug cannot link a loan to another tenant's borrower or share to another tenant's member. Future tables have tenant-scoped file/run references and uniqueness `(analysis_run_id,external_key)`. No frontend input accepts arbitrary tenant/run/source foreign keys for analytical rows.

PostgreSQL immutability triggers protect document versions, audit events, evidence, suggestions, reviewer overrides, analysis snapshots and report snapshots from UPDATE/DELETE. New decisions append; new source uploads append versions. Operational run status is mutable and distinct from immutable output. Profile/TOTP data are managed only through Fortify and hidden from serialization. Notes contain plain text and have explicit visibility.

Extension boundaries are intentionally minimal schemas until investor/extraction definitions are approved. Phase 2/3 migrations must normalize real financial domain fields, add period/account constraints and derivative/evidence coordinates, retain decimal precision and introduce append-only scan/forensics verdicts. Do not misinterpret nullable extension-boundary JSON records as implemented analytical engines.

## Phase 2 schema

`bank_runs` pins requester, organization/file, rule version, options and input hash; PostgreSQL prevents pin mutation. `bank_inputs` pins immutable original IDs/hashes with document-to-file insert enforcement. `bank_scans` records Pending/Clean/Rejected/ScanFailed/Unavailable independently of originals. Immutable `bank_pages` stores masked text/native word provenance and private derived references; `bank_statements` binds one account/period extraction; normalized `bank_transactions` stores source keys, statement/version, dates and integer minor amounts plus original extracted representations/suggestions.

Append-only review/relationship/transfer/finding tables retain every reason-version/actor/evidence decision. `bank_calculations` binds exact rational financial inputs/intermediates, selected months/accounts, source/decision IDs and hash. `bank_reports` separately freezes internal and final Broker payloads; no dynamic internal-to-public serialization. `bank_conditions` is deliberate public wording; `bank_messages` contains submitted explanations/review requests. `bank_rules` versions cannot be updated/deleted. Composite run organization/file foreign keys enforce boundaries. Provider call rows contain bounded reservation/status/usage/minimal encrypted replay data, not raw response payloads. `bank_limits` is organization/per-run micro-USD reservation control. Dedicated vendor Books are unique per organization/file.

Additional migrations enforce document-to-file INSERT scope, nonnegative/single-direction original money rows, append-only runs, and immutable requesting-user/idempotency/input pins. JSONB ledger reviews preserve originals; effective transactions fold financial corrections across later category reviews. `calculation_inputs` records reasoned actor confirmations; canonical statement identity overlays are same-run and require re-verification. Condition state is append-only under a stable public condition ID. Hashes are canonical.
