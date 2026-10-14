{{-- Light / Dark / System (design system §9). The pressed state is set by resources/js/app.js. --}}
<div role="group" aria-label="{{ __('ui.theme.label') }}" class="inline-flex rounded-control border border-line bg-surface p-0.5">
    @foreach (['light' => 'sun', 'dark' => 'moon', 'system' => 'monitor'] as $choice => $icon)
        <button type="button" data-theme-choice="{{ $choice }}" aria-pressed="false" aria-label="{{ __('ui.theme.'.$choice) }}" title="{{ __('ui.theme.'.$choice) }}"
            class="grid size-8 place-items-center rounded-[4px] text-fg-muted hover:text-fg aria-pressed:bg-surface-muted aria-pressed:text-fg">
            <x-ui.icon :name="$icon" size="16" />
        </button>
    @endforeach
</div>
