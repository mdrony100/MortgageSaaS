# Current permissions matrix

Every file operation requires **current org membership AND creator/active individual share/explicit Owner-Admin-wide grant**. Role alone is not a file grant. Platform administrator flag is metadata/Horizon access, never customer impersonation or a file bypass. Revoking a share does not revoke an independent creator/wide grant; UI explains this. No widening was made to resolve ambiguous client sharing wording.

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
