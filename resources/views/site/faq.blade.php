@extends('site.layout')
@section('content')
@include('site.partials.page-header', ['eyebrow' => 'FAQ', 'heading' => 'Frequently asked questions', 'lead' => 'Plain-English answers about documents, calculations, permissions, and what the platform does and doesn’t do.'])
<section class="site-section" aria-label="Questions and answers">
    <div class="site-container max-w-4xl">
        @include('site.partials.faq-list', ['items' => \App\Website\Site::faq()])
        <p class="site-copy mt-10">Have another question? <a class="font-semibold text-accent-dark underline" href="{{ route('site.contact') }}?type=general">Contact us</a>.</p>
    </div>
</section>
@include('site.partials.cta')
@endsection
