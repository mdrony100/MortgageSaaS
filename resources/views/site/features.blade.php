@extends('site.layout')
@section('content')
@include('site.partials.page-header', ['eyebrow' => 'Features', 'heading' => 'Everything your review team needs, backed by the documents', 'lead' => 'Each feature below is available in the platform today. Results support your team’s review; they are not lending decisions.'])

@php
$sections = [
    ['bank-statements', 'Bank statements', 'Bank Statement Analysis', 'Turn 12 or 24 months of statements into an income summary you can trace back to each deposit.', [
        'Review 12 or 24 months of personal or business statements.',
        'Classify deposits and review exclusions such as transfers, refunds and loan proceeds.',
        'Identify potential transfers between accounts and overlapping statements that could count a deposit twice.',
        'Calculate income using your organization’s versioned, approved rule settings.',
        'Review NSF and overdraft activity alongside income.',
        'Open the source page behind extracted values.',
        'Export versioned reports to Excel (XLSX), CSV and PDF.',
    ], 'bank'],
    ['document-integrity', 'Document checks', 'Document Integrity', 'Surface potential issues early so reviewers can ask for the right documents. These are potential issues requiring review, not proof of fraud or alteration.', [
        'Spot totals that don’t match: reported balances and totals compared with the transactions.',
        'Identify potential missing, out-of-order or duplicate pages from page numbers and page fingerprints.',
        'Surface available document consistency indicators, such as PDF structure and font variation observations.',
        'Review each finding beside its evidence and record a decision with a reason.',
    ], 'checks'],
    ['appraisal-review', 'Appraisal review', 'Appraisal Review', 'Check appraisal reports for completeness and compare them with the rest of the file.', [
        'Supports forms 1004, 1025, 1007 and 216.',
        'Focused on residential properties with 1–4 units; anything outside that scope is flagged for manual review.',
        'Review document completeness with links to supporting evidence on the page.',
        'Compare appraisal information with supporting documents such as leases and purchase contracts.',
        'Identify potentially reused photos using image fingerprints, for reviewer confirmation.',
        'Track findings and revisions across report versions.',
    ], 'appraisal'],
    ['rental-dscr', 'Rental income', 'Rental Income and DSCR', 'Prepare rental income and DSCR from inputs a reviewer has confirmed.', [
        'Review leases and unit-level rental information.',
        'Compare market rent with reviewer-confirmed qualifying rent for each unit.',
        'Calculate DSCR from confirmed rent and payment inputs, with the same result every time.',
        'See exactly which information is missing and when results are provisional.',
    ], 'rental'],
    ['team-workflow', 'Teamwork', 'Team Workflow', 'Keep everyone on the same file without over-sharing.', [
        'Role-based access for owners, admins, underwriters, processors and brokers.',
        'Share individual loan files and collaborate with shared notes.',
        'Review history that records who changed what, and why.',
        'Controlled report access, with broker reports that exclude restricted findings.',
    ], 'team'],
];
@endphp

<nav aria-label="Feature sections" class="border-b border-slate-200 bg-white">
    <ul class="site-container flex flex-wrap gap-2 py-4">
        @foreach ($sections as [$id, $short])
            <li><a href="#{{ $id }}" class="site-nav-link border border-slate-200">{{ $short }}</a></li>
        @endforeach
    </ul>
</nav>

@foreach ($sections as $i => [$id, $short, $title, $lead, $points, $visual])
<section id="{{ $id }}" class="site-section scroll-mt-24 {{ $i % 2 ? 'site-section-tint site-section-line' : '' }}" aria-labelledby="{{ $id }}-heading">
    <div class="site-container site-story {{ $i % 2 ? 'site-story-flip' : '' }}">
        <div data-reveal>
            <span class="site-badge site-badge-teal">{{ $short }}</span>
            <h2 id="{{ $id }}-heading" class="site-h2 mt-4">{{ $title }}</h2>
            <p class="site-lead mt-4">{{ $lead }}</p>
            <ul class="site-benefits mt-6">
                @foreach ($points as $point)
                    <li class="site-check"><span>{{ $point }}</span></li>
                @endforeach
            </ul>
        </div>
        <figure class="site-panel p-5 sm:p-7" data-reveal>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <figcaption class="font-semibold text-ink">{{ ['bank' => 'Monthly deposits counted', 'checks' => 'Findings to review', 'appraisal' => 'Appraisal checks', 'rental' => 'Units and rents', 'team' => 'Who can do what'][$visual] }}</figcaption>
                <span class="site-badge site-badge-amber">Sample data</span>
            </div>
            <div class="mt-5">
                @if ($visual === 'bank')
                    @include('site.partials.visual.deposit-chart')
                    <dl class="mt-5 grid grid-cols-2 gap-3"><div class="site-stat"><dt>Average monthly deposits counted</dt><dd>$8,725</dd></div><div class="site-stat"><dt>NSF / overdraft events</dt><dd>1</dd></div></dl>
                @else
                    @php
                        $rows = [
                            'checks' => [['Statement totals don’t match', 'Potential issue', 'amber'], ['Page 4 of 6 not found', 'Needs review', 'slate'], ['Same page appears twice', 'Needs review', 'slate'], ['PDF structure observation', 'Reviewed', 'teal']],
                            'appraisal' => [['Form 1025 · 2 units', 'In scope', 'teal'], ['Contract price vs. report', 'Matches', 'teal'], ['Rent schedule evidence', 'Missing', 'amber'], ['Possible reused photo', 'Needs review', 'slate']],
                            'rental' => [['Unit 1 · lease $1,650 · market $1,700', 'Confirmed', 'teal'], ['Unit 2 · lease $1,650 · market $1,675', 'Confirmed', 'teal'], ['Monthly payment $3,000', 'Confirmed', 'teal'], ['DSCR 1.10', 'Provisional until all inputs are confirmed', 'slate']],
                            'team' => [['Owner / Admin', 'Manage team and files', 'teal'], ['Underwriter', 'Review and decide', 'teal'], ['Processor', 'Upload and add notes', 'slate'], ['Broker', 'Limited view', 'slate']],
                        ][$visual];
                    @endphp
                    <ul class="grid gap-3">
                        @foreach ($rows as [$item, $status, $tone])
                            <li class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3"><span class="font-medium text-ink">{{ $item }}</span><span class="site-badge site-badge-{{ $tone }} !whitespace-normal">{{ $status }}</span></li>
                        @endforeach
                    </ul>
                @endif
            </div>
            <p class="site-caption mt-4">Illustration with fictional data.</p>
        </figure>
    </div>
</section>
@endforeach

<section class="site-section !pt-0" aria-labelledby="not-included-heading">
    <div class="site-container">
        <div class="site-card bg-slate-50">
            <h2 id="not-included-heading" class="site-h3">What {{ \App\Website\Site::name() }} does not do</h2>
            <ul class="site-benefits mt-4">
                <li class="site-check"><span>It does not approve or decline loans or replace underwriting judgment.</span></li>
                <li class="site-check"><span>Document checks are not proof of fraud; each one needs a person’s review.</span></li>
                <li class="site-check"><span>Direct bank account connections are planned and not available yet.</span></li>
            </ul>
        </div>
    </div>
</section>

@include('site.partials.cta')
@endsection
