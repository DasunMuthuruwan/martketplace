<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Layouts include @vite; tests do not need the built assets (CI has no Node build step).
        $this->withoutVite();
    }
}
