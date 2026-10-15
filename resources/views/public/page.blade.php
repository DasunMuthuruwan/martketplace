@extends('layouts.public')

@section('content')
    {{-- Help and legal pages (Markdown from the admin, sanitised by Page::html()). --}}
    <article class="mx-auto max-w-3xl py-2">
        <h1 class="mb-6 text-h1 font-semibold">{{ $page->title }}</h1>
        <div class="page-body">{{ $page->html() }}</div>
        @if ($page->updated_at)
            <p class="mt-10 border-t border-line pt-4 text-small text-fg-subtle">{{ __('public.page_updated', ['date' => $page->updated_at->toFormattedDateString()]) }}</p>
        @endif
    </article>
@endsection
