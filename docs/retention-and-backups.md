# Retention, legal hold and backup boundaries

**Production retention is unapproved. No authoritative original/revision/report/database/provider/backup deletion was executed.** `config/review.php` contains explicit approval plus separate original/revision/derived/report/database/provider/backup periods (unset). No client policy is inferred from a subscription cancellation/refund.

## Inventory / no-delete default
`php artisan retention:review` emits operational counts only: original versions/revisions, review runs/records, private page/image/report artifacts; flags provider copies and backups as operator/vendor inventories requiring agreement. It is dry-run only and never deletes.

`bank:purge-transient` and `review:purge-replay` now default to **DRY RUN**, including their daily scheduler calls. They require explicit `--execute` AND approved retention configuration before transient cleanup. Production originals/reports have no purge path here. Parser scratch output is temporary working data; completed derivatives/original bytes remain preserved. No retention execution was approved in this task.

Encrypted normalized Responses replay has a technical expiry (default7days), after which automatic paid resubmission is blocked. Expiry is not zero-retention or permission to delete an audit/original. While retention is unapproved, cleanup remains dry-run. `store:false` is not a contractual zero-retention guarantee; vendor abuse-monitoring/image exceptions/provider copies require approved policy. No vendor deletion API is invented or called.

## Required production policy
Client/legal/security approval must specify purpose/residency, each artifact class, retention start/event, holds/disputes, revisions/issued-report preservation, cancellation access, provider copies/deletion evidence, backup expiry/restore/erasure interactions, authorized operators, approval/audit and failure/retry/verification. Immutable-ledger triggers intentionally forbid ordinary updates/deletes; any future legally approved erasure must be a separately reviewed administrative design, not a hidden end-user delete endpoint.

## Future backups and restore acceptance (not executed deployment)
Back up PostgreSQL with encryption/least privilege and private original/derivative/report objects with checksum/version manifests, plus encrypted configuration/key recovery under separate custody. Redis queue/cache alone is not an evidence backup. Preserve key-version access needed to read encrypted replay/customer data. Do not copy secrets/plain borrower payloads to routine logs or unapproved analytics.

Define client-approved RPO/RTO, frequency, region, retention, immutable/offline protections and operator ownership. Test restore into an isolated approved environment: restore schema/data/keys/private files; verify tenant/file scopes, original/report hashes and snapshot lineage; verify queue replay idempotency without live paid calls; check revoked users remain revoked; audit results; obtain approval before switching traffic. Pending target-host backup/restore acceptance is a release blocker. This task provisioned no backup service, executed no production restore and guarantees no recovery objective.
