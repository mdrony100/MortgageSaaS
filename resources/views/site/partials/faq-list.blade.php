<div class="site-faq divide-y divide-slate-200 border-y border-slate-200">
    @foreach ($items as [$q, $a])
        <details>
            <summary>{{ $q }}</summary>
            <p class="pb-5 text-slate-600">{{ $a }}</p>
        </details>
    @endforeach
</div>
