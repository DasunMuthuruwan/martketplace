@props([
    'items',              // [key => label] or [key => ['label' => …, 'href' => …, 'count' => …]]
    'active' => null,
    'label' => null,      // accessible name of the tab set
    'wireModel' => null,  // Livewire property switched by the buttons instead of links
])
{{-- Underline tabs (component doc §3). Server-side: each tab is a link (aria-current) or a Livewire
     button (aria-pressed); the page re-renders its content, so no hidden panels are needed. --}}
<nav @if ($label) aria-label="{{ $label }}" @endif {{ $attributes->class('-mb-px flex gap-1 overflow-x-auto border-b border-line') }}>
    @foreach ($items as $key => $item)
        @php
            $item = is_array($item) ? $item : ['label' => $item];
            $isActive = (string) $key === (string) $active;
            $classes = ['inline-flex h-10 shrink-0 items-center gap-2 border-b-2 px-3 text-ui font-medium transition-colors duration-(--duration-fast)',
                $isActive ? 'border-primary text-fg' : 'border-transparent text-fg-muted hover:text-fg'];
        @endphp
        @if ($wireModel)
            <button type="button" wire:click="$set('{{ $wireModel }}', '{{ $key }}')" aria-pressed="{{ $isActive ? 'true' : 'false' }}" @class($classes)>
                {{ $item['label'] }}@isset($item['count'])<span class="rounded-full bg-surface-muted px-1.5 text-micro tabular">{{ $item['count'] }}</span>@endisset
            </button>
        @else
            <a href="{{ $item['href'] ?? '#' }}" @if ($isActive) aria-current="page" @endif @class($classes)>
                {{ $item['label'] }}@isset($item['count'])<span class="rounded-full bg-surface-muted px-1.5 text-micro tabular">{{ $item['count'] }}</span>@endisset
            </a>
        @endif
    @endforeach
</nav>
