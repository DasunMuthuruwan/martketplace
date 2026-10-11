# GeoNames Data Snapshot

| File | Source | Retrieved | Licence |
|---|---|---|---|
| `countryInfo.txt` | https://download.geonames.org/export/dump/countryInfo.txt | 2026-10-11 (file dated 2026-10-11) | [CC BY 4.0](https://creativecommons.org/licenses/by/4.0/) — attribution "Location data © GeoNames (geonames.org)" is shown on the site (`config('marketplace.geo.attribution')`) |

Used by the country seeder (`database/seeders/CountrySeeder.php`) and, from T1.3, by the `geo:import-countries` command.

**Refresh:** download the current file to this folder on a development machine, run the seeders/import on staging, review the differences, then commit. Never fetch at runtime.
