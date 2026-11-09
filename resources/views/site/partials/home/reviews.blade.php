@php $reviews = \App\Website\Site::reviews(); @endphp
{{-- Built for your workflow: always shown; it replaces customer reviews in production until approved reviews exist. --}}
<section class="site-section" aria-labelledby="workflow-roles-heading">
    <div class="site-container site-story site-story-flip">
        <div data-reveal>
            <p class="site-eyebrow">Who it’s for</p>
            <h2 id="workflow-roles-heading" class="site-h2 mt-3">Built for your workflow</h2>
            <p class="site-lead mt-4">Each role gets the view it needs on the same file.</p>
            <dl class="mt-6 grid gap-5 sm:grid-cols-2">
                @foreach ([['Brokers', 'Upload documents and see broker-safe results and reports.'], ['Loan officers', 'Keep each borrower’s documents organized in one place.'], ['Processors', 'Upload documents, add notes and track what’s missing.'], ['Underwriters', 'Review evidence, confirm inputs and record decisions.']] as [$role, $what])
                    <div class="border-l-4 border-accent pl-4"><dt class="site-h3">{{ $role }}</dt><dd class="mt-1 text-body">{{ $what }}</dd></div>
                @endforeach
            </dl>
        </div>
        <picture data-reveal>
            <source type="image/avif" srcset="/images/site/mortgage-review-640.avif 640w, /images/site/mortgage-review-1200.avif 1200w" sizes="(min-width: 1024px) 600px, 100vw">
            <source type="image/webp" srcset="/images/site/mortgage-review-640.webp 640w, /images/site/mortgage-review-1200.webp 1200w" sizes="(min-width: 1024px) 600px, 100vw">
            <img src="/images/site/mortgage-review-1200.jpg" width="1200" height="800" loading="lazy" decoding="async" alt="A mortgage professional at a desk reviewing a loan application with a client" class="site-photo aspect-[3/2]">
        </picture>
    </div>
</section>

@if ($reviews['mode'] !== 'none')
<section class="site-section site-section-tint site-section-line" aria-labelledby="reviews-heading" data-review-mode="{{ $reviews['mode'] }}">
    <div class="site-container">
        <div class="max-w-3xl">
            <p class="site-eyebrow">Customer reviews</p>
            <h2 id="reviews-heading" class="site-h2 mt-3">What teams say</h2>
            @if ($reviews['mode'] === 'placeholder')
                <p class="mt-4 rounded-xl border-2 border-amber-400 bg-amber-50 p-4 font-semibold text-amber-900" role="note">Development preview only. No approved customer reviews exist yet, so this section is hidden in production.</p>
            @endif
        </div>
        <div class="mt-10" data-carousel>
            <div class="grid gap-6 md:grid-cols-2">
                @foreach ($reviews['items'] as $i => $item)
                    <figure class="site-review {{ $reviews['mode'] === 'placeholder' ? 'site-review-placeholder' : '' }}" data-slide aria-roledescription="slide" aria-label="Review {{ $i + 1 }} of {{ count($reviews['items']) }}">
                        @if ($reviews['mode'] === 'placeholder')
                            <p class="site-badge site-badge-amber">Placeholder — not a real customer review</p>
                        @endif
                        <blockquote class="mt-4 text-[1.25rem] leading-relaxed text-ink">“{{ $item['quote'] }}”</blockquote>
                        <figcaption class="mt-5 flex items-center gap-3">
                            @if ($reviews['mode'] === 'approved' && filled($item['photo'] ?? null))
                                <img src="{{ $item['photo'] }}" width="56" height="56" loading="lazy" alt="" class="h-14 w-14 rounded-full object-cover">
                            @endif
                            <span><span class="block font-semibold text-ink">{{ $item['name'] }}</span><span class="site-caption">{{ $item['role'] }}</span></span>
                        </figcaption>
                    </figure>
                @endforeach
            </div>
            @if (count($reviews['items']) > 2)
                <div class="mt-6 flex items-center gap-3" data-js-only>
                    <button type="button" class="site-btn site-btn-ghost site-btn-sm" data-carousel-prev>Previous review</button>
                    <button type="button" class="site-btn site-btn-ghost site-btn-sm" data-carousel-next>Next review</button>
                    <p class="site-caption" data-carousel-status aria-live="polite"></p>
                </div>
            @endif
        </div>
    </div>
</section>
@endif
