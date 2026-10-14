{{-- Account area (navigation doc §2, layout §3.5): the public layout plus a side menu on desktop and
     a tab row on phones. Pages: @extends('layouts.account'), @section('title'), @section('content'). --}}
@extends('layouts.public')

@php
    $accountLinks = collect([
        'my_ads' => __('layout.my_ads'),
        'saved' => __('layout.saved'),
    ])->map(fn ($text, $key) => ['label' => $text, 'href' => \App\View\Navigation::url($key)])->filter(fn ($item) => $item['href'] !== null)
        ->merge([
            'profile' => ['label' => __('account.nav.profile'), 'href' => route('account.settings.profile')],
            'contact' => ['label' => __('account.nav.contact'), 'href' => route('account.settings.contact')],
            'security' => ['label' => __('account.nav.security'), 'href' => route('account.settings.security')],
        ]);
    $activeKey = $accountLinks->search(fn ($item) => rtrim($item['href'], '/') === rtrim(request()->url(), '/'));
@endphp

@section('content')
    <div class="grid gap-6 lg:grid-cols-[232px_minmax(0,1fr)]">
        <nav aria-label="{{ __('account.nav.label') }}" class="hidden lg:block">
            <ul class="sticky top-24 space-y-1">
                @foreach ($accountLinks as $key => $item)
                    <li><a href="{{ $item['href'] }}" @if ($key === $activeKey) aria-current="page" @endif
                        class="block rounded-control px-3 py-2 text-ui text-fg-muted hover:bg-surface-hover hover:text-fg aria-[current]:bg-surface-muted aria-[current]:font-semibold aria-[current]:text-fg">{{ $item['label'] }}</a></li>
                @endforeach
            </ul>
        </nav>
        <div class="min-w-0">
            <x-ui.tabs class="mb-6 lg:hidden" :label="__('account.nav.label')" :items="$accountLinks->all()" :active="$activeKey" />
            <div class="mx-auto max-w-2xl rounded-card border border-line bg-surface p-5 sm:p-6 lg:mx-0">
                <h1 class="mb-4 text-h1 font-semibold">@yield('title')</h1>
                @yield('account')
            </div>
        </div>
    </div>
@endsection
