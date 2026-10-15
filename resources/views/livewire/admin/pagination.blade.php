{{-- Livewire pagination for the admin screens: previous / "page n of m" / next. --}}
@if ($paginator->hasPages())
    <nav aria-label="{{ __('public.pages') }}" class="mt-4 flex items-center justify-between gap-3">
        <x-ui.button size="sm" variant="secondary" icon="chevron-left" wire:click="previousPage" rel="prev" :disabled="$paginator->onFirstPage()">{{ __('public.previous') }}</x-ui.button>
        <span class="text-small tabular text-fg-muted">{{ __('admin.page_of', ['page' => $paginator->currentPage(), 'pages' => $paginator->lastPage()]) }}</span>
        <x-ui.button size="sm" variant="secondary" icon-right="chevron-right" wire:click="nextPage" rel="next" :disabled="! $paginator->hasMorePages()">{{ __('public.next') }}</x-ui.button>
    </nav>
@endif
