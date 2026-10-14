<?php

namespace App\Livewire\Admin;

use App\Authorization\Permissions;
use App\Livewire\Admin\Concerns\RequiresPermission;
use App\Models\BlockedTerm;
use App\Models\Country;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Prohibited and suspicious terms (MOD-03, GLB-18, `blocklist.manage`): global terms and terms for
 * one country, matched as a whole word or anywhere in the text, either flagging the ad for review
 * or blocking it. The screener reads them on every submission. Every change is audited.
 */
#[Layout('layouts.admin')]
#[Title('Blocklist')]
class BlocklistAdmin extends Component
{
    use RequiresPermission, WithPagination;

    public string $q = '';

    /** '' = all, 'global', or a country id */
    public string $scope = '';

    public string $term = '';

    public string $match = BlockedTerm::MATCH_WORD;

    public string $action = BlockedTerm::ACTION_FLAG;

    public string $countryId = '';

    public function mount(): void
    {
        $this->requirePermission(Permissions::BLOCKLIST_MANAGE);
    }

    public function updatedQ(): void
    {
        $this->resetPage();
    }

    public function updatedScope(): void
    {
        $this->resetPage();
    }

    public function add(AuditLogger $audit): void
    {
        $this->requirePermission(Permissions::BLOCKLIST_MANAGE);
        $data = $this->validate([
            'term' => ['required', 'string', 'min:2', 'max:100'],
            'match' => ['required', Rule::in([BlockedTerm::MATCH_WORD, BlockedTerm::MATCH_PHRASE])],
            'action' => ['required', Rule::in([BlockedTerm::ACTION_FLAG, BlockedTerm::ACTION_BLOCK])],
            'countryId' => ['nullable', Rule::exists('countries', 'id')],
        ]);
        $countryId = blank($data['countryId']) ? null : (int) $data['countryId'];
        $normalised = mb_strtolower(trim($data['term']));

        // The unique index does not catch duplicates of global terms (NULL country).
        if (BlockedTerm::where('term', $normalised)->where('country_id', $countryId)->exists()) {
            $this->addError('term', __('admin.blocklist.exists'));

            return;
        }

        $blocked = BlockedTerm::create([
            'term' => $normalised, 'match' => $data['match'], 'action' => $data['action'], 'country_id' => $countryId, 'created_by' => Auth::id(),
        ]);
        $audit->record(Auth::user(), 'blocklist.added', $blocked, null, $blocked->only(['term', 'match', 'action', 'country_id']));
        session()->flash('status', __('admin.blocklist.added', ['term' => $blocked->term]));
        $this->reset(['term']);
    }

    public function remove(int $id, AuditLogger $audit): void
    {
        $this->requirePermission(Permissions::BLOCKLIST_MANAGE);
        $blocked = BlockedTerm::findOrFail($id);
        $blocked->delete();
        $audit->record(Auth::user(), 'blocklist.removed', null, null, $blocked->only(['id', 'term', 'match', 'action', 'country_id']));
        session()->flash('status', __('admin.blocklist.removed', ['term' => $blocked->term]));
    }

    public function render(): View
    {
        $countries = Country::orderByDesc('is_active')->orderBy('iso2')->get(['id', 'iso2', 'name', 'is_active']);

        return view('livewire.admin.blocklist', [
            'terms' => BlockedTerm::query()
                ->when($this->q !== '', fn ($query) => $query->where('term', 'like', '%'.addcslashes(mb_strtolower($this->q), '%_\\').'%'))
                ->when($this->scope === 'global', fn ($query) => $query->whereNull('country_id'))
                ->when(ctype_digit($this->scope), fn ($query) => $query->where('country_id', (int) $this->scope))
                ->orderBy('term')
                ->paginate(50),
            'countries' => $countries,
            'countryCodes' => $countries->pluck('iso2', 'id'),
        ]);
    }
}
