@php
    $selectedIso = strtoupper((string) old('phone_country', $selectedCountry ?? 'US'));
    $current = collect($countries)->firstWhere('iso', $selectedIso) ?? ($countries[0] ?? ['iso' => 'US', 'name' => 'United States', 'dial' => '1', 'flag' => '']);
@endphp
<div class="site-field">
    <label for="phone">Phone <span aria-hidden="true">*</span></label>
    <div class="phone-field" data-calling-code>
        <div class="phone-prefix">
            <select id="phone_country" name="phone_country" class="phone-native" required aria-label="Country code" {!! $field('phone_country') !!}>
                @foreach ($countries as $country)
                    <option value="{{ $country['iso'] }}" data-name="{{ $country['name'] }}" data-dial="{{ $country['dial'] }}" data-flag="{{ $country['flag'] }}" @selected($current['iso'] === $country['iso'])>{{ $country['flag'] }} +{{ $country['dial'] }}</option>
                @endforeach
            </select>
            <button type="button" class="phone-prefix-btn" aria-haspopup="listbox" aria-expanded="false" aria-controls="phone-country-list" aria-label="Country code, {{ $current['name'] }} +{{ $current['dial'] }}">
                <span class="phone-flag" data-flag aria-hidden="true">{{ $current['flag'] }}</span>
                <span class="phone-code-label" data-dial>+{{ $current['dial'] }}</span>
                <span class="phone-caret" aria-hidden="true"></span>
            </button>
            <div class="phone-menu" id="phone-country-list" hidden>
                <label class="sr-only" for="phone-country-search">Search country</label>
                <input id="phone-country-search" type="search" placeholder="Search country" autocomplete="off" aria-controls="phone-country-options">
                <ul id="phone-country-options" class="phone-menu-list" role="listbox" aria-label="Country calling code"></ul>
            </div>
        </div>
        <input id="phone" class="phone-number" name="phone" type="tel" required maxlength="30" autocomplete="tel" inputmode="tel" placeholder="201 555 0123" value="{{ old('phone') }}" {!! $field('phone') !!}>
    </div>
    @error('phone')<p class="site-error" id="phone-error">{{ $message }}</p>@enderror
    @error('phone_country')<p class="site-error" id="phone_country-error">{{ $message }}</p>@enderror
</div>
