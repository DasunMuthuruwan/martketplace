{{-- One listing card. Params: $listing, $present, $withCode (mixed-country pages). --}}
<li>
    <a href="{{ $listing->publicUrl() }}">
        @if ($cover = $present->coverUrl($listing))
            <img src="{{ $cover }}" alt="" width="480" height="360" loading="lazy">
        @endif
        <strong>{{ $listing->title }}</strong>
    </a>
    <div>{{ $present->price($listing, $withCode ?? false) }}</div>
    @if ($unit = $present->unitPrice($listing))
        <div class="muted">{{ $unit }}</div>
    @endif
    <div class="muted">{{ $present->location($listing, $withCode ?? false) }}</div>
</li>
