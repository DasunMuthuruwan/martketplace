<?php

namespace Tests\Unit\Guards;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\HardcodedValueScanner;

class NoHardcodedValuesTest extends TestCase
{
    public function test_application_code_contains_no_hardcoded_domain_country_currency_or_unit(): void
    {
        $findings = HardcodedValueScanner::scanProject(dirname(__DIR__, 3));

        $this->assertSame([], $findings, "Hardcoded values found (move them to config, the database, lang files or seeders):\n".implode("\n", $findings));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function forbiddenSamples(): array
    {
        return [
            'production domain' => ["return 'https://marketplace.dev-talk.com/ad';"],
            'domain without scheme' => ['$host = "devtalk.lk";'],
            'currency' => ["\$currency = 'LKR';"],
            'country name' => ["\$label = 'Sri Lanka';"],
            'phone prefix' => ["\$phone = '+94771234567';"],
            'land unit' => ["\$unit = 'perch';"],
            'unknown external host' => ['<script src="https://cdn.unknown-tracker.net/x.js"></script>'],
        ];
    }

    #[DataProvider('forbiddenSamples')]
    public function test_scanner_detects_forbidden_values(string $sample): void
    {
        $this->assertNotEmpty(HardcodedValueScanner::scanText($sample));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function allowedSamples(): array
    {
        return [
            'config driven url' => ["return config('app.url').'/ad';"],
            'localhost default' => ["'url' => env('APP_URL', 'http://localhost'),"],
            'svg namespace' => ['<svg xmlns="http://www.w3.org/2000/svg"></svg>'],
            'approved service' => ['https://challenges.cloudflare.com/turnstile/v0/api.js'],
            'unrelated words' => ['$perceptron = "Lankan curry"; // LKRX is not a code'],
        ];
    }

    #[DataProvider('allowedSamples')]
    public function test_scanner_allows_safe_values(string $sample): void
    {
        $this->assertSame([], HardcodedValueScanner::scanText($sample));
    }
}
