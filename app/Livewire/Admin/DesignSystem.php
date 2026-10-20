<?php

namespace App\Livewire\Admin;

use App\Authorization\Permissions;
use App\Enums\DisplayDecimals;
use App\Livewire\Admin\Concerns\RequiresPermission;
use App\Models\Country;
use App\Models\Currency;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Living style guide (design system §10): every component in its states, in the current theme
 * (switch the theme in the top bar to check the other). Owner only. Static demo content.
 */
#[Layout('layouts.admin')]
#[Title('Design system')]
class DesignSystem extends Component
{
    use RequiresPermission;

    public function mount(): void
    {
        $this->requirePermission(Permissions::SETTINGS_MANAGE);
    }

    public function toast(): void
    {
        // Every admin action checks its permission itself, not only mount() (Q7.4).
        $this->requirePermission(Permissions::SETTINGS_MANAGE);
        $this->dispatch('toast', message: __('admin.design.toast_sample'), variant: 'success');
    }

    public function render(): View
    {
        // Real currency rows when they exist (formatting comes from the database), demo values otherwise.
        $currency = Currency::where('is_active', true)->orderBy('code')->first()
            ?? (new Currency)->forceFill(['code' => 'USD', 'minor_units' => 2, 'symbol_override' => null, 'display_decimals' => DisplayDecimals::Auto]);
        $country = Country::where('default_currency', $currency->code)->first();

        return view('livewire.admin.design-system', [
            'currency' => $currency,
            'country' => $country,
            'flags' => Country::where('is_active', true)->orderBy('sort_order')->limit(8)->get(['iso2', 'name']),
            'paginator' => new LengthAwarePaginator(range(1, 24), 300, 24, 3, ['path' => request()->url()]),
            'tokens' => ['canvas', 'surface', 'surface-muted', 'line', 'control', 'fg', 'fg-muted', 'fg-subtle', 'primary', 'success', 'warning', 'danger', 'info'],
        ]);
    }
}
