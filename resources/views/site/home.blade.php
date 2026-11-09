@extends('site.layout')
@section('content')
{{-- 1. What it does --}}
<section class="relative overflow-hidden bg-gradient-to-b from-[#eef8f6] via-white to-white" aria-labelledby="hero-heading">
    <div class="site-hero-grid site-container grid grid-cols-[minmax(0,1fr)] items-center gap-12 pb-20 pt-14 md:pt-20 lg:grid-cols-[minmax(0,1.05fr)_minmax(0,1fr)] lg:gap-14">
        <div class="site-hero-in">
            <p class="site-eyebrow">For brokers, loan officers, processors and underwriters</p>
            <h1 id="hero-heading" class="site-hero-title mt-5">Understand mortgage documents.<br> Review every detail with confidence.</h1>
            <p class="site-lead mt-6">Review bank statements, spot potential document issues, and check appraisal and rental income details—all in one workspace.</p>
            <div class="mt-9 flex flex-wrap gap-3">
                <a href="{{ route('site.contact') }}?type=demo" class="site-btn site-btn-primary">Request a Demo</a>
                <a href="#walkthrough" class="site-btn site-btn-ghost" data-walkthrough-start>See How It Works</a>
            </div>
            <p class="site-caption mt-6">Your team makes the decisions. {{ \App\Website\Site::name() }} doesn’t approve or decline loans.</p>
        </div>
        @include('site.partials.home.hero-preview')
    </div>
</section>

{{-- 2. How it works --}}
@include('site.partials.home.walkthrough')

{{-- 3. What users receive --}}
@include('site.partials.home.stories')

{{-- 4. Who it's for, and why to trust it --}}
@include('site.partials.home.team')
@include('site.partials.home.reviews')
@include('site.partials.home.security')

<section class="site-section" aria-labelledby="faq-heading">
    <div class="site-container grid grid-cols-[minmax(0,1fr)] gap-10 lg:grid-cols-[minmax(0,.8fr)_minmax(0,1.6fr)]">
        <div data-reveal>
            <p class="site-eyebrow">FAQ</p>
            <h2 id="faq-heading" class="site-h2 mt-3">Questions teams ask first</h2>
            <p class="site-copy mt-4">Short answers in plain English.</p>
            <a href="{{ route('site.faq') }}" class="site-link mt-4">See all questions <span aria-hidden="true">→</span></a>
        </div>
        @include('site.partials.faq-list', ['items' => \App\Website\Site::homeFaq()])
    </div>
</section>

{{-- 5. How to get started --}}
@include('site.partials.cta')
@endsection
