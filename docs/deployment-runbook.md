# Future deployment runbook — guidance only, not executed

No production infrastructure, services, domains, IAM/buckets, live paid calls or deployment were provisioned/executed in this task. Resolve release-readiness/client decisions before running anything on a production host. Use approved operators and a maintenance/rollback plan.

## 1. Prerequisites and secrets
Supported PHP8.4+ with PDO-PG,mbstring,DOM/XML,curl,zip,GD,bcmath,intl,pcntl; Composer; Node; PostgreSQL; Redis; pinned Python venv/PyMuPDF/Pillow; ClamAV/current signatures; production bubblewrap sandbox or separately approved verified equivalent. Review dependency licenses.

Configure private environment through approved secret management: app key, DB/Redis/private storage/SMTP, domain/HTTPS/session settings, approved model/vendor/billing credentials only after approval. Never commit `.env`, log credentials, expose them asVITE variables, or add public customer storage. SetAPP_ENV=production,APP_DEBUG=false, secure cookies and correct trusted proxy/origin settings. Disable all fictional scanning/fake billing/demo approval in production. KeepAPPRAISAL_VISION_APPROVED false until model/privacy/cost/budget acceptance.

## 2. Application release preparation
Install locked dependencies (`composer install --no-dev --prefer-dist --optimize-autoloader`, `npm ci`, `npm run build`), install pinned worker requirements into isolated venv and configureBANK_PYTHON. Use a reviewed release artifact/version, not an unreviewed main/develop checkout mutation. Validate environment and private disks; do not run destructive database resets or production test suites.

Take approved encrypted DB/private-object backup and verify restore prerequisites before schema changes. Run reviewed additive `php artisan migrate --force` only in approved deployment window. Phase3 adds review ledger/guidelines/billing/usage tables and immutable tenant/lineage/event constraints; no existing evidence deletion is required. Seed only required reference plan/catalog configuration; never development accounts/synthetic files in production. Keep approved prices/catalog external to provisional seed amounts.

## 3. Web, storage and workers
Use hardened PHP-FPM/web server with document root`public`, HTTPS, bounded upload/body/request/resource limits compatible with20MiB uploads, private application/storage ownership and no public symlink to originals/derivatives/reports. Existing private local or S3-compatible disk must reject public visibility. Verify actual bucket/region/encryption/IAM and authenticated file streams on target host; do not infer fromStorage::fake tests.

Redis queue driver plus supervised `php artisan horizon`, consumingdefaultandbankqueues. Both Bank and Appraisal jobs use bank queue. Preservejobtimeout1200 < supervisortimeout1220 < Redisretry_after1230, with run locks lasting1250seconds; inspect actual config before changing. Native work checkpoints after4new documents; vision after4new batches; continuations reuse immutable results and one usage key. Long/unavailable calls stay bounded; unknown paid outcomes require manual reconciliation. Recycle workers after release withHorizon termination under a supervisor; do not kill active jobs blindly.

Run scheduler via approved cron`php artisan schedule:run` every minute or equivalent. Retention scheduler defaults are dry-run; no--executeuntil retention is approved. Monitor queue depth/failures/timeouts/stale/submitting-provider claims, storage/budgets and health using metadata-only/redacted logs. No source/financial text in analytics/error aggregators.

## 4. Target-host acceptance / health
Check`/up`, database migration status/connectivity, RedisPING, Horizon supervisor/queue health, SMTP test through approved catcher/sandbox, scanner signatures and real sandbox network/filesystem isolation. Verify private original→scan→native parse→review→decimal calculation→versioned PDF/XLSX on synthetic files; then approved provider sandbox/sample paths separately. Verify cross-org/Broker/revoked-role/source/image/report/notification boundaries and signed webhook replay/auth behavior. No browser redirect may grant paid access.

Run current PostgreSQL tests in a separately isolated test database, frontend DOM/PDF.js/build and dependency/secret scans before release, not against borrower data. Inspect rendered reports/workbooks and approve actual client mapping. Measure actual host memory/time/load with representative approved samples: local tiny-file measurements are not production sizing guarantees; PHP process peaks and Python RSS need explicit headroom. Docker configuration syntax alone is not container execution acceptance.

## 5. Backups and restoration
Follow`retention-and-backups.md`: encrypted PostgreSQL plus private originals/revisions/derivatives/issued reportbytes/checksum manifests, key/config recovery under separate custody, client-approved RPO/RTO/region/expiry/holds. Restore into isolated approved environment and verify source/report hashes, immutable versions, tenant/role grants/revocations, no duplicate paid job/webhook replays and authenticated report download. Record actual restore proof before production approval. No backup service or restore was executed here.

## 6. Rollback / incident response
Pause new processing/checkout through approved operational controls, preserve queues/evidence/log metadata and reconciliation records, notify approved operators, and assess in-flight external unknown outcomes without replaying them. Roll back application artifact only after checking schema compatibility. **Do not blindly migrate:rollback/drop/reset append-only tables or overwrite evidence**, and do not use force pushes as deployment rollback. Any database/private-object restore must be explicitly approved, audited and reconcile post-backup writes/provider events before reopening traffic. Reauthorize grants after restore, verify health/source/report hashes, then resume supervised workers/scheduler under approved release ownership.
