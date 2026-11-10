<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow">@include('partials.favicons')<title>Owner console unavailable · MortgageLens</title>
<style>
*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;background:#0f172a;color:#e2e8f0;font:16px/1.6 Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif;padding:24px}
main{max-width:520px;width:100%;background:#111c33;border:1px solid #27365a;border-radius:16px;padding:36px}
.tag{display:inline-block;font-size:13px;letter-spacing:.08em;text-transform:uppercase;color:#a5b4fc;font-weight:700}
h1{font-size:28px;line-height:1.25;margin:10px 0 12px;color:#fff}p{margin:0 0 16px;font-size:16px;color:#cbd5e1}
a{display:inline-flex;align-items:center;min-height:44px;padding:10px 18px;border-radius:10px;background:#6366f1;color:#fff;text-decoration:none;font-weight:600;margin:6px 8px 0 0}
a.secondary{background:transparent;border:1px solid #475569;color:#e2e8f0}a:focus-visible{outline:3px solid #fbbf24;outline-offset:3px}
</style></head><body><main>
<span class="tag">MortgageLens Platform · Owner Console</span>
<h1>@if($code==='two_factor_required') Two-factor sign-in required @elseif($code==='impersonating') Support session in progress @else Not authorized @endif</h1>
<p>{{ $message }}</p>
@if($code==='two_factor_required')
<p>Open <strong>My account</strong> in your workspace, turn on two-factor sign-in, confirm it with your authenticator app, then return here.</p>
<a href="/settings/profile">Set up two-factor sign-in</a><a class="secondary" href="/admin">Try again</a>
@elseif($code==='impersonating')
<a href="/dashboard">Back to the support session</a>
@else
<p>If you need access, ask a MortgageLens operator. Organization owner and admin roles do not include the owner console.</p>
<a href="/dashboard">Open customer workspace</a>
@endif
</main></body></html>
