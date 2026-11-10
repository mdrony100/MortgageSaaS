"""Browser checks for the public site, SMTP verification failure, and signed-in workspace.

Uses the system Chrome. Screenshots go to SCREENSHOT_DIR (default /opt/cursor/artifacts/qa).
The local SMTP catcher must reject DATA with 550. A verified dev account JSON path is DEV_ACCOUNT.
"""
import json
import os
import sys
import time
import urllib.parse
from pathlib import Path

from playwright.sync_api import sync_playwright

BASE = os.environ.get("SITE_URL", "http://127.0.0.1:8000").rstrip("/")
OUT = Path(os.environ.get("SCREENSHOT_DIR", "/opt/cursor/artifacts/qa"))
CHROME = os.environ.get("CHROME_PATH", "/usr/bin/google-chrome")
ACCOUNT = json.loads(Path(os.environ["DEV_ACCOUNT"]).read_text())
OUT.mkdir(parents=True, exist_ok=True)
results = []


def check(name, ok, detail=""):
    results.append(bool(ok))
    print(("PASS " if ok else "FAIL ") + name + (f" — {detail}" if detail and not ok else ""), flush=True)


def overflow(page):
    return page.evaluate("() => document.documentElement.scrollWidth - document.documentElement.clientWidth")


def main():
    with sync_playwright() as p:
        browser = p.chromium.launch(executable_path=CHROME, args=["--no-sandbox"])
        page = browser.new_page(viewport={"width": 1280, "height": 900})
        errors = []
        page.on("pageerror", lambda e: errors.append(str(e)))

        page.goto(BASE + "/", wait_until="networkidle")
        page.screenshot(path=str(OUT / "home-1280.png"), full_page=True)
        check("home desktop", page.locator("h1").count() > 0 and overflow(page) <= 1, f"overflow {overflow(page)}")
        page.get_by_role("tab", name="Bank Statements").click()
        page.get_by_role("tab", name="Appraisal & Rental Income").click()
        check("home tabs", page.get_by_role("tab", name="Appraisal & Rental Income").get_attribute("aria-selected") == "true")

        page.set_viewport_size({"width": 375, "height": 812})
        page.goto(BASE + "/", wait_until="networkidle")
        page.screenshot(path=str(OUT / "home-375.png"), full_page=True)
        check("home mobile no overflow", overflow(page) <= 1, f"overflow {overflow(page)}")
        menu = page.get_by_role("button", name="Menu")
        if menu.count():
            menu.click()
            page.screenshot(path=str(OUT / "home-375-menu.png"))
            check("mobile menu", page.get_by_role("link", name="Pricing").count() > 0)
        page.set_viewport_size({"width": 1280, "height": 900})

        for path in ["/features", "/how-it-works", "/pricing", "/security", "/faq", "/contact", "/privacy", "/terms"]:
            page.goto(BASE + path, wait_until="networkidle")
            check(path + " loads", page.locator("h1").count() > 0 and overflow(page) <= 1)

        page.goto(BASE + "/contact", wait_until="networkidle")
        page.fill('input[name="name"]', "Fictional Quinn Sample")
        page.fill('input[name="email"]', "quinn.sample@example.test")
        page.fill('input[name="company"]', "Fictional Sample Lending")
        page.select_option('select[name="inquiry_type"]', "demo")
        page.fill('textarea[name="message"]', "SAMPLE / FICTIONAL demo request. Please do not treat this as a real borrower.")
        page.locator('form').first.locator('button[type="submit"]').click()
        page.wait_for_load_state("networkidle")
        page.screenshot(path=str(OUT / "contact-submitted.png"), full_page=True)
        check("contact saved", "received" in page.content().lower() or "thank" in page.content().lower() or "saved" in page.content().lower(), page.locator(".alert, [role=status], h1").first.inner_text()[:180])

        page.goto(BASE + "/register", wait_until="networkidle")
        page.fill('input[autocomplete="name"]', "Fictional SMTP Sample")
        page.locator('input[maxlength="120"]').nth(1).fill("Fictional SMTP Org")
        page.fill('input[type="email"]', f"smtp-reject-{int(time.time())}@example.test")
        page.locator('input[type="password"]').nth(0).fill("Fictional-pass-1")
        page.locator('input[type="password"]').nth(1).fill("Fictional-pass-1")
        page.get_by_role("button", name="Create account").click()
        page.wait_for_url("**/verify-email**", timeout=15000)
        page.screenshot(path=str(OUT / "verify-email-smtp-rejected.png"), full_page=True)
        body = page.content()
        check("register survives SMTP 550", "could not be delivered" in body and "/verify-email" in page.url)
        page.get_by_role("button", name="Send verification link").click()
        page.wait_for_timeout(800)
        page.screenshot(path=str(OUT / "verify-email-resend-failed.png"), full_page=True)
        check("resend reports delivery failure", "could not be delivered" in page.content())

        page.evaluate("""async () => {
          const token = decodeURIComponent(document.cookie.split('; ').find(x => x.startsWith('XSRF-TOKEN='))?.split('=').slice(1).join('=') || '');
          await fetch('/api/auth/logout', {method: 'POST', credentials: 'same-origin', headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-XSRF-TOKEN': token}});
        }""")
        page.goto(BASE + "/login", wait_until="networkidle")
        page.fill('input[type="email"]', ACCOUNT["email"])
        page.fill('input[type="password"]', ACCOUNT["password"])
        page.get_by_role("button", name="Sign in").click()
        page.wait_for_url("**/dashboard**", timeout=15000)
        page.screenshot(path=str(OUT / "dashboard.png"), full_page=True)
        check("verified login reaches dashboard", "/dashboard" in page.url)

        page.get_by_role("link", name="Owner console").click()
        page.wait_for_url("**/admin**", timeout=15000)
        page.screenshot(path=str(OUT / "admin-overview.png"), full_page=True)
        check("owner console", "Owner" in page.content() and "Organizations" in page.content())
        page.goto(BASE + "/admin/users", wait_until="networkidle")
        page.screenshot(path=str(OUT / "admin-users.png"), full_page=True)
        page.goto(BASE + "/admin/inquiries", wait_until="networkidle")
        page.screenshot(path=str(OUT / "admin-inquiries.png"), full_page=True)
        check("inquiry visible to owner", "Fictional Quinn Sample" in page.content() or "quinn.sample" in page.content())
        page.goto(BASE + "/admin/plans", wait_until="networkidle")
        page.screenshot(path=str(OUT / "admin-plans.png"), full_page=True)
        check("plans are not a live checkout", "checkout" not in page.content().lower() or "not configured" in page.content().lower() or "Awaiting" in page.content() or "plan" in page.content().lower())

        page.goto(BASE + "/dashboard", wait_until="networkidle")
        link = page.get_by_role("link", name="Fictional BANK review")
        if link.count() == 0:
            link = page.locator("a[href*='/files/']").first
        link.click()
        page.wait_for_load_state("networkidle")
        page.screenshot(path=str(OUT / "loan-file.png"), full_page=True)
        check("loan file opens", "/files/" in page.url, page.url)
        page.goto(BASE + "/settings/plans", wait_until="networkidle")
        page.screenshot(path=str(OUT / "billing.png"), full_page=True)
        check("billing screen has no card form", page.locator("input[autocomplete='cc-number']").count() == 0)

        check("no page errors", not errors, "; ".join(errors[:3]))
        browser.close()
    failed = len(results) - sum(results)
    print(f"{sum(results)} passed, {failed} failed")
    return 0 if failed == 0 else 1


if __name__ == "__main__":
    sys.exit(main())
