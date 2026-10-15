@extends('layouts.wizard')

@section('step_title', __('posting.category.title'))
@section('step_intro', $listing ? __('posting.category.change_intro') : __('posting.category.intro'))

@section('step')
    @php
        $base = $listing ? route('listings.edit', [$listing, 'category']) : route('listings.create');
        $leaves = $tree->flatMap(fn ($r) => $r->children->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'parent' => $r->name]));
    @endphp

    @error('category_id')<x-ui.alert variant="danger" class="mb-4">{{ $message }}</x-ui.alert>@enderror

    {{-- Search across every subcategory (JavaScript filters the list; without it, browse below). --}}
    <div class="mb-5" data-category-search>
        <x-ui.input type="search" :label="__('posting.category.search')" name="category_search" id="f-category-search" autocomplete="off" data-category-filter />
        <ul class="mt-2 hidden max-h-72 space-y-0.5 overflow-y-auto rounded-card border border-line p-1" data-category-results>
            @foreach ($leaves as $leaf)
                <li data-category-name="{{ mb_strtolower($leaf['parent'].' '.$leaf['name']) }}" hidden>
                    @include('posting.partials.category-choice', ['id' => $leaf['id'], 'label' => $leaf['name'], 'hint' => $leaf['parent']])
                </li>
            @endforeach
        </ul>
    </div>

    @if ($root === null)
        <ul class="grid grid-cols-2 gap-3 sm:grid-cols-3">
            @foreach ($tree as $category)
                <li>
                    <a href="{{ $base.'?root='.$category->id }}" class="flex h-full flex-col gap-3 rounded-card border border-line bg-surface p-4 hover:border-control">
                        <span class="grid size-10 place-items-center rounded-control bg-surface-muted text-fg"><x-ui.icon :name="$category->icon && \App\Support\Icons::exists($category->icon) ? $category->icon : 'package'" size="20" /></span>
                        <span class="text-ui font-semibold text-fg">{{ $category->name }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    @else
        <a href="{{ $base }}" class="mb-3 inline-flex items-center gap-1 text-ui text-fg-muted hover:text-fg"><x-ui.icon name="chevron-left" size="16" />{{ __('posting.category.all') }}</a>
        <h2 class="mb-2 text-h3 font-semibold">{{ $root->name }}</h2>
        <ul class="space-y-0.5">
            @foreach ($root->children as $child)
                <li>@include('posting.partials.category-choice', ['id' => $child->id, 'label' => $child->name, 'hint' => null])</li>
            @endforeach
        </ul>
    @endif

    @if ($listing && $listing->status === \App\Enums\ListingStatus::Draft)
        <p class="mt-5 text-small text-fg-subtle">{{ __('posting.category.clears') }}</p>
    @endif
@endsection
