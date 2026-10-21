<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    /**
     * @return array<string, array{0: int}>
     */
    public static function statusCodes(): array
    {
        return ['400' => [400], '403' => [403], '404' => [404], '410' => [410], '413' => [413], '419' => [419], '429' => [429], '500' => [500], '503' => [503]];
    }

    #[DataProvider('statusCodes')]
    public function test_custom_error_page_is_rendered_from_language_strings(int $status): void
    {
        config(['app.debug' => false]);
        Route::get('/_test/error', fn () => abort($status));

        foreach (["errors.title.{$status}", "errors.message.{$status}"] as $key) {
            $this->assertTrue(trans()->has($key), "Missing translation: {$key}");
        }

        $response = $this->get('/_test/error');

        $response->assertStatus($status)
            ->assertSee(__("errors.title.{$status}"))
            ->assertSee(__("errors.message.{$status}"))
            ->assertSee('noindex', false);
    }

    public function test_server_error_page_never_shows_exception_details_when_debug_is_off(): void
    {
        config(['app.debug' => false]);
        Route::get('/_test/crash', fn () => throw new \RuntimeException('Secret internal detail'));

        $this->get('/_test/crash')
            ->assertStatus(500)
            ->assertDontSee('Secret internal detail')
            ->assertSee(__('errors.title.500'));
    }
}
