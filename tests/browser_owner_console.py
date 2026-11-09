"""Rendered-browser checks + screenshots for the owner console and customer workspace (development only).

Drives headless Chrome against a running LOCAL app with local fictional accounts.
Credentials are read from a private JSON file (never committed):
  {"admin": {"email", "password", "totp_secret"}, "customer": {"email", "password", "organization_id"}}
Usage:
  ACCOUNTS_FILE=/path/private.json SITE_URL=http://127.0.0.1:8000 SCREENSHOT_DIR=/tmp/owner-shots \
  CHROME_PATH=/usr/bin/google-chrome python tests/browser_owner_console.py
Optional BEFORE_URL=http://127.0.0.1:8010 captures "before" customer dashboard screenshots from an older build.
It starts and ends one owner support session for the fictional customer and adds one audit trail.
"""
import base64, hashlib, hmac, json, os, struct, sys, time, urllib.parse
from playwright.sync_api import sync_playwright

BASE = os.getenv("SITE_URL", "http://127.0.0.1:8000").rstrip("/")
BEFORE = os.getenv("BEFORE_URL")
OUT = os.getenv("SCREENSHOT_DIR", "/tmp/owner-shots")
CHROME = os.getenv("CHROME_PATH")
ACC = json.load(open(os.environ["ACCOUNTS_FILE"]))
os.makedirs(OUT, exist_ok=True)
results = []


def check(name, ok, detail=""):
    results.append(bool(ok))
    print(("PASS " if ok else "FAIL ") + name + (f" — {detail}" if detail and not ok else ""), flush=True)


def totp(secret):
    key = base64.b32decode(secret.upper() + "=" * (-len(secret) % 8))
    counter = int(time.time()) // 30
    h = hmac.new(key, struct.pack(">Q", counter), hashlib.sha1).digest()
    o = h[-1] & 15
    return str((struct.unpack(">I", h[o:o + 4])[0] & 0x7FFFFFFF) % 1000000).zfill(6)


FONT_JS = """(scope) => {
  const root = document.querySelector(scope) || document.body; const small = [], tiny = [];
  const visible = e => { const r = e.getBoundingClientRect(); const s = getComputedStyle(e); return r.width > 0 && r.height > 0 && s.visibility !== 'hidden' && s.display !== 'none' && (e.textContent||'').trim().length > 0; };
  root.querySelectorAll('p,li,td,dd,blockquote').forEach(e => { if (!visible(e)) return; const fs = parseFloat(getComputedStyle(e).fontSize); if (fs < 15) small.push(e.tagName + ' ' + fs + 'px: ' + e.textContent.trim().slice(0, 50)); });
  root.querySelectorAll('label,small,.badge,.c-badge,th,dt,span,button,a,code').forEach(e => { if (!visible(e) || e.closest('svg')) return; const fs = parseFloat(getComputedStyle(e).fontSize); if (fs < 13) tiny.push(e.tagName + '.' + (e.className||'') + ' ' + fs + 'px: ' + e.textContent.trim().slice(0, 40)); });
  return {small, tiny}; }"""
OVERFLOW_JS = "() => document.documentElement.scrollWidth - document.documentElement.clientWidth"


def fonts(page, label, scope):
    r = page.evaluate(FONT_JS, scope)
    check(f"{label}: paragraphs/body text >= 15px", not r["small"], "; ".join(r["small"][:4]))
    check(f"{label}: labels/captions >= 13px", not r["tiny"], "; ".join(r["tiny"][:4]))


def shot(page, name):
    page.screenshot(path=os.path.join(OUT, name + ".png"), full_page=True)


def login(page, base, email, password, secret=None, redirect=""):
    page.goto(base + "/login" + (f"?redirect={urllib.parse.quote(redirect)}" if redirect else ""))
    page.fill("input[type=email]", email)
    page.fill("input[type=password]", password)
    page.click("button.primary")
    page.wait_for_timeout(1200)
    if "two-factor-challenge" in page.url:
        page.fill("input[autocomplete=one-time-code]", totp(secret))
        page.click("button.primary")
        page.wait_for_timeout(1500)


def launch(p):
    kw = {"args": ["--no-sandbox"]}
    if CHROME:
        kw["executable_path"] = CHROME
    return p.chromium.launch(**kw)


