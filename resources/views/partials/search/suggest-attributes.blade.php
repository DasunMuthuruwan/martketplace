{{-- Attributes that turn a search input into a live-search combobox (G1, resources/js/ui/suggest.js). --}}
data-suggest-url="{{ route('search.suggest') }}" data-suggest-cc="{{ $cc ?? '' }}"
data-suggest-types="{{ json_encode(__('search.suggest.types')) }}" data-suggest-label="{{ __('search.suggest.label') }}" data-suggest-count="{{ __('search.suggest.count') }}"
