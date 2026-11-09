@extends('site.layout')
@section('content')
<section class="overflow-hidden bg-gradient-to-b from-slate-50 to-white">
    <div class="site-container grid items-center gap-12 py-16 md:py-24 lg:grid-cols-[1.05fr_1fr]">
        <div>
            <p class="site-eyebrow">For brokers, loan officers, processors and underwriting teams</p>
            <h1 class="site-h1 mt-4">Turn mortgage documents into clear, reviewable insights.</h1>
            <p class="site-lead mt-5 max-w-xl">Analyze bank statements, review appraisal findings, and prepare rental income calculations—with supporting evidence and reviewer control.</p>
            <div class="mt-8 flex flex-wrap gap-3">
                <a href="{{ route('site.contact') }}?type=demo" class="site-btn site-btn-primary">Request a Demo</a>
                <a href="{{ route('site.features') }}" class="site-btn site-btn-ghost">Explore Features</a>
            </div>
            <p class="mt-6 text-sm text-slate-600">Supports professional review. It does not make lending decisions.</p>
        </div>
        @include('site.partials.preview')
    </div>
</section>

<section class="py-16 md:py-24" aria-labelledby="modules-heading">
    <div class="site-container">
        <p class="site-eyebrow">Product modules</p>
        <h2 id="modules-heading" class="site-h2 mt-2 max-w-2xl">Three review modules in one shared workspace</h2>
        <div class="mt-10 grid gap-6 md:grid-cols-3">
            <article class="site-card site-card-hover">
                <h3 class="site-h3">Bank Statement Income Analysis</h3>
                <p class="mt-2 text-slate-600">Review 12 or 24 months of statements, classify deposits, identify potential transfers and calculate income using your configured rules.</p>
                <a class="mt-4 inline-block font-semibold text-accent-dark underline-offset-4 hover:underline" href="{{ route('site.features') }}#bank-statements">Bank statement features<span class="sr-only"> — Bank Statement Income Analysis</span></a>
            </article>
            <article class="site-card site-card-hover">
                <h3 class="site-h3">Document Integrity and Fraud Indicators</h3>
                <p class="mt-2 text-slate-600">Surface reconciliation discrepancies, potential missing or duplicate pages and document consistency indicators as potential issues for human review.</p>
                <a class="mt-4 inline-block font-semibold text-accent-dark underline-offset-4 hover:underline" href="{{ route('site.features') }}#document-integrity">Integrity features<span class="sr-only"> — Document Integrity and Fraud Indicators</span></a>
            </article>
            <article class="site-card site-card-hover">
                <h3 class="site-h3">Appraisal Review and Rental/DSCR Analysis</h3>
                <p class="mt-2 text-slate-600">Review forms 1004, 1025, 1007 and 216 for 1–4 unit residential properties, compare rents and calculate DSCR from reviewer-confirmed inputs.</p>
                <a class="mt-4 inline-block font-semibold text-accent-dark underline-offset-4 hover:underline" href="{{ route('site.features') }}#appraisal-review">Appraisal and DSCR features<span class="sr-only"> — Appraisal Review and Rental/DSCR Analysis</span></a>
            </article>
        </div>
    </div>
</section>

<section class="border-y border-slate-200 bg-slate-50 py-16 md:py-24" aria-labelledby="workflow-heading">
    <div class="site-container">
        <p class="site-eyebrow">Workflow</p>
        <h2 id="workflow-heading" class="site-h2 mt-2">From upload to export, every step stays reviewable</h2>
        <ol class="mt-10 grid gap-6 md:grid-cols-4">
            @foreach ([['Upload documents', 'Upload PDF, PNG or JPG files to a loan file. Originals are preserved and held until a malware scan passes.'], ['Extract and validate', 'Statements and appraisal documents are parsed, reconciled and checked for completeness.'], ['Review findings', 'Reviewers confirm classifications, transfers and rents and record decisions on flagged issues.'], ['Export reports', 'Issue versioned Excel and PDF reports with access controlled by role.']] as $i => [$t, $d])
                <li class="site-card">
                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-accent text-sm font-bold text-white" aria-hidden="true">{{ $i + 1 }}</span>
                    <h3 class="site-h3 mt-3"><span class="sr-only">Step {{ $i + 1 }}: </span>{{ $t }}</h3>
                    <p class="mt-2 text-sm text-slate-600">{{ $d }}</p>
                </li>
            @endforeach
        </ol>
        <a href="{{ route('site.how') }}" class="mt-8 inline-block font-semibold text-accent-dark underline-offset-4 hover:underline">See how it works</a>
    </div>
</section>

<section class="py-16 md:py-24" aria-labelledby="team-heading">
    <div class="site-container grid gap-12 lg:grid-cols-2">
        <div>
            <p class="site-eyebrow">Collaboration</p>
            <h2 id="team-heading" class="site-h2 mt-2">Built for teams, with permissions and history you can trace</h2>
            <p class="site-lead mt-4">Brokers, processors and underwriters work on the same file, each seeing only what their role and file access allow.</p>
        </div>
        <ul class="grid gap-4 text-slate-700">
            <li class="site-check"><span><strong class="text-ink">Role-based access.</strong> Owners, admins, underwriters, processors and brokers have distinct permissions.</span></li>
            <li class="site-check"><span><strong class="text-ink">File sharing without role elevation.</strong> Share a loan file with a teammate; sharing never raises their role.</span></li>
            <li class="site-check"><span><strong class="text-ink">Append-only audit history.</strong> Uploads, file shares, reviewer decisions and report downloads are recorded in history that cannot be edited afterwards.</span></li>
            <li class="site-check"><span><strong class="text-ink">Controlled report access.</strong> Broker-facing views and reports exclude restricted internal findings.</span></li>
        </ul>
    </div>
</section>

<section class="border-y border-slate-200 bg-slate-50 py-16 md:py-24" aria-labelledby="security-heading">
    <div class="site-container">
        <p class="site-eyebrow">Security overview</p>
        <h2 id="security-heading" class="site-h2 mt-2 max-w-2xl">Controls designed around sensitive borrower documents</h2>
        <div class="mt-10 grid gap-6 md:grid-cols-3">
            <div class="site-card"><h3 class="site-h3">Private document storage</h3><p class="mt-2 text-sm text-slate-600">Documents are stored on non-public storage and are only served through authenticated, permission-checked requests.</p></div>
            <div class="site-card"><h3 class="site-h3">Organization isolation</h3><p class="mt-2 text-sm text-slate-600">Every loan file belongs to one organization; access requires membership plus file-level permission.</p></div>
            <div class="site-card"><h3 class="site-h3">Two-factor sign-in</h3><p class="mt-2 text-sm text-slate-600">Users can protect their accounts with authenticator-app two-factor authentication.</p></div>
        </div>
        <a href="{{ route('site.security') }}" class="mt-8 inline-block font-semibold text-accent-dark underline-offset-4 hover:underline">Read the security overview</a>
    </div>
</section>

<section class="py-16 md:py-24" aria-labelledby="faq-heading">
    <div class="site-container grid gap-10 lg:grid-cols-[1fr_1.6fr]">
        <div>
            <p class="site-eyebrow">FAQ</p>
            <h2 id="faq-heading" class="site-h2 mt-2">Common questions</h2>
            <a href="{{ route('site.faq') }}" class="mt-4 inline-block font-semibold text-accent-dark underline-offset-4 hover:underline">View all questions</a>
        </div>
        @include('site.partials.faq-list', ['items' => array_slice(\App\Website\Site::faq(), 0, 4)])
    </div>
</section>

@include('site.partials.cta')
@endsection
