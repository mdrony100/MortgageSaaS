@extends('site.layout')
@section('content')
@include('site.partials.page-header', ['eyebrow' => 'Features', 'heading' => 'Everything a mortgage review team needs to work from evidence', 'lead' => 'Each capability below is available in the platform today. Results are prepared for professional review; they are not lending decisions.'])

@php
$sections = [
    ['bank-statements', 'Bank Statement Analysis', 'Calculate qualifying income from statements with every deposit traceable to its source.', [
        'Review 12 or 24 months of personal or business statements.',
        'Classify deposits and review exclusions such as transfers, refunds and loan proceeds.',
        'Identify potential transfers between accounts and overlapping coverage that could cause duplicate counting.',
        'Calculate income deterministically using your organization’s versioned, approved rule profiles.',
        'Review NSF and overdraft activity alongside income.',
        'Open the source page and region behind extracted values.',
        'Export versioned reports to Excel (XLSX), CSV and PDF.',
    ], ['Deposits — fictional sample', [['Payroll deposit', 'Included'], ['Transfer from savings ••12', 'Excluded · transfer'], ['Refund', 'Excluded · refund']]]],
    ['document-integrity', 'Document Integrity', 'Surface potential issues early so reviewers can request the right documents. Indicators are potential issues requiring review, not proof of fraud or alteration.', [
        'Flag reconciliation discrepancies between reported and calculated balances and totals.',
        'Identify potential missing, reordered or duplicate pages from page numbering and page fingerprints.',
        'Surface available document consistency indicators, such as PDF structure and font variation observations.',
        'Review each finding beside its evidence and record a decision with a reason.',
    ], ['Findings — fictional sample', [['Credits total differs from transactions', 'Open'], ['Duplicate page fingerprint', 'Needs document'], ['PDF structure observation', 'Reviewed']]]],
    ['appraisal-review', 'Appraisal Review', 'Review appraisal reports for completeness and consistency with the rest of the file.', [
        'Supports forms 1004, 1025, 1007 and 216.',
        'Focused on residential properties with 1–4 units; anything outside that scope is flagged for manual review.',
        'Review document completeness with links to supporting evidence on the page.',
        'Compare appraisal information with relevant supporting documents such as leases and purchase contracts.',
        'Identify potentially reused photos using image fingerprints, for reviewer confirmation.',
        'Track findings and revisions across report versions.',
    ], ['Appraisal checks — fictional sample', [['Form 1025 · 4 units', 'In scope'], ['Contract price vs. report', 'Matches'], ['Rent schedule evidence', 'Missing']]]],
    ['rental-dscr', 'Rental Income and DSCR', 'Prepare rental income and DSCR from inputs a reviewer has confirmed.', [
        'Review leases and unit-level rental information.',
        'Compare market rent with reviewer-confirmed qualifying rent for each unit.',
        'Calculate DSCR deterministically from confirmed rent and payment inputs.',
        'See exactly which information is missing and when results are provisional.',
    ], ['Units — fictional sample', [['Unit 1 · lease $950', 'Confirmed'], ['Unit 2 · lease $950', 'Confirmed'], ['Unit 4 · vacant', 'Provisional']]]],
    ['team-workflow', 'Team Workflow', 'Keep everyone on the same file without over-sharing.', [
        'Role-based access for owners, admins, underwriters, processors and brokers.',
        'Share individual loan files and collaborate with shared notes.',
        'Review history that records who changed what, and why.',
        'Controlled report access, with Broker-facing reports that exclude restricted findings.',
    ], ['Access — fictional sample', [['Underwriter', 'Full review'], ['Processor', 'Upload and notes'], ['Broker', 'Broker-safe view']]]],
];
@endphp

@foreach ($sections as $i => [$id, $title, $lead, $points, [$visualTitle, $rows]])
<section id="{{ $id }}" class="scroll-mt-20 py-14 md:py-20 {{ $i % 2 ? 'border-y border-slate-200 bg-slate-50' : '' }}" aria-labelledby="{{ $id }}-heading">
    <div class="site-container grid items-start gap-10 lg:grid-cols-2">
        <div>
            <h2 id="{{ $id }}-heading" class="site-h2">{{ $title }}</h2>
            <p class="site-lead mt-3">{{ $lead }}</p>
            <ul class="mt-6 grid gap-3 text-slate-700">
                @foreach ($points as $point)
                    <li class="site-check"><span>{{ $point }}</span></li>
                @endforeach
            </ul>
        </div>
        <figure class="site-card" aria-label="{{ $visualTitle }}">
            <div class="flex items-center justify-between gap-2">
                <p class="text-sm font-semibold text-ink">{{ $visualTitle }}</p>
                <span class="site-badge site-badge-amber">SAMPLE</span>
            </div>
            <table class="mt-4 w-full text-left text-sm">
                <caption class="sr-only">{{ $visualTitle }} (illustrative, not real data)</caption>
                <thead><tr class="text-xs uppercase tracking-wider text-slate-600"><th scope="col" class="pb-2 font-semibold">Item</th><th scope="col" class="pb-2 text-right font-semibold">Status</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($rows as [$item, $status])
                        <tr><td class="py-2.5 text-slate-700">{{ $item }}</td><td class="py-2.5 text-right"><span class="site-badge site-badge-slate">{{ $status }}</span></td></tr>
                    @endforeach
                </tbody>
            </table>
            <figcaption class="mt-3 text-xs text-slate-600">Illustration with fictional data.</figcaption>
        </figure>
    </div>
</section>
@endforeach

<section class="py-12" aria-labelledby="not-included-heading">
    <div class="site-container">
        <div class="site-card bg-slate-50">
            <h2 id="not-included-heading" class="site-h3">What {{ \App\Website\Site::name() }} does not do</h2>
            <ul class="mt-3 list-disc space-y-1 pl-5 text-slate-700">
                <li>It does not approve or decline loans or replace underwriting judgment.</li>
                <li>Integrity indicators are not proof of fraud; each requires human review.</li>
                <li>Direct bank account connections are planned and not available yet.</li>
            </ul>
        </div>
    </div>
</section>

@include('site.partials.cta')
@endsection
