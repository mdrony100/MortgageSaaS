#!/usr/bin/env python3
"""Native same-origin HTTP + real SMTP + Redis smoke. Synthetic .test accounts only.
Requires an empty/local development environment, running Horizon and Mailpit.
Creates isolated fictional records; never prints passwords, tokens or message bodies.
Not a browser/visual acceptance test.
"""
import http.cookiejar
import json
import os
import re
import secrets
import time
import urllib.error
import urllib.parse
import urllib.request

BASE = os.getenv("SMOKE_URL", "http://localhost:8000")
MAIL = os.getenv("MAILPIT_URL", "http://127.0.0.1:8025")
checks = []
def check(label, condition):
    assert condition, label
    checks.append(label)
    print("PASS", label)

class Client:
    def __init__(self):
        self.jar = http.cookiejar.CookieJar()
        self.http = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.jar))
        self.call("/sanctum/csrf-cookie", expect=204)

    def call(self, path, method="GET", data=None, expect=200, raw=False, content_type=None):
        headers = {"Accept": "application/json", "X-Requested-With": "XMLHttpRequest"}
        for cookie in self.jar:
            if cookie.name == "XSRF-TOKEN":
                headers["X-XSRF-TOKEN"] = urllib.parse.unquote(cookie.value)
        body = data if isinstance(data, bytes) else json.dumps(data).encode() if data is not None else None
        if body is not None:
            headers["Content-Type"] = content_type or "application/json"
        req = urllib.request.Request(path if path.startswith("http") else BASE + path,
                                     data=body, method=method, headers=headers)
        try:
            response = self.http.open(req)
        except urllib.error.HTTPError as e:
            response = e
        content = response.read()
        if response.status != expect:
            raise AssertionError(f"{method} {urllib.parse.urlsplit(path).path}: expected {expect}, got {response.status}")
        return content if raw else json.loads(content) if content and content.startswith((b"{", b"[")) else None

def mail_link(address, pattern):
    for _ in range(30):
        with urllib.request.urlopen(MAIL + "/api/v1/messages") as r:
            messages = json.load(r)["messages"]
        for m in messages:
            if any(t.get("Address") == address for t in m["To"]):
                with urllib.request.urlopen(MAIL + "/api/v1/message/" + m["ID"]) as r:
                    body = json.load(r)
                match = re.search(pattern, body.get("Text", ""))
                if match:
                    return match.group(0)
        time.sleep(0.2)
    raise AssertionError("Expected synthetic local email not found")

def register(client, address, name, password):
    client.call("/api/auth/register", "POST", {"name": name, "organization_name": "Fictional smoke workspace",
                "email": address, "password": password, "password_confirmation": password}, expect=201)
    user = client.call("/api/me")
    client.call("/api/files", expect=403)
    link = mail_link(address, r"http[^\s<>]+/api/auth/email/verify/[^\s<>]+")
    client.call(link, raw=True, expect=204)
    check("SMTP verification + signed link for " + name, bool(client.call("/api/me")["user"]["email_verified_at"]))
    return user["memberships"][0]["organization_id"], user["user"]["id"]

def sample_pdf():
    stream = b"BT /F1 12 Tf 40 740 Td (FICTIONAL SAMPLE ONLY - MortgageLens smoke test.) Tj ET"
    objects = [b"<< /Type /Catalog /Pages 2 0 R >>", b"<< /Type /Pages /Kids [3 0 R] /Count 1 >>",
               b"<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>",
               b"<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>",
               b"<< /Length " + str(len(stream)).encode() + b" >>\nstream\n" + stream + b"\nendstream"]
    pdf = b"%PDF-1.4\n"
    offsets = []
    for i, obj in enumerate(objects, 1):
        offsets.append(len(pdf))
        pdf += str(i).encode() + b" 0 obj\n" + obj + b"\nendobj\n"
    start = len(pdf)
    pdf += b"xref\n0 6\n0000000000 65535 f \n"
    pdf += b"".join(f"{o:010d} 00000 n \n".encode() for o in offsets)
    return pdf + f"trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n{start}\n%%EOF".encode()

