"""Rendered-browser checks for the public marketing website (development only).

Drives a real headless Chromium against a running local app, asserts layout,
interaction and accessibility behaviour, and saves review screenshots.

Usage (Playwright is NOT a project dependency; install it in a throwaway venv):
  python3 -m venv /tmp/pw && /tmp/pw/bin/pip install playwright && /tmp/pw/bin/playwright install chromium
  SITE_URL=http://127.0.0.1:8000 SCREENSHOT_DIR=/tmp/site-shots /tmp/pw/bin/python tests/browser_website.py
Optional: CHROME_PATH=/usr/bin/google-chrome to use an installed Chrome instead of the Playwright build.
Read-only: it does not submit forms or upload anything.
"""
import os, sys, urllib.parse
from playwright.sync_api import sync_playwright

BASE = os.getenv("SITE_URL", "http://127.0.0.1:8000").rstrip("/")
OUT = os.getenv("SCREENSHOT_DIR", "/tmp/site-shots")
CHROME = os.getenv("CHROME_PATH")
os.makedirs(OUT, exist_ok=True)
PAGES = ["/", "/features", "/how-it-works", "/pricing", "/security", "/faq", "/contact", "/privacy", "/terms"]
WIDTHS = [375, 768, 1280, 1440]
LOCAL_HOSTS = {urllib.parse.urlparse(BASE).hostname, "127.0.0.1", "localhost"}
results = []


def check(name, ok, detail=""):
    results.append(bool(ok))
    print(("PASS " if ok else "FAIL ") + name + (f" — {detail}" if detail and not ok else ""), flush=True)


def launch(p):
    kw = {"args": ["--no-sandbox"]}
    if CHROME:
        kw["executable_path"] = CHROME
    return p.chromium.launch(**kw)


def watch(page, errors, third_party):
    page.on("console", lambda m: errors.append(m.text) if m.type == "error" else None)
    page.on("pageerror", lambda e: errors.append(str(e)))
    page.on("request", lambda r: third_party.append(r.url) if urllib.parse.urlparse(r.url).hostname not in LOCAL_HOSTS and not r.url.startswith("data:") else None)


def settle(page):
    """Scroll through once so reveal-on-entry sections are shown, then return to top."""
    h = page.evaluate("document.body.scrollHeight")
    for y in range(0, h, 500):
        page.evaluate(f"window.scrollTo(0,{y})")
        page.wait_for_timeout(30)
    page.evaluate("window.scrollTo(0,0)")
    page.wait_for_timeout(800)


SMALL_TARGETS_JS = """() => {
  const sel = '.site-btn,.site-tab,.site-step,.site-nav-link,.site-mobile-link,.site-link,.site-menu-button,.site-faq summary,.site-footer a,.site-field input,.site-field select,.site-brand';
  const bad = [];
  document.querySelectorAll(sel).forEach(e => { const r = e.getBoundingClientRect(); const st = getComputedStyle(e);
    if (r.width === 0 || st.visibility === 'hidden' || e.closest('[hidden]')) return;
    if (r.height < 43.5 || r.width < 43.5) bad.push((e.textContent || e.id || e.tagName).trim().slice(0, 40) + ` ${Math.round(r.width)}x${Math.round(r.height)}`); });
  return bad; }"""

SMALL_TEXT_JS = """() => {
  const bad = new Set();
  const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
  while (walker.nextNode()) { const n = walker.currentNode; if (!n.textContent.trim()) continue; const el = n.parentElement; if (!el || el.closest('.sr-only,[hidden],noscript,script,style,.site-hp')) continue;
    const r = el.getBoundingClientRect(); if (r.width === 0 || r.height === 0) continue;
    const fs = parseFloat(getComputedStyle(el).fontSize); if (fs < 13.9) bad.add(n.textContent.trim().slice(0, 30) + ' ' + fs + 'px'); }
  return [...bad]; }"""


