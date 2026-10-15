@props([
    'caption' => null, // accessible name (visually hidden)
])
{{-- Data table (components §5.4): dense rows, sticky header, hover; stacked cards below md, where each
     <td data-label="Column"> shows its column name. Numeric cells take class "num". --}}
<div {{ $attributes->class('overflow-x-auto rounded-card border border-line bg-surface') }}>
    <table class="data-table">
        @if ($caption)<caption class="sr-only">{{ $caption }}</caption>@endif
        {{ $slot }}
    </table>
</div>
