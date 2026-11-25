@php
    $emailLines = $emailLines ?? [];
    $emailDetails = $emailDetails ?? [];
    $emailLogoUrl = $emailLogoUrl ?? 'https://nonqmdoc.ai/images/brand/non-qm-doc-logo.png';
    $emailSupport = $emailSupport ?? 'info@nonqmdoc.ai';
    $emailCompany = $emailCompany ?? 'Non-QM Doc AI';
    $emailReason = $emailReason ?? 'You received this email because of activity on your Non-QM Doc AI account.';
    $emailPreheader = $emailPreheader ?? ($emailHeading ?? 'Non-QM Doc AI');
@endphp
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta name="color-scheme" content="light dark">
<meta name="supported-color-schemes" content="light dark">
<title>{{ $emailHeading ?? 'Non-QM Doc AI' }}</title>
<style>
@media only screen and (max-width: 620px) {
  .email-shell { width: 100% !important; }
  .email-pad { padding-left: 20px !important; padding-right: 20px !important; }
  .email-button { width: 100% !important; }
}
@media (prefers-color-scheme: dark) {
  .email-bg { background: #0e1a28 !important; }
  .email-card { background: #163049 !important; }
  .email-heading, .email-text, .email-detail { color: #f4f7fb !important; }
  .email-muted, .email-footer-text { color: #c5d4e4 !important; }
  .email-rule { border-color: #2a4664 !important; }
}
</style>
</head>
<body class="email-bg" style="margin:0;padding:0;background:#e7eef3;color:#24364d;-webkit-text-size-adjust:100%;">
<div style="display:none;max-height:0;overflow:hidden;mso-hide:all;font-size:1px;line-height:1px;color:#e7eef3;">{{ $emailPreheader }}</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" class="email-bg" bgcolor="#e7eef3" style="background:#e7eef3;margin:0;padding:0;width:100%;">
<tr>
<td align="center" style="padding:24px 12px;">
<table role="presentation" class="email-shell" width="600" cellpadding="0" cellspacing="0" style="width:600px;max-width:600px;border-collapse:separate;">
<tr>
<td class="email-header" bgcolor="#102840" align="center" style="background:#102840;padding:28px 24px 22px;border-radius:16px 16px 0 0;">
<a href="https://nonqmdoc.ai" style="text-decoration:none;display:inline-block;">
<img src="{{ $emailLogoUrl }}" width="220" alt="Non-QM Doc AI" style="display:block;border:0;outline:none;text-decoration:none;width:220px;max-width:220px;height:auto;margin:0 auto;">
</a>
</td>
</tr>
<tr>
<td bgcolor="#22c6e6" style="background:#22c6e6;height:4px;line-height:4px;font-size:0;">&nbsp;</td>
</tr>
<tr>
<td class="email-card email-pad" bgcolor="#ffffff" style="background:#ffffff;padding:32px 36px 28px;">
<h1 class="email-heading" style="margin:0 0 16px;font-family:Segoe UI,Roboto,Helvetica,Arial,sans-serif;font-size:24px;line-height:1.3;font-weight:700;color:#102840;">{{ $emailHeading }}</h1>
@foreach ($emailLines as $line)
<p class="email-text" style="margin:0 0 14px;font-family:Segoe UI,Roboto,Helvetica,Arial,sans-serif;font-size:16px;line-height:1.6;color:#24364d;">{{ $line }}</p>
@endforeach
@if ($emailDetails !== [])
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:8px 0 18px;border-collapse:collapse;">
@foreach ($emailDetails as $row)
<tr>
<td class="email-muted email-rule" valign="top" style="padding:10px 12px 10px 0;border-bottom:1px solid #e2e8f0;font-family:Segoe UI,Roboto,Helvetica,Arial,sans-serif;font-size:13px;line-height:1.4;color:#5c6e82;width:38%;">{{ $row['label'] }}</td>
<td class="email-detail email-rule" valign="top" style="padding:10px 0;border-bottom:1px solid #e2e8f0;font-family:Segoe UI,Roboto,Helvetica,Arial,sans-serif;font-size:15px;line-height:1.4;color:#102840;">{{ $row['value'] }}</td>
</tr>
@endforeach
</table>
@endif
@if (! empty($emailActionUrl) && ! empty($emailActionLabel))
<table role="presentation" cellpadding="0" cellspacing="0" style="margin:8px 0 6px;">
<tr>
<td class="email-button" bgcolor="#22c6e6" align="center" style="background:#22c6e6;border-radius:8px;">
<!--[if mso]>
<v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="{{ $emailActionUrl }}" style="height:48px;v-text-anchor:middle;width:260px;" arcsize="14%" strokecolor="#22c6e6" fillcolor="#22c6e6">
<w:anchorlock/>
<center style="color:#102840;font-family:Segoe UI,sans-serif;font-size:16px;font-weight:bold;">{{ $emailActionLabel }}</center>
</v:roundrect>
<![endif]-->
<!--[if !mso]><!-->
<a href="{{ $emailActionUrl }}" style="display:inline-block;padding:14px 28px;font-family:Segoe UI,Roboto,Helvetica,Arial,sans-serif;font-size:16px;line-height:1.2;font-weight:700;color:#102840;text-decoration:none;border-radius:8px;">{{ $emailActionLabel }}</a>
<!--<![endif]-->
</td>
</tr>
</table>
<p class="email-muted" style="margin:14px 0 0;font-family:Segoe UI,Roboto,Helvetica,Arial,sans-serif;font-size:13px;line-height:1.5;color:#5c6e82;">If the button does not open, copy this link into your browser:<br><a href="{{ $emailActionUrl }}" style="color:#0e7490;word-break:break-all;">{{ $emailActionUrl }}</a></p>
@endif
</td>
</tr>
<tr>
<td class="email-bg email-pad" bgcolor="#e7eef3" style="background:#e7eef3;padding:22px 36px 28px;border-radius:0 0 16px 16px;">
<p class="email-footer-text" style="margin:0 0 6px;font-family:Segoe UI,Roboto,Helvetica,Arial,sans-serif;font-size:13px;line-height:1.5;color:#5c6e82;"><strong style="color:#102840;">{{ $emailCompany }}</strong></p>
<p class="email-footer-text" style="margin:0 0 8px;font-family:Segoe UI,Roboto,Helvetica,Arial,sans-serif;font-size:13px;line-height:1.5;color:#5c6e82;">Questions? Email <a href="mailto:{{ $emailSupport }}" style="color:#0e7490;text-decoration:underline;">{{ $emailSupport }}</a></p>
<p class="email-footer-text" style="margin:0;font-family:Segoe UI,Roboto,Helvetica,Arial,sans-serif;font-size:12px;line-height:1.5;color:#5c6e82;">{{ $emailReason }}</p>
</td>
</tr>
</table>
</td>
</tr>
</table>
</body>
</html>