def run():
    with sync_playwright() as p:
        browser = launch(p)

        # 1. Every public page at every width: renders, no overflow, targets, text size, errors, third-party requests.
        for width in WIDTHS:
            ctx = browser.new_context(viewport={"width": width, "height": 900})
            for path in PAGES:
                page = ctx.new_page(); errors, third = [], []; watch(page, errors, third)
                resp = page.goto(BASE + path, wait_until="networkidle")
                settle(page)
                sw = page.evaluate("document.documentElement.scrollWidth"); vw = page.evaluate("document.documentElement.clientWidth")
                check(f"{path} @{width} renders 200", resp and resp.status == 200)
                check(f"{path} @{width} no horizontal overflow", sw <= vw, f"scrollWidth {sw} > {vw}")
                small = page.evaluate(SMALL_TARGETS_JS)
                check(f"{path} @{width} interactive targets >= 44px", not small, ", ".join(small[:6]))
                tiny = page.evaluate(SMALL_TEXT_JS)
                check(f"{path} @{width} visible text >= 14px", not tiny, ", ".join(tiny[:6]))
                check(f"{path} @{width} no console errors", not errors, "; ".join(errors[:3]))
                check(f"{path} @{width} no third-party requests", not third, ", ".join(third[:3]))
                if path == "/" or (path == "/features" and width in (375, 1440)):
                    name = "home" if path == "/" else "features"
                    page.screenshot(path=f"{OUT}/{name}-{width}-full.png", full_page=True)
                page.close()
            ctx.close()

        # 2. Homepage interactions at desktop width.
        ctx = browser.new_context(viewport={"width": 1440, "height": 900})
        page = ctx.new_page(); errors, third = [], []; watch(page, errors, third)
        page.goto(BASE + "/", wait_until="networkidle")
        page.wait_for_function("document.documentElement.classList.contains('site-ready')")
        page.screenshot(path=f"{OUT}/home-1440-top.png")
        hero = page.locator('[data-tabs="preview"]')
        seen = []
        for tab_id, panel_id, expect in [("hero-tab-bank", "hero-panel-bank", "Income summary"), ("hero-tab-checks", "hero-panel-checks", "Potential issue: totals don’t match"), ("hero-tab-rental", "hero-panel-rental", "DSCR")]:
            page.click(f"#{tab_id}"); page.wait_for_timeout(500)
            visible = [pid for pid in ["hero-panel-bank", "hero-panel-checks", "hero-panel-rental"] if page.is_visible(f"#{pid}")]
            text = page.inner_text(f"#{panel_id}")
            seen.append(text)
            check(f"hero tab {tab_id} shows only its panel", visible == [panel_id], str(visible))
            check(f"hero tab {tab_id} content", expect in text)
            check(f"hero tab {tab_id} aria-selected", page.get_attribute(f"#{tab_id}", "aria-selected") == "true")
            hero.screenshot(path=f"{OUT}/hero-tab-{tab_id.split('-')[-1]}.png")
        check("hero tabs change visible content", len(set(seen)) == 3)
        page.focus("#hero-tab-rental"); page.keyboard.press("ArrowRight")
        check("hero tabs ArrowRight wraps to first tab and focuses it", page.get_attribute("#hero-tab-bank", "aria-selected") == "true" and page.evaluate("document.activeElement.id") == "hero-tab-bank")
        page.keyboard.press("End")
        check("hero tabs End key selects last tab", page.get_attribute("#hero-tab-rental", "aria-selected") == "true")
        check("hero shows 'Sample data' label", page.locator('[data-tabs="preview"] .site-badge', has_text="Sample data").count() == 1)

        # Walkthrough
        walk = page.locator('[data-tabs="walkthrough"]')
        ids = ["upload", "extract", "review", "export"]
        def walk_state():
            sel = [i for i in ids if page.get_attribute(f"#walk-tab-{i}", "aria-selected") == "true"]
            vis = [i for i in ids if page.is_visible(f"#walk-panel-{i}")]
            return sel, vis, page.inner_text("[data-walk-status]"), page.get_attribute("[data-walk-progress]", "aria-valuenow")
        check("walkthrough labelled as interactive sample", "interactive sample walkthrough" in page.inner_text("#walkthrough").lower())
        check("walkthrough starts on step 1, Previous disabled", walk_state() == (["upload"], ["upload"], "Step 1 of 4", "1") and page.is_disabled("[data-walk-prev]"))
        walk.scroll_into_view_if_needed(); page.wait_for_timeout(400)
        walk.screenshot(path=f"{OUT}/walkthrough-step-1.png")
        for n in range(2, 5):
            page.click("[data-walk-next]"); page.wait_for_timeout(650)
            st = walk_state()
            check(f"walkthrough Next -> step {n}", st == ([ids[n-1]], [ids[n-1]], f"Step {n} of 4", str(n)), str(st))
            walk.screenshot(path=f"{OUT}/walkthrough-step-{n}.png")
        check("walkthrough Next disabled on last step", page.is_disabled("[data-walk-next]"))
        page.click("[data-walk-prev]"); page.wait_for_timeout(200)
        check("walkthrough Previous -> step 3", walk_state()[0] == ["review"])
        page.focus("#walk-tab-review"); page.keyboard.press("ArrowDown")
        check("walkthrough ArrowDown -> step 4 with focus", walk_state()[0] == ["export"] and page.evaluate("document.activeElement.id") == "walk-tab-export")
        page.keyboard.press("Home")
        check("walkthrough Home -> step 1", walk_state()[0] == ["upload"])
        page.click("#walk-tab-review")
        page.evaluate("window.scrollTo(0,0)"); page.wait_for_timeout(300)
        page.click("[data-walkthrough-start]"); page.wait_for_timeout(1500)
        top = page.evaluate("document.getElementById('walkthrough').getBoundingClientRect().top")
        check("'See How It Works' scrolls to walkthrough", -5 <= top <= 120, f"top={top}")
        check("'See How It Works' activates and focuses step 1", walk_state()[0] == ["upload"] and page.evaluate("document.activeElement.id") == "walk-tab-upload")

        # Calculator
        calc = page.locator("[data-dscr]")
        calc.scroll_into_view_if_needed()
        def calc_set(rent, pay):
            page.fill("#dscr-rent", rent); page.fill("#dscr-payment", pay); page.wait_for_timeout(120)
            return page.inner_text("[data-dscr-result]").strip(), page.inner_text("[data-dscr-equation]").strip(), page.inner_text("#dscr-rent-error").strip(), page.inner_text("#dscr-payment-error").strip()
        check("calculator default 3,300 / 3,000 = 1.10", page.inner_text("[data-dscr-result]").strip() == "1.10" and "In this simplified example, rent is 1.10 times the monthly property payment." in page.inner_text("[data-dscr]"))
        check("calculator label present", "Illustration only. Actual calculations depend on confirmed inputs and program requirements." in page.inner_text("[data-dscr]"))
        r = calc_set("4,125", "3,300"); check("calculator 4,125 / 3,300 = 1.25", r[0] == "1.25" and r[1] == "$4,125 ÷ $3,300 = 1.25", str(r))
        r = calc_set("1", "8"); check("calculator half-up rounding 1/8 = 0.13", r[0] == "0.13", str(r))
        r = calc_set("1000", "3"); check("calculator 1000/3 = 333.33", r[0] == "333.33", str(r))
        r = calc_set("2,999.99", "3000"); check("calculator cents 2,999.99/3000 = 1.00", r[0] == "1.00", str(r))
        r = calc_set("", "3000"); check("calculator empty rent error", r[0] == "—" and "Enter a monthly rent amount." in r[2] and page.get_attribute("#dscr-rent", "aria-invalid") == "true", str(r))
        r = calc_set("abc", "3000"); check("calculator non-numeric error", "Use numbers only" in r[2], str(r))
        r = calc_set("3300", "-100"); check("calculator negative error", "can’t be a negative amount" in r[3], str(r))
        r = calc_set("3300", "0"); check("calculator zero payment error", "greater than $0" in r[3] and page.get_attribute("#dscr-payment", "aria-invalid") == "true", str(r))
        check("calculator errors are announced (aria-live)", page.get_attribute("#dscr-payment-error", "aria-live") == "polite")
        calc.screenshot(path=f"{OUT}/calculator-error.png")
        page.click("[data-dscr-reset]"); page.wait_for_timeout(150)
        check("calculator reset restores 1.10 and clears errors", page.inner_text("[data-dscr-result]").strip() == "1.10" and page.get_attribute("#dscr-payment", "aria-invalid") is None)
        calc.screenshot(path=f"{OUT}/calculator-default.png")

        # FAQ keyboard
        first = page.locator(".site-faq summary").first
        first.scroll_into_view_if_needed(); first.focus(); page.keyboard.press("Enter"); page.wait_for_timeout(500)
        opened = page.evaluate("document.querySelector('.site-faq details').open")
        page.locator(".site-faq").screenshot(path=f"{OUT}/faq-open.png")
        page.keyboard.press("Enter"); page.wait_for_timeout(300)
        closed = not page.evaluate("document.querySelector('.site-faq details').open")
        check("FAQ toggles open/closed with keyboard", opened and closed)
        faq_q = page.inner_text(".site-faq")
        check("FAQ has the five practical questions", all(q in faq_q for q in ["What documents can I upload?", "Can I review and correct extracted information?", "Does the platform make lending decisions?", "What reports can I export?", "Can my team work on the same file?"]))

        # Final CTA + links
        check("final CTA copy", "See how MortgageLens fits your workflow." in page.inner_text("#final-cta-heading"))
        check("Contact Sales preselects pricing inquiry", page.get_attribute('section[aria-labelledby="final-cta-heading"] a:has-text("Contact Sales")', "href").endswith("/contact?type=pricing"))
        page.click('section[aria-labelledby="final-cta-heading"] a:has-text("Request a Demo")'); page.wait_for_load_state("networkidle")
        check("Request a Demo opens contact with demo selected", page.url.endswith("/contact?type=demo") and page.eval_on_selector("#inquiry_type", "e => e.value") == "demo")
        check("no console errors during interactions", not errors, "; ".join(errors[:3]))
        check("no third-party requests during interactions", not third, ", ".join(third[:3]))
        ctx.close()

        # 3. Mobile navigation and stacked walkthrough preview.
        ctx = browser.new_context(viewport={"width": 375, "height": 812}, has_touch=True, is_mobile=True)
        page = ctx.new_page(); page.goto(BASE + "/", wait_until="networkidle")
        page.wait_for_function("document.documentElement.classList.contains('site-ready')")
        page.screenshot(path=f"{OUT}/home-375-top.png")
        page.tap("[data-menu-button]"); page.wait_for_timeout(200)
        check("mobile menu opens with aria-expanded", page.get_attribute("[data-menu-button]", "aria-expanded") == "true" and page.is_visible("#site-mobile-nav"))
        page.screenshot(path=f"{OUT}/mobile-menu-open.png")
        page.keyboard.press("Escape")
        check("mobile menu closes on Escape", page.get_attribute("[data-menu-button]", "aria-expanded") == "false" and not page.is_visible("#site-mobile-nav"))
        page.tap("[data-menu-button]"); page.click("#site-mobile-nav >> text=Features"); page.wait_for_load_state("networkidle")
        check("mobile menu link navigates", page.url.endswith("/features"))
        page.goto(BASE + "/", wait_until="networkidle")
        tabs_box = page.locator('#walkthrough [role="tablist"]').bounding_box(); panel_box = page.locator("#walk-panel-upload").bounding_box()
        check("mobile walkthrough preview stacks beneath step controls", panel_box["y"] > tabs_box["y"] + tabs_box["height"])
        page.locator("#walk-tab-review").tap(); page.wait_for_timeout(300)
        check("walkthrough responds to touch", page.get_attribute("#walk-tab-review", "aria-selected") == "true" and page.is_visible("#walk-panel-review"))
        page.locator("#walkthrough").screenshot(path=f"{OUT}/walkthrough-375-step-3.png")
        page.locator("[data-dscr]").screenshot(path=f"{OUT}/calculator-375.png")
        ctx.close()

        # 4. Reduced motion.
        ctx = browser.new_context(viewport={"width": 1440, "height": 900}, reduced_motion="reduce")
        page = ctx.new_page(); page.goto(BASE + "/", wait_until="networkidle")
        page.wait_for_function("document.documentElement.classList.contains('site-ready')")
        rm = page.evaluate("""() => ({ reveal: document.documentElement.classList.contains('reveal-on'),
            btn: getComputedStyle(document.querySelector('.site-btn')).transitionDuration,
            hero: getComputedStyle(document.querySelector('.site-hero-in')).animationName,
            panel: getComputedStyle(document.querySelector('#hero-panel-bank')).animationName,
            bar: getComputedStyle(document.querySelector('.is-active .site-bar')).animationName,
            hidden: [...document.querySelectorAll('[data-reveal]')].filter(e => getComputedStyle(e).opacity !== '1').length })""")
        check("reduced motion: no reveal hiding, transitions and animations off", not rm["reveal"] and rm["btn"] in ("0s", "1e-05s") and rm["hero"] == "none" and rm["panel"] == "none" and rm["bar"] == "none" and rm["hidden"] == 0, str(rm))
        page.click("[data-walkthrough-start]"); page.wait_for_timeout(100)
        check("reduced motion: See How It Works jumps without smooth scroll", abs(page.evaluate("document.getElementById('walkthrough').getBoundingClientRect().top")) < 120)
        page.evaluate("window.scrollTo(0,0)")
        page.screenshot(path=f"{OUT}/home-1440-reduced-motion-full.png", full_page=True)
        ctx.close()

        # 5. JavaScript disabled: all content readable.
        ctx = browser.new_context(viewport={"width": 1280, "height": 900}, java_script_enabled=False)
        page = ctx.new_page(); page.goto(BASE + "/", wait_until="networkidle"); page.wait_for_timeout(4500)
        vis = {pid: page.is_visible(f"#{pid}") for pid in ["hero-panel-bank", "hero-panel-checks", "hero-panel-rental", "walk-panel-upload", "walk-panel-extract", "walk-panel-review", "walk-panel-export"]}
        check("no-JS: every hero and walkthrough panel is visible", all(vis.values()), str(vis))
        opacity = page.evaluate("[...document.querySelectorAll('[data-reveal]')].filter(e => getComputedStyle(e).opacity !== '1').length")
        check("no-JS: no section left hidden by reveal animation", opacity == 0)
        check("no-JS: calculator shows the default example", page.inner_text("[data-dscr-result]").strip() == "1.10")
        page.locator(".site-faq summary").first.click()
        check("no-JS: FAQ opens natively", page.evaluate("document.querySelector('.site-faq details').open"))
        page.screenshot(path=f"{OUT}/home-1280-no-js-full.png", full_page=True)
        ctx.close()
        browser.close()

    print(f"\n{sum(results)}/{len(results)} browser checks passed")
    return 0 if all(results) else 1


if __name__ == "__main__":
    sys.exit(run())
