<div class="site-faq">
    @foreach ($items as [$q, $a])
        <details>
            <summary>{{ $q }}</summary>
            <div class="site-faq-answer"><p>{{ $a }}</p></div>
        </details>
    @endforeach
</div>
