@extends('layouts.account')

@section('title', __('boosts.order.orders'))

{{-- The seller's promotion orders (B5). --}}
@section('account_full')
    @if ($orders->isEmpty())
        <x-ui.empty-state icon="chevron-up" :title="__('boosts.order.none')" />
    @else
        <ul class="space-y-3">
            @foreach ($orders as $order)
                <li class="flex flex-wrap items-center justify-between gap-3 rounded-card border border-line bg-surface p-3">
                    <div class="min-w-0">
                        <a href="{{ route('account.orders.show', $order) }}" class="text-ui font-semibold text-fg hover:underline">{{ $order->reference }} · {{ $order->productName() }}</a>
                        <p class="prose-user text-small text-fg-muted">{{ $order->listing?->title }} · {{ $order->created_at->toFormattedDateString() }}</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <x-ui.money :amount="$order->amount" :currency="$order->currencyModel" :country="$order->country" size="inherit" class="text-ui font-semibold text-fg" />
                        <x-ui.badge :variant="match ($order->status->value) { 'paid' => 'success', 'pending' => 'warning', default => 'neutral' }">{{ __('boosts.order.status.'.$order->status->value) }}</x-ui.badge>
                    </div>
                </li>
            @endforeach
        </ul>
        <x-ui.pagination :paginator="$orders" class="mt-6" />
    @endif
@endsection
