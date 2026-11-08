# MortgageLens — Phase 1

Configurable mortgage review workspace built with Laravel 13, PHP 8.4, Vue 3 Composition API, TypeScript, Vue Router, Pinia, Tailwind 4, PostgreSQL 17, Redis and Horizon. SPA and cookie/session APIs share one origin. Working product name: `APP_NAME`, with organization branding overrides.

> **Fictional samples only.** Account/workspace/document management is real. Extraction, AI, fraud detection, sustainability scoring, appraisal/lease analysis, live DSCR/LTV calculations, financial exports and payments are **not operational**. Uploaded documents always show **“Uploaded — analysis integration pending.”** Demo results require an explicitly fictional file and the separate **Load sample analysis** action. They never represent analysis of an upload.

## Docker local setup (no deployment)

Requirements: Docker Engine + Compose v2, Python 3 for initial secret generation. No GPU or cloud accounts.

```sh
./bin/setup-env                 # generates .env secrets locally; never prints them
# Read/review .env locally; use localhost:8000 consistently, not a mix of hostnames.
docker compose up --build -d
docker compose exec app php artisan migrate --force
docker compose exec app php artisan db:seed --force
```

Open `http://localhost:8000`; register your own account/workspace. Verify your email using **Mailpit at `http://localhost:8025`**. Registration, invitations and resets use the local SMTP catcher; no real external messages are necessary. PostgreSQL and Redis are not exposed to the host by Compose. App and Mailpit ports bind only to loopback.

Optional development-only review seed:

```sh
docker compose exec app php artisan mortgage:dev-account
```

This creates a new fictional owner/workspace and three demo files, **not** an impersonation session. A generated credential file is written privately under `storage/app` with mode 0600. Read it locally and store/delete it appropriately; never commit or paste it in chat. No fixed passwords, seeded superusers, or platform-administrator backdoors. It does **not** load analysis. Use the separate sample action.

```sh
docker compose exec app php artisan test
# Test database mortgagelens_test is created by first-run PostgreSQL initialization.
# Existing PostgreSQL volumes need that database created manually once.
docker compose logs worker
```

Horizon runs in `worker`, scheduler in `scheduler`, Python boundary in `document-worker`. `/horizon` is restricted to explicitly provisioned platform admins, **including in local mode**. There is no UI to self-elevate. The Python service has a health endpoint and no document consumer or AI processing. Rebuild app/worker after source changes; source is intentionally not host bind-mounted over locked dependencies.

## Native local setup

PHP 8.4 with PDO PostgreSQL, mbstring, XML/DOM, curl, zip, GD, bcmath, intl and pcntl; Composer 2; Node 24; PostgreSQL 17; Redis 7; Mailpit SMTP 1025/UI 8025.

```sh
composer install
npm ci
./bin/setup-env
# Configure local PostgreSQL role/password from private .env.
# Create databases mortgagelens and mortgagelens_test, owned by that role.
php artisan migrate
php artisan db:seed
npm run build
php -d upload_max_filesize=20M -d post_max_size=22M artisan serve --host=127.0.0.1 --port=8000
# Another terminal:
php artisan horizon
```

For native tests copy `.env` to a **private, ignored** `.env.testing`, use `DB_DATABASE=mortgagelens_test`, `APP_ENV=testing`, `QUEUE_CONNECTION=sync`, `MAIL_MAILER=array`, `CACHE_STORE=array`, `SESSION_DRIVER=array`. `phpunit.xml` forces PostgreSQL and the test database; SQLite is intentionally unsupported. Tests use transactions/fresh migrations and synthetic fixtures; never point them at a real database.

```sh
php artisan test                 # PostgreSQL integration / security suite
npm run test:pdf                 # actual PDF.js source renderer
npm test                         # Vue component interactions with happy-dom, not a visual browser
npm run build                    # vue-tsc + production Vite build
vendor/bin/pint --test
composer audit
npm audit --omit=dev
# Real cookie+CSRF+SMTP+Redis smoke, with native server/Horizon/Mailpit running:
python3 tests/smoke_http.py
```

Vite development mode: `npm run dev` while the Laravel server remains the application origin. `public/hot`, dependencies, environment secrets, build outputs, runtime data and documents are ignored.

## Review walkthrough

1. Register and verify email through Mailpit; create borrower/file (mark fictional to enable sample results).
2. Upload a fictional PDF/PNG/JPEG with the explicit fictional-data acknowledgement. Preview or download authenticated original bytes; add replacement to create version 2.
3. Invite another synthetic account; accept with that email. Share a file separately. Ordinary membership does not expose all files.
4. Load sample analysis. Refresh status after Horizon completes; open all three modules, review a finding beside its PDF.js source and open a role-scoped report preview.
5. Use an actual Broker test account (sign out/in, no impersonation shortcut). Confirm restricted integrity/sustainability/internal audit data and reviewer override controls are absent and API requests are denied.
6. Revoke the share and confirm subsequent file, document, preview, report and export requests fail unless another independent grant exists (e.g. an explicit admin-wide grant).

## Storage and integrations

Private local storage is default. No `storage:link` or public document URLs. Every document request checks current membership, file policy and role, sends `no-store`, and logs an audit event. S3-compatible storage uses Laravel's private S3 disk through `PRIVATE_DOCUMENT_DISK=s3` and protected AWS environment variables. S3 credentials/live behavior have not been verified. Do not enable bucket-public access or replace policy-gated downloads with long-lived URLs.

Uploads are sample-only, max 20 MiB, extension/MIME/byte-validated. Originals use opaque keys, SHA-256 and append-only version records. Scanner reports **not_configured**, never clean; samples remain in private quarantine. Production malware scanning, legal retention and real financial-document ingestion require later approval. Untrusted source text never authorizes tools, sharing, disclosure or configuration changes.

SMTP configuration is Brevo-compatible; `.env.example` explains host/port. No Brevo credentials or live delivery tested. Extraction/OpenAI/fraud/billing/Plaid adapters fail closed. Fraud vendor choice (Ocrolus Detect or MoneyThumb Thumbprint) remains open. No API keys are in the SPA.

## Handoff

See [actual verification and limitations](docs/phase-1-completion.md), [architecture](docs/architecture.md), [permissions](docs/permissions-matrix.md), [data model](docs/data-model.md), [Phase 2](docs/phase-2-bank-statements.md), [Phase 3](docs/phase-3-appraisal-dscr.md) and [open decisions](docs/open-decisions.md). No server deployment, main-branch changes or paid resources are included.

Dependency security: PDF.js is patched to 6.x. Test-utils formatter/glob/minimatch overrides select patched releases; the full component/build/render suites verify compatibility. Keep these overrides until upstream dependency ranges are updated.
