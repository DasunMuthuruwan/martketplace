<?php

namespace Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use Tests\Support\AccessibilityAudit;

/** The structural checker used by the page tests finds what it should (and nothing else). */
class AccessibilityAuditTest extends TestCase
{
    public function test_a_clean_page_passes(): void
    {
        $html = <<<'HTML'
            <!DOCTYPE html><html lang="en"><body><main>
            <h1>Title</h1>
            <a href="/x"><svg aria-hidden="true"></svg>Read more</a>
            <button type="button" aria-label="Close"><svg></svg></button>
            <label for="q">Search</label><input id="q" name="q">
            <label>Remember <input type="checkbox" name="r"></label>
            <select aria-labelledby="sl"><option>A</option></select><span id="sl">Sort</span>
            <img src="/a.jpg" alt="">
            <input type="hidden" name="t">
            <template><button></button></template>
            <button hidden></button>
            </main></body></html>
            HTML;

        $this->assertSame([], (new AccessibilityAudit($html))->problems());
    }

    public function test_problems_are_reported(): void
    {
        $html = <<<'HTML'
            <!DOCTYPE html><html><body>
            <h1>One</h1><h1>Two</h1>
            <a href="/x"><svg></svg></a>
            <button type="button"><span aria-hidden="true">×</span></button>
            <input id="q" name="q">
            <img src="/a.jpg">
            <p id="d">a</p><p id="d">b</p>
            <div aria-describedby="nope"></div>
            </body></html>
            HTML;

        $problems = (new AccessibilityAudit($html))->problems();

        $this->assertContains('html has no lang attribute', $problems);
        $this->assertContains('expected 1 h1, found 2', $problems);
        $this->assertContains('expected 1 main landmark, found 0', $problems);
        $this->assertContains('duplicate id "d" (2×)', $problems);
        $this->assertContains('no accessible name: <a href="/x">', $problems);
        $this->assertContains('no accessible name: <button type="button">', $problems);
        $this->assertContains('form control without a label: <input id="q" name="q">', $problems);
        $this->assertContains('img without alt: <img src="/a.jpg">', $problems);
        $this->assertContains('aria-describedby points at missing id "nope": <div>', $problems);
    }
}
