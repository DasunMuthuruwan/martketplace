<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Reference data only. Every seeder is idempotent and safe to re-run in any environment.
 * No user accounts are seeded: never ship default credentials.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            CurrencySeeder::class,
            CountrySeeder::class,
        ]);
    }
}
