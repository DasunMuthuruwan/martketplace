{{-- Proof of payment on an order (B7): attach (one file per request), view, remove while unpaid;
     read-only afterwards. Files are served only through the order's own routes. --}}
@php
    $proofErrors = $errors->getBag('proof');
    $maxFiles = (int) config('marketplace.boosts.proof_max_files');
    $open = $order->status->isOpen();
@endphp
@if ($open || $order->proofs->isNotEmpty())
    <section aria-labelledby="proof-title" class="mb-5 rounded-card border border-line bg-surface p-4" data-payment-proofs>
        <h2 id="proof-title" class="mb-1 text-h3 font-semibold">{{ __('boosts.proof.title') }}</h2>
        @if ($open)<p class="mb-3 text-small text-fg-muted">{{ __('boosts.proof.help') }}</p>@endif

        @if ($proofErrors->any())
            <x-ui.alert variant="danger" class="mb-3" data-proof-errors>
                <ul class="list-disc ps-5">@foreach ($proofErrors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
            </x-ui.alert>
        @endif

        @if ($order->proofs->isNotEmpty())
            <ul class="mb-3 divide-y divide-line rounded-control border border-line">
                @foreach ($order->proofs as $proof)
                    <li class="flex flex-wrap items-center gap-2 px-3 py-2" data-proof="{{ $proof->id }}">
                        <x-ui.icon :name="$proof->isPdf() ? 'file-text' : 'image'" size="16" class="text-fg-muted" />
                        <span class="min-w-0 flex-1 truncate text-ui text-fg">{{ $proof->original_name }}</span>
                        <span class="text-small text-fg-subtle">{{ \Illuminate\Support\Number::fileSize($proof->bytes) }}</span>
                        <x-ui.button size="sm" variant="ghost" :href="route('account.orders.proofs.show', [$order, $proof])" target="_blank" rel="noopener">
                            {{ $proof->isPdf() ? __('boosts.proof.download') : __('boosts.proof.view') }}<span class="sr-only">: {{ $proof->original_name }}</span>
                        </x-ui.button>
                        @if ($open)
                            <form method="POST" action="{{ route('account.orders.proofs.destroy', [$order, $proof]) }}">
                                @csrf @method('DELETE')
                                <x-ui.button type="submit" size="sm" variant="ghost" class="text-danger">{{ __('boosts.proof.remove') }}<span class="sr-only">: {{ $proof->original_name }}</span></x-ui.button>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
        @if ($order->transfer_date || $order->transfer_note)
            <dl class="mb-3 grid gap-x-4 gap-y-1 text-small sm:grid-cols-[10rem_minmax(0,1fr)]">
                @if ($order->transfer_date)<dt class="text-fg-muted">{{ __('boosts.proof.transfer_date') }}</dt><dd class="text-fg">{{ $order->transfer_date->toFormattedDateString() }}</dd>@endif
                @if ($order->transfer_note)<dt class="text-fg-muted">{{ __('boosts.proof.transfer_note') }}</dt><dd class="prose-user text-fg">{{ $order->transfer_note }}</dd>@endif
            </dl>
        @endif

        @if ($open)
            <p class="mb-3 text-small text-fg-subtle" data-proof-count>{{ __('boosts.proof.count', ['count' => $order->proofs->count(), 'max' => $maxFiles]) }}</p>
            @if ($order->proofs->count() < $maxFiles)
                <form method="POST" action="{{ route('account.orders.proofs.store', $order) }}" enctype="multipart/form-data" class="space-y-3" data-proof-form>
                    @csrf
                    <x-ui.field :label="__('boosts.proof.file')" for="proof-file" :required="true" :error="$proofErrors->first('proof')"
                        :hint="__('boosts.proof.file_hint', ['mb' => intdiv((int) config('marketplace.boosts.proof_max_kb'), 1024), 'max' => $maxFiles])">
                        <input type="file" id="proof-file" name="proof" required accept="image/jpeg,image/png,image/webp,application/pdf,.pdf"
                            aria-describedby="proof-file-hint" @if ($proofErrors->has('proof')) aria-invalid="true" @endif
                            class="block w-full text-ui text-fg file:me-3 file:rounded-control file:border file:border-control file:bg-surface-muted file:px-3 file:py-2 file:text-ui file:font-medium file:text-fg hover:file:bg-surface">
                    </x-ui.field>
                    <div class="grid gap-3 sm:grid-cols-[12rem_minmax(0,1fr)]">
                        <x-ui.input type="date" :label="__('boosts.proof.transfer_date')" id="proof-date" name="transfer_date"
                            :value="old('transfer_date', $order->transfer_date?->toDateString())" max="{{ now()->toDateString() }}" :error="$proofErrors->first('transfer_date')" />
                        <x-ui.input :label="__('boosts.proof.transfer_note')" id="proof-note" name="transfer_note" maxlength="300"
                            :value="old('transfer_note', $order->transfer_note)" :hint="__('boosts.proof.transfer_note_hint')" :error="$proofErrors->first('transfer_note')" />
                    </div>
                    <x-ui.button type="submit" icon="upload">{{ __('boosts.proof.upload') }}</x-ui.button>
                </form>
            @endif
        @endif
    </section>
@endif
