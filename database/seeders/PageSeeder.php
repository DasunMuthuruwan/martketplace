<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

/**
 * Creates the standard pages (ADM-06) unpublished and empty; the Owner writes and publishes them
 * in Admin → Pages (legal texts need the Owner's own review). Existing pages are never changed.
 */
class PageSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Page::DEFAULTS as $slug => $title) {
            Page::firstOrCreate(['slug' => $slug], ['title' => ['en' => $title], 'body' => ['en' => ''], 'is_published' => false]);
        }
    }
}
