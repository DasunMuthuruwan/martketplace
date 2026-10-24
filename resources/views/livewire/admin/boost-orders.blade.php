{{-- Promotion orders (B5): find by reference, mark paid, cancel, refund. --}}
<div>
    <x-admin.page-header :title="__('admin.nav.orders')" :count="$orders->total()" :description="__('admin.orders.help')" />

    @if (session('status'))
        <x-ui.alert variant="success" role="status" class="mb-4">{{ session('status') }}</x-ui.alert>
    @endif

    <div class="mb-4 flex flex-wrap items-end gap-3">
        <x-ui.input type="search" :label="__('admin.orders.search')" id="o-q" wire:model.live.debounce.400ms="q" maxlength="16" class="min-w-56" />
        <x-ui.select :label="__('boosts.order.status_label')" id="o-status" wire:model.live="status" :placeholder="__('public.any')" class="min-w-48"
            :options="__('boosts.order.status')" />
        <x-ui.select :label="__('admin.orders.proof_filter')" id="o-proof" wire:model.live="proof" :placeholder="__('admin.orders.proof_any')" class="min-w-44"
            :options="['with' => __('admin.orders.proof_with'), 'without' => __('admin.orders.proof_without')]" />
    </div>

    @if ($orders->isEmpty())
        <x-ui.empty-state icon="inbox" :title="__('admin.orders.empty')" />
    @else
        <x-ui.table :caption="__('admin.nav.orders')">
            <thead>
                <tr>
                    <th scope="col">{{ __('boosts.order.reference') }}</th>
                    <th scope="col">{{ __('boosts.order.ad') }}</th>
                    <th scope="col">{{ __('boosts.order.amount') }}</th>
                    <th scope="col">{{ __('boosts.order.status_label') }}</th>
                    <th scope="col"><span class="sr-only">{{ __('admin.orders.actions') }}</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($orders as $order)
                    <tr wire:key="order-{{ $order->id }}">
                        <td data-label="{{ __('boosts.order.reference') }}">
                            <span class="tabular font-semibold text-fg">{{ $order->reference }}</span>
                            <span class="block text-small text-fg-subtle">{{ $order->created_at->toFormattedDateString() }} · {{ $order->user?->name ?? __('account.deleted_user') }}</span>
                            @if ($order->proofs->isNotEmpty())
                                <x-ui.badge variant="info" class="mt-1" data-proof-received>{{ __('boosts.proof.received') }}</x-ui.badge>
                            @endif
                        </td>
                        <td data-label="{{ __('boosts.order.ad') }}" class="min-w-0">
                            @if ($order->listing && $order->listing->isPublic())
                                <a href="{{ $order->listing->publicUrl() }}" class="prose-user text-fg hover:underline">{{ $order->listing->title }}</a>
                            @else
                                <span class="prose-user text-fg-muted">{{ $order->listing?->title ?? '—' }}</span>
                            @endif
                            <span class="block text-small text-fg-subtle">{{ $order->productName() }}</span>
                        </td>
                        <td data-label="{{ __('boosts.order.amount') }}"><x-ui.money :amount="$order->amount" :currency="$order->currencyModel" :country="$order->country" :with-code="true" size="inherit" /></td>
                        <td data-label="{{ __('boosts.order.status_label') }}">
                            <x-ui.badge :variant="match ($order->status->value) { 'paid' => 'success', 'pending' => 'warning', default => 'neutral' }">{{ __('boosts.order.status.'.$order->status->value) }}</x-ui.badge>
                            @if ($order->note)<span class="block text-small text-fg-subtle">{{ $order->note }}</span>@endif
                        </td>
                        <td class="text-right">
                            <div class="flex flex-wrap justify-end gap-1">
                                @if ($order->proofs->isNotEmpty())
                                    <x-ui.button size="sm" variant="secondary" icon="file-text" wire:click="toggleProof({{ $order->id }})" aria-expanded="{{ $proofId === $order->id ? 'true' : 'false' }}" aria-controls="order-proof-{{ $order->id }}">
                                        {{ $proofId === $order->id ? __('admin.orders.hide_proof') : __('admin.orders.show_proof', ['count' => $order->proofs->count()]) }}<span class="sr-only"> {{ $order->reference }}</span>
                                    </x-ui.button>
                                @endif
                                @if ($order->status->value === 'pending')
                                    <x-ui.button size="sm" icon="check" wire:click="start({{ $order->id }}, 'paid')">{{ __('admin.orders.mark_paid') }}<span class="sr-only"> {{ $order->reference }}</span></x-ui.button>
                                    <x-ui.button size="sm" variant="ghost" wire:click="start({{ $order->id }}, 'cancel')">{{ __('admin.orders.cancel') }}<span class="sr-only"> {{ $order->reference }}</span></x-ui.button>
                                @elseif ($order->status->value === 'paid')
                                    <x-ui.button size="sm" variant="ghost" wire:click="start({{ $order->id }}, 'refund')">{{ __('admin.orders.refund') }}<span class="sr-only"> {{ $order->reference }}</span></x-ui.button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @if ($proofId === $order->id && $order->proofs->isNotEmpty())
                        <tr wire:key="order-{{ $order->id }}-proof">
                            <td colspan="5">
                                <section id="order-proof-{{ $order->id }}" aria-labelledby="order-proof-title-{{ $order->id }}" class="space-y-3 rounded-card bg-surface-muted p-3" data-order-proof>
                                    <h3 id="order-proof-title-{{ $order->id }}" class="text-ui font-semibold text-fg">{{ __('admin.orders.proof_title', ['reference' => $order->reference]) }}</h3>
                                    <x-ui.alert variant="warning">{{ __('admin.orders.proof_warning') }}</x-ui.alert>
                                    @if ($order->transfer_date || $order->transfer_note)
                                        <dl class="grid gap-x-4 gap-y-1 text-small sm:grid-cols-[12rem_minmax(0,1fr)]">
                                            @if ($order->transfer_date)<dt class="text-fg-muted">{{ __('admin.orders.transfer_date') }}</dt><dd class="text-fg">{{ $order->transfer_date->toFormattedDateString() }}</dd>@endif
                                            @if ($order->transfer_note)<dt class="text-fg-muted">{{ __('admin.orders.transfer_note') }}</dt><dd class="prose-user text-fg">{{ $order->transfer_note }}</dd>@endif
                                        </dl>
                                    @endif
                                    <ul class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                        @foreach ($order->proofs as $proof)
                                            <li wire:key="proof-{{ $proof->id }}" class="min-w-0 rounded-control border border-line bg-surface p-2">
                                                @if ($proof->isPdf())
                                                    <a href="{{ route('admin.orders.proof', $proof) }}" class="flex items-center gap-2 text-ui text-fg hover:underline">
                                                        <x-ui.icon name="file-text" size="20" class="text-fg-muted" /><span class="truncate">{{ $proof->original_name }}</span>
                                                    </a>
                                                @else
                                                    <a href="{{ route('admin.orders.proof', $proof) }}" target="_blank" rel="noopener" class="block">
                                                        <img src="{{ route('admin.orders.proof', $proof) }}" alt="{{ __('admin.orders.proof_image', ['n' => $loop->iteration, 'reference' => $order->reference]) }}"
                                                            loading="lazy" class="max-h-80 w-full rounded-control object-contain">
                                                    </a>
                                                    <span class="mt-1 block truncate text-small text-fg-subtle">{{ $proof->original_name }}</span>
                                                @endif
                                                <span class="block text-small text-fg-subtle">{{ \Illuminate\Support\Number::fileSize($proof->bytes) }} · {{ $proof->created_at->toFormattedDateString() }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                </section>
                            </td>
                        </tr>
                    @endif
                    @if ($actingId === $order->id)
                        <tr wire:key="order-{{ $order->id }}-action">
                            <td colspan="5">
                                <form wire:submit="confirm" class="space-y-3 rounded-card bg-surface-muted p-3" aria-labelledby="order-action-{{ $order->id }}">
                                    <p id="order-action-{{ $order->id }}" class="text-ui font-semibold text-fg">{{ __('admin.orders.confirm.'.$action, ['reference' => $order->reference]) }}</p>
                                    <x-ui.textarea :label="$action === 'paid' ? __('admin.orders.payment_note') : __('admin.orders.reason')" id="o-note" wire:model="note" rows="2" maxlength="500"
                                        :required="$action !== 'paid'" :hint="$action === 'paid' ? __('admin.orders.payment_note_hint') : __('admin.orders.reason_hint')" :error="$errors->first('note')" />
                                    <div class="flex gap-2">
                                        <x-ui.button type="submit" :variant="$action === 'paid' ? 'primary' : 'danger'">{{ __('admin.orders.confirm_button.'.$action) }}</x-ui.button>
                                        <x-ui.button type="button" variant="secondary" wire:click="stop">{{ __('admin.catalogue.cancel') }}</x-ui.button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </x-ui.table>
        {{ $orders->links('livewire.admin.pagination') }}
    @endif
</div>
