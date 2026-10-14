{{-- Photo gallery (components §5.2): a scroll-snap strip (swipe on touch), previous / next buttons,
     ← → keys, counter "3 / 8", thumbnails and a full-screen view. Without JavaScript it is still a
     scrollable row of photos. Behaviour: resources/js/ui/listing.js. --}}
@if ($images->isEmpty())
    @php($icon = ($category->parent?->icon ?? $category->icon) ?: 'package')
    <div class="grid aspect-[4/3] place-items-center rounded-card border border-line bg-surface-muted text-fg-subtle">
        <x-ui.icon :name="\App\Support\Icons::exists($icon) ? $icon : 'package'" size="24" />
    </div>
@else
    <section aria-label="{{ __('listings.detail.photos') }}" data-gallery class="space-y-2">
        <div class="relative overflow-hidden rounded-card border border-line bg-[#0a0a0a]">
            <ul data-gallery-track tabindex="0" aria-label="{{ __('listings.detail.photos_hint') }}"
                class="flex aspect-[4/3] snap-x snap-mandatory overflow-x-auto scroll-smooth [scrollbar-width:none] focus-visible:outline-offset-[-2px] [&::-webkit-scrollbar]:hidden">
                @foreach ($images as $image)
                    <li class="relative size-full shrink-0 snap-center" data-gallery-slide>
                        <button type="button" class="block size-full cursor-zoom-in" data-gallery-zoom="{{ $image->url('lg') }}" aria-label="{{ __('listings.detail.zoom', ['n' => $loop->iteration]) }}">
                            <img src="{{ $image->url('lg') }}" alt="{{ $listing->title }} – {{ __('listings.detail.photo_n', ['n' => $loop->iteration, 'total' => $images->count()]) }}"
                                width="{{ $image->width }}" height="{{ $image->height }}" class="size-full object-contain"
                                @if ($loop->first) fetchpriority="high" @else loading="lazy" @endif decoding="async">
                        </button>
                    </li>
                @endforeach
            </ul>
            @if ($images->count() > 1)
                <button type="button" data-gallery-prev aria-label="{{ __('ui.previous') }}" class="absolute left-2 top-1/2 grid size-10 -translate-y-1/2 place-items-center rounded-full bg-surface/90 text-fg shadow-overlay hover:bg-surface"><x-ui.icon name="chevron-left" size="20" /></button>
                <button type="button" data-gallery-next aria-label="{{ __('ui.next') }}" class="absolute right-2 top-1/2 grid size-10 -translate-y-1/2 place-items-center rounded-full bg-surface/90 text-fg shadow-overlay hover:bg-surface"><x-ui.icon name="chevron-right" size="20" /></button>
                <p class="absolute bottom-2 right-2 rounded-full bg-overlay px-2.5 py-0.5 text-micro tabular text-white" aria-live="polite" data-gallery-counter>1 / {{ $images->count() }}</p>
            @endif
        </div>
        @if ($images->count() > 1)
            <ul class="flex gap-2 overflow-x-auto pb-1" aria-label="{{ __('listings.detail.thumbnails') }}">
                @foreach ($images as $image)
                    <li class="shrink-0">
                        <button type="button" data-gallery-thumb="{{ $loop->index }}" aria-label="{{ __('listings.detail.photo_n', ['n' => $loop->iteration, 'total' => $images->count()]) }}" @if ($loop->first) aria-current="true" @endif
                            class="block h-14 w-[75px] overflow-hidden rounded-control border-2 border-transparent opacity-70 aria-[current]:border-primary aria-[current]:opacity-100 hover:opacity-100">
                            <img src="{{ $image->url('sm') }}" alt="" width="75" height="56" loading="lazy" class="size-full object-cover">
                        </button>
                    </li>
                @endforeach
            </ul>
        @endif
        <dialog id="gallery-zoom" aria-label="{{ __('listings.detail.photos') }}" class="m-0 size-full max-h-none max-w-none bg-[#0a0a0a] p-0 backdrop:bg-black/90">
            <x-ui.button variant="secondary" icon="x" :label="__('ui.close')" class="fixed right-4 top-4 z-10" data-modal-close />
            <img src="" alt="" data-gallery-zoom-image class="size-full object-contain">
        </dialog>
    </section>
@endif
