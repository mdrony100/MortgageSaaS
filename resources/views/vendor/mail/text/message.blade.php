<x-mail::layout>
    {{-- Header --}}
    <x-slot:header>
        <x-mail::header :url="config('app.url')">
            {{ config('app.name') }}
        </x-mail::header>
    </x-slot:header>

    {{-- Body --}}
    {{ $slot }}

    {{-- Subcopy --}}
    @isset($subcopy)
        <x-slot:subcopy>
            <x-mail::subcopy>
                {{ $subcopy }}
            </x-mail::subcopy>
        </x-slot:subcopy>
    @endisset

    {{-- Footer --}}
    <x-slot:footer>
        <x-mail::footer>
            {{ config('notifications.company', 'Non-QM Doc AI') }}
            Questions? {{ config('notifications.support_email', 'info@nonqmdoc.ai') }}
            You received this email because of activity on your Non-QM Doc AI account.
        </x-mail::footer>
    </x-slot:footer>
</x-mail::layout>
