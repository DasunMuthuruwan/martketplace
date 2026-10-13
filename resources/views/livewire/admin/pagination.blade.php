{{-- Minimal Livewire pagination for the plain admin views (previous / next). --}}
@if ($paginator->hasPages())
    <nav aria-label="{{ __('public.pages') }}">
        @unless ($paginator->onFirstPage())
            <button type="button" wire:click="previousPage" rel="prev">{{ __('public.previous') }}</button>
        @endunless
        <span class="muted">{{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>
        @if ($paginator->hasMorePages())
            <button type="button" wire:click="nextPage" rel="next">{{ __('public.next') }}</button>
        @endif
    </nav>
@endif
