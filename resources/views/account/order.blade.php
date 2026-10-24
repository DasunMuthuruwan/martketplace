@extends('layouts.account')

@section('title', __('boosts.order.title', ['reference' => $order->reference]))

{{-- One promotion order (B5): status, what was ordered, and while unpaid how to pay. --}}
@section('account')
    @if (session('status'))<x-ui.alert variant="success" role="status" class="mb-4">{{ session('status') }}</x-ui.alert>@endif
    <x-ui.form-errors class="mb-4" />

    <dl class="mb-5 grid gap-x-4 gap-y-2 text-ui sm:grid-cols-[10rem_minmax(0,1fr)]">
        <dt class="text-fg-muted">{{ __('boosts.order.status_label') }}</dt>
        <dd><x-ui.badge :variant="match ($order->status->value) { 'paid' => 'success', 'pending' => 'warning', default => 'neutral' }">{{ __('boosts.order.status.'.$order->status->value) }}</x-ui.badge></dd>
        <dt class="text-fg-muted">{{ __('boosts.order.product') }}</dt>
        <dd class="text-fg">{{ $order->productName() }}</dd>
        <dt class="text-fg-muted">{{ __('boosts.order.ad') }}</dt>
        <dd class="prose-user text-fg">{{ $order->listing?->title ?? '—' }}</dd>
        <dt class="text-fg-muted">{{ __('boosts.order.amount') }}</dt>
        <dd><x-ui.money :amount="$order->amount" :currency="$order->currencyModel" :country="$order->country" size="inherit" class="font-semibold text-fg" /></dd>
        <dt class="text-fg-muted">{{ __('boosts.order.ordered') }}</dt>
        <dd class="text-fg">{{ $order->created_at->toFormattedDayDateString() }}</dd>
        @if ($order->boost)
            <dt class="text-fg-muted">{{ __('account.stats.boosts') }}</dt>
            <dd class="text-fg">{{ $order->boost->starts_at->toFormattedDateString() }}@if ($order->boost->ends_at) – {{ $order->boost->ends_at->toFormattedDateString() }}@endif</dd>
        @endif
    </dl>

    @if ($order->status->isOpen())
        <section aria-labelledby="pay" class="mb-5 rounded-card border border-line bg-surface-muted p-4" data-payment-instructions>
            <h2 id="pay" class="mb-2 text-h3 font-semibold">{{ __('boosts.order.pay_title') }}</h2>
            <p class="mb-3 text-ui text-fg">{{ __('boosts.order.reference') }}: <strong class="tabular text-h3 tracking-wider" data-order-reference>{{ $order->reference }}</strong></p>
            <p class="mb-3 text-small text-fg-muted">{{ __('boosts.order.reference_help') }}</p>
            @if ($bank)
                <dl class="mb-3 grid gap-x-4 gap-y-1 text-ui sm:grid-cols-[10rem_minmax(0,1fr)]">
                    <dt class="text-fg-muted">{{ __('boosts.order.bank') }}</dt><dd class="text-fg">{{ $bank->bank_name }}@if ($bank->branch), {{ $bank->branch }}@endif</dd>
                    <dt class="text-fg-muted">{{ __('boosts.order.account_name') }}</dt><dd class="text-fg">{{ $bank->account_name }}</dd>
                    <dt class="text-fg-muted">{{ __('boosts.order.account_number') }}</dt><dd class="tabular text-fg">{{ $bank->account_number }}</dd>
                    @if ($bank->swift_code)<dt class="text-fg-muted">{{ __('boosts.order.swift') }}</dt><dd class="tabular text-fg">{{ $bank->swift_code }}</dd>@endif
                </dl>
                @if ($bank->instructions)<p class="mb-3 whitespace-pre-line text-small text-fg">{{ $bank->instructions }}</p>@endif
            @endif
            <p class="text-small text-fg-muted">{{ __('boosts.order.pay_by', ['date' => $order->expires_at?->toFormattedDayDateString()]) }}</p>
        </section>
    @endif

    @include('account.partials.payment-proofs')

    @if ($order->status->isOpen())

        <x-ui.button variant="ghost" icon="x" class="text-danger" data-modal-open="cancel-order">{{ __('boosts.order.cancel') }}</x-ui.button>
        <x-ui.modal id="cancel-order" :title="__('boosts.order.cancel')" size="sm">
            <p class="text-ui text-fg-muted">{{ __('boosts.order.cancel_confirm') }}</p>
            <x-slot:footer>
                <x-ui.button variant="secondary" data-modal-close>{{ __('admin.catalogue.cancel') }}</x-ui.button>
                <form method="POST" action="{{ route('account.orders.cancel', $order) }}">@csrf<x-ui.button type="submit" variant="danger">{{ __('boosts.order.cancel') }}</x-ui.button></form>
            </x-slot:footer>
        </x-ui.modal>
    @endif

    <p class="mt-5 text-small"><a href="{{ route('account.orders') }}" class="text-fg-muted hover:underline">{{ __('boosts.order.orders') }}</a></p>
@endsection
