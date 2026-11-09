{{-- Three feature stories with fictional sample visuals. --}}
<section class="site-section" aria-labelledby="stories-heading">
    <div class="site-container">
        <div class="max-w-3xl" data-reveal>
            <p class="site-eyebrow">What you get</p>
            <h2 id="stories-heading" class="site-h2 mt-3">Clear answers you can trace back to the documents.</h2>
        </div>

        {{-- A: bank statements --}}
        <article class="site-story mt-14" aria-labelledby="story-bank-heading" data-reveal>
            <div>
                <span class="site-badge site-badge-teal">Bank statements</span>
                <h3 id="story-bank-heading" class="site-h2 mt-4">Turn bank statements into a clear income summary.</h3>
                <p class="site-copy mt-4">Bring 12 or 24 months of statements into one place. Review deposits, transfers, and exclusions before confirming the results.</p>
                <ul class="site-benefits mt-6">
                    <li class="site-check"><span>See which deposits count, and why.</span></li>
                    <li class="site-check"><span>Catch transfers between the borrower’s own accounts.</span></li>
                    <li class="site-check"><span>Export Excel, CSV and PDF reports.</span></li>
                </ul>
                <a href="{{ route('site.features') }}#bank-statements" class="site-link mt-6">Explore bank statement analysis <span aria-hidden="true">→</span></a>
            </div>
            <figure class="site-panel p-5 sm:p-7">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <figcaption class="font-semibold text-ink">Monthly deposits counted</figcaption>
                    <span class="site-badge site-badge-amber">Sample data</span>
                </div>
                <div class="mt-4">@include('site.partials.visual.deposit-chart')</div>
                <div class="mt-6 overflow-x-auto">
                    <table class="site-table">
                        <caption class="sr-only">Sample March deposits and how each one is treated (fictional)</caption>
                        <thead><tr><th scope="col">Deposit</th><th scope="col" class="site-num">Amount</th><th scope="col">Treatment</th></tr></thead>
                        <tbody>
                            <tr><td>Payroll — Sample Employer</td><td class="site-num">$4,250.00</td><td><span class="site-badge site-badge-teal">Counted</span></td></tr>
                            <tr><td>Transfer from savings ••12</td><td class="site-num">$1,500.00</td><td><span class="site-badge site-badge-slate">Excluded: own transfer</span></td></tr>
                            <tr><td>Customer payment — Sample Client</td><td class="site-num">$2,580.00</td><td><span class="site-badge site-badge-teal">Counted</span></td></tr>
                            <tr><td>Refund — Sample Store</td><td class="site-num">$45.00</td><td><span class="site-badge site-badge-slate">Excluded: refund</span></td></tr>
                        </tbody>
                    </table>
                </div>
            </figure>
        </article>

        {{-- B: document checks --}}
        <article class="site-story site-story-flip mt-24" aria-labelledby="story-checks-heading" data-reveal>
            <div>
                <span class="site-badge site-badge-amber">Document checks</span>
                <h3 id="story-checks-heading" class="site-h2 mt-4">Spot document issues that need a closer look.</h3>
                <p class="site-copy mt-4">See potential missing pages, inconsistent totals, and other available document checks alongside the supporting evidence.</p>
                <ul class="site-benefits mt-6">
                    <li class="site-check"><span>Spot totals that don’t match.</span></li>
                    <li class="site-check"><span>Notice missing or repeated pages.</span></li>
                    <li class="site-check"><span>Record a decision and a reason for each finding.</span></li>
                </ul>
                <p class="site-caption mt-5">Checks point to potential issues for a person to review. They are not proof of fraud.</p>
                <a href="{{ route('site.features') }}#document-integrity" class="site-link mt-4">Explore document checks <span aria-hidden="true">→</span></a>
            </div>
            <figure class="site-panel p-5 sm:p-7">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <figcaption class="font-semibold text-ink">March statement · Sample Bank · page 1 of 6</figcaption>
                    <span class="site-badge site-badge-amber">Sample data</span>
                </div>
                <div class="mt-5 grid gap-5 md:grid-cols-[1fr_1fr]">
                    <div class="site-doc">
                        <p class="site-caption">Statement summary</p>
                        <div class="mt-3 grid gap-2.5 text-[0.9375rem]">
                            <div class="flex justify-between gap-3"><span>Beginning balance</span><span class="site-num">$10,250.00</span></div>
                            <div class="flex justify-between gap-3"><span>Deposits</span><span class="site-num">$6,830.00</span></div>
                            <div class="flex justify-between gap-3"><span>Withdrawals</span><span class="site-num">$4,600.00</span></div>
                            <div class="site-highlight flex justify-between gap-3 px-2 py-1.5"><strong>Ending balance</strong><strong class="site-num">$12,780.00</strong></div>
                        </div>
                        <div class="mt-5 grid gap-2" aria-hidden="true"><span class="site-doc-line w-full"></span><span class="site-doc-line w-4/5"></span><span class="site-doc-line w-11/12"></span></div>
                    </div>
                    <div>
                        <p class="site-caption">Review panel</p>
                        <ul class="mt-3 grid gap-3">
                            <li class="rounded-xl border border-amber-300 bg-amber-50 p-4">
                                <span class="site-badge site-badge-amber">Potential issue</span>
                                <p class="mt-2 font-semibold text-ink">Totals don’t match</p>
                                <p class="text-[0.9375rem]">The printed balance is $300.00 higher than the transactions add up to.</p>
                            </li>
                            <li class="rounded-xl border border-slate-200 bg-white p-4">
                                <span class="site-badge site-badge-slate">Needs review</span>
                                <p class="mt-2 font-semibold text-ink">Page 4 of 6 not found</p>
                                <p class="text-[0.9375rem]">Ask for the full statement.</p>
                            </li>
                        </ul>
                    </div>
                </div>
            </figure>
        </article>

        {{-- C: property and rental income --}}
        <article id="rental" class="site-story mt-24 scroll-mt-24" aria-labelledby="story-rental-heading" data-reveal>
            <div>
                <span class="site-badge site-badge-teal">Appraisal &amp; rental income</span>
                <h3 id="story-rental-heading" class="site-h2 mt-4">Review property and rental income details together.</h3>
                <p class="site-copy mt-4">Compare appraisal information, leases, and rental income inputs in one organized review.</p>
                <p class="site-copy mt-4"><strong class="text-ink">DSCR</strong> compares qualifying rental income with the applicable monthly property payment.</p>
                <ul class="site-benefits mt-6">
                    <li class="site-check"><span>Supports forms 1004, 1025, 1007 and 216 for 1–4 unit homes.</span></li>
                    <li class="site-check"><span>Match leases to units and compare with market rent.</span></li>
                    <li class="site-check"><span>See what’s missing before a result is final.</span></li>
                </ul>
                <a href="{{ route('site.features') }}#appraisal-review" class="site-link mt-6">Explore appraisal and rental review <span aria-hidden="true">→</span></a>
            </div>
            <div class="grid gap-5">
                <figure class="site-panel overflow-hidden">
                    <picture>
                        <source type="image/avif" srcset="/images/site/townhomes-640.avif 640w, /images/site/townhomes-1200.avif 1200w" sizes="(min-width: 1024px) 640px, 100vw">
                        <source type="image/webp" srcset="/images/site/townhomes-640.webp 640w, /images/site/townhomes-1200.webp 1200w" sizes="(min-width: 1024px) 640px, 100vw">
                        <img src="/images/site/townhomes-1200.jpg" width="1200" height="675" loading="lazy" decoding="async" alt="Row of brick townhomes with garages on a residential street" class="block aspect-[16/9] w-full object-cover">
                    </picture>
                    <div class="grid gap-4 p-5 sm:grid-cols-[1fr_auto] sm:items-center">
                        <div>
                            <div class="flex flex-wrap items-center gap-2"><p class="font-semibold text-ink">Sample 2-unit property · Form 1025</p><span class="site-badge site-badge-amber">Sample data</span></div>
                            <p class="site-caption mt-1">Leases: $1,650 + $1,650 · Monthly payment: $3,000</p>
                        </div>
                        <div class="rounded-xl bg-accent-tint px-4 py-2 text-center"><p class="site-caption">DSCR</p><p class="text-2xl font-extrabold text-accent-dark">1.10</p></div>
                    </div>
                    <figcaption class="sr-only">Illustrative property photo with a fictional rental summary.</figcaption>
                </figure>
                @include('site.partials.home.dscr-calculator')
            </div>
        </article>
    </div>
</section>
