{{-- Fictional monthly deposits (in thousands of dollars). Purely illustrative. --}}
@php
    $months = [['Jan', 8.1], ['Feb', 8.6], ['Mar', 7.9], ['Apr', 9.2], ['May', 8.4], ['Jun', 8.8], ['Jul', 9.5], ['Aug', 8.2], ['Sep', 8.9], ['Oct', 9.1], ['Nov', 8.7], ['Dec', 9.3]];
@endphp
<div>
    <div class="site-chart" style="--bars: 12" role="img" aria-label="Sample bar chart of monthly qualifying deposits, January to December, ranging from about $7,900 to $9,500.">
        @foreach ($months as $i => [$m, $v])
            <span class="site-bar" style="--h: {{ $v * 10 }}%; --i: {{ $i }}"></span>
        @endforeach
    </div>
    <div class="site-chart-labels" style="--bars: 12" aria-hidden="true">
        @foreach ($months as $i => [$m, $v])<span>{{ $i % 2 === 0 ? $m : '' }}</span>@endforeach
    </div>
</div>
