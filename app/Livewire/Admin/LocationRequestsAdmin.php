<?php

namespace App\Livewire\Admin;

use App\Actions\Locations\ResolveLocationRequest;
use App\Authorization\Permissions;
use App\Enums\LocationRequestStatus;
use App\Livewire\Admin\Concerns\RequiresPermission;
use App\Models\City;
use App\Models\Locality;
use App\Models\LocationRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * "My town is not listed" queue (GLB-16): map a request to an existing city/locality, approve it
 * (create the place) or reject it with a note. The decision logic lives in ResolveLocationRequest.
 */
#[Layout('layouts.admin')]
#[Title('Location requests')]
class LocationRequestsAdmin extends Component
{
    use RequiresPermission, WithPagination;

    public string $status = 'pending';

    #[Locked]
    public ?int $selectedId = null;

    public string $mapSearch = '';

    public string $mapCity = '';

    public string $mapLocality = '';

    public string $approveCity = '';

    public string $approveLocality = '';

    public string $note = '';

    public function mount(): void
    {
        $this->requirePermission(Permissions::LOCATIONS_MANAGE);
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedMapCity(): void
    {
        $this->mapLocality = '';
    }

    public function select(int $id): void
    {
        $this->requirePermission(Permissions::LOCATIONS_MANAGE);
        $request = LocationRequest::findOrFail($id);
        $this->reset(['mapSearch', 'mapCity', 'mapLocality', 'note']);
        $this->resetErrorBag();
        $this->selectedId = $request->id;
        $this->approveCity = (string) $request->proposed_city;
        $this->approveLocality = (string) $request->proposed_locality;
        $this->mapSearch = (string) ($request->proposed_city ?? '');
        $this->mapCity = (string) ($request->city_id ?? '');
    }

    public function map(ResolveLocationRequest $resolve): void
    {
        $this->requirePermission(Permissions::LOCATIONS_MANAGE);
        $request = LocationRequest::findOrFail($this->selectedId);
        $data = $this->validate([
            'mapCity' => ['required', Rule::exists('cities', 'id')->where('country_id', $request->country_id)],
            'mapLocality' => ['nullable', Rule::exists('localities', 'id')->where('city_id', (int) $this->mapCity)],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $this->run(fn () => $resolve->map(Auth::user(), $request, City::findOrFail((int) $data['mapCity']),
            blank($data['mapLocality']) ? null : Locality::findOrFail((int) $data['mapLocality']), $data['note']), 'locations.requests.mapped');
    }

    public function approve(ResolveLocationRequest $resolve): void
    {
        $this->requirePermission(Permissions::LOCATIONS_MANAGE);
        $request = LocationRequest::findOrFail($this->selectedId);
        $data = $this->validate([
            'approveCity' => ['nullable', 'string', 'max:80'],
            'approveLocality' => ['nullable', 'string', 'max:80'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $this->run(fn () => $resolve->approve(Auth::user(), $request, $data['approveCity'], $data['approveLocality'], $data['note']), 'locations.requests.approved');
    }

    public function reject(ResolveLocationRequest $resolve): void
    {
        $this->requirePermission(Permissions::LOCATIONS_MANAGE);
        $request = LocationRequest::findOrFail($this->selectedId);
        $data = $this->validate(['note' => ['required', 'string', 'max:500']]);

        $this->run(fn () => $resolve->reject(Auth::user(), $request, $data['note']), 'locations.requests.rejected');
    }

    public function close(): void
    {
        $this->selectedId = null;
        $this->resetErrorBag();
    }

    public function render(): View
    {
        $selected = $this->selectedId === null ? null
            : LocationRequest::with(['country', 'region', 'subregion', 'city', 'user'])->find($this->selectedId);
        $term = '%'.addcslashes($this->mapSearch, '%_\\').'%';

        return view('livewire.admin.location-requests', [
            'requests' => LocationRequest::with(['country', 'region', 'subregion', 'city'])
                ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
                ->oldest()
                ->paginate(30),
            'statuses' => LocationRequestStatus::cases(),
            'selected' => $selected,
            'cityChoices' => $selected === null || mb_strlen($this->mapSearch) < 2 ? collect()
                : City::where('country_id', $selected->country_id)
                    ->where(fn ($w) => $w->where('ascii_name', 'like', $term)->orWhere('name', 'like', $term))
                    ->orderBy('ascii_name')->limit(20)->get(),
            'mappedCity' => $this->mapCity === '' ? null : City::with('localities')->find((int) $this->mapCity),
        ]);
    }

    private function run(callable $action, string $message): void
    {
        try {
            $action();
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError($field, $messages[0]);
            }

            return;
        }

        session()->flash('status', __($message));
        $this->close();
    }
}
