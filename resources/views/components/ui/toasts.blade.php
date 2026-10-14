{{-- Toast region (component doc §4), once per layout. Shows the flashed `status` message and any
     `toast` browser event (Livewire: $this->dispatch('toast', message: '…', variant: 'success')).
     Success/info auto-dismiss after 5 s; errors stay until closed. Behaviour: resources/js/ui/toasts.js. --}}
<div data-toasts aria-live="polite" class="pointer-events-none fixed inset-x-4 bottom-[calc(72px+env(safe-area-inset-bottom))] z-50 flex flex-col items-center gap-2 lg:inset-x-auto lg:right-6 lg:bottom-6 lg:items-end"
    data-close-label="{{ __('ui.close') }}">
    @if (session('status'))
        <div data-toast data-variant="success" role="status" class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-card border border-line bg-surface p-3 text-ui text-fg shadow-overlay">
            <x-ui.icon name="circle-check" size="20" class="text-success" />
            <p class="flex-1">{{ session('status') }}</p>
            <button type="button" data-toast-close aria-label="{{ __('ui.close') }}" class="text-fg-muted hover:text-fg"><x-ui.icon name="x" size="16" /></button>
        </div>
    @endif
    <template data-toast-template>
        <div data-toast class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-card border border-line bg-surface p-3 text-ui text-fg shadow-overlay">
            <span data-toast-icon></span>
            <p class="flex-1" data-toast-message></p>
            <button type="button" data-toast-close aria-label="{{ __('ui.close') }}" class="text-fg-muted hover:text-fg"><x-ui.icon name="x" size="16" /></button>
        </div>
    </template>
    <template data-toast-icon-success><x-ui.icon name="circle-check" size="20" class="text-success" /></template>
    <template data-toast-icon-error><x-ui.icon name="circle-alert" size="20" class="text-danger" /></template>
    <template data-toast-icon-info><x-ui.icon name="info" size="20" class="text-info" /></template>
</div>
