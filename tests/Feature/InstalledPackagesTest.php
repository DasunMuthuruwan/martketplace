<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\ImageManager;
use Laravel\Fortify\Fortify;
use Livewire\Component;
use PHPUnit\Framework\Attributes\DataProvider;
use Propaganistas\LaravelPhone\Rules\Phone;
use Spatie\Permission\PermissionServiceProvider;
use Spatie\Translatable\HasTranslations;
use Tests\TestCase;

/**
 * Smoke tests for the approved package set (docs/architecture/10-package-assessment.md §3).
 * Also catches an incomplete manual vendor upload on hosting without Composer.
 */
class InstalledPackagesTest extends TestCase
{
    /**
     * @return array<string, array{0: class-string}>
     */
    public static function approvedPackageClasses(): array
    {
        return [
            'livewire/livewire' => [Component::class],
            'laravel/fortify' => [Fortify::class],
            'spatie/laravel-permission' => [PermissionServiceProvider::class],
            'spatie/laravel-translatable' => [HasTranslations::class],
            'intervention/image' => [ImageManager::class],
            'propaganistas/laravel-phone' => [Phone::class],
        ];
    }

    #[DataProvider('approvedPackageClasses')]
    public function test_approved_package_is_installed(string $class): void
    {
        $this->assertTrue(class_exists($class) || trait_exists($class), "{$class} is missing from vendor/");
    }

    public function test_only_the_enabled_fortify_features_expose_routes(): void
    {
        $enabled = [
            'login', 'register', 'password.request', 'password.reset', 'verification.notice', 'password.confirm', // T2.1
            'two-factor.login', 'two-factor.enable', // T2.2
            'user-profile-information.update', 'user-password.update', // T2.3
        ];
        foreach ($enabled as $name) {
            $this->assertTrue(Route::has($name), "Fortify route [{$name}] should be enabled");
        }
    }

    public function test_passkey_routes_are_never_registered(): void
    {
        foreach (['passkey.login', 'passkey.login-options', 'passkey.store', 'passkey.confirm'] as $name) {
            $this->assertFalse(Route::has($name), "Passkeys are disabled (ADR-015): [{$name}] must not exist");
        }
    }

    public function test_intervention_image_can_encode_webp_and_jpeg_with_gd(): void
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('GD extension not available.');
        }

        $manager = new ImageManager(new GdDriver);
        $image = $manager->createImage(16, 12);

        $this->assertNotEmpty((string) $image->encodeUsingFileExtension('webp'));
        $this->assertNotEmpty((string) $image->encodeUsingFileExtension('jpg'));
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: bool}>
     */
    public static function phoneNumbers(): array
    {
        return [
            'valid LK mobile' => ['+94771234567', 'LK', true],
            'valid LK local format' => ['0771234567', 'LK', true],
            'valid GB mobile' => ['+447400123456', 'GB', true],
            'reserved fictional GB range' => ['+447700900123', 'GB', false],
            'valid US number' => ['+12015550123', 'US', true],
            'too short' => ['12345', 'LK', false],
            'letters' => ['not-a-phone', 'GB', false],
        ];
    }

    #[DataProvider('phoneNumbers')]
    public function test_phone_rule_validates_numbers_per_country(string $number, string $country, bool $valid): void
    {
        $validator = Validator::make(['phone' => $number], ['phone' => [(new Phone)->country($country)]]);

        $this->assertSame($valid, $validator->passes());
    }
}
