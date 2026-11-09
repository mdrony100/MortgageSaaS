<section class="relative overflow-hidden bg-gradient-to-br from-[#0f766e] to-[#0b3f4a]" aria-labelledby="final-cta-heading">
    <div class="site-container relative grid grid-cols-[minmax(0,1fr)] items-center gap-8 py-16 md:py-20 lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]">
        <div>
            <h2 id="final-cta-heading" class="site-h2 !text-white">See how {{ \App\Website\Site::name() }} fits your workflow.</h2>
            <p class="site-lead mt-4 !text-teal-50">Explore bank statement analysis, document checks, and appraisal review in a guided demo.</p>
        </div>
        <div class="flex flex-wrap gap-3 lg:justify-end">
            <a href="{{ route('site.contact') }}?type=demo" class="site-btn site-btn-light">Request a Demo</a>
            <a href="{{ route('site.contact') }}?type=pricing" class="site-btn site-btn-outline-light">Contact Sales</a>
        </div>
    </div>
</section>
