@extends('site.layout')
@section('content')
@php
    $field = fn ($name) => $errors->has($name) ? 'aria-invalid=true aria-describedby='.$name.'-error' : '';
@endphp
@include('site.partials.page-header', ['eyebrow' => 'Demo', 'heading' => 'Request a demo', 'lead' => 'Tell us about your team. We will contact you to arrange a time. This form does not start a subscription.'])

<section class="site-section">
    <div class="site-container grid grid-cols-[minmax(0,1fr)] gap-10 lg:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)]">
        <div>
            @if (session('demo_saved'))
                <div class="site-card border-accent bg-accent-tint" role="status" tabindex="-1" id="demo-success">
                    <h2 class="site-h3 text-xl">Thank you — your demo request has been received.</h2>
                    <p class="mt-3 text-body">We’ve saved your request and the team will follow up by email to arrange a time.</p>
                </div>
            @else
                @if ($errors->any())
                    <div class="mb-6 rounded-lg border border-red-300 bg-red-50 p-4 text-red-900" role="alert" tabindex="-1" id="form-errors">
                        <p class="font-semibold">Please correct the highlighted fields.</p>
                        @if ($errors->has('form'))<p class="mt-1">{{ $errors->first('form') }}</p>@endif
                    </div>
                @endif
                <form method="POST" action="{{ route('site.demo.submit') }}" class="site-panel demo-form grid gap-6 p-6 sm:p-8" novalidate>
                    @csrf
                    <div class="grid gap-5 md:grid-cols-2">
                        <div class="site-field">
                            <label for="name">Name <span aria-hidden="true">*</span></label>
                            <input id="name" name="name" type="text" required maxlength="120" autocomplete="name" placeholder="Jane Smith" value="{{ old('name') }}" {!! $field('name') !!}>
                            @error('name')<p class="site-error" id="name-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="site-field">
                            <label for="email">Work email <span aria-hidden="true">*</span></label>
                            <input id="email" name="email" type="email" required maxlength="190" autocomplete="email" placeholder="jane@company.com" value="{{ old('email') }}" {!! $field('email') !!}>
                            @error('email')<p class="site-error" id="email-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="site-field">
                            <label for="company">Company <span aria-hidden="true">*</span></label>
                            <input id="company" name="company" type="text" required maxlength="150" autocomplete="organization" placeholder="Acme Lending" value="{{ old('company') }}" {!! $field('company') !!}>
                            @error('company')<p class="site-error" id="company-error">{{ $message }}</p>@enderror
                        </div>
                        @include('site.partials.phone-field', ['countries' => $countries])
                    </div>
                    <div class="site-field">
                        <label for="captcha">What is {{ $captcha['a'] }} + {{ $captcha['b'] }}? <span aria-hidden="true">*</span></label>
                        <input id="captcha" name="captcha" type="text" inputmode="numeric" required maxlength="2" autocomplete="off" placeholder="Your answer" {!! $field('captcha') !!}>
                        @error('captcha')<p class="site-error" id="captcha-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="site-hp" aria-hidden="true">
                        <label for="company_website">Leave this field empty</label>
                        <input id="company_website" name="company_website" type="text" tabindex="-1" autocomplete="off" value="">
                    </div>
                    <p class="site-caption">Fields marked * are required. We use your details only to respond to this request; see the <a class="underline" href="{{ route('site.privacy') }}">Privacy Policy</a>.</p>
                    <div><button type="submit" class="site-btn site-btn-teal cursor-pointer">Request a demo</button></div>
                </form>
            @endif
        </div>
        <aside class="grid content-start gap-6">
            <div class="site-card bg-tint">
                <h2 class="site-h3">What happens next</h2>
                <ol class="mt-4 list-decimal space-y-2 pl-5 text-body">
                    <li>Your request is saved for our team to review.</li>
                    <li>We reply by email or phone to arrange a time.</li>
                    <li>A demo walks through the product with sample files.</li>
                </ol>
            </div>
            <div class="site-card">
                <h2 class="site-h3">Looking at plans?</h2>
                <p class="mt-3 text-body"><a class="underline" href="{{ route('site.pricing') }}">See the plans</a>, then come back here. Payment is arranged offline.</p>
            </div>
        </aside>
    </div>
</section>
@endsection
