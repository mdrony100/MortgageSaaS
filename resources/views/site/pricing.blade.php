@extends('site.layout')
@section('content')
@php $plans = \App\Website\Site::publishedPlans(); @endphp
@include('site.partials.page-header', ['eyebrow' => 'Pricing', 'heading' => $plans ? 'Plans and pricing' : 'Contact for pricing', 'lead' => $plans ? 'Approved plans are listed below. Contact us with any questions.' : 'Pricing is tailored to your team. Request a demo or contact sales and we’ll discuss your workflow and requirements.'])

<section class="py-14 md:py-20" aria-labelledby="pricing-heading">
    <div class="site-container">
        <h2 id="pricing-heading" class="sr-only">Pricing options</h2>
        @if ($plans)
            <div class="grid gap-6 {{ [1 => 'md:grid-cols-1', 2 => 'md:grid-cols-2'][count($plans)] ?? 'md:grid-cols-3' }}">
                @foreach ($plans as $plan)
                    <article class="site-card flex flex-col">
                        <h3 class="site-h3 text-xl">{{ $plan['name'] }}</h3>
                        <p class="mt-2 text-2xl font-bold text-ink">{{ $plan['price_label'] }}</p>
                        @if (!empty($plan['summary']))<p class="mt-2 text-slate-600">{{ $plan['summary'] }}</p>@endif
                        @if (!empty($plan['includes']))
                            <ul class="mt-4 grid gap-2 text-slate-700">@foreach ($plan['includes'] as $item)<li class="site-check"><span>{{ $item }}</span></li>@endforeach</ul>
                        @endif
                        <a href="{{ route('site.contact') }}?type=pricing" class="site-btn site-btn-primary mt-6 justify-center">Contact Sales</a>
                    </article>
                @endforeach
            </div>
        @else
            <div class="grid gap-8 lg:grid-cols-[1.4fr_1fr]">
                <div class="site-card">
                    <p class="text-sm font-semibold uppercase tracking-wider text-slate-600">All modules</p>
                    <p class="mt-2 text-3xl font-bold text-ink">Contact for pricing</p>
                    <p class="mt-3 text-slate-700">Every organization can use bank statement analysis, document integrity review, appraisal review and rental/DSCR analysis in one workspace.</p>
                    <ul class="mt-5 grid gap-2 text-slate-700">
                        <li class="site-check"><span>Usage allowances, such as loan files, team members and document storage, depend on the selected plan.</span></li>
                        <li class="site-check"><span>Role-based team access and file sharing.</span></li>
                        <li class="site-check"><span>Versioned Excel and PDF reports.</span></li>
                    </ul>
                    <div class="mt-6 flex flex-wrap gap-3">
                        <a href="{{ route('site.contact') }}?type=demo" class="site-btn site-btn-primary">Request a Demo</a>
                        <a href="{{ route('site.contact') }}?type=pricing" class="site-btn site-btn-ghost">Contact Sales</a>
                    </div>
                </div>
                <div class="site-card bg-slate-50">
                    <h3 class="site-h3">What to include in your request</h3>
                    <ul class="mt-3 list-disc space-y-1 pl-5 text-slate-700">
                        <li>Your team size and roles</li>
                        <li>Which modules you plan to use</li>
                        <li>Approximate monthly file volume</li>
                    </ul>
                    <p class="mt-4 text-sm text-slate-600">Please don’t send borrower documents or account information through the contact form.</p>
                </div>
            </div>
        @endif
    </div>
</section>
@include('site.partials.cta')
@endsection
