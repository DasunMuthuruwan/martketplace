<?php

namespace App\Services\Geo\GeoNames;

/**
 * Counts from one import run, printed by the geo:import-* commands.
 */
class ImportResult
{
    public int $created = 0;

    public int $updated = 0;

    public int $unchanged = 0;

    public int $deactivated = 0;

    public int $skipped = 0;

    /** Rows deliberately left out (population threshold, excluded feature codes). Never deactivated. */
    public int $filtered = 0;

    /** @var list<string> */
    public array $notes = [];

    public function note(string $message): void
    {
        if (count($this->notes) < 50) {
            $this->notes[] = $message;
        }
    }

    /**
     * @return array<string, int>
     */
    public function counts(): array
    {
        return [
            'created' => $this->created,
            'updated' => $this->updated,
            'unchanged' => $this->unchanged,
            'deactivated' => $this->deactivated,
            'skipped' => $this->skipped,
            'filtered' => $this->filtered,
        ];
    }
}
