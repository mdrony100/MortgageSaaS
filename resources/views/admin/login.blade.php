<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
@include('partials.favicons')
<title>Owner console sign-in · Non-QM Doc AI</title>
<style>
*{box-sizing:border-box}
body{margin:0;min-height:100vh;display:grid;place-items:center;background:#0f172a;color:#e2e8f0;font:16px/1.5 Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif;padding:24px}
main{width:min(440px,100%);background:#111c33;border:1px solid #27365a;border-radius:16px;padding:32px 28px}
.brand{display:flex;align-items:center;gap:12px;margin:0 0 18px;color:#fff;text-decoration:none}
.brand img{width:42px;height:42px;object-fit:contain;flex:none}
.brand strong{display:block;font-size:18px;line-height:1.2}
.brand small{display:block;margin-top:2px;font-size:13px;letter-spacing:.12em;text-transform:uppercase;color:#a5b4fc;font-weight:700}
h1{margin:0 0 8px;font-size:clamp(26px,4vw,30px);line-height:1.25;color:#fff}
.lead{margin:0 0 18px;font-size:16px;color:#cbd5e1}
form{display:flex;flex-direction:column;gap:14px}
label{display:flex;flex-direction:column;gap:6px;font-size:15px;font-weight:650;color:#e2e8f0}
input{font:inherit;font-size:16px;font-weight:400;color:#0f172a;background:#fff;border:1px solid #cbd5e1;border-radius:10px;padding:10px 12px;min-height:44px;width:100%}
input:focus{outline:3px solid #c7d2fe;border-color:#6366f1}
button{min-height:44px;border-radius:10px;border:1px solid #4f46e5;background:#4f46e5;color:#fff;font:inherit;font-size:16px;font-weight:700;cursor:pointer}
button:hover{background:#4338ca}
button:focus-visible,a:focus-visible{outline:3px solid #fbbf24;outline-offset:3px}
.alert{margin:0 0 16px;padding:12px 14px;border-radius:10px;background:#fef2f2;border:1px solid #fecaca;color:#991b1b;font-size:16px}
.links{display:flex;flex-wrap:wrap;justify-content:space-between;gap:8px 16px;margin-top:18px}
a{color:#c7d2fe;font-size:15px;font-weight:650}
.note{margin:16px 0 0;font-size:14px;color:#94a3b8}
@media(max-width:480px){body{padding:16px}main{padding:24px 18px}}
</style>
</head>
<body>
<main>
<a class="brand" href="/">
<img src="/images/brand/non-qm-doc-icon.png" alt="" width="42" height="42">
<span><strong>Non-QM Doc AI</strong><small>Owner console</small></span>
</a>
<h1>Sign in</h1>
<p class="lead">Platform staff only. Customer accounts stay on the workspace sign-in.</p>
@if($disabled)
<p class="alert" role="alert">This account has been disabled. Contact your administrator.</p>
@endif
@if($customerSession)
<p class="alert" role="alert">You are signed in with a customer account. The owner console did not open. Sign in with a platform administrator account, or return to the workspace.</p>
@endif
@if($errors->any())
<p class="alert" role="alert">{{ $errors->first() }}</p>
@endif
<form method="post" action="{{ route('admin.login.store') }}">
@csrf
<input type="hidden" name="redirect" value="{{ $redirectTo }}">
<label>Email address<input type="email" name="email" value="{{ old('email') }}" required autocomplete="username" maxlength="255" autofocus></label>
<label>Password<input type="password" name="password" required autocomplete="current-password"></label>
<button class="primary" type="submit">Sign in to owner console</button>
</form>
<div class="links">
<a href="/login">Customer workspace sign-in</a>
<a href="/forgot-password">Forgot password?</a>
</div>
<p class="note">Organization owner and admin roles do not include the owner console.</p>
</main>
</body>
</html>
