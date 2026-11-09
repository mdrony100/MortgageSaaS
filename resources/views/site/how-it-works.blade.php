@extends('site.layout')
@section('content')
@include('site.partials.page-header', ['eyebrow' => 'How It Works', 'heading' => 'Five steps from documents to authorized reports', 'lead' => 'A consistent process for every loan file, with people making the decisions at each review point.'])

@php
$steps = [
    ['Create an organization and invite your team', 'Set up your organization, then invite colleagues by email and assign each person a role. Members only see the loan files their role and file access allow.', 'Example: “Sample Lending Group” invites a processor and an underwriter.'],
    ['Create a file and securely upload supported documents', 'Create a loan file and upload bank statements, appraisal reports, leases or supporting documents as PDF, PNG or JPG. Originals are preserved unchanged and held in quarantine until a malware scan passes.', 'Example: a fictional file with 24 monthly statements and a Form 1025 appraisal.'],
    ['Run document analysis', 'Start bank statement or appraisal analysis. Documents are parsed, reconciled and checked for completeness, and potential issues are recorded as findings.', 'Example: the analysis detects that printed page 4 of 6 is missing.'],
    ['Review extracted information, calculations and flagged issues', 'Reviewers confirm deposit classifications, transfers, rents and other inputs, and record a decision with a reason on each finding. Calculations use only confirmed inputs and stay provisional while information is missing.', 'Example: an underwriter confirms a transfer between the borrower’s own accounts and excludes it from income.'],
    ['Export and share authorized reports', 'Issue versioned Excel and PDF reports. Report access follows each member’s role; Broker-facing reports exclude restricted internal findings.', 'Example: an internal report for the underwriting team and a separate Broker report.'],
];
@endphp

<section class="py-14 md:py-20" aria-labelledby="steps-heading">
    <div class="site-container">
        <h2 id="steps-heading" class="sr-only">Steps</h2>
        <ol class="relative grid gap-8">
            @foreach ($steps as $i => [$t, $d, $example])
                <li class="site-card grid gap-4 md:grid-cols-[auto_1fr]">
                    <span class="inline-flex h-11 w-11 items-center justify-center rounded-full bg-accent text-lg font-bold text-white" aria-hidden="true">{{ $i + 1 }}</span>
                    <div>
                        <h3 class="site-h3 text-xl"><span class="sr-only">Step {{ $i + 1 }}: </span>{{ $t }}</h3>
                        <p class="mt-2 text-slate-700">{{ $d }}</p>
                        <p class="mt-3 rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-600"><span class="site-badge site-badge-amber mr-2">FICTIONAL</span>{{ $example }}</p>
                    </div>
                </li>
            @endforeach
        </ol>
    </div>
</section>

<section class="pb-16" aria-labelledby="judgment-heading">
    <div class="site-container">
        <div class="rounded-2xl border border-accent/30 bg-accent-tint p-6 md:p-8">
            <h2 id="judgment-heading" class="site-h3 text-xl">Professional review stays in charge</h2>
            <p class="mt-2 max-w-3xl text-slate-700">{{ \App\Website\Site::name() }} supports professional review. It does not issue lender approvals, make lending decisions or replace underwriting judgment. Program and investor guidelines must still be verified by your team.</p>
        </div>
    </div>
</section>

@include('site.partials.cta')
@endsection
