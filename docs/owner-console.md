# Owner console (`/admin`)

The owner console is the platform owner's back office. It is separate from the customer workspace: it has its own URL (`/admin`), its own Vite bundle (`resources/js/admin.ts`, `resources/css/admin.css`), its own Blade shell (`resources/views/admin.blade.php`) and a dark navy/indigo design labelled **Owner Console · MortgageLens Platform**. It has no customer workspace switcher and no customer navigation. **Open customer workspace** goes to `/dashboard`.

Customers never see it. In the customer app, platform admins get only a small **Owner console** link at the bottom of the sidebar. Old URLs `/platform` and `/platform/inquiries[?id=]` redirect to `/admin` and `/admin/inquiries`. Horizon (`/horizon`) works as before for platform admins.

## Sections

| Section | URL | What it does |
|---|---|---|
| Overview | `/admin` | Live counts (organizations, users, loan files as a count only, approvals waiting, new inquiries, suspended orgs, disabled users), subscription status breakdown, recent orgs, signups and inquiries. |
| Analytics | `/admin/analytics` | Charts built only from database rows: signups, new orgs, active orgs (from audited sign-ins/actions), loan files per module, Bank/appraisal/sample analysis runs, inquiries and their status, plan distribution, storage per org (top 10). Date range up to about 3 years, grouped by day/week/month. Each chart has an empty state and a data table. No trackers or third-party scripts. |
| Revenue | `/admin/revenue` | MRR/ARR only when approved prices exist in `config/billing.php` → `catalog`. Otherwise it shows **Pricing not configured**. Lists stored subscriptions and billing events, and labels test-provider data. No provider credentials are shown or stored here. |
| Approvals | `/admin/approvals` | Signup approvals (only used when `PLATFORM_SIGNUP_APPROVAL=true`) and customer plan-change requests. Approve or reject; a reason is required to reject and is emailed to the requester and org owners. If the email fails, the decision still goes through. Approving a plan change applies the plan. |
| Organizations | `/admin/organizations` | Search and filter by status/plan. Detail page: change plan, set status (trial / active / past due / suspended / cancelled), suspend with a required reason, reactivate, extend or end a trial, per-org limit overrides (files, members, storage), internal admin notes, members (name, email, role, account state), request and admin history. |
| Users | `/admin/users` | Search users. Disable/enable sign-in, resend verification, send a password reset link, start a support session. Recent support sessions. |
| Plans & billing | `/admin/plans` | Read-only plan catalog and how many orgs use each plan. |
| Website inquiries | `/admin/inquiries` | Moved here from the customer app. Filter, change status, notes, spam, and **Convert to organization invite**. |
| Reports | `/admin/reports` | CSV and XLSX downloads with date filters: organizations & subscriptions, users, usage, inquiries, approvals, platform audit, revenue & billing events. Cells starting with `= + - @` are escaped. Each export is audited. |
| Audit log | `/admin/audit` | Platform events (admin, auth, impersonation, inquiries, requests, CLI), filterable by category, actor, action and dates. Loan-file events are not listed. |
| System status | `/admin/system` | Environment, debug mode, version, queue connection, mail configured yes/no, provider approvals, key-present yes/no, billing provider and owner-console settings. Values are labels or yes/no only; no secrets are shown. |

## Who can open it

All of these must be true, or the request is refused (403 JSON for `/api/admin/*`, a dark "not available" page for `/admin`):

1. Signed in and email verified.
2. `users.platform_admin = true`. The UI can never grant this. Organization roles, including Owner/Admin, get 403.
3. The account is not disabled.
4. Two-factor authentication is confirmed, when `OWNER_CONSOLE_REQUIRE_2FA=true`. This defaults to **on when `APP_ENV=production`** and off locally.
5. Not currently inside a support session.

Grant or revoke from the server shell. Both are audited (`platform.admin_granted` / `platform.admin_revoked`):

```bash
php artisan mortgage:platform-admin someone@example.com
php artisan mortgage:platform-admin someone@example.com --revoke
```

The command warns if the account is unverified or has no 2FA. The person then turns on 2FA under **My account → Two-factor authentication** before opening `/admin` in production.

