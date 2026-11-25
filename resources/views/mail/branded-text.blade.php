{{ $emailCompany ?? 'Non-QM Doc AI' }}
{{ $emailHeading }}

@foreach ($emailLines ?? [] as $line)
{{ $line }}

@endforeach
@foreach ($emailDetails ?? [] as $row)
{{ $row['label'] }}: {{ $row['value'] }}
@endforeach
@if (! empty($emailActionUrl))

{{ $emailActionLabel ?: 'Open' }}: {{ $emailActionUrl }}
@endif

---
{{ $emailCompany ?? 'Non-QM Doc AI' }}
Questions? {{ $emailSupport ?? 'info@nonqmdoc.ai' }}
{{ $emailReason }}
