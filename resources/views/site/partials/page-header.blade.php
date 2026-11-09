<section class="relative overflow-hidden border-b border-[#dfece9] bg-gradient-to-b from-[#eef8f6] to-white">
    <div class="site-container py-16 md:py-24">
        <p class="site-eyebrow">{{ $eyebrow }}</p>
        <h1 class="site-h1 mt-4 max-w-4xl">{{ $heading }}</h1>
        <p class="site-lead mt-5">{{ $lead }}</p>
        @isset($actions)
            <div class="mt-8 flex flex-wrap gap-3">{!! $actions !!}</div>
        @endisset
    </div>
</section>
