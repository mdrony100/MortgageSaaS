# Phase2 executed verification — 2026-11-08 (Dhaka, UTC+6)

This is local implementation acceptance, not deployment or proof of live model/vendor accuracy. Baseline develop `373801baf95360662a7786168d6aede95a527c53`; existing source preserved. Authoritative23-section map: [checklist](phase-2-checklist.md). Commit/push proof belongs in the completion handoff (cannot put a commit's own hash into itself).

## Final commands and observed outcomes

Run from `/workspace/mortgagelens` on the box:

| Exact command | Observed outcome |
|---|---|
| `php artisan test --compact` | **91 passed,731 assertions**, PostgreSQL;14.674s final post-Pint run. No SQLite substitution. |
| `npm test` | **16 passed**,2 files; mocked API DOM wiring and Phase1 regressions, not a live visual test. |
| `npm run test:pdf` | Actual **PDF.js6.5.438 render passed**,1 fictional page,704×911. |
| `npm run build` | **Passed** `vue-tsc --noEmit && vite build`;1600 modules, Vite7.3.7. |
| `vendor/bin/pint` then `vendor/bin/pint --test` | Formatter applied; final **passed**. PostgreSQL suite rerun after formatting. |
| `php artisan horizon:terminate` then `python3 tests/smoke_http.py` | **22/22 checks passed** with actual HTTP/cookies/CSRF/PostgreSQL/Redis/Horizon bank/default queues/Python native parser/XLSX+CSV+PDF/SMTP-Mailpit. Final worker refresh repeated after formatting. |
| `composer audit --format=json` | **0 advisories,0 abandoned** packages. |
| `npm audit --json` | **0 vulnerabilities**, including dev dependencies. |
| `/workspace/mortgagelens-build/pip-audit-venv/bin/pip-audit -r python-worker/requirements.txt -f json` | **No known vulnerabilities** for PyMuPDF1.26.7/Pillow13.0.0. Pillow upgraded in response to initial audit; parser venv also upgraded. |
| `/workspace/mortgagelens-build/security-bin/gitleaks protect --staged --redact --no-banner --report-format json --report-path /workspace/mortgagelens-build/phase2-gitleaks-final.json` | **No leaks found**, reviewed staged changes, report`[]`; not a claim about full historical Git contents. |
| `git diff --cached --check` | **Passed**; staged source inventory found no private env/runtime/generated/binary/financial-document candidates or key-pattern matches. |
| `php artisan migrate:status` | All PostgreSQL migrations through`000600_pin_request_identity` **Ran**. |
| `php artisan bank:purge-transient` | **Passed**,0 expired caches; originals untouched. |
| `docker compose config --quiet` | **Passed** configuration only. Docker daemon unavailable; no container execution claimed. |
| `php artisan horizon:status` | **Running** after graceful final reload under the existing detached local watchdog. |

Raw logs and scanner reports are kept outside Git under `/workspace/mortgagelens-build/phase2-*-final.*`. Earlier iterations include fixed synthetic sharing-column/regex typos and a rejected unsupported optional PDF.js TypeScript parameter; final source does not cast around that error. Neither mock IDs nor successful JSON are represented as live provider success or measured accuracy.

## Actual versus fixture

- Real local: original-byte upload/quarantine/hash/private streaming, PostgreSQL constraints, Redis/Horizon async processing, actual native PDF/image parsing and derived rendering, production-mode bubblewrap fictional parser test, deterministic review/calculation/snapshots, authenticated API/source boundaries, real PhpSpreadsheet/Dompdf exports and PDF.js CPU rendering.
- Fictional/mocked: every financial acceptance document is generated fictional data; PHPUnit TEST extraction is explicitly injected, not an ordinary-workflow fallback; Responses and Ocrolus HTTP contracts are mocked. Native HTTP smoke uses actual native extraction, explicit fictional-only malware bypass and disabled external providers. No real borrower or live paid call was used.
- Exact12/24 denominator and rounding, missing/overlap coverage, repeated chunk versus legitimate row provenance, partial/split/combined/circular transfers, personal recent-business support, cumulative corrections, daily reconstruction, selected NSF scope, policy/input actor history, immutable/stale reports, budgets/refusal/rate-limit/unknown submissions and all Broker/tenant/revocation/export/source cases are exercised. See [fixture coverage](phase2-fixture-coverage.md).

## Source/config/privacy/race review

Reviewed immutable/run/file scope constraints and append-only triggers; run-review row locking; shared processing/poll queue locks and timeout<retry-after ordering; atomic organization budget reservation and submission compare-and-swap; exact pinned hashes; authorized original streaming before providers; production-only OS sandbox/credential-cleared subprocess environment; no document-directed URL/tool/destination; sanitized queue/SQL/provider errors; allowlisted usage; masked source identifiers; bounded encrypted replay retention and transient cleanup; separate Broker resources/templates/report numbering and per-version original ownership; real exports' injection handling; source freshness and final recomputation of immutable snapshot. Regression tests verify these controls, not a penetration-test certification or stress/concurrent-load acceptance. No secret was emitted or real document committed.

Official documentation for Responses structured output and Ocrolus original upload/signals was opened and the concrete payload/response mapping cross-checked again during final review. URLs and contract limitations are in [provider setup](provider-setup.md); no undocumented webhook or Book-create endpoint was fabricated.

## Runtime and remaining release blockers

Shell HTTP checks: `http://127.0.0.1:8000` and `http://localhost:8000`200, Mailpit`http://127.0.0.1:8025`200, Python`http://127.0.0.1:8081/health`200; PostgreSQL/Redis healthy. Watchdog state/log outside Git: `/workspace/mortgagelens-build/runtime/{state.json,supervisor.log,supervise.py}`. This detached box-local supervision is not deployment/system-reboot persistence. Shared Redis/Python listen addresses were not silently changed.

Desktop browser loopback8000 is platform-firewall blocked despite shell HTTP health. No browser automation, proxy, tunnel or firewall bypass was used; visual/accessibility acceptance remains unverified.

Release gates remain: actual client workbook (clearly marked fallback supplied), lender-approved rules/thresholds/inputs/evidence/completeness gates, OpenAI key/model/budget and approval, Ocrolus access/token/dedicated tenant-file Book/commercial+retention approval, approved live samples, actual ClamAV/current signatures, real S3/target-host/container and licensing/legal acceptance. Missing credentials did not stop unrelated implementation/tests. Phase3 screens/draft state are preserved; no main/deploy/paid/live/force action occurred.
