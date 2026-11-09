"""Public website HTTP smoke checks against a running local app (development only).

Usage: SMOKE_URL=http://localhost:8000 MAILPIT_URL=http://127.0.0.1:8025 python3 tests/smoke_website.py
Submits fictional contact inquiries; requires a local/dev database, never production.
"""
import http.cookiejar, json, os, re, sys, time, urllib.parse, urllib.request, urllib.error
BASE = os.getenv("SMOKE_URL", "http://localhost:8000").rstrip("/")
MAILPIT = os.getenv("MAILPIT_URL", "http://127.0.0.1:8025").rstrip("/")
results = []
def check(name, ok):
    results.append(ok); print(("PASS " if ok else "FAIL ") + name, flush=True)
jar = http.cookiejar.CookieJar()
class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *a, **k): return None
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
raw = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar), NoRedirect)
def get(path, o=opener, headers=None):
    try:
        r = o.open(urllib.request.Request(BASE + path, headers=headers or {}))
        return r.status, r.headers, r.read().decode()
    except urllib.error.HTTPError as e:
        return e.code, e.headers, e.read().decode(errors="replace")
pages = {"/": "Understand mortgage documents.", "/features": "Supports forms 1004, 1025, 1007 and 216.", "/how-it-works": "does not issue lender approvals",
         "/pricing": "Contact for pricing", "/security": "Private document access", "/faq": "Can I connect bank accounts directly?", "/contact": "Request a demo or ask a question",
         "/privacy": "DRAFT", "/terms": "DRAFT"}
titles = set()
for path, text in pages.items():
    s, h, body = get(path)
    titles.add(re.search(r"<title>(.*?)</title>", body).group(1) if "<title>" in body else None)
    check(f"{path} renders server HTML ({s})", s == 200 and text in body and "<main id=\"main\"" in body)
    check(f"{path} nav + CTAs + mobile menu aria", all(x in body for x in [">Features<", ">How It Works<", ">Pricing<", ">FAQ<", ">Contact<", ">Sign In<", ">Request a Demo<", 'aria-controls="site-mobile-nav"', 'aria-expanded="false"', 'href="#main"', "Privacy Policy", "Terms of Service"]))
    check(f"{path} no dashboard bundle, noindex outside production", "assets/app-" not in body and "noindex" in (h.get("X-Robots-Tag") or ""))
    links = set(re.findall(r'href="([^"#]+)"', body))
    bad = []
    for link in links:
        u = urllib.parse.urlparse(link)
        if u.scheme in ("mailto", "tel"): continue
        if u.netloc and u.netloc not in urllib.parse.urlparse(BASE).netloc and not u.netloc.startswith(("127.0.0.1", "localhost")): bad.append(link); continue
        p = u.path or "/"
        if p.startswith("/build/") or p.endswith(".svg"): continue
        st = get(p + (("?" + u.query) if u.query else ""))[0]
        if st != 200: bad.append(f"{link}={st}")
    check(f"{path} all internal links resolve ({len(links)})", not bad)
check("unique titles", len(titles) == len(pages))
s, h, xml = get("/sitemap.xml")
check("sitemap lists public pages only", s == 200 and "/features</loc>" in xml and not any(x in xml for x in ["/login", "/dashboard", "/api", "/files", "/settings", "/platform"]))
s, h, robots = get("/robots.txt")
check("robots disallows all in non-production", s == 200 and "Disallow: /" in robots)
for p in ["/dashboard", "/login"]:
    s, h, body = get(p)
    check(f"{p} still serves SPA shell noindex", s == 200 and '<div id="app"></div>' in body and "noindex" in (h.get("X-Robots-Tag") or ""))
s, h, body = get("/api/platform/inquiries", headers={"Accept": "application/json"})
check("inquiry admin API rejects guests", s == 401)
def submit(fields):
    m = None
    for _ in range(2):  # a pending success flash is shown once instead of the form
        s, h, page = get("/contact")
        m = re.search(r'name="_token" value="([^"]+)"', page)
        if m: break
    token = m.group(1)
    data = urllib.parse.urlencode({"_token": token, **fields}).encode()
    try:
        r = raw.open(urllib.request.Request(BASE + "/contact", data=data, headers={"Referer": BASE + "/contact"}))
        return r.status, r.headers.get("Location")
    except urllib.error.HTTPError as e:
        return e.code, e.headers.get("Location")
stamp = str(int(time.time()))
s, loc = submit({"name": "", "email": "bad", "company": "", "inquiry_type": "demo", "message": "x", "homepage_url": ""})
s2, h2, after = get("/contact")
check("invalid submission redirects back with errors, no success", s == 302 and "Please correct the highlighted fields." in after and "has been received" not in after)
s, loc = submit({"name": "Fictional Smoke " + stamp, "email": f"smoke{stamp}@example.test", "company": "Example Smoke Lending", "role": "Processor", "inquiry_type": "demo", "message": "Fictional smoke-test demo request.", "homepage_url": ""})
s2, h2, after = get("/contact")
check("valid submission saved then success shown", s == 302 and "your message has been received" in after)
found = False
for _ in range(20):
    try:
        msgs = json.loads(urllib.request.urlopen(MAILPIT + "/api/v1/search?query=" + urllib.parse.quote("subject:\"New website inquiry\""), timeout=5).read())
        found = any(m.get("ReplyTo") and any(r.get("Address") == f"smoke{stamp}@example.test" for r in m["ReplyTo"]) for m in msgs.get("messages", []))
    except Exception:
        found = False
    if found: break
    time.sleep(1)
check("queued notification delivered to local Mailpit business inbox", found)
ok = True
for i in range(6):
    s, loc = submit({"name": "Fictional Burst", "email": f"burst{stamp}{i}@example.test", "company": "Example", "inquiry_type": "general", "message": "Fictional rate limit check.", "homepage_url": ""})
s2, h2, after = get("/contact")
check("rate limiting engages after repeated submissions", "Too many requests" in after)
print(json.dumps({"passed": sum(results), "total": len(results)}))
sys.exit(0 if all(results) else 1)
