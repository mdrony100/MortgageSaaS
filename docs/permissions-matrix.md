# Permissions and assumptions

All rows concerning a file additionally require current membership **and** a file grant. A grant means creator, active individual same-organization share, or explicit organization-wide access on an Owner/Admin membership. No organization-wide grant is inferred solely from the Admin role. Registration creates the Owner's explicit grant; invitations default to no wide access, and an admin must deliberately select it.

| Capability | Owner / Admin | Underwriter | Processor | Broker | Viewer | Platform admin only |
|---|---|---|---|---|---|---|
| Create borrower / file | Yes in own org | Yes | Yes | No | No | No |
| View accessible file/original/preview | Yes with grant | With grant | With grant | With grant | With grant | No bypass |
| Edit/archive/upload versions/draft inputs | With grant | With grant | With grant | No | No | No bypass |
| Individual file sharing / revocation | With file grant | Own-created files only | No | No | No | No bypass |
| Shared notes | With grant | With grant | With grant | With grant | Read only | No bypass |
| Internal notes/restricted audit | With grant | With grant | No | No | No | No bypass |
| Integrity/fraud/sustainability sample data | With grant | With grant | No | No | No | No bypass |
| Classify/override/review-history | With grant | With grant | No | No | No | No bypass |
| Role-scoped sample reports | With grant | With grant | Redacted | Redacted | Redacted | No bypass |
| Real financial export | Disabled for all | Disabled | Disabled | Disabled | Disabled | Disabled |
| Invite/revoke/member roles/branding | Own org only | No | No | No | No | No bypass |
| Metadata platform view / Horizon | Only if platform flag | Only if platform flag | Only if platform flag | Only if platform flag | Only if platform flag | Yes, verified |

Owner cannot be removed or demoted through ordinary member management; own membership cannot be changed. Ownership transfer is an open administrative workflow, not an insecure shortcut. Admins without a file grant cannot browse that file. File shares never turn a Broker into an Underwriter. Role changes/removal are evaluated on the next API request. Member removal removes obsolete shares. Revocation does not revoke an independent creator/admin-wide grant; the UI explicitly explains this.

Broker restrictions are enforced on the server, not CSS: analysis projection, report projection, notes visibility, activity visibility, review-history/direct restricted routes and write policies. No export endpoint returns sensitive data; it authorizes first, then returns an explicit 501 not-configured response. No global customer document search, impersonation, customer-login link or restricted-data notification exists.

Initial product assumptions: processors can maintain accessible files but not adjudicate classifications; shared notes are visible to every authorized file user; viewers cannot add notes. Final lender/shared-file permission policy requires sign-off before Phase 2. Arbitrary user-authored shared notes are not automated fraud findings; users must follow their organization's information-sharing policy.

## Phase 2 stricter bank boundary (supersedes weaker Phase 1 sample fields)

All file endpoints first require current membership AND creator/share/explicit admin-wide file grant. Owner/Admin/Underwriter additionally require file access for bank review, transaction/source corrections, policies, allocations, risk, history, internal reports and processing controls. Approved policy creation is Owner/Admin only, still file constrained. Sharing never elevates a role; platform admin has no bypass.

Broker/other nonreviewer bank responses are allowlisted final public income/monthly summaries plus deliberately public conditions/explanations/review requests. Broker cannot read policy, transaction, fraud/integrity, sustainability, reviewer decisions/overrides, internal run data or internal reports by API/legacy preview/search/poll/export/audit/cache. Their original-source stream is restricted to their own uploaded versions and a successful scan. Brokers can upload new explanations/documents without replacing reviewer originals, submit explanation/review requests, and download only final independently generated Broker reports. All downloads reauthorize and are private/no-store. No internal finding is copied automatically into public wording.

Broker source exception is version-specific: their own clean explanation/upload version only. Uploading one version never exposes reviewer-owned versions of that document. New bank polling, report metadata, CSV/XLSX/PDF, audit and legacy routes use final-only public allowlists; sharing never elevates role. No bank notifications/websockets are implemented that bypass these resources. Only Owner/Admin may approve a policy, and both role and file access are still required.
