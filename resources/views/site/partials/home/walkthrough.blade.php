{{-- Interactive sample walkthrough. Frontend demonstration only: no uploads, no network or AI requests, fictional data. --}}
@php
    $steps = [
        ['upload', 'Upload your documents', 'Add statements, appraisals and leases to one loan file.'],
        ['extract', 'Review the extracted information', 'See key details pulled from each page, with their source.'],
        ['review', 'Check flagged issues', 'Look at anything that needs a closer look, next to the evidence.'],
        ['export', 'Export your report', 'Download Excel or PDF reports for the right audience.'],
    ];
@endphp
<section id="walkthrough" class="site-section site-section-tint site-section-line scroll-mt-20" aria-labelledby="walkthrough-heading">
    <div class="site-container">
        <div class="max-w-3xl" data-reveal>
            <p class="site-eyebrow"><span class="site-badge site-badge-amber">Interactive sample walkthrough</span></p>
            <h2 id="walkthrough-heading" class="site-h2 mt-4">From uploaded documents to a clear report.</h2>
            <p class="site-lead mt-4">Click through the four steps your team follows on every file. This demo uses fictional data and doesn’t upload anything.</p>
        </div>

        <div class="mt-12 grid grid-cols-[minmax(0,1fr)] gap-8 lg:grid-cols-[minmax(0,0.85fr)_minmax(0,1.15fr)] lg:items-start" data-tabs="walkthrough">
            <div>
                <div role="tablist" aria-label="Walkthrough steps" aria-orientation="vertical" class="site-steps">
                    @foreach ($steps as $i => [$id, $title, $sub])
                        <button type="button" role="tab" id="walk-tab-{{ $id }}" aria-controls="walk-panel-{{ $id }}" aria-selected="{{ $i === 0 ? 'true' : 'false' }}" tabindex="{{ $i === 0 ? '0' : '-1' }}" class="site-step">
                            <span class="site-step-num" aria-hidden="true">{{ $i + 1 }}</span>
                            <span><span class="site-step-title"><span class="sr-only">Step {{ $i + 1 }}: </span>{{ $title }}</span><span class="site-step-sub">{{ $sub }}</span></span>
                        </button>
                    @endforeach
                </div>
                <div class="mt-6" data-js-only>
                    <div class="flex items-center justify-between gap-4">
                        <p class="font-semibold text-ink" data-walk-status aria-live="polite">Step 1 of 4</p>
                        <div class="flex gap-2">
                            <button type="button" class="site-btn site-btn-ghost site-btn-sm" data-walk-prev disabled><span aria-hidden="true">←</span> Previous</button>
                            <button type="button" class="site-btn site-btn-primary site-btn-sm" data-walk-next>Next <span aria-hidden="true">→</span></button>
                        </div>
                    </div>
                    <div class="site-progress mt-4" role="progressbar" aria-label="Walkthrough progress" aria-valuemin="1" aria-valuemax="4" aria-valuenow="1" data-walk-progress style="--progress: .25"><span></span></div>
                </div>
            </div>

            <div class="site-browser">
                <div class="site-browser-bar">
                    <div class="site-browser-dots" aria-hidden="true"><span></span><span></span><span></span></div>
                    <span class="site-browser-url max-sm:invisible" aria-hidden="true">Sample loan file · LF-0001</span>
                    <span class="site-badge site-badge-amber">Sample data</span>
                </div>

                {{-- Step 1: upload --}}
                <div role="tabpanel" id="walk-panel-upload" aria-labelledby="walk-tab-upload" tabindex="0" class="site-step-panel site-tabpanel is-active">
                    <h3 class="site-h3">Documents added to the loan file</h3>
                    <p class="site-caption mt-1">Each file is checked for malware before anyone can open it.</p>
                    <ul class="mt-5 grid gap-3">
                        @foreach ([['Bank statement — January.pdf', '2 pages', 'Ready', 'teal'], ['Bank statement — February.pdf', '3 pages', 'Ready', 'teal'], ['Appraisal — Form 1025.pdf', '38 pages', 'Ready', 'teal'], ['Lease — Unit 2.pdf', '6 pages', 'Scanning', 'slate']] as $i => [$name, $pages, $state, $tone])
                            <li class="site-file-card" style="--i: {{ $i }}">
                                <span class="site-file-icon" aria-hidden="true">PDF</span>
                                <span class="min-w-0 flex-1"><span class="block break-words font-semibold leading-snug text-ink">{{ $name }}</span><span class="site-caption">{{ $pages }} · fictional</span></span>
                                <span class="site-badge site-badge-{{ $tone }}">{{ $state }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>

                {{-- Step 2: extract --}}
                <div role="tabpanel" id="walk-panel-extract" aria-labelledby="walk-tab-extract" tabindex="0" class="site-step-panel site-tabpanel">
                    <h3 class="site-h3">Details pulled from the statement</h3>
                    <p class="site-caption mt-1">Every value links back to the page it came from.</p>
                    <div class="mt-5 grid items-center gap-4 sm:grid-cols-[0.8fr_auto_1.2fr]">
                        <div class="site-doc" aria-hidden="true">
                            <p class="font-semibold text-ink">Sample Bank</p>
                            <div class="mt-3 grid gap-2"><span class="site-doc-line w-3/4"></span><span class="site-doc-line w-1/2"></span><span class="site-doc-line w-5/6 bg-teal-200"></span><span class="site-doc-line w-2/3"></span><span class="site-doc-line w-3/5 bg-teal-200"></span><span class="site-doc-line w-1/2"></span></div>
                            <p class="mt-3 text-[0.875rem] text-muted">Page 1 of 2</p>
                        </div>
                        <div class="hidden w-10 sm:block" aria-hidden="true"><div class="site-connector"></div></div>
                        <dl class="grid gap-2">
                            @foreach ([['Account holder', 'Sample Borrower LLC'], ['Statement period', 'Mar 1 – Mar 31'], ['Total deposits', '$6,830.00'], ['Ending balance', '$12,780.00']] as $i => [$k, $v])
                                <div class="site-field-row rounded-xl border border-slate-200 bg-white px-4 py-2.5" style="--i: {{ $i }}">
                                    <dt class="site-caption">{{ $k }}</dt>
                                    <dd class="flex items-center justify-between gap-2 font-semibold text-ink"><span>{{ $v }}</span><span class="site-badge site-badge-slate">Page 1</span></dd>
                                </div>
                            @endforeach
                        </dl>
                    </div>
                </div>

                {{-- Step 3: review --}}
                <div role="tabpanel" id="walk-panel-review" aria-labelledby="walk-tab-review" tabindex="0" class="site-step-panel site-tabpanel">
                    <h3 class="site-h3">A finding, shown next to its source</h3>
                    <div class="mt-5 grid gap-4 sm:grid-cols-2">
                        <div class="site-doc">
                            <p class="site-caption">Source · March statement, page 1</p>
                            <div class="mt-3 grid gap-2 text-[0.9375rem]">
                                <div class="flex justify-between"><span>Deposits</span><span class="site-num">$6,830.00</span></div>
                                <div class="flex justify-between"><span>Withdrawals</span><span class="site-num">$4,600.00</span></div>
                                <div class="site-highlight flex justify-between px-2 py-1"><span><strong>Ending balance</strong></span><span class="site-num"><strong>$12,780.00</strong></span></div>
                            </div>
                        </div>
                        <div class="rounded-xl border border-amber-300 bg-amber-50 p-4">
                            <span class="site-badge site-badge-amber">Potential issue</span>
                            <p class="mt-2 font-semibold text-ink">Ending balance doesn’t match the transactions</p>
                            <p class="mt-1 text-[0.9375rem] text-body">Difference of $300.00. A reviewer records a decision and a reason.</p>
                            <p class="mt-3 flex flex-wrap gap-2"><span class="site-badge site-badge-slate">Status: Needs review</span></p>
                        </div>
                    </div>
                </div>

                {{-- Step 4: export --}}
                <div role="tabpanel" id="walk-panel-export" aria-labelledby="walk-tab-export" tabindex="0" class="site-step-panel site-tabpanel">
                    <h3 class="site-h3">Reports for each audience</h3>
                    <div class="mt-5 grid gap-4 sm:grid-cols-2">
                        <div class="site-file-card flex-col items-stretch" style="--i: 0">
                            <div class="flex items-center justify-between"><span class="font-semibold text-ink">Income report</span><span class="site-badge site-badge-teal">Excel</span></div>
                            <div class="mt-2 grid grid-cols-3 gap-px overflow-hidden rounded-md border border-slate-200 bg-slate-200 text-[0.875rem]" aria-hidden="true">
                                @foreach (['Month', 'Counted', 'Excluded', 'Jan', '$8,100', '$0', 'Feb', '$8,600', '$1,500', 'Mar', '$7,900', '$45'] as $c)<span class="bg-white px-2 py-1">{{ $c }}</span>@endforeach
                            </div>
                        </div>
                        <div class="site-file-card flex-col items-stretch" style="--i: 1">
                            <div class="flex items-center justify-between"><span class="font-semibold text-ink">Review summary</span><span class="site-badge site-badge-red">PDF</span></div>
                            <div class="mt-3 grid gap-2" aria-hidden="true"><span class="site-doc-line w-2/3 bg-slate-300"></span><span class="site-doc-line w-full"></span><span class="site-doc-line w-5/6"></span><span class="site-doc-line w-3/4"></span></div>
                        </div>
                    </div>
                    <p class="mt-4 site-caption">Broker reports leave out restricted internal findings. Each issued report keeps its version.</p>
                </div>
            </div>
        </div>
    </div>
</section>
