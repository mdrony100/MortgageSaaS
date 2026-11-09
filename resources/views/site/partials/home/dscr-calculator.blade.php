{{-- Fully local illustrative DSCR example (resources/js/site/dscr.ts). No data leaves the page. --}}
<form class="site-calc" data-dscr novalidate aria-labelledby="dscr-heading" aria-describedby="dscr-note">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h4 id="dscr-heading" class="site-h3">Try a sample calculation</h4>
        <span class="site-badge site-badge-amber">Illustration only</span>
    </div>
    <p class="mt-2 font-semibold text-body">DSCR = qualifying rent ÷ monthly payment</p>
    <div class="mt-4 grid gap-4">
        <div class="site-field">
            <label for="dscr-rent">Illustrative monthly qualifying rent</label>
            <div class="site-money"><span aria-hidden="true">$</span><input id="dscr-rent" name="rent" type="text" inputmode="decimal" autocomplete="off" value="3,300" data-dscr-rent aria-describedby="dscr-rent-error"></div>
            <p id="dscr-rent-error" class="site-error" aria-live="polite"></p>
        </div>
        <div class="site-field">
            <label for="dscr-payment">Illustrative monthly property payment</label>
            <div class="site-money"><span aria-hidden="true">$</span><input id="dscr-payment" name="payment" type="text" inputmode="decimal" autocomplete="off" value="3,000" data-dscr-payment aria-describedby="dscr-payment-error"></div>
            <p id="dscr-payment-error" class="site-error" aria-live="polite"></p>
        </div>
    </div>
    <div class="mt-5 flex flex-wrap items-end justify-between gap-4 rounded-xl bg-tint p-4" aria-live="polite" aria-atomic="true">
        <div>
            <p class="site-caption">Sample DSCR</p>
            <p class="site-calc-result" data-dscr-result>1.10</p>
        </div>
        <div class="min-w-0 flex-1 basis-56">
            <p class="font-semibold text-ink" data-dscr-equation>$3,300 ÷ $3,000 = 1.10</p>
            <p class="mt-1 text-body" data-dscr-explanation>In this simplified example, rent is 1.10 times the monthly property payment.</p>
        </div>
    </div>
    <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
        <p id="dscr-note" class="site-caption max-w-md">Illustration only. Actual calculations depend on confirmed inputs and program requirements.</p>
        <button type="button" class="site-btn site-btn-ghost site-btn-sm" data-dscr-reset data-js-only>Reset example</button>
    </div>
    <noscript><p class="site-caption mt-2">Turn on JavaScript to try your own numbers.</p></noscript>
</form>
