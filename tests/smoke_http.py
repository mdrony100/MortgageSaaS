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

def sample_pdf(lines=None):
    lines = lines or ["FICTIONAL BANK STATEMENT - SYNTHETIC NATIVE HTTP TEST ONLY",
             "Institution: FICTIONAL Test Bank", "Holder: Fictional Example LLC", "Account: ****1111",
             "Currency: USD", "Designation: business", "Period start: 2025-01-01", "Period end: 2025-01-31",
             "Opening balance: 10000.00", "Closing balance: 11000.00", "Total credits: 1000.00",
             "Total debits: 0.00", "Fees: 0.00", "Fees separate: no",
             "Date | Description | Credit | Debit | Balance | Reference",
             "2025-01-02 | Merchant sales | 1000.00 | 0.00 | 11000.00 | TEST-M1"]
    escaped = [line.replace("\\", "\\\\").replace("(", "\\(").replace(")", "\\)") for line in lines]
    stream = ("BT /F1 8 Tf 10 TL 40 740 Td " + " T* ".join("(" + line + ") Tj" for line in escaped) + " ET").encode()
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
    check("Private upload quarantined and never falsely clean", uploaded["version"]["scan_status"] != "Clean")
    owner.call(f"/api/document-versions/{version}/content?download=1", expect=423)
    bank = endpoint + "/bank"
    policy = {"workflow":"business","eligible_categories":["business_revenue"],"expense_treatment":"remaining_percent",
              "remaining_percent":"50.00","ownership_required":True,"fixed_ownership_percent":None,"rounding":"HALF_UP",
              "required_evidence":["full_selected_months","verified_accounts","reviewed_transactions"],"allow_provisional":True,
              "approval_reference":None,"require_external_integrity":True,"require_no_open_findings":True,"approved_thresholds":False,
              "nsf_counting_mode":"reference_per_day","nsf_convention":"TEST ONLY explicit non-fee rows"}
    rule = owner.call(bank + "/rules", "POST", {"name":"FICTIONAL HTTP example, not lender policy", "status":"example", "policy":policy}, expect=201)
    queued = owner.call(bank + "/runs", "POST", {"idempotency_key":"native-http-"+suffix,"document_version_ids":[version],
                       "workflow":"business","months":12,"end_month":"2025-01","rule_id":rule["id"]}, expect=202)
    for _ in range(100):
        state = owner.call(bank)
        if state["run"]["status"] in ["Completed","PartiallyCompleted","NeedsReview","Failed"]:
            break
        time.sleep(0.2)
    check("Actual Redis/Horizon bank job + Python native extraction finished", state["run"]["status"] == "PartiallyCompleted" and state["run"]["attempts"] > 0 and len(state["transactions"]) == 1)
    check("Native statement reconciled without invented data", not any(f["data"]["category"] in ["financial_inconsistency","extraction_uncertainty"] for f in state["findings"]))
    check("Preserved exact original bytes after explicit fictional scan", owner.call(f"/api/document-versions/{version}/content?download=1", raw=True) == pdf)
    run = bank + "/runs/" + queued["run_id"]
    account, tx = state["accounts"][0], state["transactions"][0]
    owner.call(run + "/reviews", "POST", {"kind":"account","account_key":account["account_key"],"designation":"business","verified":True,"reason_code":"source_verified","notes":"TEST actual source account checked"}, expect=201)
    owner.call(run + "/reviews", "POST", {"kind":"transaction","transaction_ids":[tx["id"]],"category":"business_revenue","include":None,"reason_code":"source_verified","notes":"TEST source classification checked"}, expect=201)
    calculation = owner.call(run + "/calculate", "POST", {"account_keys":[account["account_key"]],"ownership_percent":"100.00","reason_code":"source_verified","notes":"TEST financial inputs confirmed against fictional source"}, expect=201)
    check("Observed-only provisional numerator retains exact 12-month denominator", calculation["data"]["status"] == "provisional" and calculation["data"]["monthly_display"] == "41.67")
    issued = owner.call(run + "/reports", "POST", {"calculation_id":calculation["id"],"final":False}, expect=201)[0]
    for fmt, magic in [("xlsx",b"PK"),("pdf",b"%PDF-"),("csv",b'"Transaction ID"')]:
        check("Actual native workflow downloadable " + fmt.upper(), owner.call(run + "/reports/"+str(issued["id"])+"/"+fmt, raw=True).startswith(magic))
    owner.call(run + "/conditions", "POST", {"text":"Please provide the missing statement months.","status":"open","public_acknowledgement":True}, expect=201)
    # Phase3 uses the SAME upload/scanner/source/file system and real native queue.
    appraisal_bytes = sample_pdf(["FICTIONAL APPRAISAL HTTP ONLY", "classification | | appraisal", "form | | 1004", "form_version | | 2005", "address | | 1 Fictional Example Street", "units | | 1", "appraised_value | | 330000.00"])
    appraisal_body = (f"--{boundary}\r\nContent-Disposition: form-data; name=\"fictional_sample\"\r\n\r\n1\r\n--{boundary}\r\nContent-Disposition: form-data; name=\"file\"; filename=\"FICTIONAL-appraisal.pdf\"\r\nContent-Type: application/pdf\r\n\r\n").encode()+appraisal_bytes+f"\r\n--{boundary}--\r\n".encode()
    appraisal_version=owner.call(endpoint+"/documents","POST",appraisal_body,expect=201,content_type="multipart/form-data; boundary="+boundary)["version"]["id"]
    review=endpoint+"/appraisal"
    rq=owner.call(review+"/runs","POST",{"document_version_ids":[appraisal_version],"context":{"loan_occupancy":"primary","transaction":"purchase","units":1},"idempotency_key":"review-http-"+suffix},expect=202)
    for _ in range(100):
        rs=owner.call(review)
        if rs.get("run",{}).get("status") in ["NeedsReview","Failed"]:break
        time.sleep(.2)
    check("Actual appraisal Redis/Horizon native processing, no fixture extractor",rs["run"]["status"]=="NeedsReview" and len(rs["facts"])>=5)
    def confirmed(value):return {"value":value,"source":"FICTIONAL source, reviewer verified","basis":"TEST explicit reviewer choice","confirmed":True}
    ri={"units":[{"id":"1","inspection_occupancy":"unknown","current_occupancy":"occupied","lease_status":"executed","rent_method":"long_term","is_adu":False,"market":confirmed("3300.00"),"qualifying":confirmed("2850.00")}],"payment":{"mode":"total","total":confirmed("3000.00")},"loan":confirmed("250000.00"),"value_basis":confirmed("325000.00"),"appraised_value":"330000.00"}
    rp=review+"/runs/"+rq["run_id"]
    rc=owner.call(rp+"/inputs","POST",{"input":ri,"notes":"TEST financial source confirmation"},expect=201)
    check("Real backend market1.10 / qualifying0.95 / LTV76.92",rc["data"]["market_dscr"]["display"]=="1.10" and rc["data"]["qualifying_dscr"]["display"]=="0.95" and rc["data"]["ltv"]["display_percent"]=="76.92")
    report=owner.call(rp+"/report","POST",{"notes":"TEST review report with open findings"},expect=201)
    for fmt,magic in [("pdf",b"%PDF-"),("xlsx",b"PK")]:check("Actual appraisal open-findings "+fmt.upper()+" export",owner.call(rp+"/reports/"+str(report["id"])+"/"+fmt,raw=True).startswith(magic))
    check("Unconfigured billing never fabricates checkout",owner.call(f"/api/organizations/{org}/billing")["provider"]=="disabled")
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
    for module in ["appraisal", "dscr"]:
        report = broker.call(endpoint + "/reports/" + module)
        check("Role-scoped " + module + " sample preview", report["title"].startswith("SAMPLE"))
    broker.call(endpoint + "/reports/bank", expect=403)
    broker.call(run + "/reports/"+str(issued["id"])+"/pdf", expect=403)
    broker.call(f"/api/document-versions/{version}/content", expect=403)
    public = broker.call(bank)
    check("Broker bank API is final-only and contains deliberate public conditions", public["final_income"] is None and len(public["conditions"]) == 1 and "sustainability" not in json.dumps(public) and "transactions" not in public)
    broker.call(bank + "/messages", "POST", {"text":"Fictional review request only.","kind":"review_request"}, expect=201)
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
    print(json.dumps({"result": "passed", "checks": len(checks), "transport": "HTTP + PostgreSQL + Redis/Horizon bank and default queues + Python native parser + real exports + SMTP/Mailpit", "visual_browser_test": "not performed"}))

if __name__ == "__main__":
    main()
