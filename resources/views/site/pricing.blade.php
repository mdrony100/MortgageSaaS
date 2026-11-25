@extends('site.layout')
@section('content')
@php $plans = \App\Website\Site::publishedPlans(); @endphp
@include('site.partials.page-header', ['eyebrow' => 'Pricing', 'heading' => $plans ? 'Plans and pricing' : 'Contact for pricing', 'lead' => $plans ? 'Monthly prices for each plan. Request a demo and the team will contact you. Payment is arranged offline.' : 'Pricing is tailored to your team. Request a demo or contact sales and we’ll discuss your workflow and requirements.', 'headerInnerClass' => 'pt-16 pb-3 md:pt-24 md:pb-4'])

<section class="pt-2 pb-12 md:pt-6 md:pb-16" aria-labelledby="pricing-heading">
    <div class="site-container">
        <h2 id="pricing-heading" class="sr-only">Pricing options</h2>
        @if ($plans)
            <div class="site-plan-grid grid gap-6 {{ [1 => 'md:grid-cols-1', 2 => 'md:grid-cols-2', 3 => 'md:grid-cols-3', 4 => 'md:grid-cols-2 xl:grid-cols-4'][count($plans)] ?? 'md:grid-cols-3' }}">
                @foreach ($plans as $plan)
                    <article class="site-card site-plan-card">
                        <h3 class="site-h3">{{ $plan['name'] }}</h3>
                        <p class="site-plan-price">{{ $plan['price_label'] }}</p>
                        <p class="site-caption mt-2">Payment is arranged offline.</p>
                        @if (!empty($plan['summary']))<p class="site-copy mt-2">{{ $plan['summary'] }}</p>@endif
                        @if (!empty($plan['includes']))
                            <ul class="site-benefits">@foreach ($plan['includes'] as $item)<li class="site-check"><span>{{ $item }}</span></li>@endforeach</ul>
                        @else
                            <div class="site-plan-spacer"></div>
                        @endif
                        <a href="{{ route('site.demo') }}" class="site-btn site-btn-teal justify-center">Request a demo</a>
                    </article>
                @endforeach
            </div>
        @else
            <div class="grid grid-cols-[minmax(0,1fr)] gap-8 lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]">
                <div class="site-card site-panel">
                    <p class="site-eyebrow">All modules</p>
                    <p class="site-h2 mt-3">Contact for pricing</p>
                    <p class="site-copy mt-4">Every organization can use bank statement analysis, document integrity review, appraisal review and rental/DSCR analysis in one workspace.</p>
                    <ul class="site-benefits mt-6">
                        <li class="site-check"><span>Usage allowances, such as loan files, team members and document storage, depend on the selected plan.</span></li>
                        <li class="site-check"><span>Role-based team access and file sharing.</span></li>
                        <li class="site-check"><span>Versioned Excel and PDF reports.</span></li>
                    </ul>
                    <div class="mt-6 flex flex-wrap gap-3">
                        <a href="{{ route('site.demo') }}" class="site-btn site-btn-teal">Request a Demo</a>
                        <a href="{{ route('site.contact') }}" class="site-btn site-btn-ghost">Contact us</a>
                    </div>
                </div>
                <div class="site-card bg-tint">
                    <h3 class="site-h3">What to include in your request</h3>
                    <ul class="mt-4 list-disc space-y-2 pl-5 text-body">
                        <li>Your team size and roles</li>
                        <li>Which modules you plan to use</li>
                        <li>Approximate monthly file volume</li>
                    </ul>
                    <p class="site-caption mt-5">Please don’t send borrower documents or account information through the contact form.</p>
                </div>
            </div>
        @endif
    </div>
</section>
@include('site.partials.cta')
@endsection
