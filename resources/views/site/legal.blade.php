@extends('site.layout')
@section('content')
@php $approved = (bool) config('website.legal.approved'); $product = \App\Website\Site::name(); @endphp
<section class="border-b border-slate-200 bg-slate-50">
    <div class="site-container py-14">
        <p class="site-eyebrow">Legal</p>
        <h1 class="site-h1 mt-3">{{ $heading }}</h1>
        @if ($approved && config('website.legal.last_updated'))
            <p class="mt-3 text-slate-600">Last updated {{ config('website.legal.last_updated') }}</p>
        @endif
    </div>
</section>
<section class="py-12">
    <div class="site-container max-w-3xl">
        @if ($approved && view()->exists('site.legal-approved.'.$doc))
            <div class="site-prose">@include('site.legal-approved.'.$doc)</div>
        @else
            <div class="rounded-xl border-2 border-amber-400 bg-amber-50 p-5" role="note">
                <p class="text-lg font-bold text-amber-900">DRAFT — not an approved legal document</p>
                <p class="mt-2 text-amber-900">This page is development placeholder copy. It has not been reviewed or approved and is not the {{ strtolower($heading) }} of any company. The final text, the legal entity, contact address, governing jurisdiction and any retention periods will be published after legal approval.</p>
            </div>
            <div class="site-prose mt-8">
                @if ($doc === 'privacy')
                    <h2>Topics the approved policy will cover</h2>
                    <ul>
                        <li>Who is responsible for personal information processed through {{ $product }} — <em>[legal entity pending approval]</em>.</li>
                        <li>Information collected through this website’s contact form (name, work email, company, optional role, inquiry type and message) and why.</li>
                        <li>Information processed in customer workspaces on behalf of customer organizations.</li>
                        <li>Service providers used to operate the platform, including any optional AI document-processing provider when enabled.</li>
                        <li>Retention periods — <em>[pending approval]</em>.</li>
                        <li>Individual rights and how to make requests — <em>[contact details pending approval]</em>.</li>
                    </ul>
                    <h2>Current website behavior</h2>
                    <p>This website does not use analytics or advertising trackers. Contact form submissions are stored so the team can respond to them.</p>
                @else
                    <h2>Topics the approved terms will cover</h2>
                    <ul>
                        <li>The contracting party — <em>[legal entity pending approval]</em>.</li>
                        <li>Acceptable use of the platform and customer responsibilities for uploaded documents.</li>
                        <li>That {{ $product }} supports professional review and does not make lending decisions or replace underwriting judgment.</li>
                        <li>Subscription, billing and cancellation terms — <em>[pending approval]</em>.</li>
                        <li>Governing law and jurisdiction — <em>[pending approval]</em>.</li>
                    </ul>
                @endif
            </div>
        @endif
    </div>
</section>
@endsection