with sync_playwright() as p:
    b = launch(p)
    errors = []
    # ---------- Owner console ----------
    ctx = b.new_context(viewport={"width": 1440, "height": 900})
    page = ctx.new_page()
    page.on("pageerror", lambda e: errors.append(str(e)))
    page.goto(BASE + "/admin")
    check("guest /admin goes to sign-in with return path", "/login?redirect=%2Fadmin" in page.url, page.url)
    login(page, BASE, ACC["admin"]["email"], ACC["admin"]["password"], ACC["admin"]["totp_secret"], "/admin")
    page.wait_for_selector(".c-brand", timeout=15000)
    check("admin lands in the owner console after sign-in", page.url.rstrip("/").endswith("/admin"), page.url)
    bg = page.evaluate("getComputedStyle(document.querySelector('.c-top')).backgroundColor")
    check("console shell is distinct (dark navy top bar, Owner Console label)", bg == "rgb(11, 18, 32)" and "owner console" in page.inner_text(".c-top").lower(), bg)
    check("no customer workspace switcher in console", page.locator("select#org").count() == 0)
    org_id = ACC["customer"]["organization_id"]
    views = [("overview", "/admin"), ("analytics", "/admin/analytics"), ("revenue", "/admin/revenue"), ("approvals", "/admin/approvals"), ("organizations", "/admin/organizations"), ("org-detail", f"/admin/organizations/{org_id}"), ("users", "/admin/users"), ("inquiries", "/admin/inquiries"), ("audit", "/admin/audit"), ("plans", "/admin/plans"), ("reports", "/admin/reports"), ("system", "/admin/system")]
    for width in (1440, 375):
        page.set_viewport_size({"width": width, "height": 900 if width > 400 else 812})
        for name, url in views:
            page.goto(BASE + url)
            page.wait_for_selector(".c-main h1", timeout=15000)
            page.wait_for_timeout(700)
            check(f"{name} @{width}: no horizontal overflow", page.evaluate(OVERFLOW_JS) <= 1, str(page.evaluate(OVERFLOW_JS)))
            check(f"{name} @{width}: no error alert", page.locator(".c-alert.error").count() == 0, page.locator(".c-alert.error").first.inner_text() if page.locator(".c-alert.error").count() else "")
            fonts(page, f"{name} @{width}", ".c-main")
            if name in ("overview", "analytics", "revenue", "approvals", "organizations", "org-detail", "users", "inquiries", "audit"):
                shot(page, f"console-{name}-{width}")
    page.set_viewport_size({"width": 375, "height": 812})
    page.goto(BASE + "/admin")
    page.wait_for_selector(".c-main h1")
    check("mobile: nav hidden until menu opened", not page.is_visible("#console-nav a[href='/admin/users']"))
    page.click("button.c-menu")
    page.wait_for_timeout(300)
    check("mobile: menu opens navigation", page.is_visible("#console-nav a[href='/admin/users']") and page.get_attribute("button.c-menu", "aria-expanded") == "true")
    shot(page, "console-mobile-menu-375")
    page.keyboard.press("Escape")
    page.wait_for_timeout(300)
    check("mobile: Escape closes navigation", page.get_attribute("button.c-menu", "aria-expanded") == "false")
    page.set_viewport_size({"width": 1440, "height": 900})
    page.goto(BASE + "/admin")
    page.wait_for_selector(".c-main h1")
    page.keyboard.press("Tab")
    check("keyboard: first Tab reaches skip link", page.evaluate("document.activeElement.className") == "skip-link")
    for _ in range(4):
        page.keyboard.press("Tab")
    outline = page.evaluate("getComputedStyle(document.activeElement).outlineStyle")
    check("keyboard: focused control shows a visible outline", outline not in ("none", ""), outline)
    small_targets = page.evaluate("() => [...document.querySelectorAll('.c-btn:not(.small),.c-nav a,.c-icon-btn,.c-top-link')].filter(e=>{const r=e.getBoundingClientRect();return r.width>0&&r.height<43.5}).map(e=>e.textContent.trim().slice(0,30))")
    check("touch targets >= 44px for main controls", not small_targets, ", ".join(small_targets[:5]))
    # ---------- Support session (impersonation) ----------
    page.goto(BASE + "/admin/users?q=" + urllib.parse.quote(ACC["customer"]["email"]))
    page.wait_for_selector("text=View as customer")
    page.click("button:has-text('View as customer')")
    page.click(".c-support button.c-btn")
    page.wait_for_timeout(300)
    check("support session needs a reason (form will not submit empty)", page.evaluate("document.querySelector('#support-reason').validity.valueMissing") and "/admin/users" in page.url)
    page.fill("#support-reason", "Fictional check: customer asked for help finding a loan file")
    page.click(".c-support button.c-btn")
    page.wait_for_url("**/dashboard", timeout=15000)
    page.wait_for_selector(".support-banner", timeout=15000)
    banner = page.inner_text(".support-banner")
    check("customer app shows support banner with the customer name", "Viewing as Dana Fictional" in banner and "Owner support session" in banner, banner)
    check("console link hidden during support session", page.locator("a.owner-link").count() == 0)
    status = page.evaluate("async()=>{const t=decodeURIComponent((document.cookie.split('; ').find(x=>x.startsWith('XSRF-TOKEN='))||'').split('=').slice(1).join('='));const r=await fetch('/api/auth/user/password',{method:'PUT',credentials:'same-origin',headers:{'Accept':'application/json','Content-Type':'application/json','X-XSRF-TOKEN':t,'X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({current_password:'x',password:'y',password_confirmation:'y'})});return [r.status,(await r.json()).code]}")
    check("password change is blocked during the support session", status == [403, "impersonation_blocked"], str(status))
    page.wait_for_timeout(500)
    check("banner does not cover the workspace header @1440", page.evaluate("() => { const b = document.querySelector('.support-banner').getBoundingClientRect(); const h = document.querySelector('.main-shell header').getBoundingClientRect(); return h.top >= b.bottom - 1; }"))
    shot(page, "support-banner-dashboard-1440")
    page.set_viewport_size({"width": 375, "height": 812})
    page.wait_for_timeout(400)
    check("banner visible on mobile", page.is_visible(".support-banner button"))
    check("mobile support session: no horizontal overflow", page.evaluate(OVERFLOW_JS) <= 1)
    check("banner does not cover the workspace header @375", page.evaluate("() => { const b = document.querySelector('.support-banner').getBoundingClientRect(); const h = document.querySelector('.main-shell header').getBoundingClientRect(); return h.top >= b.bottom - 1; }"))
    shot(page, "support-banner-dashboard-375")
    page.goto(BASE + "/admin")
    check("console is closed while viewing as a customer", "Support session in progress" in page.content())
    page.goto(BASE + "/dashboard")
    page.wait_for_selector(".support-banner button")
    page.click(".support-banner button")
    page.wait_for_url("**/admin/users", timeout=15000)
    page.wait_for_selector(".c-main h1")
    check("Return to owner console restores the admin", page.locator(".c-brand").count() == 1 and "Fictional Platform Admin" in page.inner_text(".c-top") if page.viewport_size["width"] > 640 else page.locator(".c-brand").count() == 1)
    page.set_viewport_size({"width": 1440, "height": 900})
    page.goto(BASE + "/admin/audit?")
    page.wait_for_selector("tbody tr")
    page.select_option("select", "impersonation")
    page.wait_for_timeout(800)
    txt = page.inner_text("tbody")
    check("audit log shows support session start, blocked action and end", all(x in txt for x in ["impersonation.started", "impersonation.blocked", "impersonation.ended"]), txt[:300])
    shot(page, "console-audit-support-1440")
    ctx.close()

    # ---------- Customer workspace font floor + after screenshots ----------
    def customer_pass(base, tag, check_fonts=True):
        c = b.new_context(viewport={"width": 1440, "height": 900})
        pg = c.new_page()
        login(pg, base, ACC["customer"]["email"], ACC["customer"]["password"])
        pg.goto(base + "/dashboard")
        pg.wait_for_selector("main", timeout=15000)
        pg.wait_for_timeout(1500)
        shot(pg, f"customer-dashboard-{tag}-1440")
        if check_fonts:
            fonts(pg, "customer dashboard @1440", "#app")
            check("customer sidebar has no platform items", "Platform administration" not in pg.inner_text("aside") and "Website inquiries" not in pg.inner_text("aside"))
            files = pg.evaluate("async()=>{const r=await fetch('/api/files',{headers:{Accept:'application/json'},credentials:'same-origin'});const d=await r.json();return (d.data||d).map(f=>f.id)}")
            if files:
                pg.goto(base + f"/files/{files[0]}")
                pg.wait_for_selector(".module-switch", timeout=15000)
                pg.wait_for_timeout(1200)
                for i, mod in enumerate(["bank", "appraisal", "dscr"]):
                    pg.locator(".module-switch button").nth(i).click()
                    pg.wait_for_timeout(1200)
                    fonts(pg, f"loan file {mod} review @1440", "#app")
                    check(f"loan file {mod} @1440: no horizontal overflow", pg.evaluate(OVERFLOW_JS) <= 1)
                    shot(pg, f"customer-file-{mod}-1440")
            for path in ["/settings/organization", "/settings/profile", "/settings/plans"]:
                pg.goto(base + path)
                pg.wait_for_timeout(1500)
                fonts(pg, f"customer {path} @1440", "#app")
                check(f"customer {path}: no horizontal overflow", pg.evaluate(OVERFLOW_JS) <= 1)
            shot(pg, "customer-plans-1440")
        pg.set_viewport_size({"width": 375, "height": 812})
        pg.goto(base + "/dashboard")
        pg.wait_for_timeout(1500)
        shot(pg, f"customer-dashboard-{tag}-375")
        if check_fonts:
            fonts(pg, "customer dashboard @375", "#app")
            check("customer dashboard @375: no horizontal overflow", pg.evaluate(OVERFLOW_JS) <= 1, str(pg.evaluate(OVERFLOW_JS)))
        c.close()

    customer_pass(BASE, "after")
    if BEFORE:
        customer_pass(BEFORE, "before", check_fonts=False)
    check("no uncaught page errors", not errors, "; ".join(errors[:3]))
    b.close()

print(f"{sum(results)}/{len(results)} checks passed")
sys.exit(0 if all(results) else 1)
