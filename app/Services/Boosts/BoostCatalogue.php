<?php

namespace App\Services\Boosts;

use App\Models\BankTransferAccount;
use App\Models\BoostPrice;
use App\Models\Country;
use Illuminate\Database\Eloquent\Collection;

/**
 * What sellers in a country can buy (ADR-026). Selling is open in a country only when the Owner has
 * switched sales on (`boosts.sales_enabled`, after the accountant review D6), the country has an
 * active bank transfer account (D3) and at least one active product has a price there (D2).
 */
class BoostCatalogue
{
    public function salesEnabled(): bool
    {
        return (bool) config('marketplace.boosts.sales_enabled');
    }

    /**
     * Active products with a price in the country, in product order.
     *
     * @return Collection<int, BoostPrice>
     */
    public function offers(Country $country): Collection
    {
        return BoostPrice::query()
            ->where('boost_prices.country_id', $country->id)
            ->join('boost_products', 'boost_products.id', '=', 'boost_prices.boost_product_id')
            ->where('boost_products.is_active', true)
            ->orderBy('boost_products.sort_order')
            ->with(['product', 'currencyModel'])
            ->get(['boost_prices.*']);
    }

    public function bankAccount(Country $country): ?BankTransferAccount
    {
        return BankTransferAccount::where('country_id', $country->id)->where('is_active', true)->first();
    }

    public function isOpen(Country $country): bool
    {
        return $this->missing($country) === [];
    }

    /**
     * What stops selling in the country, so the Boost prices screen can name it (B7): `sales_off`,
     * `bank` (no active account), `prices` (no price at all) or `products_off` (prices exist, but
     * only for products switched off).
     *
     * @return list<string>
     */
    public function missing(Country $country): array
    {
        $missing = [];
        if (! $this->salesEnabled()) {
            $missing[] = 'sales_off';
        }
        if ($this->bankAccount($country) === null) {
            $missing[] = 'bank';
        }
        if ($this->offers($country)->isEmpty()) {
            $missing[] = BoostPrice::where('country_id', $country->id)->exists() ? 'products_off' : 'prices';
        }

        return $missing;
    }
}
