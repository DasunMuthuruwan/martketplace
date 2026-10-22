<?php

namespace App\Livewire\Admin;

use App\Authorization\Permissions;
use App\Livewire\Admin\Concerns\RequiresPermission;
use App\Models\BankTransferAccount;
use App\Models\BoostPrice;
use App\Models\BoostProduct;
use App\Models\Country;
use App\Rules\MoneyAmount;
use App\Services\Audit\AuditLogger;
use App\Services\Boosts\BoostCatalogue;
use App\Support\Decimal;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Boost prices and payment details per country (ADR-026, B4, `boosts.manage`): the price of each
 * product in the country's currency (empty = not offered there), products on or off for all
 * countries, and the bank account sellers transfer to (D3). Every change is audited. Selling itself
 * is switched on in Settings by the Owner, after the accountant review (D6).
 */
#[Layout('layouts.admin')]
#[Title('Boost prices')]
class BoostPricesAdmin extends Component
{
    use RequiresPermission;

    #[Url(as: 'country')]
    public string $countryId = '';

    /** @var array<int|string, string> product id => amount as typed */
    public array $prices = [];

    /** @var array<string, string|bool|null> */
    public array $bank = [];

    public function mount(): void
    {
        $this->requirePermission(Permissions::BOOSTS_MANAGE);
        if ($this->country() === null) {
            $this->countryId = (string) (Country::where('is_active', true)->orderBy('iso2')->value('id') ?? '');
        }
        $this->load();
    }

    public function updatedCountryId(): void
    {
        $this->resetErrorBag();
        $this->load();
    }

    private function country(): ?Country
    {
        return ctype_digit($this->countryId) ? Country::with('defaultCurrency')->find((int) $this->countryId) : null;
    }

    private function load(): void
    {
        $country = $this->country();
        $this->prices = [];
        $this->bank = ['bank_name' => '', 'account_name' => '', 'account_number' => '', 'branch' => '', 'swift_code' => '', 'instructions' => '', 'is_active' => false];
        if ($country === null) {
            return;
        }
        foreach (BoostPrice::where('country_id', $country->id)->get() as $price) {
            $this->prices[$price->boost_product_id] = Decimal::trim((string) $price->amount);
        }
        $account = BankTransferAccount::where('country_id', $country->id)->first();
        if ($account !== null) {
            $this->bank = $account->only(array_keys($this->bank));
            $this->bank = array_map(fn ($v) => is_bool($v) ? $v : (string) $v, $this->bank);
        }
    }

    public function savePrices(AuditLogger $audit): void
    {
        $this->requirePermission(Permissions::BOOSTS_MANAGE);
        $country = $this->country();
        $currency = $country?->defaultCurrency;
        if ($country === null || $currency === null) {
            return;
        }
        $products = BoostProduct::orderBy('sort_order')->get();
        // Typed in the country's number format ("1,500.50", "1.500,50").
        foreach ($this->prices as $id => $typed) {
            $this->prices[$id] = (string) Decimal::fromLocale((string) $typed, (string) $country->number_locale);
        }
        $rules = [];
        foreach ($products as $product) {
            $rules['prices.'.$product->id] = ['nullable', 'string', 'max:30', new MoneyAmount($currency)];
        }
        $this->validate($rules, [], collect($products)->mapWithKeys(fn (BoostProduct $p) => ['prices.'.$p->id => $p->name()])->all());

        $before = BoostPrice::where('country_id', $country->id)->pluck('amount', 'boost_product_id')->map(fn ($a) => Decimal::trim((string) $a))->all();
        $after = [];
        DB::transaction(function () use ($products, $country, $currency, &$after): void {
            foreach ($products as $product) {
                $typed = trim((string) ($this->prices[$product->id] ?? ''));
                if ($typed === '') {
                    BoostPrice::where('country_id', $country->id)->where('boost_product_id', $product->id)->delete();

                    continue;
                }
                $amount = (string) Decimal::parse($typed);
                BoostPrice::updateOrCreate(
                    ['country_id' => $country->id, 'boost_product_id' => $product->id],
                    ['currency' => $currency->code, 'amount' => $amount],
                );
                $after[$product->id] = Decimal::trim($amount);
            }
        });

        if ($before != $after) {
            $audit->record(Auth::user(), 'boosts.prices_changed', $country, null, ['currency' => $currency->code, 'before' => $before, 'after' => $after]);
        }
        $this->load();
        session()->flash('status', __('admin.boost_prices.saved', ['country' => $country->name]));
    }

    public function toggleProduct(int $id, AuditLogger $audit): void
    {
        $this->requirePermission(Permissions::BOOSTS_MANAGE);
        $product = BoostProduct::findOrFail($id);
        $product->update(['is_active' => ! $product->is_active]);
        $audit->record(Auth::user(), 'boosts.product_'.($product->is_active ? 'enabled' : 'disabled'), null, null, ['product' => $product->id, 'name' => $product->name()]);
    }

    public function saveBank(AuditLogger $audit): void
    {
        $this->requirePermission(Permissions::BOOSTS_MANAGE);
        $country = $this->country();
        if ($country === null) {
            return;
        }
        $data = $this->validate([
            'bank.bank_name' => ['required', 'string', 'max:120'],
            'bank.account_name' => ['required', 'string', 'max:120'],
            'bank.account_number' => ['required', 'string', 'max:60', 'regex:/^[A-Za-z0-9 \-]+$/'],
            'bank.branch' => ['nullable', 'string', 'max:120'],
            'bank.swift_code' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9]+$/'],
            'bank.instructions' => ['nullable', 'string', 'max:1000'],
            'bank.is_active' => ['boolean'],
        ], [], [
            'bank.bank_name' => __('admin.boost_prices.bank_name'), 'bank.account_name' => __('admin.boost_prices.account_name'),
            'bank.account_number' => __('admin.boost_prices.account_number'), 'bank.branch' => __('admin.boost_prices.branch'),
            'bank.swift_code' => __('admin.boost_prices.swift_code'), 'bank.instructions' => __('admin.boost_prices.instructions'),
        ])['bank'];

        $values = array_map(fn ($v) => is_string($v) ? (trim($v) === '' ? null : trim($v)) : $v, $data);
        $account = BankTransferAccount::firstOrNew(['country_id' => $country->id]);
        $account->fill($values + ['is_active' => false]);
        $account->forceFill(['updated_by' => Auth::id()])->save();

        // The account number is not copied into the audit log, only that it changed.
        $audit->record(Auth::user(), 'boosts.bank_account_changed', $country, null, [
            'active' => $account->is_active, 'bank' => $account->bank_name, 'number_changed' => $account->wasChanged('account_number') || $account->wasRecentlyCreated,
        ]);
        session()->flash('status', __('admin.boost_prices.bank_saved', ['country' => $country->name]));
    }

    public function render(BoostCatalogue $catalogue): View
    {
        $country = $this->country();

        return view('livewire.admin.boost-prices', [
            'countries' => Country::orderByDesc('is_active')->orderBy('iso2')->get(['id', 'iso2', 'name', 'is_active']),
            'country' => $country,
            'currency' => $country?->defaultCurrency,
            'products' => BoostProduct::orderBy('sort_order')->get(),
            'salesEnabled' => $catalogue->salesEnabled(),
            'open' => $country !== null && $catalogue->isOpen($country),
        ]);
    }
}
