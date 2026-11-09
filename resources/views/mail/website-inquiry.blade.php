A new website inquiry was submitted.

Type: {{ $typeLabel }}
Name: {!! $inquiry->name !!}
Work email: {!! $inquiry->email !!}
Company: {!! $inquiry->company !!}
Role: {!! $inquiry->role ?: 'Not provided' !!}
Received: {{ $inquiry->created_at?->toIso8601String() }}

Message:
{!! $inquiry->message !!}

Review and update this inquiry in Website Inquiries (platform staff only):
{{ $adminUrl }}
