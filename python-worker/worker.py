"""Phase 2 document worker boundary. No PDF processing, AI, or queue consumption."""
import json
from http.server import BaseHTTPRequestHandler, HTTPServer

class Health(BaseHTTPRequestHandler):
    def do_GET(self):
        status = 200 if self.path == "/health" else 404
        self.send_response(status)
        self.send_header("Content-Type", "application/json")
        self.end_headers()
        self.wfile.write(json.dumps({"status": "disabled", "processing_enabled": False,
                                    "reason": "Phase 1 skeleton only"}).encode())

if __name__ == "__main__":
    HTTPServer(("0.0.0.0", 8081), Health).serve_forever()
