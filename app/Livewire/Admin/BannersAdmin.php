<?php

namespace App\Livewire\Admin;

use App\Authorization\Permissions;
use App\Enums\BannerPlacement;
use App\Livewire\Admin\Concerns\RequiresPermission;
use App\Models\Banner;
use App\Models\Country;
use App\Services\Audit\AuditLogger;
use App\Services\Banners\BannerImages;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * Banners (B10, `boosts.manage`, like the other sales screens): staff upload an image with a link,
 * country, placement and dates. There is no seller self-service. Every change is audited.
 */
#[Layout('layouts.admin')]
#[Title('Banners')]
class BannersAdmin extends Component
{
    use RequiresPermission, WithFileUploads, WithPagination;

    #[Locked]
    public ?int $selectedId = null;

    public bool $editing = false;

    public string $title = '';

    /** @var TemporaryUploadedFile|null */
    public $image = null;

    public string $linkUrl = '';

    public string $countryId = '';

    public string $placement = BannerPlacement::HomeTop->value;

    public string $startsOn = '';

    public string $endsOn = '';

    public bool $active = true;

    public function mount(): void
    {
        $this->requirePermission(Permissions::BOOSTS_MANAGE);
    }

    public function create(): void
    {
        $this->requirePermission(Permissions::BOOSTS_MANAGE);
        $this->resetForm();
        $this->editing = true;
    }

    public function edit(int $id): void
    {
        $this->requirePermission(Permissions::BOOSTS_MANAGE);
        $banner = Banner::findOrFail($id);
        $this->resetForm();
        $this->selectedId = $banner->id;
        $this->title = $banner->title;
        $this->linkUrl = (string) $banner->link_url;
        $this->countryId = (string) $banner->country_id;
        $this->placement = $banner->placement->value;
        $this->startsOn = (string) $banner->starts_at?->toDateString();
        $this->endsOn = (string) $banner->ends_at?->toDateString();
        $this->active = $banner->is_active;
        $this->editing = true;
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function save(BannerImages $images, AuditLogger $audit): void
    {
        $this->requirePermission(Permissions::BOOSTS_MANAGE);
        $banner = $this->selectedId === null ? new Banner : Banner::findOrFail($this->selectedId);
        $maxKb = (int) config('marketplace.banners.max_upload_kb');
        $data = $this->validate([
            'title' => ['required', 'string', 'max:120'],
            'image' => [$banner->exists ? 'nullable' : 'required', 'file', 'extensions:jpg,jpeg,png,webp', 'mimes:jpg,jpeg,png,webp', 'max:'.$maxKb],
            'linkUrl' => ['nullable', 'string', 'max:500', 'url:http,https'],
            'countryId' => ['nullable', 'integer', Rule::exists('countries', 'id')],
            'placement' => ['required', Rule::enum(BannerPlacement::class)],
            'startsOn' => ['nullable', 'date'],
            'endsOn' => ['nullable', 'date', 'after_or_equal:startsOn'],
        ], [
            'image.extensions' => __('images.type'),
            'image.mimes' => __('images.type'),
            'image.max' => __('images.too_large', ['mb' => intdiv($maxKb, 1024)]),
        ]);

        $old = $banner->exists ? $banner->image_path : null;
        if ($this->image !== null) {
            try {
                $stored = $images->store($this->image);
            } catch (ValidationException $e) {
                $this->addError('image', collect($e->errors())->flatten()->first());

                return;
            }
            $banner->forceFill(['image_path' => $stored['path'], 'width' => $stored['width'], 'height' => $stored['height']]);
        }

        $created = ! $banner->exists;
        $banner->fill([
            'title' => trim($data['title']),
            'link_url' => blank($data['linkUrl']) ? null : trim($data['linkUrl']),
            'country_id' => blank($data['countryId']) ? null : (int) $data['countryId'],
            'placement' => $data['placement'],
            'starts_at' => blank($data['startsOn']) ? null : Carbon::parse($data['startsOn'])->startOfDay(),
            'ends_at' => blank($data['endsOn']) ? null : Carbon::parse($data['endsOn'])->endOfDay(),
            'is_active' => $this->active,
        ]);
        if ($created) {
            $banner->created_by = Auth::id();
        }
        $banner->save();
        if ($old !== null && $old !== $banner->image_path) {
            $images->delete($old);
        }

        $audit->record(Auth::user(), $created ? 'banner.created' : 'banner.updated', $banner, null, [
            'title' => $banner->title, 'link' => $banner->link_url, 'country' => $banner->country_id,
            'placement' => $banner->placement->value, 'active' => $banner->is_active,
        ]);
        session()->flash('status', __('admin.catalogue.saved', ['name' => $banner->title]));
        $this->resetForm();
    }

    public function delete(int $id, BannerImages $images, AuditLogger $audit): void
    {
        $this->requirePermission(Permissions::BOOSTS_MANAGE);
        $banner = Banner::findOrFail($id);
        $banner->delete();
        $images->delete($banner->image_path);
        $audit->record(Auth::user(), 'banner.deleted', null, null, ['title' => $banner->title]);
        session()->flash('status', __('admin.banners.deleted', ['title' => $banner->title]));
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->reset(['selectedId', 'editing', 'title', 'image', 'linkUrl', 'countryId', 'placement', 'startsOn', 'endsOn', 'active']);
        $this->resetValidation();
    }

    public function render(): View
    {
        return view('livewire.admin.banners', [
            'banners' => Banner::with('country')->latest('id')->paginate(30),
            'countries' => ['' => __('admin.banners.all_countries')] + Country::where('is_active', true)->orderBy('name')->pluck('name', 'id')->all(),
            'selected' => $this->selectedId === null ? null : Banner::find($this->selectedId),
        ]);
    }
}
