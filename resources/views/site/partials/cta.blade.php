<section class="bg-ink" aria-labelledby="final-cta-heading">
    <div class="site-container flex flex-col items-start justify-between gap-6 py-14 md:flex-row md:items-center">
        <div class="max-w-2xl">
            <h2 id="final-cta-heading" class="text-2xl font-bold tracking-tight text-white md:text-3xl">See {{ \App\Website\Site::name() }} with your team’s workflow in mind.</h2>
            <p class="mt-3 text-slate-300">We’ll walk through bank statement analysis, appraisal review and rental/DSCR calculations using fictional sample files.</p>
        </div>
        <div class="flex flex-wrap gap-3">
            <a href="{{ route('site.contact') }}?type=demo" class="site-btn site-btn-light">Request a Demo</a>
            <a href="{{ route('site.contact') }}?type=pricing" class="site-btn site-btn-outline-light">Contact Sales</a>
        </div>
    </div>
</section>
