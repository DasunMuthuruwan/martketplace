<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Services\Seo\PageSeo;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** `/page/{slug}`: published static pages (ADM-06). Unpublished pages are 404, even for staff. */
class PageController extends Controller
{
    public function show(string $slug): View
    {
        $page = Page::where('slug', $slug)->where('is_published', true)->firstOrFail();
        $description = $page->meta['description'] ?? Str::limit(trim(strip_tags($page->html()->toHtml())), 160);

        return view('public.page', [
            'page' => $page,
            'seo' => PageSeo::make($page->title, $description, route('page.show', $page->slug)),
        ]);
    }
}
