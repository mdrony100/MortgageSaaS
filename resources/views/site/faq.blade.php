@extends('site.layout')
@section('content')
@include('site.partials.page-header', ['eyebrow' => 'FAQ', 'heading' => 'Frequently asked questions', 'lead' => 'Answers about supported documents, calculations, permissions and what the platform does and does not do.'])
<section class="py-14 md:py-20" aria-label="Questions and answers">
    <div class="site-container max-w-4xl">
        @include('site.partials.faq-list', ['items' => \App\Website\Site::faq()])
        <p class="mt-8 text-slate-700">Have another question? <a class="font-semibold text-accent-dark underline" href="{{ route('site.contact') }}?type=general">Contact us</a>.</p>
    </div>
</section>
@include('site.partials.cta')
@endsection
