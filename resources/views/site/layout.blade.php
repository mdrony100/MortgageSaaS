@php
    use App\Website\Site;
    $product = Site::name();
    [$path, $title, $description] = Site::pages()[$routeName];
    $canonical = Site::url($path);
    $signedIn = auth('web')->check();
    $nav = [['site.home', 'Home'], ['site.features', 'Features'], ['site.how', 'How It Works'], ['site.pricing', 'Pricing'], ['site.faq', 'FAQ'], ['site.contact', 'Contact']];
@endphp
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <meta name="description" content="{{ $description }}">
    <link rel="canonical" href="{{ $canonical }}">
    <meta name="robots" content="{{ Site::indexable($routeName) ? 'index,follow' : 'noindex,nofollow' }}">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $product }}">
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ $canonical }}">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="{{ $title }}">
    <meta name="twitter:description" content="{{ $description }}">
    <meta name="theme-color" content="#102840">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    @vite(['resources/css/site.css', 'resources/js/site.ts'])
</head>
<body class="site-body">
<a class="skip-link" href="#main">Skip to main content</a>
<header class="site-header">
    <div class="site-container flex h-16 items-center justify-between gap-4">
        <a href="{{ route('site.home') }}" class="site-brand" aria-label="{{ $product }} home">
            <span class="site-brand-mark" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M5 21V10l7-5 7 5v11M9 21v-6h6v6"/></svg>
            </span>
            {{ $product }}
        </a>
        <nav aria-label="Primary" class="hidden lg:block">
            <ul class="flex items-center gap-1">
                @foreach ($nav as [$r, $label])
                    <li><a href="{{ route($r) }}" class="site-nav-link" @if($routeName === $r) aria-current="page" @endif>{{ $label }}</a></li>
                @endforeach
            </ul>
        </nav>
        <div class="hidden items-center gap-2 lg:flex">
            @if ($signedIn)
                <a href="/dashboard" class="site-btn site-btn-primary">Open Dashboard</a>
            @else
                <a href="/login" class="site-btn site-btn-ghost">Sign In</a>
                <a href="{{ route('site.contact') }}?type=demo" class="site-btn site-btn-primary">Request a Demo</a>
            @endif
        </div>
        <button type="button" class="site-menu-button lg:hidden" data-menu-button aria-expanded="false" aria-controls="site-mobile-nav">
            <svg aria-hidden="true" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
            <span class="sr-only">Menu</span>
        </button>
    </div>
    <nav id="site-mobile-nav" aria-label="Mobile" class="site-mobile-nav lg:hidden" hidden data-mobile-nav>
        <ul class="site-container flex flex-col gap-1 py-4">
            @foreach ($nav as [$r, $label])
                <li><a href="{{ route($r) }}" class="site-mobile-link" @if($routeName === $r) aria-current="page" @endif>{{ $label }}</a></li>
            @endforeach
            <li><a href="{{ route('site.security') }}" class="site-mobile-link" @if($routeName === 'site.security') aria-current="page" @endif>Security</a></li>
            <li class="mt-3 flex flex-col gap-2 border-t border-slate-200 pt-4">
                @if ($signedIn)
                    <a href="/dashboard" class="site-btn site-btn-primary justify-center">Open Dashboard</a>
                @else
                    <a href="/login" class="site-btn site-btn-ghost justify-center">Sign In</a>
                    <a href="{{ route('site.contact') }}?type=demo" class="site-btn site-btn-primary justify-center">Request a Demo</a>
                @endif
            </li>
        </ul>
    </nav>
</header>

<main id="main" tabindex="-1">
    @yield('content')
</main>

<footer class="site-footer">
    <div class="site-container grid gap-10 py-12 md:grid-cols-4">
        <div class="md:col-span-1">
            <p class="text-lg font-semibold text-white">{{ $product }}</p>
            <p class="mt-2 text-sm text-slate-300">Mortgage document analysis that supports professional review. {{ $product }} does not make lending decisions.</p>
        </div>
        <div>
            <h2 class="site-footer-heading">Product</h2>
            <ul class="mt-3 space-y-2 text-sm">
                <li><a href="{{ route('site.features') }}">Features</a></li>
                <li><a href="{{ route('site.how') }}">How It Works</a></li>
                <li><a href="{{ route('site.pricing') }}">Pricing</a></li>
                <li><a href="{{ route('site.security') }}">Security</a></li>
                <li><a href="{{ route('site.faq') }}">FAQ</a></li>
            </ul>
        </div>
        <div>
            <h2 class="site-footer-heading">Company</h2>
            <ul class="mt-3 space-y-2 text-sm">
                <li><a href="{{ route('site.contact') }}">Contact</a></li>
                <li><a href="{{ route('site.contact') }}?type=demo">Request a Demo</a></li>
                <li><a href="{{ $signedIn ? '/dashboard' : '/login' }}">{{ $signedIn ? 'Open Dashboard' : 'Sign In' }}</a></li>
                @if (config('website.contact.email'))
                    <li><a href="mailto:{{ config('website.contact.email') }}">{{ config('website.contact.email') }}</a></li>
                @endif
                @if (config('website.contact.phone'))
                    <li><a href="tel:{{ preg_replace('/[^0-9+]/', '', config('website.contact.phone')) }}">{{ config('website.contact.phone') }}</a></li>
                @endif
            </ul>
        </div>
        <div>
            <h2 class="site-footer-heading">Legal</h2>
            <ul class="mt-3 space-y-2 text-sm">
                <li><a href="{{ route('site.privacy') }}">Privacy Policy</a></li>
                <li><a href="{{ route('site.terms') }}">Terms of Service</a></li>
            </ul>
        </div>
    </div>
    <div class="border-t border-white/10">
        <p class="site-container py-6 text-xs text-slate-400">Product previews on this site use fictional sample data. &copy; {{ date('Y') }} {{ config('website.legal.company_name') ?: $product }}.</p>
    </div>
</footer>
</body>
</html>
