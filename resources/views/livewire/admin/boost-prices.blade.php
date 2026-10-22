{{-- Boost prices and bank details per country (ADR-026, B4). --}}
<div>
    <x-admin.page-header :title="__('admin.nav.boost_prices')" :description="__('admin.boost_prices.help')" />

    @if (session('status'))
        <x-ui.alert variant="success" role="status" class="mb-4">{{ session('status') }}</x-ui.alert>
    @endif

    <x-ui.alert :variant="$open ? 'success' : 'info'" class="mb-4" data-sales-state>
        @if (! $salesEnabled)
            {{ __('admin.boost_prices.sales_off') }}
        @elseif ($open)
            {{ __('admin.boost_prices.open', ['country' => $country->name]) }}
        @else
            {{ __('admin.boost_prices.not_open') }}
        @endif
    </x-ui.alert>

    <div class="mb-4 max-w-sm">
        <x-ui.select :label="__('geo.labels.country')" id="bp-country" wire:model.live="countryId" :selected="$countryId"
            :options="$countries->mapWithKeys(fn ($c) => [$c->id => $c->iso2.' · '.$c->name.($c->is_active ? '' : ' ('.__('admin.boost_prices.inactive_country').')')])->all()" />
    </div>

    @if ($country)
        <section aria-labelledby="bp-prices" class="mb-6 rounded-card border border-line bg-surface p-4 lg:p-5">
            <h2 id="bp-prices" class="mb-1 text-h3 font-semibold">{{ __('admin.boost_prices.prices_title', ['country' => $country->name]) }}</h2>
            @if (! $currency)
                <p class="text-ui text-fg-muted">{{ __('admin.boost_prices.no_currency') }}</p>
            @else
                <p class="mb-3 text-small text-fg-subtle">{{ __('admin.boost_prices.prices_help', ['currency' => $currency->code]) }}</p>
                <form wire:submit="savePrices" class="space-y-3">
                    <ul class="divide-y divide-line">
                        @foreach ($products as $product)
                            <li wire:key="product-{{ $product->id }}" class="flex flex-wrap items-end gap-3 py-3">
                                <div class="min-w-48 flex-1">
                                    <p class="text-ui font-medium text-fg">{{ $product->name() }}</p>
                                    <p class="text-small text-fg-subtle">{{ $product->is_active ? __('admin.boost_prices.product_on') : __('admin.boost_prices.product_off') }}</p>
                                </div>
                                <x-ui.input :label="__('admin.boost_prices.price', ['currency' => $currency->code])" id="bp-price-{{ $product->id }}" inputmode="decimal"
                                    wire:model="prices.{{ $product->id }}" maxlength="30" class="w-44" :error="$errors->first('prices.'.$product->id)" />
                                <x-ui.button type="button" size="sm" variant="ghost" wire:click="toggleProduct({{ $product->id }})">
                                    {{ $product->is_active ? __('admin.boost_prices.turn_off') : __('admin.boost_prices.turn_on') }}<span class="sr-only">: {{ $product->name() }}</span>
                                </x-ui.button>
                            </li>
                        @endforeach
                    </ul>
                    <x-ui.button type="submit" icon="check">{{ __('admin.boost_prices.save_prices') }}</x-ui.button>
                </form>
            @endif
        </section>

        <section aria-labelledby="bp-bank" class="rounded-card border border-line bg-surface p-4 lg:p-5">
            <h2 id="bp-bank" class="mb-1 text-h3 font-semibold">{{ __('admin.boost_prices.bank_title') }}</h2>
            <p class="mb-3 text-small text-fg-subtle">{{ __('admin.boost_prices.bank_help') }}</p>
            <form wire:submit="saveBank" class="space-y-3">
                <div class="grid gap-3 sm:grid-cols-2">
                    <x-ui.input :label="__('admin.boost_prices.bank_name')" id="bp-bank-name" wire:model="bank.bank_name" maxlength="120" required :error="$errors->first('bank.bank_name')" />
                    <x-ui.input :label="__('admin.boost_prices.branch')" id="bp-branch" wire:model="bank.branch" maxlength="120" :error="$errors->first('bank.branch')" />
                    <x-ui.input :label="__('admin.boost_prices.account_name')" id="bp-account-name" wire:model="bank.account_name" maxlength="120" required :error="$errors->first('bank.account_name')" />
                    <x-ui.input :label="__('admin.boost_prices.account_number')" id="bp-account-number" wire:model="bank.account_number" maxlength="60" required :error="$errors->first('bank.account_number')" />
                    <x-ui.input :label="__('admin.boost_prices.swift_code')" id="bp-swift" wire:model="bank.swift_code" maxlength="20" :error="$errors->first('bank.swift_code')" />
                </div>
                <x-ui.textarea :label="__('admin.boost_prices.instructions')" id="bp-instructions" wire:model="bank.instructions" rows="3" maxlength="1000" :error="$errors->first('bank.instructions')" />
                <x-ui.checkbox type="switch" :label="__('admin.boost_prices.bank_active')" id="bp-active" wire:model="bank.is_active" :checked="(bool) ($bank['is_active'] ?? false)" />
                <x-ui.button type="submit" icon="check">{{ __('admin.boost_prices.save_bank') }}</x-ui.button>
            </form>
        </section>
    @endif
</div>
