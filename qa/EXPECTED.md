# Hand-calculated expected results

Every file under `qa/fixtures/` is marked **SAMPLE / FICTIONAL**. These figures were calculated from the printed rows before the app ran. Money is USD. The app's personal example profile treats `payroll_income` as eligible and does not apply an expense ratio (ownership 100%, remaining income 100%). The business example profile keeps `business_revenue`, ownership 100%, and remaining income 50%. The denominator is the selected 12 or 24 months even when a month is missing. Display rounding is HALF_UP to two decimal places. NSF fees are not a second event when they share the event's account, date, and reference (`reference_per_day`).

None of these figures is a loan approval, a decline, or a fraud verdict.

## Personal 12 months — `qa/fixtures/bank/personal-12/`

Account `****1001`, Fictional Avery Sample, calendar months January–December 2025.

Each month has a $4,000.00 payroll deposit (eligible) and a $1,500.00 rent debit (not a deposit).

December also has credits that the example profile does not treat as eligible income:

| Deposit | Amount | Why it is excluded |
|---|---:|---|
| Loan proceeds | 5,000.00 | `loan_proceeds` is not an eligible personal category |
| Refund of vendor | 200.00 | `refund` is not eligible |
| Cash deposit | 100.00 | `cash_deposit` is not eligible |

June also has these debits, which do not change deposits:

| Row | Amount | Expected count |
|---|---:|---|
| NSF returned item, reference NSF-1 | 25.00 | 1 NSF event |
| NSF fee, reference NSF-1 | 35.00 | fee only, not a second NSF event |
| Overdraft, reference OD-9 | 20.00 | 1 overdraft event |
| Overdraft fee, reference OD-9 | 35.00 | fee only, not a second overdraft event |

- Observed deposits = 12 × 4,000.00 + 5,000.00 + 200.00 + 100.00 = **53,300.00**
- Eligible deposits = 12 × 4,000.00 = **48,000.00**
- Excluded deposits = **5,300.00**
- Qualifying income = 48,000.00 × 100% × 100% / 12 = **4,000.00**
- NSF events **1**, overdraft events **1**, NSF fees **35.00**, overdraft fees **35.00**
- The example rule is not an approved lender policy, so the figure is provisional. The denominator stays 12.

## Missing February — `qa/fixtures/bank/personal-missing-february/`

Same rows as the 12-month set, without February (that month's only credit is the $4,000.00 payroll).

- Observed deposits = 53,300.00 − 4,000.00 = **49,300.00**
- Eligible deposits = 11 × 4,000.00 = **44,000.00**
- Qualifying income = 44,000.00 / 12 = 3,666.666… → **3,666.67**
- Flag: missing statement period for February 2025. March's opening balance does not continue January's closing balance. Missing months are not treated as zero, and the denominator stays 12.

## Business 24 months — `qa/fixtures/bank/business-24/`

Account `****2002`, Fictional Sample Holdings LLC, January 2024–December 2025. Each month: $10,000.00 business revenue and a $4,000.00 operating debit.

- Observed deposits = 24 × 10,000.00 = **240,000.00**
- Eligible deposits = **240,000.00**
- Adjusted for 50% remaining income = **120,000.00**
- Qualifying income = 120,000.00 / 24 = **5,000.00**
- NSF events **0**

## Inter-account transfer — `qa/fixtures/bank/transfers/`

December 2025 only, inside a 12-month window ending December 2025 (11 months are absent on purpose).

Business `****3003`: $2,000.00 business revenue and an $800.00 debit `Own account transfer to 4004`, reference XFER-9.

Personal `****4004`: $2,000.00 payroll, an $800.00 credit `Own account transfer from 3003`, reference XFER-9, and a $500.00 rent debit.

Personal account only (the business account is a different workflow):

- Observed deposits = 2,000.00 + 800.00 = **2,800.00**
- The $800.00 is an own-account transfer, so eligible deposits = **2,000.00** and excluded = **800.00**
- Qualifying income = 2,000.00 / 12 = **166.67**
- After both identities and the relationship are confirmed, the $800.00 pair is a proposed same-file transfer. Confirming it must not exclude the $800.00 twice.
- Flag: 11 missing months. Denominator stays 12.

## Printed totals do not match — `qa/fixtures/bank/personal-totals-mismatch-2025-03.pdf`

The only transaction is a $100.00 payroll deposit. Opening $1,000.00 plus $100.00 is $1,100.00, but the statement prints closing **$5,000.00** and total credits **$999.00**.

- Flag: balance arithmetic and credit total do not match. This is not a fraud conclusion.
- Eligible deposit on the row = **100.00**. It must not be replaced with the printed $999.00 or $5,000.00.

## Missing pages — `qa/fixtures/bank/personal-missing-pages-2025-04.pdf`

One uploaded page is labeled `Page 1 of 3`. The page itself reconciles ($1,000.00 + $4,000.00 − $1,500.00 = $3,500.00).

- Flag: printed page count does not match the upload. Eligible deposit on the uploaded page = **4,000.00**. The two missing pages are not invented as zero.

## Duplicate upload — `qa/fixtures/bank/duplicate-of-personal-2025-01.pdf`

Byte-for-byte the same file as `personal-12/personal-2025-01.pdf` (January payroll $4,000.00).

- Processing both in one run keeps one extraction. Observed and eligible deposits = **4,000.00**, not 8,000.00.
- Flag: duplicate original bytes.

## Scanned statement — `qa/fixtures/bank/personal-scanned-2025-01.pdf`

Image-only copy of January. There is no text layer.

- Expected without an approved vision/OCR key: no transactions and no invented deposits.
- Blocker: `BANK_OPENAI_APPROVED`, `OPENAI_API_KEY`, and `BANK_OPENAI_MODEL` are unset, so vision extraction is not live.

## Unreadable file — `qa/fixtures/bank/corrupt-truncated.pdf` and `not-a-statement.txt`

- The truncated PDF starts with `%PDF` and then stops. Parsing should fail with a safe message and must not invent transactions.
- The `.txt` file is not a PDF, PNG, or JPEG, so upload validation rejects it before parsing.

## Appraisal and rent — `qa/fixtures/appraisal/`

| File | What it is |
|---|---|
| `form-1004-one-unit.pdf` | Fictional 1004, 1 unit, appraised value 330,000.00 |
| `form-1025-four-unit.pdf` | Fictional 1025, 4 units, appraised value 880,000.00, market rents 900 / 800 / 800 / 800 |
| `form-1007-rent-schedule.pdf` | Fictional 1007 with the same four market rents |
| `form-216-operating-income.pdf` | Fictional 216, gross rent 3,300.00 |
| `lease-unit-1.pdf` … `lease-unit-4.pdf` | Fictional leases at 900.00, 800.00, 800.00, 800.00 |

Reviewer-confirmed qualifying rents are those lease amounts, not an automatic higher/lower choice:

- Qualifying rent = 900 + 800 + 800 + 800 = **3,300.00**
- Confirmed monthly payment = **3,000.00**
- Qualifying DSCR = 3,300.00 / 3,000.00 = **1.10**
- Market rent uses the same four amounts, so market DSCR = **1.10**
- Open findings, including the native-layout limitation, must not block the PDF or Excel export.

Native extraction does not read photographs. No vision API key is configured.
