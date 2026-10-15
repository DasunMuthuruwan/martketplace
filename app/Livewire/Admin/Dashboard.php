<?php

namespace App\Livewire\Admin;

use App\Authorization\Permissions;
use App\Enums\ListingStatus;
use App\Enums\ReportStatus;
use App\Livewire\Admin\Concerns\RequiresPermission;
use App\Models\Category;
use App\Models\Country;
use App\Models\Listing;
use App\Models\LocationRequest;
use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Admin dashboard (ADM-01): ads waiting for review, open reports, open place requests, new users
 * (7 and 30 days) and live ads by top-level category and by country. The heavier breakdowns are
 * cached for five minutes; the work queues are always current.
 */
#[Layout('layouts.admin')]
#[Title('Dashboard')]
class Dashboard extends Component
{
    use RequiresPermission;

    public function mount(): void
    {
        $this->requirePermission(Permissions::ACCESS_ADMIN);
    }

    public function render(): View
    {
        $breakdown = Cache::remember('admin:dashboard-breakdown', now()->addMinutes(5), fn (): array => $this->breakdown());

        return view('livewire.admin.dashboard', [
            'pending' => Listing::where('status', ListingStatus::PendingReview)->count(),
            'openReports' => Report::where('status', ReportStatus::Open)->count(),
            'placeRequests' => LocationRequest::where('status', 'pending')->count(),
            'newUsers7' => User::where('created_at', '>=', now()->subDays(7))->count(),
            'newUsers30' => User::where('created_at', '>=', now()->subDays(30))->count(),
            'byCategory' => $breakdown['categories'],
            'byCountry' => $breakdown['countries'],
            'countryNames' => Country::whereIn('iso2', array_keys($breakdown['countries']))->get()
                ->mapWithKeys(fn (Country $country) => [$country->iso2 => $country->name])->all(),
            'live' => array_sum($breakdown['categories']),
        ]);
    }

    /** @return array{categories: array<string, int>, countries: array<string, int>} */
    private function breakdown(): array
    {
        $perCategory = Listing::public()->toBase()->selectRaw('category_id, count(*) as total')->groupBy('category_id')->pluck('total', 'category_id');
        $categories = Category::get(['id', 'parent_id', 'name'])->keyBy('id');

        $byRoot = [];
        foreach ($perCategory as $categoryId => $total) {
            $category = $categories->get($categoryId);
            $root = $category === null ? null : ($category->parent_id === null ? $category : $categories->get($category->parent_id));
            $name = $root->name ?? '?';
            $byRoot[$name] = ($byRoot[$name] ?? 0) + (int) $total;
        }
        arsort($byRoot);

        $perCountry = Listing::public()->toBase()->selectRaw('country_id, count(*) as total')->groupBy('country_id')->pluck('total', 'country_id');
        $codes = Country::whereIn('id', $perCountry->keys())->pluck('iso2', 'id');
        $byCountry = [];
        foreach ($perCountry as $countryId => $total) {
            $byCountry[(string) ($codes[$countryId] ?? '?')] = (int) $total;
        }
        arsort($byCountry);

        return ['categories' => $byRoot, 'countries' => $byCountry];
    }
}
