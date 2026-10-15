{{-- Back / Continue: under the form on desktop, a sticky bar on phones (layout doc §3.4). --}}
<div class="flex justify-between gap-2 border-t border-line pt-4 max-lg:fixed max-lg:inset-x-0 max-lg:bottom-0 max-lg:z-30 max-lg:bg-surface max-lg:px-4 max-lg:pb-[calc(12px+env(safe-area-inset-bottom))] max-lg:pt-3">
    <x-ui.button variant="secondary" :href="$back" icon="chevron-left">{{ __('posting.back') }}</x-ui.button>
    <x-ui.button type="submit" icon-right="chevron-right">{{ $continue ?? __('posting.continue') }}</x-ui.button>
</div>
