<?php

namespace App\Http\Controllers\Account;

use App\Actions\Listings\ManageListingImages;
use App\Http\Controllers\Controller;
use App\Models\Listing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Photos step (layout doc §3.4, components §2 image-uploader): upload several photos at once (each
 * one checked and stored by ManageListingImages; one bad file does not stop the others), move a
 * photo earlier or later, set the cover, remove. Plain forms; resources/js/ui/posting.js adds an
 * upload progress bar and asks for JSON.
 */
class ListingPhotosController extends Controller
{
    public function __construct(private readonly ManageListingImages $images) {}

    public function store(Request $request, Listing $listing): RedirectResponse|JsonResponse
    {
        Gate::authorize('update', $listing);
        $files = array_values(array_filter((array) $request->file('photos', []), fn ($f) => $f instanceof UploadedFile));
        if ($files === []) {
            return $this->done($request, $listing, [__('posting.photos.none_chosen')], 0);
        }

        $errors = [];
        $added = 0;
        foreach ($files as $file) {
            try {
                $this->images->add($request->user(), $listing, $file);
                $added++;
            } catch (ValidationException $e) {
                $errors[] = $file->getClientOriginalName().': '.collect($e->errors())->flatten()->first();
            }
        }

        return $this->done($request, $listing, $errors, $added);
    }

    /** Move one photo earlier (-1) or later (+1); the order is saved for every photo. */
    public function move(Request $request, Listing $listing, int $image): RedirectResponse
    {
        Gate::authorize('update', $listing);
        $ids = $listing->images()->orderBy('sort_order')->orderBy('id')->pluck('id')->all();
        $from = array_search($image, $ids, true);
        $to = $from === false ? false : $from + ($request->input('direction') === 'earlier' ? -1 : 1);
        if ($from !== false && $to >= 0 && $to < count($ids)) {
            [$ids[$from], $ids[$to]] = [$ids[$to], $ids[$from]];

            return $this->run(fn () => $this->images->reorder($request->user(), $listing, $ids), $listing);
        }

        return back();
    }

    public function cover(Request $request, Listing $listing, int $image): RedirectResponse
    {
        Gate::authorize('update', $listing);

        return $this->run(fn () => $this->images->setCover($request->user(), $listing, $image), $listing);
    }

    public function destroy(Request $request, Listing $listing, int $image): RedirectResponse
    {
        Gate::authorize('update', $listing);

        return $this->run(fn () => $this->images->remove($request->user(), $listing, $image), $listing);
    }

    /** @param  list<string>  $errors */
    private function done(Request $request, Listing $listing, array $errors, int $added): RedirectResponse|JsonResponse
    {
        $status = $added > 0 ? trans_choice('posting.photos.added', $added, ['count' => $added]) : null;
        if ($request->expectsJson()) {
            // The page reloads after the upload: flash the outcome so it is shown there too.
            session()->flash('photo_errors', $errors);
            session()->flash('status', $status);

            return response()->json(['added' => $added, 'errors' => $errors, 'status' => $status], $added > 0 ? 200 : 422);
        }

        return redirect()->route('listings.edit', [$listing, 'photos'])
            ->with('photo_errors', $errors)
            ->with('status', $status);
    }

    private function run(callable $action, Listing $listing): RedirectResponse
    {
        try {
            $action();
        } catch (ValidationException $e) {
            return redirect()->route('listings.edit', [$listing, 'photos'])
                ->with('photo_errors', [collect($e->errors())->flatten()->first()]);
        }

        return redirect()->route('listings.edit', [$listing, 'photos'])->with('status', __('posting.saved'));
    }
}
