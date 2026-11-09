{{-- Hero product preview: three working tabs built from fictional sample data only. --}}
<div class="site-browser site-hero-in-late" data-tabs="preview">
    <div class="site-browser-bar">
        <div class="site-browser-dots" aria-hidden="true"><span></span><span></span><span></span></div>
        <span class="site-browser-url max-sm:invisible" aria-hidden="true">{{ \App\Website\Site::name() }} · Sample loan file</span>
        <span class="site-badge site-badge-amber">Sample data</span>
    </div>
    <div role="tablist" aria-label="Product preview" class="site-tablist">
        <button type="button" role="tab" id="hero-tab-bank" aria-controls="hero-panel-bank" aria-selected="true" tabindex="0" class="site-tab">Bank Statements</button>
        <button type="button" role="tab" id="hero-tab-checks" aria-controls="hero-panel-checks" aria-selected="false" tabindex="-1" class="site-tab">Document Checks</button>
        <button type="button" role="tab" id="hero-tab-rental" aria-controls="hero-panel-rental" aria-selected="false" tabindex="-1" class="site-tab">Appraisal &amp; Rental Income</button>
    </div>

    <div role="tabpanel" id="hero-panel-bank" aria-labelledby="hero-tab-bank" tabindex="0" class="site-tabpanel is-active">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="font-semibold text-ink">Income summary · 12 months</p>
            <span class="site-badge site-badge-teal"><span class="site-dot" aria-hidden="true"></span>Reviewer confirmed</span>
        </div>
        <div class="mt-4">@include('site.partials.visual.deposit-chart')</div>
        <dl class="mt-4 grid grid-cols-2 gap-3">
            <div class="site-stat"><dt>Average monthly deposits counted</dt><dd>$8,725</dd></div>
            <div class="site-stat"><dt>Transfers excluded</dt><dd>3</dd></div>
        </dl>
    </div>

    <div role="tabpanel" id="hero-panel-checks" aria-labelledby="hero-tab-checks" tabindex="0" class="site-tabpanel">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="font-semibold text-ink">March statement · Totals check</p>
            <span class="site-badge site-badge-amber"><span class="site-dot" aria-hidden="true"></span>Needs review</span>
        </div>
        <table class="site-table mt-4">
            <caption class="sr-only">Sample statement totals (fictional)</caption>
            <tbody>
                <tr><td>Beginning balance</td><td class="site-num">$10,250.00</td></tr>
                <tr><td>Deposits</td><td class="site-num">+ $6,830.00</td></tr>
                <tr><td>Withdrawals</td><td class="site-num">− $4,600.00</td></tr>
                <tr class="site-highlight site-highlight-pulse"><td><strong>Ending balance printed</strong></td><td class="site-num"><strong>$12,780.00</strong></td></tr>
                <tr><td>Ending balance from transactions</td><td class="site-num">$12,480.00</td></tr>
            </tbody>
        </table>
        <div class="mt-4 rounded-xl border border-amber-300 bg-amber-50 p-4">
            <p class="font-semibold text-amber-900">Potential issue: totals don’t match</p>
            <p class="mt-1 text-[0.9375rem] text-amber-950">The printed ending balance is $300.00 higher than the transactions add up to. A reviewer should check the source page.</p>
        </div>
    </div>

    <div role="tabpanel" id="hero-panel-rental" aria-labelledby="hero-tab-rental" tabindex="0" class="site-tabpanel">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="font-semibold text-ink">2-unit property · Form 1025</p>
            <span class="site-badge site-badge-teal"><span class="site-dot" aria-hidden="true"></span>Inputs confirmed</span>
        </div>
        <table class="site-table mt-4">
            <caption class="sr-only">Sample unit rents (fictional)</caption>
            <thead><tr><th scope="col">Unit</th><th scope="col" class="site-num">Lease rent</th><th scope="col" class="site-num">Market rent</th></tr></thead>
            <tbody>
                <tr><td>Unit 1</td><td class="site-num">$1,650</td><td class="site-num">$1,700</td></tr>
                <tr><td>Unit 2</td><td class="site-num">$1,650</td><td class="site-num">$1,675</td></tr>
            </tbody>
        </table>
        <dl class="mt-4 grid grid-cols-3 gap-3">
            <div class="site-stat"><dt>Qualifying rent</dt><dd>$3,300</dd></div>
            <div class="site-stat"><dt>Monthly payment</dt><dd>$3,000</dd></div>
            <div class="site-stat bg-accent-tint"><dt>DSCR</dt><dd class="text-accent-dark">1.10</dd></div>
        </dl>
    </div>
    <p class="border-t border-slate-200 px-5 py-3 text-[0.875rem] text-muted">Fictional sample data for illustration — not real customer results.</p>
</div>
