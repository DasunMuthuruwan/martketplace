<?php

namespace App\Support;

use App\Models\User;
use App\Services\Moderation\ModerationRules;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Typed site settings (ADM-05, architecture §2: "Settings with config defaults"). Each setting maps
 * to a config path; values saved by the Owner are stored in `settings` and laid over the config at
 * boot, so the code keeps reading config() and the defaults stay in config/marketplace.php and
 * .env. Only the keys defined here can be stored.
 */
final class Settings
{
    private const CACHE_KEY = 'settings:values';

    /**
     * key => [config path, type, validation rules]
     *
     * @return array<string, array{config: string, type: 'string'|'int'|'bool', rules: list<mixed>}>
     */
    public static function definitions(): array
    {
        return [
            'site_name' => ['config' => 'app.name', 'type' => 'string', 'rules' => ['required', 'string', 'max:60']],
            'contact_email' => ['config' => 'marketplace.site.contact_email', 'type' => 'string', 'rules' => ['nullable', 'email', 'max:191']],
            'seo_country_description' => ['config' => 'marketplace.seo.country_description', 'type' => 'string', 'rules' => ['nullable', 'string', 'max:300']],
            'moderation_mode' => ['config' => 'marketplace.moderation_mode', 'type' => 'string', 'rules' => ['required', Rule::in([ModerationRules::MODE_ALL, ModerationRules::MODE_NEW_USERS, ModerationRules::MODE_NONE])]],
            'listing_expiry_days' => ['config' => 'marketplace.listings.expiry_days', 'type' => 'int', 'rules' => ['required', 'integer', 'min:7', 'max:365']],
            'max_active_per_user' => ['config' => 'marketplace.listings.max_active_per_user', 'type' => 'int', 'rules' => ['required', 'integer', 'min:1', 'max:1000']],
            'max_new_per_day' => ['config' => 'marketplace.listings.max_new_per_day', 'type' => 'int', 'rules' => ['required', 'integer', 'min:1', 'max:200']],
            'max_images' => ['config' => 'marketplace.images.default_max_per_listing', 'type' => 'int', 'rules' => ['required', 'integer', 'min:1', 'max:20']],
            'top_ad_slots' => ['config' => 'marketplace.boosts.top_slots', 'type' => 'int', 'rules' => ['required', 'integer', 'min:0', 'max:4']],
            'contact_call' => ['config' => 'marketplace.contact.call_enabled', 'type' => 'bool', 'rules' => ['boolean']],
            'contact_whatsapp' => ['config' => 'marketplace.contact.whatsapp_enabled', 'type' => 'bool', 'rules' => ['boolean']],
        ];
    }

    /** Lay the stored values over the config. Safe before migrations have run. */
    public static function apply(): void
    {
        try {
            $stored = Cache::rememberForever(self::CACHE_KEY, fn () => DB::table('settings')->pluck('value', 'key')->all());
        } catch (Throwable) {
            return; // no database or table yet (install, first migration)
        }

        foreach (self::definitions() as $key => $definition) {
            if (array_key_exists($key, $stored)) {
                config([$definition['config'] => self::cast(json_decode((string) $stored[$key], true), $definition['type'])]);
            }
        }
    }

    /** @return array<string, string|int|bool|null> current effective values */
    public static function current(): array
    {
        $values = [];
        foreach (self::definitions() as $key => $definition) {
            $values[$key] = self::cast(config($definition['config']), $definition['type']);
        }

        return $values;
    }

    /** @return array<string, list<mixed>> */
    public static function rules(): array
    {
        return array_map(fn (array $definition) => $definition['rules'], self::definitions());
    }

    /**
     * Store validated values; returns the changed keys with before/after.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, array{before: mixed, after: mixed}>
     */
    public static function save(array $values, User $by): array
    {
        $before = self::current();
        $changes = [];

        DB::transaction(function () use ($values, $by, $before, &$changes): void {
            foreach (self::definitions() as $key => $definition) {
                if (! array_key_exists($key, $values)) {
                    continue;
                }
                $value = self::cast($values[$key], $definition['type']);
                if ($value === $before[$key]) {
                    continue;
                }
                DB::table('settings')->updateOrInsert(['key' => $key], ['value' => json_encode($value), 'updated_by' => $by->id, 'updated_at' => now()]);
                config([$definition['config'] => $value]);
                $changes[$key] = ['before' => $before[$key], 'after' => $value];
            }
        });
        Cache::forget(self::CACHE_KEY);

        return $changes;
    }

    private static function cast(mixed $value, string $type): string|int|bool|null
    {
        return match ($type) {
            'int' => (int) $value,
            'bool' => (bool) $value,
            default => $value === null || $value === '' ? null : (string) $value,
        };
    }
}
