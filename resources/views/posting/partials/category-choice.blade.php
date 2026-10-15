{{-- One subcategory to choose. New ad: a link to the location step. Existing draft: a small form that
     saves the new category and keeps the location. --}}
@if ($listing === null)
    <a href="{{ route('listings.create', ['category' => $id]) }}" class="flex items-center justify-between gap-2 rounded-control px-3 py-2.5 text-ui text-fg hover:bg-surface-hover">
        <span>{{ $label }}@if ($hint)<span class="ml-1.5 text-small text-fg-subtle">· {{ $hint }}</span>@endif</span>
        <x-ui.icon name="chevron-right" size="16" class="text-fg-subtle" />
    </a>
@else
    <form method="POST" action="{{ route('listings.update', [$listing, 'category']) }}">
        @csrf
        <input type="hidden" name="category_id" value="{{ $id }}">
        <input type="hidden" name="keep_location" value="1">
        <button type="submit" @if ($listing->category_id === $id) aria-current="true" @endif
            class="flex w-full items-center justify-between gap-2 rounded-control px-3 py-2.5 text-left text-ui text-fg hover:bg-surface-hover aria-[current]:bg-surface-muted aria-[current]:font-semibold">
            <span>{{ $label }}@if ($hint)<span class="ml-1.5 text-small font-normal text-fg-subtle">· {{ $hint }}</span>@endif</span>
            @if ($listing->category_id === $id)<x-ui.icon name="check" size="16" />@else<x-ui.icon name="chevron-right" size="16" class="text-fg-subtle" />@endif
        </button>
    </form>
@endif
