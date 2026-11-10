#!/usr/bin/env python3
"""Write SAMPLE / FICTIONAL MortgageLens QA PDFs. Not a lender form or a real statement.

Run: .venv-document-worker/bin/python qa/generate_fixtures.py
"""
import os
from pathlib import Path

import pymupdf

ROOT = Path(__file__).resolve().parent / "fixtures"
MARK = "SAMPLE / FICTIONAL"


def write_text_pdf(path: Path, lines):
    path.parent.mkdir(parents=True, exist_ok=True)
    doc = pymupdf.open()
    page = doc.new_page(width=612, height=792)
    y = 36
    for line in lines:
        page.insert_text((36, y), line, fontsize=9, fontname="helv")
        y += 12
        if y > 760:
            page = doc.new_page(width=612, height=792)
            y = 36
    doc.save(path)
    doc.close()


def statement_lines(header, transactions, page_label="Page 1 of 1"):
    lines = [
        MARK,
        "This is a fictional bank statement created for MortgageLens QA. Not a real account.",
        "Bank statement",
        page_label,
        f"Institution: {header['institution']}",
        f"Holder: {header['holder']}",
        f"Account: {header['account']}",
        f"Currency: {header['currency']}",
        f"Designation: {header['designation']}",
        f"Period start: {header['period_start']}",
        f"Period end: {header['period_end']}",
        f"Opening balance: {header['opening_balance']}",
        f"Closing balance: {header['closing_balance']}",
        f"Total credits: {header['reported_credits']}",
        f"Total debits: {header['reported_debits']}",
        f"Fees: {header['reported_fees']}",
        f"Fees separate: {header['fees_separate']}",
        "Date | Description | Credit | Debit | Balance | Reference",
    ]
    for tx in transactions:
        lines.append(
            f"{tx['date']} | {tx['description']} | {tx['credit']} | {tx['debit']} | {tx['balance']} | {tx['reference']}"
        )
    return lines


def money(cents):
    sign = "-" if cents < 0 else ""
    cents = abs(cents)
    return f"{sign}{cents // 100}.{cents % 100:02d}"


def month_end(year, month):
    if month == 2:
        return 28
    if month in (4, 6, 9, 11):
        return 30
    return 31


def personal_month(year, month, opening_cents, extra_credits=(), extra_debits=()):
    start = f"{year:04d}-{month:02d}-01"
    end = f"{year:04d}-{month:02d}-{month_end(year, month):02d}"
    balance = opening_cents
    credits = 0
    debits = 0
    fees = 0
    txs = []

    def add(day, description, credit, debit, reference):
        nonlocal balance, credits, debits, fees
        credits += credit
        debits += debit
        if "fee" in description.lower():
            fees += debit
        balance += credit - debit
        txs.append({
            "date": f"{year:04d}-{month:02d}-{day:02d}",
            "description": description,
            "credit": money(credit) if credit else "",
            "debit": money(debit) if debit else "",
            "balance": money(balance),
            "reference": reference,
        })

    add(5, "Payroll deposit", 400000, 0, "PAY")
    add(6, "Rent payment", 0, 150000, "RENT")
    for item in extra_credits:
        add(*item)
    for item in extra_debits:
        add(*item)
    header = {
        "institution": "Fictional Sample Bank",
        "holder": "Fictional Avery Sample",
        "account": "****1001",
        "currency": "USD",
        "designation": "personal",
        "period_start": start,
        "period_end": end,
        "opening_balance": money(opening_cents),
        "closing_balance": money(balance),
        "reported_credits": money(credits),
        "reported_debits": money(debits),
        "reported_fees": money(fees),
        "fees_separate": "no",
    }
    return header, txs, balance


def build_personal(dest: Path, skip_month=None):
    opening = 500000  # $5,000.00
    paths = []
    for month in range(1, 13):
        extras_c = ()
        extras_d = ()
        if month == 6:
            extras_d = (
                (10, "NSF returned item", 0, 2500, "NSF-1"),
                (10, "NSF fee", 0, 3500, "NSF-1"),
                (18, "Overdraft", 0, 2000, "OD-9"),
                (18, "Overdraft fee", 0, 3500, "OD-9"),
            )
        if month == 12:
            extras_c = (
                (12, "Loan proceeds", 500000, 0, "LOAN-1"),
                (15, "Refund of vendor", 20000, 0, "REF-1"),
                (20, "Cash deposit", 10000, 0, "CASH-1"),
            )
        header, txs, opening = personal_month(2025, month, opening, extras_c, extras_d)
        if skip_month == month:
            continue
        path = dest / f"personal-2025-{month:02d}.pdf"
        write_text_pdf(path, statement_lines(header, txs))
        paths.append(path)
    return paths


