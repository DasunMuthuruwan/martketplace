@extends('layouts.wizard')

@section('step_title', __('posting.photos.title'))
@section('step_intro', __('posting.photos.intro', ['max' => $max]))

@section('step')
    {{-- Photos (components §2 image-uploader): add several at once (camera on phones), errors per file,
         move earlier / later with buttons (keyboard friendly), set the cover, remove. --}}
    @php($full = $images->count() >= $max)

    {{-- A submit sent back here (no photos) brings its reasons along. --}}
    <x-ui.form-errors class="mb-4" :title="__('posting.review.not_ready')" />

    @if (session('photo_errors'))
        <x-ui.alert variant="danger" :title="__('posting.photos.some_failed')" class="mb-4">
            <ul class="list-disc pl-4">@foreach (session('photo_errors') as $message)<li>{{ $message }}</li>@endforeach</ul>
        </x-ui.alert>
    @endif

    <p class="mb-3 text-ui text-fg-muted" aria-live="polite"><span class="tabular font-semibold text-fg">{{ $images->count() }} / {{ $max }}</span> {{ __('posting.photos.count') }}</p>

    @if ($images->isNotEmpty())
        <ul class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-3">
            @foreach ($images as $image)
                @php($isCover = $listing->cover_image_id === $image->id || ($listing->cover_image_id === null && $loop->first))
                <li class="overflow-hidden rounded-card border border-line bg-surface">
                    <div class="relative aspect-[4/3] bg-surface-muted">
                        <img src="{{ $image->url('sm') }}" alt="{{ __('posting.photos.photo_n', ['n' => $loop->iteration]) }}" width="480" height="360" loading="lazy" class="size-full object-cover">
                        @if ($isCover)<x-ui.badge variant="neutral" class="absolute left-2 top-2">{{ __('posting.photos.cover') }}</x-ui.badge>@endif
                    </div>
                    <div class="flex flex-wrap items-center gap-1 p-2">
                        <form method="POST" action="{{ route('listings.photos.move', [$listing, $image->id]) }}">@csrf<input type="hidden" name="direction" value="earlier">
                            <x-ui.button type="submit" variant="ghost" size="sm" icon="chevron-left" :label="__('posting.photos.earlier', ['n' => $loop->iteration])" :disabled="$loop->first" /></form>
                        <form method="POST" action="{{ route('listings.photos.move', [$listing, $image->id]) }}">@csrf<input type="hidden" name="direction" value="later">
                            <x-ui.button type="submit" variant="ghost" size="sm" icon="chevron-right" :label="__('posting.photos.later', ['n' => $loop->iteration])" :disabled="$loop->last" /></form>
                        @unless ($isCover)
                            <form method="POST" action="{{ route('listings.photos.cover', [$listing, $image->id]) }}">@csrf
                                <x-ui.button type="submit" variant="ghost" size="sm">{{ __('posting.photos.make_cover') }}</x-ui.button></form>
                        @endunless
                        <form method="POST" action="{{ route('listings.photos.destroy', [$listing, $image->id]) }}" class="ml-auto">@csrf @method('DELETE')
                            <x-ui.button type="submit" variant="ghost" size="sm" icon="trash-2" :label="__('posting.photos.remove', ['n' => $loop->iteration])" class="text-danger" /></form>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif

    @unless ($full)
        <form method="POST" action="{{ route('listings.photos.store', $listing) }}" enctype="multipart/form-data" class="mb-6" data-photo-upload>
            @csrf
            <label for="photos" class="flex cursor-pointer flex-col items-center gap-2 rounded-card border-2 border-dashed border-control px-4 py-8 text-center hover:bg-surface-hover focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-primary">
                <x-ui.icon name="camera" size="24" class="text-fg-muted" />
                <span class="text-ui font-semibold text-fg">{{ __('posting.photos.add') }}</span>
                <span class="text-small text-fg-subtle">{{ __('posting.photos.rules', ['mb' => $maxMb]) }}</span>
                <input type="file" id="photos" name="photos[]" multiple accept="image/jpeg,image/png,image/webp" class="sr-only">
            </label>
            <div class="mt-2 hidden" data-upload-progress>
                <div class="h-2 overflow-hidden rounded-full bg-surface-muted"><div class="h-full w-0 bg-primary transition-[width]" data-upload-bar></div></div>
                <p class="mt-1 text-small text-fg-subtle" aria-live="polite" data-upload-text>{{ __('posting.photos.uploading') }}</p>
            </div>
            <x-ui.button type="submit" variant="secondary" class="mt-3" data-upload-submit>{{ __('posting.photos.upload') }}</x-ui.button>
        </form>
    @else
        <x-ui.alert class="mb-6">{{ __('posting.photos.full', ['max' => $max]) }}</x-ui.alert>
    @endunless

    <div class="flex justify-between gap-2 border-t border-line pt-4 max-lg:fixed max-lg:inset-x-0 max-lg:bottom-0 max-lg:z-30 max-lg:bg-surface max-lg:px-4 max-lg:pb-[calc(12px+env(safe-area-inset-bottom))] max-lg:pt-3">
        <x-ui.button variant="secondary" :href="route('listings.edit', [$listing, 'price'])" icon="chevron-left">{{ __('posting.back') }}</x-ui.button>
        @if ($images->isNotEmpty())
            <x-ui.button :href="route('listings.edit', [$listing, 'review'])" icon-right="chevron-right">{{ __('posting.continue') }}</x-ui.button>
        @else
            <x-ui.button :disabled="true" icon-right="chevron-right" :title="__('posting.photos.one_needed')">{{ __('posting.continue') }}</x-ui.button>
        @endif
    </div>
@endsection
