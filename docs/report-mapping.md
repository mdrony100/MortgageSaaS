# Actual report mapping and version guarantees

`Reports::snapshot` captures one immutable run/checklist/input/calculation/reviewer/finding/source/revision state. New issue creates a distinct report version; the PDF/XLSX bytes are generated at issuance through existing Dompdf/PhpSpreadsheet, stored privately through the established adapter, and SHA/size recorded. Downloads retrieve and integrity-check those exact bytes, never regenerate or overwrite history. Missing/corrupt artifact requires explicit new issuance.

| PDF section | Frozen source |
|---|---|
| Property/loan summary | File/borrower metadata, user loan context, separately reported appraised value and explicitly selected LTV basis |
| Review results | Both backend DSCRs, LTV, processing/coverage status, calculation provisional/missing status |
| Forms/coverage | Exact pinned version/hash/classification, processed forms/versions/page/image manifests and classification decisions |
| Rental/payment | Included unit statuses/rent/source/basis, selected payment mode/components/confirmation |
| Formula/precision/policy | Numerator/denominator/exact rational/display rounding, IO definition, assumptions, overlay/no-overlay verification |
| Items Requiring Attention | Original evidence-backed observations plus current human dispositions, no condition clearance |
| Guideline citations | Frozen registry source/section/URL/effective/retrieved/version/status/applicability |
| Facts/source references | Raw/normalized values/units, original version/page/excerpt/verified region or explicit unknown, source/image record ids |
| Reviewer notes/decisions | Actor/time/reason and immutable original confirmation/disposition history |
| Revision/version summary | Field-identity prior/proposed differences, run/parent/input/report/checklist versions |

PDF is a review report even with open findings and provisional calculations. It is never blocked merely because findings are open. It is not an appraisal accuracy certificate, loan eligibility decision, lender condition clearance or underwriting sign-off. Signature presence is not authenticity; appraisal specialist coverage unavailable is explicit.

## XLSX workbook
Nine sheets: **Summary; Appraisal facts; Unit rents and leases; Payment inputs; DSCR and LTV; Findings; Source references; Reviewer notes; Revision summary**. Values are frozen from the same report snapshot. Wide text is wrapped; long values are split into explicitly numbered continuation rows without dropping content. Landscape fit-to-width/repeated headers support printing. Imported/user/extracted text uses explicit string cells, including `=`, `+`, `-` or `@` prefixes, never formulas.

Only backend-owned numeric ratio/LTV formulas are emitted. PhpSpreadsheet calculates them and compares2place results against backend values; mismatches fail issuance. Formula caches are precalculated, numeric display uses0.00, no false/stale cached0. Report/calculation inputs show minor-unit labels where appropriate and source dollars separately. No client-provided workbook was available; this is the implemented appraisal workbook, not a fabricated supplied template. Existing Bank fallback/template rules and selected12/24period regression remain unchanged.

## Access/integrity
Authenticated org/file/reviewer gating applies to metadata and actual downloads. Broker has no internal appraisal export and no Bank internal fraud/sustainability export. Private original/source/image/report URLs reauthorize on every request; no public attachment URL. Byte quotas include appraisal report/page/image artifacts. A new source, decision or renderer version never mutates an issued report. Snapshot hash proves content identity; per-artifact SHA proves stored bytes.

Actual rendered-page/workbook inspection, formula cache values and remaining visual/browser limitations are recorded in acceptance results. Financial outputs use explicit presentation times in Asia/Dhaka; database audit instants remain UTC.

Checked numeric formula rows are inside the DSCR/LTV print area; the final actual XLSX caches and LibreOffice print output were independently inspected. Property identity summary retains each original source and reviewer-confirmation status.
