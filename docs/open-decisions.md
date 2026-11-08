# Open client/operational decisions — final development

Implementation does not invent pricing, lender rules, legal retention or provider coverage. Missing credentials block affected live functions only, not completed surrounding workflows.

| Decision / required input | Current safe behavior |
|---|---|
| Actual appraisal/bank specification and supplied report/workbook templates | Inspected actual attachment directory: only two PNGs, no such documents. Existing Bank fallback remains labeled; real appraisal PDF/XLSX implemented, not described as supplied-client format. |
| Madison/designated client reviewer identity and approval date/content | Official Fannie minimal baseline stays draft. Local/testing demo approval is isolated and cannot enter ordinary files. |
| Investor/program applicability, supported exact form/UAD paths, property/ADU/mixed/rural/site interpretations | Explicit manual/“Verify Against Program Guidelines”; no automatic ineligibility. Traditional confirmed forms are not silently interchangeable with new/different formats. |
| Qualifying-rent selection, vacancy permission/market-rent evidence, executed/MTM/STR methods and ADU inclusion | Explicit per-unit reviewer source/basis/confirmation; no cap/min/max/substitution/renewal/annualization. Unknown stays unknown. |
| Monthly payment/IO definition, rounding, LTV value basis/approved maximum/order | Deterministic explicit inputs, two DSCRs, display-only convention pending approval; no universal lower-of or invented overlay. |
| Bank lender source/expense/ownership/completeness/finalization rules, NSF event-vs-fee/reversals/OD conventions, transfer ambiguity/sustainability thresholds | Existing versioned approval and conservative evidence/review gates retained. Income/risk stay separate. |
| Broker/processor appraisal editing/export rights versus sharing wording; ownership-transfer/wide-grant policy | Existing conservative role/file policy, no sharing-based elevation. |
| Approved model/key/budget/privacy/retention and real extraction/photo acceptance targets | Responses actual-pixel adapter implemented/mocked; live submissions disabled by default and not performed. |
| Specialist vendor bank scope/commercial approvals/Book/token and any officially documented appraisal support | Bank adapter retained; appraisal specialist coverage explicitly not enabled/claimed. |
| Live billing provider and client-approved catalog/pricing/taxes/billing cycles | Neutral disabled/fake interface only, no invented commercial offers or live dependency. |
| Failed/cancelled/refunded/retried usage counting, downgrade overage, cancellation/refund/access retention | Configured operational idempotent counters, no inferred charge. Policy-pending preserves existing access; no destructive cancellation behavior. |
| Original/revision/derived/report/DB/provider/backup retention, legal holds/residency/deletion authority/RPO-RTO | Configuration and dry-run inventory; no irreversible deletion/provisioning/backup guarantee. |
| Production scanner/current signatures, OS sandbox/container/HTTPS/secrets/privateS3/SMTP/Brevo/monitoring/backup-restore host acceptance | Future setup/runbook only; local mocks/native proof are not production-host proof. |
| Client approved real/anonymized samples, browser/visual/accessibility/security/performance/lender acceptance | Synthetic/local test coverage recorded; no production accuracy/capacity/lender/regulatory guarantees. |
| PyMuPDF/MuPDF and other dependency license acceptance | Review AGPL/commercial obligations with client/legal; no license purchased. |

Plaid remains future. No new scope change selects it. No production deployment or main-branch modification is authorized by final development.