def main():
    suffix = secrets.token_hex(5)
    owner_email, broker_email = f"owner-{suffix}@example.test", f"broker-{suffix}@example.test"
    password = secrets.token_urlsafe(24) + "9a"
    owner, broker = Client(), Client()
    try:
        owner.http.open(urllib.request.Request(BASE + "/api/auth/login", data=b"{}", headers={"Accept":"application/json","Content-Type":"application/json"}))
        raise AssertionError("CSRF forgery was accepted")
    except urllib.error.HTTPError as e:
        check("Cookie authentication rejects missing CSRF proof", e.code == 419)
    org, owner_id = register(owner, owner_email, "Synthetic Owner", password)
    _, broker_id = register(broker, broker_email, "Synthetic Broker", password)
    borrower = owner.call(f"/api/organizations/{org}/borrowers", "POST", {"name": "Fictional smoke borrower"}, expect=201)
    file = owner.call(f"/api/organizations/{org}/files", "POST", {"name": "Fictional three-module review", "borrower_id": borrower["id"], "module": "bank", "is_demo": True}, expect=201)
    endpoint = "/api/files/" + str(file["id"])
    check("Create borrower and loan file", bool(file["id"]))
    pdf = sample_pdf()
    boundary = "MortgageLens" + suffix
    body = (f"--{boundary}\r\nContent-Disposition: form-data; name=\"fictional_sample\"\r\n\r\n1\r\n--{boundary}\r\nContent-Disposition: form-data; name=\"file\"; filename=\"fictional.pdf\"\r\nContent-Type: application/pdf\r\n\r\n").encode() + pdf + f"\r\n--{boundary}--\r\n".encode()
    uploaded = owner.call(endpoint + "/documents", "POST", body, expect=201, content_type="multipart/form-data; boundary=" + boundary)
    version = uploaded["version"]["id"]
    check("Private upload exact pending status and no false clean", uploaded["message"] == "Uploaded — analysis integration pending." and uploaded["version"]["scan_status"] == "not_configured")
    check("Preserved original bytes", owner.call(f"/api/document-versions/{version}/content?download=1", raw=True) == pdf)
    broker.call(endpoint, expect=403)
    owner.call(f"/api/organizations/{org}/invitations", "POST", {"email": broker_email, "role": "broker"}, expect=201)
    invitation = mail_link(broker_email, r"http[^\s<>]+/invitations/accept\?token=[A-Za-z0-9]+")
    token = urllib.parse.parse_qs(urllib.parse.urlsplit(invitation).query)["token"][0]
    broker.call("/api/invitations/accept", "POST", {"token": token}, expect=201)
    broker.call(endpoint, expect=403)
    share = owner.call(endpoint + "/shares", "POST", {"user_id": broker_id}, expect=201)
    check("Invitation + explicit same-org file sharing", broker.call(endpoint)["id"] == file["id"])
    owner.call(endpoint + "/sample-analysis", "POST", {"idempotency_key": "smoke-v1"}, expect=202)
    for _ in range(50):
        analysis = owner.call(endpoint + "/analysis")["analysis"]
        if analysis:
            break
        time.sleep(0.2)
    check("Real Redis/Horizon sample job completed", bool(analysis and analysis["is_demo"]))
    for module in ["bank", "appraisal", "dscr"]:
        report = broker.call(endpoint + "/reports/" + module)
        check("Role-scoped " + module + " sample preview", report["title"].startswith("SAMPLE"))
    source = analysis["data"]["appraisal"]["findings"][0]["source"]
    check("Finding beside authenticated source boundary", owner.call("/api/document-versions/" + str(source["document_version_id"]) + "/content", raw=True).startswith(b"%PDF"))
    broker_analysis = broker.call(endpoint + "/analysis")
    check("Broker restricted fields absent", "sustainability" not in json.dumps(broker_analysis) and "typeface inconsistency" not in json.dumps(broker_analysis))
    broker.call(endpoint + "/analysis/restricted/integrity", expect=403)
    broker.call(endpoint + "/analysis/restricted/sustainability", expect=403)
    broker.call(endpoint + "/overrides", "POST", {"kind": "classification", "target_id": "tx-1", "value": "eligible_deposit", "reason": "Blocked broker attempt"}, expect=403)
    check("Broker cannot retrieve restricted data or override", True)
    owner.call(endpoint + "/shares/" + str(share["id"]), "DELETE", expect=204)
    for path in [endpoint, f"/api/document-versions/{version}/content", endpoint + "/reports/bank", endpoint + "/export"]:
        broker.call(path, expect=403)
    check("Revocation denies subsequent file/preview/report/export requests", True)
    broker.call("/api/auth/logout", "POST", expect=204)
    broker.call("/api/auth/forgot-password", "POST", {"email": broker_email})
    reset_link = mail_link(broker_email, r"http[^\s<>]+/reset-password\?[^\s<>]+")
    query = urllib.parse.parse_qs(urllib.parse.urlsplit(reset_link).query)
    new_password = secrets.token_urlsafe(24) + "9b"
    broker.call("/api/auth/reset-password", "POST", {"token": query["token"][0], "email": broker_email, "password": new_password, "password_confirmation": new_password})
    broker.call("/api/auth/login", "POST", {"email":broker_email,"password":new_password})
    check("Actual SMTP password reset + new-password login flow", bool(broker.call("/api/me")["user"]["email_verified_at"]))
    print(json.dumps({"result": "passed", "checks": len(checks), "transport": "HTTP + PostgreSQL + Redis/Horizon + SMTP/Mailpit", "visual_browser_test": "not performed"}))

if __name__ == "__main__":
    main()
