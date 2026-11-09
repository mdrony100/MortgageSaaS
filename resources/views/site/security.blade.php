@extends('site.layout')
@section('content')
@include('site.partials.page-header', ['eyebrow' => 'Security', 'heading' => 'How we protect mortgage documents', 'lead' => 'A summary of the controls built into the platform. Hosting-level controls depend on how each environment is deployed and are described as such.'])

@php
$controls = [
    ['Private document access', 'Uploaded documents are stored on non-public storage. The application refuses to start document storage on a public location, and files are served only through authenticated requests that check organization membership, role and file access.'],
    ['Malware scan gate', 'Original uploads are preserved unchanged and held in quarantine. Processing and viewing are blocked unless a malware scan reports the file clean; an unavailable scanner is never treated as clean.'],
    ['Role-based permissions', 'Owners, admins, underwriters, processors and brokers have distinct permissions. Brokers cannot see restricted integrity findings or override results.'],
    ['Organization (tenant) isolation', 'Every borrower, loan file and document belongs to one organization. Access requires membership in that organization plus file-level access; sharing a file never raises a member’s role. Platform administrators have no document bypass.'],
    ['Audit history', 'Key actions, such as sign-ins, uploads, shares, reviewer decisions and report downloads, are recorded. Audit and evidence records are append-only and protected against updates and deletion at the database level.'],
    ['Account security', 'Email verification, password reset, rate-limited sign-in and optional authenticator-app two-factor authentication.'],
    ['Encryption', 'Encryption in transit (HTTPS/TLS) is configured by the hosting deployment. Cached AI extraction results are encrypted with the application key. Storage-level encryption at rest depends on the configured storage and database provider.'],
    ['Retention and deletion', 'Original documents and issued reports are preserved. Retention periods have not yet been published; automated cleanup of temporary processing data runs in a review-only mode until a retention policy is approved.'],
];
@endphp

<section class="py-14 md:py-20" aria-labelledby="controls-heading">
    <div class="site-container">
        <h2 id="controls-heading" class="sr-only">Security controls</h2>
        <div class="grid gap-6 md:grid-cols-2">
            @foreach ($controls as [$t, $d])
                <article class="site-card"><h3 class="site-h3">{{ $t }}</h3><p class="mt-2 text-slate-700">{{ $d }}</p></article>
            @endforeach
        </div>
    </div>
</section>

<section class="border-t border-slate-200 bg-slate-50 py-14" aria-labelledby="processors-heading">
    <div class="site-container grid gap-8 lg:grid-cols-2">
        <div>
            <h2 id="processors-heading" class="site-h2">External processing providers</h2>
            <p class="mt-3 text-slate-700">Documents are processed by the platform’s own parser by default. If the platform operator enables optional AI-assisted extraction, page images from the relevant documents are sent to the configured AI provider (OpenAI) for extraction. This option is disabled unless it is explicitly configured and approved, and no other external document-processing provider is enabled by default.</p>
            <p class="mt-3 text-slate-700">For details on how personal information is handled, see our <a class="font-semibold text-accent-dark underline" href="{{ route('site.privacy') }}">Privacy Policy</a>.</p>
        </div>
        <div class="site-card">
            <h3 class="site-h3">Our approach to claims</h3>
            <p class="mt-2 text-slate-700">We only describe controls that are implemented. We do not claim third-party certifications, and integrity indicators are presented as potential issues for review rather than guaranteed fraud detection.</p>
            <p class="mt-3 text-slate-700">Security questions? <a class="font-semibold text-accent-dark underline" href="{{ route('site.contact') }}?type=general">Contact us</a>.</p>
        </div>
    </div>
</section>
@endsection
