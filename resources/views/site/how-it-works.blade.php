@extends('site.layout')
@section('content')
@include('site.partials.page-header', ['eyebrow' => 'How It Works', 'heading' => 'Five steps from documents to reports you can share', 'lead' => 'The same clear process on every loan file, with people making the decisions at each review point.'])

@php
$steps = [
    ['Create an organization and invite your team', 'Set up your organization, invite colleagues by email and give each person a role. People only see the files their role and file access allow.', 'Example: “Sample Lending Group” invites a processor and an underwriter.'],
    ['Create a file and securely upload supported documents', 'Create a loan file and upload bank statements, appraisal reports, leases or supporting documents as PDF, PNG or JPG. Originals are kept unchanged and held until a malware scan passes.', 'Example: a fictional file with 24 monthly statements and a Form 1025 appraisal.'],
    ['Run document analysis', 'Start bank statement or appraisal analysis. Documents are read, totals are checked, and anything that needs a closer look is listed as a finding.', 'Example: the analysis notices that printed page 4 of 6 is missing.'],
    ['Review extracted information, calculations and flagged issues', 'Reviewers confirm deposit types, transfers, rents and other inputs, and record a decision with a reason on each finding. Calculations use only confirmed inputs and stay provisional while information is missing.', 'Example: an underwriter confirms a transfer between the borrower’s own accounts and leaves it out of income.'],
    ['Export and share authorized reports', 'Issue versioned Excel and PDF reports. Report access follows each person’s role; broker reports leave out restricted internal findings.', 'Example: an internal report for the underwriting team and a separate broker report.'],
];
@endphp

<section class="site-section" aria-labelledby="steps-heading">
    <div class="site-container">
        <h2 id="steps-heading" class="sr-only">Steps</h2>
        <ol class="relative grid gap-6 before:absolute before:bottom-8 before:left-[1.6rem] before:top-8 before:w-0.5 before:bg-teal-200 md:before:left-[1.85rem]">
            @foreach ($steps as $i => [$t, $d, $example])
                <li class="relative grid gap-4 md:grid-cols-[auto_1fr] md:gap-8" data-reveal>
                    <span class="relative z-10 inline-flex h-[3.25rem] w-[3.25rem] items-center justify-center rounded-full bg-accent text-xl font-bold text-white shadow-lg shadow-teal-900/20 md:h-[3.75rem] md:w-[3.75rem]" aria-hidden="true">{{ $i + 1 }}</span>
                    <div class="site-card site-card-hover">
                        <h3 class="site-h3"><span class="sr-only">Step {{ $i + 1 }}: </span>{{ $t }}</h3>
                        <p class="site-copy mt-3">{{ $d }}</p>
                        <p class="mt-4 flex flex-wrap items-center gap-2 rounded-xl bg-tint px-4 py-3 text-body"><span class="site-badge site-badge-amber">Fictional</span>{{ $example }}</p>
                    </div>
                </li>
            @endforeach
        </ol>
        <div class="mt-12 flex flex-wrap gap-3">
            <a href="{{ route('site.home') }}#walkthrough" class="site-btn site-btn-ghost">Try the interactive walkthrough</a>
        </div>
    </div>
</section>

<section class="site-section site-section-tint site-section-line" aria-labelledby="judgment-heading">
    <div class="site-container">
        <div class="max-w-3xl">
            <h2 id="judgment-heading" class="site-h2">Professional review stays in charge</h2>
            <p class="site-lead mt-4">{{ \App\Website\Site::name() }} supports professional review. It does not issue lender approvals, make lending decisions or replace underwriting judgment. Program and investor guidelines must still be verified by your team.</p>
        </div>
    </div>
</section>

@include('site.partials.cta')
@endsection
