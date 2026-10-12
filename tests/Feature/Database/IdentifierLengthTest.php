<?php

namespace Tests\Feature\Database;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * MySQL/MariaDB reject identifiers longer than 64 characters; SQLite does not. Laravel's generated
 * index names can exceed that, so this guard runs on every database (regression: T4.1 EAV indexes
 * broke migrations on MariaDB only).
 */
class IdentifierLengthTest extends TestCase
{
    use RefreshDatabase;

    private const MYSQL_MAX_IDENTIFIER = 64;

    public function test_every_table_index_and_foreign_key_name_fits_mysql(): void
    {
        $tooLong = [];

        foreach (Schema::getTables() as $table) {
            $names = [$table['name']];
            foreach (Schema::getIndexes($table['name']) as $index) {
                $names[] = $index['name'];
            }
            foreach (Schema::getForeignKeys($table['name']) as $foreignKey) {
                $names[] = $foreignKey['name'] ?? '';
            }

            foreach ($names as $name) {
                if (strlen((string) $name) > self::MYSQL_MAX_IDENTIFIER) {
                    $tooLong[] = "{$table['name']}: {$name}";
                }
            }
        }

        $this->assertSame([], $tooLong, 'Give these an explicit shorter name in the migration');
    }
}
