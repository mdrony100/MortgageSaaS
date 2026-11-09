<section class="site-section site-section-tint site-section-line" aria-labelledby="team-heading">
    <div class="site-container site-story">
        <div data-reveal>
            <p class="site-eyebrow">Teamwork</p>
            <h2 id="team-heading" class="site-h2 mt-3">One file. Your whole team. Clear responsibilities.</h2>
            <p class="site-lead mt-4">Everyone works on the same loan file, and each person sees only what their role allows.</p>
            <ul class="site-benefits mt-6">
                <li class="site-check"><span><strong class="text-ink">Share files with the right people.</strong> Sharing keeps access permissions in place.</span></li>
                <li class="site-check"><span><strong class="text-ink">Keep internal findings restricted.</strong> Brokers get a limited view without restricted findings.</span></li>
                <li class="site-check"><span><strong class="text-ink">See changes and reviewer notes.</strong> See who changed what, and when.</span></li>
            </ul>
            <a href="{{ route('site.features') }}#team-workflow" class="site-link mt-6">See team features <span aria-hidden="true">→</span></a>
        </div>
        <figure class="grid gap-4 sm:grid-cols-[1.1fr_1fr]" data-reveal>
            <div class="site-panel p-5">
                <div class="flex items-center justify-between gap-2"><p class="font-semibold text-ink">Shared with</p><span class="site-badge site-badge-amber">Sample data</span></div>
                <ul class="mt-4 grid gap-3">
                    @foreach ([['AR', 'Alex R.', 'Owner', 'site-badge-navy', 'Full access', 'bg-ink text-white'], ['JM', 'Jordan M.', 'Underwriter', 'site-badge-teal', 'Full review', 'bg-teal-700 text-white'], ['SK', 'Sam K.', 'Processor', 'site-badge-teal', 'Upload and notes', 'bg-teal-100 text-teal-900'], ['TP', 'Taylor P.', 'Broker', 'site-badge-slate', 'Limited view', 'bg-slate-200 text-slate-800']] as [$ini, $name, $role, $badge, $access, $avatar])
                        <li class="flex items-center gap-3">
                            <span class="inline-flex h-11 w-11 flex-none items-center justify-center rounded-full font-bold {{ $avatar }}" aria-hidden="true">{{ $ini }}</span>
                            <span class="min-w-0 flex-1"><span class="block font-semibold text-ink">{{ $name }} <span class="sr-only">(fictional)</span></span><span class="site-caption">{{ $access }}</span></span>
                            <span class="site-badge {{ $badge }}">{{ $role }}</span>
                        </li>
                    @endforeach
                </ul>
                <p class="mt-4 rounded-lg bg-slate-50 px-3 py-2 text-[0.9375rem] text-body">Broker view: restricted findings hidden.</p>
            </div>
            <div class="site-panel p-5">
                <p class="font-semibold text-ink">File history</p>
                <ol class="mt-4 grid gap-4 border-l-2 border-teal-200 pl-4">
                    @foreach ([['Sam K. uploaded 4 documents', '9:05 AM'], ['Jordan M. confirmed a transfer', '10:20 AM'], ['Jordan M. added a reviewer note', '10:24 AM'], ['Alex R. issued report v2', '11:02 AM']] as [$what, $when])
                        <li class="relative"><span class="absolute -left-[1.4rem] top-2 h-2.5 w-2.5 rounded-full bg-accent" aria-hidden="true"></span><span class="block text-[0.9375rem] font-semibold text-ink">{{ $what }}</span><span class="site-caption">{{ $when }}</span></li>
                    @endforeach
                </ol>
            </div>
            <figcaption class="site-caption sm:col-span-2">Fictional team members and activity. Role names match the platform: owner, admin, underwriter, processor and broker.</figcaption>
        </figure>
    </div>
</section>
