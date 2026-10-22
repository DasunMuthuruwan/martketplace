<?php

namespace App\Services\Search;

use App\Models\Country;
use App\Support\Bots;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Counted search terms (G1, ADR-027 E4): per country and day, without user, session or IP. A visitor
 * counts once per term and day (remembered in their own session). Rare terms are never shown:
 * popular() only returns terms searched at least `search.min_searches` times.
 */
class SearchTerms
{
    public const SESSION_KEY = 'searched_terms';

    public const MAX_LENGTH = 60;

    /** "  Toyota   AQUA!! " → "toyota aqua"; null when too short or no letters/digits. */
    public static function normalise(?string $keyword): ?string
    {
        if ($keyword === null) {
            return null;
        }
        preg_match_all('/[\p{L}\p{N}]+/u', mb_strtolower($keyword), $words);
        $term = mb_substr(implode(' ', $words[0]), 0, self::MAX_LENGTH);

        return mb_strlen($term) >= 2 ? trim($term) : null;
    }

    public function record(Request $request, ?Country $country, ?string $keyword): void
    {
        $term = self::normalise($keyword);
        if ($term === null || Bots::is($request)) {
            return;
        }
        $today = now()->toDateString();
        $key = ($country->id ?? 0).':'.$term;
        if ($request->hasSession()) {
            $seen = (array) $request->session()->get(self::SESSION_KEY, []);
            if (($seen['date'] ?? null) !== $today) {
                $seen = ['date' => $today, 'terms' => []];
            }
            if (in_array($key, $seen['terms'], true)) {
                return;
            }
            $seen['terms'] = array_slice([...$seen['terms'], $key], -100);
            $request->session()->put(self::SESSION_KEY, $seen);
        }

        DB::table('search_terms')->upsert(
            [['country_id' => $country->id ?? 0, 'term' => $term, 'date' => $today, 'searches' => 1]],
            ['country_id', 'term', 'date'],
            ['searches' => DB::raw('search_terms.searches + 1')],
        );
    }

    /**
     * Most searched terms in the last $days days starting with a prefix (or all), most searched first.
     *
     * @return list<array{term: string, searches: int}>
     */
    public function popular(?Country $country, string $prefix = '', int $days = 30, int $limit = 5): array
    {
        $min = (int) config('marketplace.search.min_searches');

        return DB::table('search_terms')
            ->where('country_id', $country->id ?? 0)
            ->where('date', '>=', now()->subDays($days)->toDateString())
            ->when($prefix !== '', fn ($q) => $q->where('term', 'like', addcslashes($prefix, '%_\\').'%'))
            ->groupBy('term')
            ->havingRaw('SUM(searches) >= ?', [$min])
            ->orderByRaw('SUM(searches) DESC')
            ->orderBy('term')
            ->limit($limit)
            ->get([DB::raw('term'), DB::raw('SUM(searches) as total')])
            ->map(fn ($row) => ['term' => (string) $row->term, 'searches' => (int) $row->total])
            ->all();
    }
}
