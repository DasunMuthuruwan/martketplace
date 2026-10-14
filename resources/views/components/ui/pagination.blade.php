@props([
    'paginator',          // LengthAwarePaginator
    'livewire' => false,  // Livewire component: buttons call its page methods
])
{{-- Prev / numbers / Next with "Page 2 of 14"; phones get Prev / Next only (component doc §3). --}}
@if ($paginator->hasPages())
    @php
        $current = $paginator->currentPage();
        $last = $paginator->lastPage();
        $pages = collect([1, $last, $current - 1, $current, $current + 1])->filter(fn ($p) => $p >= 1 && $p <= $last)->unique()->sort()->values();
        $pageName = $paginator->getPageName();
        $base = 'inline-flex h-10 min-w-10 items-center justify-center gap-1 rounded-control border px-3 text-ui';
        $idle = $base.' border-line bg-surface text-fg hover:bg-surface-hover';
        $off = $base.' border-line bg-surface text-fg-subtle opacity-50';
    @endphp
    <nav aria-label="{{ __('ui.pagination') }}" {{ $attributes->class('flex flex-wrap items-center justify-between gap-3') }}>
        <p class="text-small text-fg-subtle">{{ __('ui.page_of', ['page' => $current, 'last' => $last]) }}</p>
        <ul class="flex items-center gap-1">
            <li>
                @if ($paginator->onFirstPage())
                    <span class="{{ $off }}" aria-disabled="true"><x-ui.icon name="chevron-left" size="16" /><span class="sr-only sm:not-sr-only">{{ __('ui.previous') }}</span></span>
                @elseif ($livewire)
                    <button type="button" wire:click="previousPage('{{ $pageName }}')" rel="prev" class="{{ $idle }}"><x-ui.icon name="chevron-left" size="16" /><span class="sr-only sm:not-sr-only">{{ __('ui.previous') }}</span></button>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="{{ $idle }}"><x-ui.icon name="chevron-left" size="16" /><span class="sr-only sm:not-sr-only">{{ __('ui.previous') }}</span></a>
                @endif
            </li>
            @foreach ($pages as $page)
                @if (! $loop->first && $page - $pages[$loop->index - 1] > 1)
                    <li class="hidden px-1 text-fg-subtle sm:block" aria-hidden="true">…</li>
                @endif
                <li class="hidden sm:block">
                    @if ($page === $current)
                        <span aria-current="page" class="{{ $base }} border-primary bg-primary font-semibold text-primary-fg">{{ $page }}</span>
                    @elseif ($livewire)
                        <button type="button" wire:click="gotoPage({{ $page }}, '{{ $pageName }}')" class="{{ $idle }}" aria-label="{{ __('ui.go_to_page', ['page' => $page]) }}">{{ $page }}</button>
                    @else
                        <a href="{{ $paginator->url($page) }}" class="{{ $idle }}" aria-label="{{ __('ui.go_to_page', ['page' => $page]) }}">{{ $page }}</a>
                    @endif
                </li>
            @endforeach
            <li>
                @if (! $paginator->hasMorePages())
                    <span class="{{ $off }}" aria-disabled="true"><span class="sr-only sm:not-sr-only">{{ __('ui.next') }}</span><x-ui.icon name="chevron-right" size="16" /></span>
                @elseif ($livewire)
                    <button type="button" wire:click="nextPage('{{ $pageName }}')" rel="next" class="{{ $idle }}"><span class="sr-only sm:not-sr-only">{{ __('ui.next') }}</span><x-ui.icon name="chevron-right" size="16" /></button>
                @else
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="{{ $idle }}"><span class="sr-only sm:not-sr-only">{{ __('ui.next') }}</span><x-ui.icon name="chevron-right" size="16" /></a>
                @endif
            </li>
        </ul>
    </nav>
@endif