Rate limits: 180 console requests/min, 30 write actions/min and 10 support-session starts/min per admin.

## Intentionally not possible

- Viewing or setting anyone's password. Only a standard reset link can be sent.
- Granting `platform_admin` from the UI.
- Disabling yourself or another platform admin. Starting a support session as yourself, another platform admin or a disabled user.
- Listing customer documents, borrower details, analysis results or findings in the console. That data is only reachable inside an audited support session, with the limits below.
- Charging cards, creating invoices or editing prices. Revenue is read-only.

## Login as customer (support sessions)

A platform admin can see the customer app exactly as a customer does, for troubleshooting:

- **Requirements:** confirmed 2FA on the admin account (always, in every environment) and a written reason of 10–500 characters. Targets cannot be platform admins, disabled users or yourself. Only one session at a time.
- **Time limit:** `IMPERSONATION_MINUTES` (default 30, allowed 5–240). The session ends when time runs out, on sign out, or with **Return to owner console**.
- **Identity:** the admin's identity is kept server-side in the session (`impersonation_sessions` table plus session data). The session ID is regenerated on start and on stop. No password, token or credential is exposed.
- **Banner:** every page shows *Viewing as &lt;user&gt; — Owner support session by &lt;admin&gt; · ends &lt;time&gt;* with **Return to owner console**.
- **Audit:** `impersonation.started`, `impersonation.ended` (returned / expired / logout / invalidated, which covers the kill switch and identity mismatches), `impersonation.blocked`, and `impersonation.write` for every write. Each carries both the admin ID and the customer ID. Normal audit rows written during the session also carry `impersonator_id` and `impersonated_user_id`. The customer's org audit log records **Platform support accessed this workspace**.
- **Blocked during a session (403 `impersonation_blocked`):** password, email/profile, 2FA and recovery codes, password confirmation, login/register/reset, email verification, billing changes, plan-change requests, invitations and accepting them, changing or removing members (granting roles), approving appraisal guidelines and Bank rules, the owner console and its API, and downloading original documents or appraisal artifacts unless `IMPERSONATION_ALLOW_DOCUMENT_DOWNLOAD=true`. The app has no delete-account or delete-organization endpoint.
- **Kill switch:** `IMPERSONATION_ENABLED`. It defaults to `true` in local/testing and `false` otherwise. Set it explicitly in production. Turning it off also ends any session already running on its next request.

## Subscriber states

`organizations.status` is one of trial, active, past_due, suspended or cancelled. Suspension (`suspended_at`) and approval state (`approval_status` pending/rejected) block the customer API with 423 and show members a "workspace unavailable" screen. The console still works for the platform owner. Effective limits use the per-org override when set, otherwise the plan limit.

## Signup approval

`PLATFORM_SIGNUP_APPROVAL=false` by default. When true, a new registration creates an organization in **pending approval** with a signup request in Approvals. Members see a pending screen until it is approved. A rejection stores the reason and emails it.

## Inquiry → organization invite

**Convert to organization invite** creates a trial organization named after the inquiry company, plus an owner invitation (hashed token, 7-day expiry) sent through the existing invitation email. The inquiry is marked converted. Known limitation: a prospect who registers through `/register` instead of the invitation link creates a separate organization, because registration asks for an organization name.

## Environment

| Variable | Default | Meaning |
|---|---|---|
| `OWNER_CONSOLE_REQUIRE_2FA` | true in production, else false | Require confirmed 2FA to open `/admin`. |
| `PLATFORM_SIGNUP_APPROVAL` | false | New orgs wait for approval. |
| `IMPERSONATION_ENABLED` | true in local/testing, else false | Support sessions kill switch. |
| `IMPERSONATION_MINUTES` | 30 | Support session length (5–240). |
| `IMPERSONATION_ALLOW_DOCUMENT_DOWNLOAD` | false | Allow original document / artifact downloads during a support session. |
| `APP_VERSION` | empty (falls back to the git commit when readable) | Shown on System status. |

## Privacy notice (shown in the console)

> The owner console shows account and operational metadata only. It never lists customer documents, borrower details, analysis results or findings. Customer data can only be seen inside an audited, time-limited support session.
