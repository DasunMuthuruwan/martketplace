<?php

namespace App\Livewire\Admin;

use App\Authorization\Permissions;
use App\Livewire\Admin\Concerns\RequiresPermission;
use App\Services\Audit\AuditLogger;
use App\Support\Settings;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Site settings (ADM-05), Owner only (`settings.manage`): site name, contact email, SEO default,
 * moderation mode, ad limits and expiry, photo limit, call and WhatsApp switches. Values are typed
 * and validated by App\Support\Settings; changes take effect on the next request and are audited.
 */
#[Layout('layouts.admin')]
#[Title('Settings')]
class SettingsAdmin extends Component
{
    use RequiresPermission;

    /** @var array<string, string|int|bool|null> */
    public array $values = [];

    public function mount(): void
    {
        $this->requirePermission(Permissions::SETTINGS_MANAGE);
        $this->values = Settings::current();
    }

    public function save(AuditLogger $audit): void
    {
        $this->requirePermission(Permissions::SETTINGS_MANAGE);
        if (! $this->hasRecentPassword()) {
            return;
        }

        $rules = [];
        foreach (Settings::rules() as $key => $keyRules) {
            $rules['values.'.$key] = $keyRules;
        }
        $data = $this->validate($rules)['values'];

        $changes = Settings::save($data, Auth::user());
        if ($changes !== []) {
            $audit->record(Auth::user(), 'settings.updated', null, null, $changes);
        }
        $this->values = Settings::current();
        session()->flash('status', __('admin.settings.saved'));
    }

    public function render(): View
    {
        return view('livewire.admin.settings');
    }
}
