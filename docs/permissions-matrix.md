# Current permissions matrix

Every file operation requires **current org membership AND creator/active individual share/explicit wide grant**. The wide grant can be held by an owner, an admin, or an underwriter (reviewer). Processors, brokers, and viewers cannot hold it. Role alone is not a file grant. The platform administrator flag opens the owner console (`/admin`) and Horizon. It is never a file bypass: the console shows account and operational metadata only. Customer data is reachable only through an audited, time-limited support session (see below and [owner-console.md](owner-console.md)). Revoking a share does not revoke an independent creator/wide grant; UI explains this. No widening was made to resolve ambiguous client sharing wording.

| Capability | Owner/Admin with file grant | Underwriter with grant | Processor with grant | Broker with grant | Viewer with grant |
|---|---|---|---|---|---|
| Accessible file metadata | Yes | Yes | Yes | Yes | Yes |
| Create/edit/archive accessible file | Yes | Yes | Yes | No | No |
| New original/explanation upload | Yes | Yes | Yes | Yes, no replacement override | No |
| Replace logical original with new immutable version | Yes | Yes | Yes | No | No |
| Original preview/content | After clean verdict | After clean verdict | After clean verdict | Only own uploaded version after clean verdict | After clean verdict |
| Individual same-org sharing/revoke | Yes | Own-created file only | No | No | No |
| Shared notes | Read/write | Read/write | Read/write | Read/write | Read |
| Internal notes/audit | Yes | Yes | No | No | No |
| Bank source correction/classification/transfer/input overrides | Yes | Yes | No | No | No |
| Bank fraud/integrity/sustainability/internal snapshots/exports | Yes | Yes | No | No | No |
| Bank deliberately public final income/conditions/reports | Yes | Yes | Allowlisted only | Allowlisted only | Allowlisted only |
| Appraisal internal extraction/photos/derivatives/lease match/rent/calcs/findings/revisions/reports | Yes | Yes | No | No | No |
| Appraisal guideline draft/retirement | Yes | No | No | No | No |
| Production guideline approval | Designated client reviewer id AND admin/file access | No | No | No | No |
| Organization billing/member management | Own-org admin | No | No | No | No |

An authorized user may see all three module tabs but internal appraisal endpoints/derivatives remain reviewer-only. Nonreviewers receive an explicit restricted notice, not internal observations/calculations. Historical sample previews remain role-projected and visibly separate. Sharing never grants transaction classification override, fraud/sustainability access or internal export.

`LoanFilePolicy`, `DocumentPolicy`, `Access`, `Present`, Bank/Appraisal controllers and background stage checks establish boundaries. Original uploader checks are version-specific. All original/page/image/report streams reauthorize and use private/no-store. Searches are accessible-file metadata only. Generic notifications contain no financial payload and recheck reviewer access before submission; opening their link reauthorizes.

Tests cover cross-org IDs/parents/records, shared Broker restrictions/revocation, private source/derivative/report endpoints, polling projections and revoked background requester roles. Revocation affects subsequent requests, not previously downloaded copies. User-authored shared notes are not automatically sanitized into approved public fraud wording; organizations remain responsible for what they intentionally share.

Open policy: whether particular Broker/processor appraisal editing/public appraisal exports should exist. Existing conservative restrictions remain until explicit client decision. No auto-approval, condition clearance or underwriting role is created.

## Owner console and support sessions

| Capability | Platform admin (verified, not disabled, 2FA when required) | Platform admin in a support session | Org Owner/Admin | Other org roles |
|---|---|---|---|---|
| Open `/admin` and `/api/admin/*` | Yes | No (403) | No (403) | No (403) |
| Org plan/status/trial/limits/suspend/notes | Yes, audited | No | No | No |
| Disable/enable users, resend verification, send reset link | Yes, audited (not self, not other platform admins) | No | No | No |
| View or set passwords | No | No | No | No |
| Grant `platform_admin` | No (CLI `mortgage:platform-admin` only) | No | No | No |
| Approve/reject signups and plan changes | Yes, audited | No | Request plan change (Owner only) | No |
| CSV/XLSX reports | Yes, audited | No | No | No |
| List customer documents/borrower data/findings in the console | No | n/a | n/a | n/a |
| Start a support session | Yes: confirmed 2FA, reason, `IMPERSONATION_ENABLED`; not admins/disabled/self | No | No | No |
| Act in the customer app | n/a | As the customer, limited to that customer's own access; every write audited with both IDs | Own access | Own access |
| Password/email/2FA, billing, invitations/member roles, guideline/rule approval during a session | n/a | Blocked (403) | Own access | Per the table above |
| Original document/artifact download during a session | n/a | Blocked unless `IMPERSONATION_ALLOW_DOCUMENT_DOWNLOAD=true` | Per file grant | Per file grant |