def build_business():
    opening = 2000000  # $20,000.00
    dest = ROOT / "bank" / "business-24"
    for i in range(24):
        year = 2024 + (i // 12)
        month = (i % 12) + 1
        start = f"{year:04d}-{month:02d}-01"
        end = f"{year:04d}-{month:02d}-{month_end(year, month):02d}"
        balance = opening + 1000000 - 400000
        txs = [
            {"date": f"{year:04d}-{month:02d}-03", "description": "Business revenue deposit", "credit": "10000.00", "debit": "", "balance": money(opening + 1000000), "reference": "REV"},
            {"date": f"{year:04d}-{month:02d}-20", "description": "Operating expense", "credit": "", "debit": "4000.00", "balance": money(balance), "reference": "EXP"},
        ]
        header = {
            "institution": "Fictional Sample Bank",
            "holder": "Fictional Sample Holdings LLC",
            "account": "****2002",
            "currency": "USD",
            "designation": "business",
            "period_start": start,
            "period_end": end,
            "opening_balance": money(opening),
            "closing_balance": money(balance),
            "reported_credits": "10000.00",
            "reported_debits": "4000.00",
            "reported_fees": "0.00",
            "fees_separate": "no",
        }
        write_text_pdf(dest / f"business-{year:04d}-{month:02d}.pdf", statement_lines(header, txs))
        opening = balance


def build_transfers():
    dest = ROOT / "bank" / "transfers"
    biz_txs = [
        {"date": "2025-12-04", "description": "Business revenue deposit", "credit": "2000.00", "debit": "", "balance": "7000.00", "reference": "REV"},
        {"date": "2025-12-08", "description": "Own account transfer to 4004", "credit": "", "debit": "800.00", "balance": "6200.00", "reference": "XFER-9"},
    ]
    biz = {
        "institution": "Fictional Sample Bank", "holder": "Fictional Sample Holdings LLC", "account": "****3003",
        "currency": "USD", "designation": "business", "period_start": "2025-12-01", "period_end": "2025-12-31",
        "opening_balance": "5000.00", "closing_balance": "6200.00", "reported_credits": "2000.00",
        "reported_debits": "800.00", "reported_fees": "0.00", "fees_separate": "no",
    }
    per_txs = [
        {"date": "2025-12-05", "description": "Payroll deposit", "credit": "2000.00", "debit": "", "balance": "3000.00", "reference": "PAY"},
        {"date": "2025-12-08", "description": "Own account transfer from 3003", "credit": "800.00", "debit": "", "balance": "3800.00", "reference": "XFER-9"},
        {"date": "2025-12-09", "description": "Rent payment", "credit": "", "debit": "500.00", "balance": "3300.00", "reference": "RENT"},
    ]
    per = {
        "institution": "Fictional Sample Bank", "holder": "Fictional Avery Sample", "account": "****4004",
        "currency": "USD", "designation": "personal", "period_start": "2025-12-01", "period_end": "2025-12-31",
        "opening_balance": "1000.00", "closing_balance": "3300.00", "reported_credits": "2800.00",
        "reported_debits": "500.00", "reported_fees": "0.00", "fees_separate": "no",
    }
    write_text_pdf(dest / "transfer-business-2025-12.pdf", statement_lines(biz, biz_txs))
    write_text_pdf(dest / "transfer-personal-2025-12.pdf", statement_lines(per, per_txs))


def build_mismatch():
    header = {
        "institution": "Fictional Sample Bank", "holder": "Fictional Avery Sample", "account": "****1001",
        "currency": "USD", "designation": "personal", "period_start": "2025-03-01", "period_end": "2025-03-31",
        "opening_balance": "1000.00", "closing_balance": "5000.00", "reported_credits": "999.00",
        "reported_debits": "0.00", "reported_fees": "0.00", "fees_separate": "no",
    }
    txs = [{"date": "2025-03-05", "description": "Payroll deposit", "credit": "100.00", "debit": "", "balance": "1100.00", "reference": "PAY"}]
    write_text_pdf(ROOT / "bank" / "personal-totals-mismatch-2025-03.pdf", statement_lines(header, txs))


def build_missing_page():
    header = {
        "institution": "Fictional Sample Bank", "holder": "Fictional Avery Sample", "account": "****1001",
        "currency": "USD", "designation": "personal", "period_start": "2025-04-01", "period_end": "2025-04-30",
        "opening_balance": "1000.00", "closing_balance": "3500.00", "reported_credits": "4000.00",
        "reported_debits": "1500.00", "reported_fees": "0.00", "fees_separate": "no",
    }
    txs = [
        {"date": "2025-04-05", "description": "Payroll deposit", "credit": "4000.00", "debit": "", "balance": "5000.00", "reference": "PAY"},
        {"date": "2025-04-06", "description": "Rent payment", "credit": "", "debit": "1500.00", "balance": "3500.00", "reference": "RENT"},
    ]
    write_text_pdf(ROOT / "bank" / "personal-missing-pages-2025-04.pdf", statement_lines(header, txs, "Page 1 of 3"))


def build_scanned():
    """Image-only PDF. Native text extraction must not invent transactions."""
    src = ROOT / "bank" / "personal-12" / "personal-2025-01.pdf"
    text = pymupdf.open(src)
    pix = text[0].get_pixmap(matrix=pymupdf.Matrix(2, 2), alpha=False)
    img = ROOT / "bank" / "_scan-render.png"
    pix.save(img)
    text.close()
    out = pymupdf.open()
    page = out.new_page(width=612, height=792)
    page.insert_image(page.rect, filename=str(img))
    dest = ROOT / "bank" / "personal-scanned-2025-01.pdf"
    out.save(dest)
    out.close()
    img.unlink()


def build_corrupt():
    path = ROOT / "bank" / "corrupt-truncated.pdf"
    path.write_bytes(b"%PDF-1.4\n% SAMPLE / FICTIONAL\n1 0 obj\n<< /Type /Catalog >>\n")
    junk = ROOT / "bank" / "not-a-statement.txt"
    junk.write_text("SAMPLE / FICTIONAL\nThis file is not a bank statement or a PDF.\n")


def labeled(path, lines):
    write_text_pdf(path, [MARK, "Fictional appraisal or lease input. Not a Fannie Mae form and not an executed document."] + lines)


def build_appraisal():
    dest = ROOT / "appraisal"
    labeled(dest / "form-1004-one-unit.pdf", [
        "classification | | appraisal",
        "form | | 1004",
        "form_version | | fictional-qa",
        "address | | 1 Fictional Sample Street",
        "units | | 1",
        "appraised_value | | 330000.00",
        "Page 1 of 1",
    ])
    labeled(dest / "form-1025-four-unit.pdf", [
        "classification | | appraisal",
        "form | | 1025",
        "form_version | | fictional-qa",
        "address | | 4 Fictional Sample Street",
        "units | | 4",
        "appraised_value | | 880000.00",
        "market_rent | 1 | 900.00",
        "market_rent | 2 | 800.00",
        "market_rent | 3 | 800.00",
        "market_rent | 4 | 800.00",
        "Page 1 of 1",
    ])
    labeled(dest / "form-1007-rent-schedule.pdf", [
        "classification | | rental_schedule",
        "form | | 1007",
        "address | | 4 Fictional Sample Street",
        "market_rent | 1 | 900.00",
        "market_rent | 2 | 800.00",
        "market_rent | 3 | 800.00",
        "market_rent | 4 | 800.00",
        "Page 1 of 1",
    ])
    labeled(dest / "form-216-operating-income.pdf", [
        "classification | | operating_income",
        "form | | 216",
        "address | | 4 Fictional Sample Street",
        "gross_rent | | 3300.00",
        "Page 1 of 1",
    ])
    for unit, rent in (("1", "900.00"), ("2", "800.00"), ("3", "800.00"), ("4", "800.00")):
        labeled(dest / f"lease-unit-{unit}.pdf", [
            "classification | | lease",
            "lease.address | | 4 Fictional Sample Street",
            f"lease.unit | {unit} | {unit}",
            "lease.parties | | Fictional landlord and fictional tenant",
            f"lease.rent | {unit} | {rent}",
            f"lease.start | {unit} | 2024-01-01",
            f"lease.end | {unit} | 2026-12-31",
            "Page 1 of 1",
        ])


def main():
    if ROOT.exists():
        for dirpath, _, files in os.walk(ROOT):
            for name in files:
                if name.endswith((".pdf", ".txt", ".png")):
                    os.remove(os.path.join(dirpath, name))
    build_personal(ROOT / "bank" / "personal-12")
    build_personal(ROOT / "bank" / "personal-missing-february", skip_month=2)
    build_business()
    build_transfers()
    build_mismatch()
    build_missing_page()
    build_scanned()
    build_corrupt()
    build_appraisal()
    # Duplicate is the same bytes as January, copied so the fixture folder shows the case.
    src = ROOT / "bank" / "personal-12" / "personal-2025-01.pdf"
    dup = ROOT / "bank" / "duplicate-of-personal-2025-01.pdf"
    dup.write_bytes(src.read_bytes())
    print("Wrote fictional fixtures under", ROOT)


if __name__ == "__main__":
    main()
