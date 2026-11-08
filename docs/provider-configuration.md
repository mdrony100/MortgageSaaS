# Provider configuration and live-verification boundaries

No live paid/provider/borrower submission was authorized or performed in final development. Fixtures/HTTP mocks and real local native processing are separate. All credentials are server-side/private; never `VITE_*`, frontend bundles, source fixtures or logs.

## OpenAI Responses
Appraisal uses `APPRAISAL_VISION_APPROVED=false` by default; unset `APPRAISAL_VISION_MODEL`, shared server `OPENAI_API_KEY`, positive `APPRAISAL_VISION_RESERVATION_MICRO_USD`, and an operator-approved organization/per-run `bank_limits` budget are required before any call. Reservation amounts are operator ceilings, not invented provider prices or actual charges. Bank approvals/models remain their separate existing settings; no credential unlocks unapproved work automatically.

Request contract: foreground `POST https://api.openai.com/v1/responses`, actual inline rendered PNG pixels, strict `text.format` JSON schema, `store:false`, no tools/background/remote URLs/Files API. Schema/page coverage/finite source provenance are validated server-side. Native labeled-layout fallback is explicit incomplete/manual support, never a claim that real appraisal/photo extraction worked.

Three target pages per batch, adjacent native text+bounded prior structured context, at most4new vision batches per execution; immutable completion markers and queued continuations preserve coverage without resubmission. Atomic call claim/budget reservation;429 retries retain one reservation. Transport-unknown/submitting/refused/invalid outcomes are not silently recharged. Completed normalized responses are encrypted for bounded replay; expiry prevents automatic duplicate submission even while unapproved retention keeps the encrypted record pending policy. `REVIEW_REPLAY_DAYS` defaults7, configurable1–30 as a technical replay window, not a production retention approval.

`store:false` **does not mean zero retention**. Abuse-monitoring/image exceptions, data processing/residency and contractual retention require approval. No separate OpenAI File object is uploaded by this path. Raw request/response bodies and document text are not ordinary usage/error logs. Provider usage allowlists token counts/status/reservations only.

Official references (read for current implementation):
- https://developers.openai.com/api/reference/resources/responses/methods/create
- https://developers.openai.com/api/docs/guides/structured-outputs
- https://developers.openai.com/api/docs/guides/images-vision
- https://developers.openai.com/api/docs/guides/your-data

Live model/key/budget/privacy approval and approved sample accuracy acceptance remain pending. TEST model names are HTTP-mock labels, not real selected models.

## Scanner and Python
Reuse `BANK_PYTHON`, pinned worker requirements and existing ClamAV verdict adapter. Missing binary/signatures/failure/infection is never clean. Fictional bypass requires local/testing+explicit demo file+fictional upload acknowledgement+enabled flag; impossible in production.

Worker bounds:20MiB input;120pages/document;240pages/review; rendered pages≤4Mpixels; images≤20Mpixels before bounded thumbnail;40embedded extractions/page with visible limitations;200MiB derivative storage budget; CPU/address-space/file/descriptor limits and credential-cleared environment. Network/filesystem isolation in production requires approved `BANK_PARSER_SANDBOX=bwrap`; local Python monkeypatching is not claimed as a full sandbox. Target-host/container/signature acceptance remains pending. Known malformed/unsupported images preserve full rendered page evidence and declare incomplete embedded coverage rather than breaking otherwise native-readable bank content.

## Private storage and mail
Existing `mortgage.private_disk` selects private local or S3-compatible adapter; public disks/serve flags fail closed. Original bytes, page/image derivatives and actual versioned report bytes are private. Streams reauthorize tenant/file/role and are no-store; reports/images have integrity hashes. S3-compatible configuration is not liveS3 verification; no bucket/IAM/resources were provisioned.

Laravel SMTP remains Brevo-compatible (`MAIL_*` server configuration). Real local Mailpit verification/invitation/reset delivery was exercised. Appraisal ready-notification is opt-in `REVIEW_EMAIL_NOTIFICATIONS=false`; generic authenticated application link only, no borrower/address/rent/financial/document content or attachments. Rechecks recipient access, claims once, records unknown delivery for manual follow-up rather than blindly duplicating email. Live Brevo/domain/delivery acceptance remains pending.

## Specialist integrity
Existing Ocrolus bank adapter/Book mapping is retained; see `provider-setup.md` for documented bank contract and current required approvals. No approved/current official appraisal coverage was established, so appraisal specialist checks are **disabled/unclaimed**. This is not a statement that every vendor can or cannot support a format; do not enable it without current official support and explicit contractual/scope approval. Internal/vision appraisal checks expose their actual limitations. Plaid remains future.

## Billing
Reuse existing `App\Contracts\SubscriptionBilling`, backed by disabled/fake adapters. Default `BILLING_PROVIDER=disabled`, `BILLING_FAKE_ENABLED=false`, approved catalog empty; seeded provisional prices are hidden/not offered. Owner/Admin sees organization limits/counters/history/plan configuration. Fake upgrade/downgrade/cancel request cannot change entitlements by itself or by redirect.

Explicit fake local/testing only: HMAC-SHA256 over timestamp+'.'+raw body with server `BILLING_WEBHOOK_SECRET`,5minute replay window, stored request/reference→org mapping, unique immutable event id, payload-bound idempotency and stale-request rejection. Only signed matching active event and configured approved test plan can transition fake entitlements. Endpoint returns404 in production/disabled mode. It is **not a Stripe/other vendor signature implementation**, not a public production paid-grant API.

Processing operation counters reserve once per pinned key across retries. Configured statuses/limits govern operational counts; defaults conservatively count reserved/completed/failed/cancelled, no financial charge is inferred. Test policy explicitly verifies released failed/cancelled slots cannot bypass quotas on retry. Cancellation/refund/access retention remains policy-pending and does not silently revoke/delete. Live provider, approved prices/taxes/cycles/refund/cancel/access policy and actual sandbox acceptance are release blockers. No live payment dependency was selected or added.
