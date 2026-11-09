@extends('site.layout')
@section('content')
@php
    $types = config('website.inquiry_types');
    $selected = old('inquiry_type', array_key_exists((string) request('type'), $types) ? request('type') : 'demo');
    $field = fn ($name) => $errors->has($name) ? 'aria-invalid=true aria-describedby='.$name.'-error' : '';
@endphp
@include('site.partials.page-header', ['eyebrow' => 'Contact', 'heading' => 'Request a demo or ask a question', 'lead' => 'Tell us about your team and what you’d like to see. We’ll reply by email.'])

<section class="py-14 md:py-20">
    <div class="site-container grid gap-10 lg:grid-cols-[1.5fr_1fr]">
        <div>
            @if (session('inquiry_saved'))
                <div class="site-card border-accent bg-accent-tint" role="status" tabindex="-1" id="inquiry-success">
                    <h2 class="site-h3 text-xl">Thank you — your message has been received.</h2>
                    <p class="mt-2 text-slate-700">We’ve saved your request and the team will follow up by email.</p>
                </div>
            @else
                @if ($errors->any())
                    <div class="mb-6 rounded-lg border border-red-300 bg-red-50 p-4 text-red-900" role="alert" tabindex="-1" id="form-errors">
                        <p class="font-semibold">Please correct the highlighted fields.</p>
                        @if ($errors->has('form'))<p class="mt-1">{{ $errors->first('form') }}</p>@endif
                    </div>
                @endif
                <form method="POST" action="{{ route('site.contact.submit') }}" class="site-card grid gap-5" novalidate>
                    @csrf
                    <div class="grid gap-5 md:grid-cols-2">
                        <div class="site-field">
                            <label for="name">Name <span aria-hidden="true">*</span></label>
                            <input id="name" name="name" type="text" required maxlength="120" autocomplete="name" value="{{ old('name') }}" {!! $field('name') !!}>
                            @error('name')<p class="site-error" id="name-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="site-field">
                            <label for="email">Work email <span aria-hidden="true">*</span></label>
                            <input id="email" name="email" type="email" required maxlength="190" autocomplete="email" value="{{ old('email') }}" {!! $field('email') !!}>
                            @error('email')<p class="site-error" id="email-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="site-field">
                            <label for="company">Company <span aria-hidden="true">*</span></label>
                            <input id="company" name="company" type="text" required maxlength="150" autocomplete="organization" value="{{ old('company') }}" {!! $field('company') !!}>
                            @error('company')<p class="site-error" id="company-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="site-field">
                            <label for="role">Role <span class="font-normal text-slate-600">(optional)</span></label>
                            <input id="role" name="role" type="text" maxlength="120" autocomplete="organization-title" value="{{ old('role') }}" {!! $field('role') !!}>
                            @error('role')<p class="site-error" id="role-error">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div class="site-field">
                        <label for="inquiry_type">Inquiry type <span aria-hidden="true">*</span></label>
                        <select id="inquiry_type" name="inquiry_type" required {!! $field('inquiry_type') !!}>
                            @foreach ($types as $value => $label)
                                <option value="{{ $value }}" @selected($selected === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('inquiry_type')<p class="site-error" id="inquiry_type-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="site-field">
                        <label for="message">Message <span aria-hidden="true">*</span></label>
                        <textarea id="message" name="message" required minlength="10" maxlength="5000" aria-describedby="message-hint{{ $errors->has('message') ? ' message-error' : '' }}" @if($errors->has('message')) aria-invalid="true" @endif>{{ old('message') }}</textarea>
                        <p id="message-hint" class="mt-1 text-sm text-slate-600">Please don’t include borrower documents, account numbers or other financial account information.</p>
                        @error('message')<p class="site-error" id="message-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="site-hp" aria-hidden="true">
                        <label for="homepage_url">Leave this field empty</label>
                        <input id="homepage_url" name="homepage_url" type="text" tabindex="-1" autocomplete="off" value="">
                    </div>
                    <p class="text-sm text-slate-600">Fields marked * are required. We use your details only to respond to this request; see the <a class="underline" href="{{ route('site.privacy') }}">Privacy Policy</a>.</p>
                    <div><button type="submit" class="site-btn site-btn-primary cursor-pointer">Send request</button></div>
                </form>
            @endif
        </div>
        <aside class="grid content-start gap-6">
            <div class="site-card bg-slate-50">
                <h2 class="site-h3">What happens next</h2>
                <ol class="mt-3 list-decimal space-y-1 pl-5 text-slate-700">
                    <li>Your request is saved for our team to review.</li>
                    <li>We reply by email to arrange a time.</li>
                    <li>Demos use fictional sample files only.</li>
                </ol>
            </div>
            @if (config('website.contact.email') || config('website.contact.phone'))
                <div class="site-card">
                    <h2 class="site-h3">Other ways to reach us</h2>
                    <ul class="mt-3 space-y-1 text-slate-700">
                        @if (config('website.contact.email'))<li>Email: <a class="underline" href="mailto:{{ config('website.contact.email') }}">{{ config('website.contact.email') }}</a></li>@endif
                        @if (config('website.contact.phone'))<li>Phone: {{ config('website.contact.phone') }}</li>@endif
                    </ul>
                </div>
            @endif
            <div class="site-card">
                <h2 class="site-h3">Already a customer?</h2>
                <p class="mt-2 text-slate-700"><a class="underline" href="{{ auth('web')->check() ? '/dashboard' : '/login' }}">{{ auth('web')->check() ? 'Open Dashboard' : 'Sign in' }}</a> to access your workspace.</p>
            </div>
        </aside>
    </div>
</section>
@endsection
