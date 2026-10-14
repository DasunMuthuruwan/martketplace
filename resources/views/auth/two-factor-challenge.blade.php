@extends('layouts.auth')

@section('title', __('staff.two_factor.challenge_title'))

@section('content')
    <p class="mb-4 text-ui text-fg-muted">{{ __('staff.two_factor.challenge_intro') }}</p>
    <form method="POST" action="{{ route('two-factor.login.store') }}" novalidate class="space-y-4">
        @csrf
        <x-ui.input :label="__('staff.two_factor.code')" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]*" autofocus
            class="tabular tracking-[0.3em]" />
        <details class="group rounded-control border border-line p-3" @if ($errors->has('recovery_code')) open @endif>
            <summary class="cursor-pointer list-none text-ui font-medium text-fg [&::-webkit-details-marker]:hidden">
                <span class="inline-flex items-center gap-1">{{ __('staff.two_factor.use_recovery') }}<x-ui.icon name="chevron-down" size="16" class="transition-transform group-open:rotate-180" /></span>
            </summary>
            <div class="mt-3">
                <x-ui.input :label="__('staff.two_factor.recovery_code')" name="recovery_code" autocomplete="off" />
            </div>
        </details>
        <x-ui.button type="submit" size="lg" block>{{ __('staff.two_factor.continue') }}</x-ui.button>
    </form>
@endsection
