{{-- Posting wizard (layout doc §3.4): minimal header with Exit, the six steps (sidebar with ticks on
     desktop, "Step 2 of 6" on phones), the step card and a sticky Back / Continue bar on phones.
     Pages: @extends('layouts.wizard'), @section('step_title'), @section('step'), @section('actions'). --}}
@php
    $labels = collect(\App\View\PostingSteps::STEPS)->mapWithKeys(fn ($s) => [$s => __('posting.steps.'.$s)]);
    $index = array_search($step, \App\View\PostingSteps::STEPS, true) + 1;
    $stepUrl = fn (string $s) => $listing ? route('listings.edit', [$listing, $s]) : null;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.layout.head')
    <meta name="robots" content="noindex">
    <title>@yield('step_title') | {{ __('posting.title') }} | {{ config('app.name') }}</title>
    @stack('head')
</head>
<body class="min-h-dvh pb-[calc(72px+env(safe-area-inset-bottom))] lg:pb-0">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-control focus:bg-primary focus:px-4 focus:py-2 focus:text-primary-fg">{{ __('layout.skip') }}</a>
    <header class="sticky top-0 z-30 border-b border-line bg-surface">
        <div class="mx-auto flex h-14 max-w-5xl items-center gap-3 px-4">
            @php($isDraft = $listing === null || $listing->status === \App\Enums\ListingStatus::Draft)
            <x-ui.button variant="ghost" size="sm" icon="chevron-left" :href="$isDraft ? route('account.listings', ['status' => 'drafts']) : route('account.listings')">{{ $listing && $isDraft ? __('posting.exit_saved') : __('posting.exit') }}</x-ui.button>
            <p class="flex-1 text-center text-ui font-semibold text-fg">{{ $listing && $listing->status !== \App\Enums\ListingStatus::Draft ? __('posting.edit_title') : __('posting.title') }}</p>
            <p class="w-24 text-right text-small text-fg-subtle" role="status" aria-live="polite">@if (session('status'))<x-ui.icon name="check" size="16" class="inline text-success" /> {{ session('status') }}@endif</p>
        </div>
    </header>

    <div class="mx-auto grid max-w-5xl gap-6 px-4 py-6 lg:grid-cols-[220px_minmax(0,1fr)]">
        <nav aria-label="{{ __('posting.steps_label') }}">
            <p class="mb-3 text-small text-fg-subtle lg:hidden">{{ __('posting.step_of', ['n' => $index, 'total' => count($labels)]) }} · <span class="font-semibold text-fg">{{ $labels[$step] }}</span></p>
            <div class="mb-1 h-1 overflow-hidden rounded-full bg-surface-muted lg:hidden"><div class="h-full bg-primary {{ ['w-1/6', 'w-2/6', 'w-3/6', 'w-4/6', 'w-5/6', 'w-full'][$index - 1] ?? 'w-full' }}"></div></div>
            <ol class="hidden space-y-1 lg:block">
                @foreach ($labels as $key => $label)
                    @php($reach = $steps->reachable($key))
                    <li>
                        @if ($reach && $stepUrl($key) && $key !== $step)
                            <a href="{{ $stepUrl($key) }}" class="flex items-center gap-2 rounded-control px-3 py-2 text-ui text-fg hover:bg-surface-hover">
                        @else
                            <span @if ($key === $step) aria-current="step" @endif @class(['flex items-center gap-2 rounded-control px-3 py-2 text-ui', 'bg-surface-muted font-semibold text-fg' => $key === $step, 'text-fg-subtle' => $key !== $step])>
                        @endif
                            <span @class(['grid size-6 shrink-0 place-items-center rounded-full border text-micro',
                                'border-success bg-success text-canvas' => $steps->done($key) && $key !== $step,
                                'border-primary bg-primary text-primary-fg' => $key === $step,
                                'border-line' => ! $steps->done($key) && $key !== $step])>
                                @if ($steps->done($key) && $key !== $step)<x-ui.icon name="check" size="16" class="size-3.5" /><span class="sr-only">{{ __('posting.done') }}</span>@else{{ $loop->iteration }}@endif
                            </span>
                            {{ $label }}
                        @if ($reach && $stepUrl($key) && $key !== $step)</a>@else</span>@endif
                    </li>
                @endforeach
            </ol>
        </nav>

        <main id="main" tabindex="-1" class="min-w-0">
            <div class="rounded-card border border-line bg-surface p-5 sm:p-6">
                <h1 class="mb-1 text-h1 font-semibold">@yield('step_title')</h1>
                @hasSection('step_intro')<p class="mb-5 text-ui text-fg-muted">@yield('step_intro')</p>@else<div class="mb-4"></div>@endif
                @yield('step')
            </div>
        </main>
    </div>
    <x-ui.toasts />
</body>
</html>
