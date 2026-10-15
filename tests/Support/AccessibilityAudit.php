<?php

namespace Tests\Support;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Structural accessibility checks on rendered HTML (forms/a11y doc §3), without a browser:
 * one h1 and one main, page language, image alt text, accessible names for links, buttons and
 * form controls, unique ids, and aria references that point at real elements. It does not replace
 * axe or a screen-reader pass (contrast, focus order and live behaviour need a browser).
 */
final class AccessibilityAudit
{
    private DOMXPath $xpath;

    /** @var array<string, int> */
    private array $ids = [];

    public function __construct(string $html)
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $this->xpath = new DOMXPath($document);

        foreach ($this->xpath->query('//*[@id]') ?: [] as $element) {
            /** @var DOMElement $element */
            $id = $element->getAttribute('id');
            $this->ids[$id] = ($this->ids[$id] ?? 0) + 1;
        }
    }

    /** @return list<string> Problems found; empty when the page passes. */
    public function problems(): array
    {
        $problems = [];

        $lang = $this->xpath->query('//html[@lang]')->length ?? 0;
        if ($lang !== 1) {
            $problems[] = 'html has no lang attribute';
        }
        if (($count = $this->count('//h1')) !== 1) {
            $problems[] = "expected 1 h1, found {$count}";
        }
        if (($count = $this->count('//main')) !== 1) {
            $problems[] = "expected 1 main landmark, found {$count}";
        }

        foreach ($this->ids as $id => $times) {
            if ($times > 1) {
                $problems[] = "duplicate id \"{$id}\" ({$times}×)";
            }
        }

        foreach ($this->elements('//img[not(@alt)]') as $img) {
            $problems[] = 'img without alt: '.$this->describe($img);
        }

        foreach ($this->elements('//a[@href] | //button | //summary | //*[@role="button"]') as $control) {
            if ($this->hidden($control) || $this->name($control) === '') {
                if (! $this->hidden($control)) {
                    $problems[] = 'no accessible name: '.$this->describe($control);
                }
            }
        }

        foreach ($this->elements('//input[not(@type="hidden")] | //select | //textarea') as $field) {
            if ($this->hidden($field)) {
                continue;
            }
            $type = strtolower($field->getAttribute('type'));
            if (in_array($type, ['submit', 'button', 'reset'], true)) {
                if (trim($field->getAttribute('value')) === '' && $this->ariaName($field) === '') {
                    $problems[] = 'button input without a value: '.$this->describe($field);
                }

                continue;
            }
            if (! $this->labelled($field)) {
                $problems[] = 'form control without a label: '.$this->describe($field);
            }
        }

        foreach ($this->elements('//*[@aria-labelledby or @aria-describedby or @aria-controls]') as $element) {
            foreach (['aria-labelledby', 'aria-describedby', 'aria-controls'] as $attribute) {
                foreach (preg_split('/\s+/', trim($element->getAttribute($attribute))) ?: [] as $ref) {
                    if ($ref !== '' && ! isset($this->ids[$ref])) {
                        $problems[] = "{$attribute} points at missing id \"{$ref}\": ".$this->describe($element);
                    }
                }
            }
        }

        return array_values(array_unique($problems));
    }

    private function count(string $query): int
    {
        return $this->xpath->query($query)->length ?? 0;
    }

    /** @return list<DOMElement> */
    private function elements(string $query): array
    {
        $found = [];
        foreach ($this->xpath->query($query) ?: [] as $node) {
            if ($node instanceof DOMElement && ! $this->insideTemplate($node)) {
                $found[] = $node;
            }
        }

        return $found;
    }

    private function insideTemplate(DOMElement $element): bool
    {
        for ($node = $element->parentNode; $node instanceof DOMElement; $node = $node->parentNode) {
            if ($node->tagName === 'template') {
                return true;
            }
        }

        return false;
    }

    /** Hidden from everyone (the hidden attribute or aria-hidden on the element or an ancestor). */
    private function hidden(DOMElement $element): bool
    {
        for ($node = $element; $node instanceof DOMElement; $node = $node->parentNode) {
            if ($node->hasAttribute('hidden') || $node->getAttribute('aria-hidden') === 'true') {
                return true;
            }
        }

        return false;
    }

    private function ariaName(DOMElement $element): string
    {
        $label = trim($element->getAttribute('aria-label'));
        if ($label !== '') {
            return $label;
        }
        $text = '';
        foreach (preg_split('/\s+/', trim($element->getAttribute('aria-labelledby'))) ?: [] as $ref) {
            if ($ref !== '' && ($target = $this->xpath->query('//*[@id="'.$ref.'"]')->item(0)) instanceof DOMElement) {
                $text .= ' '.$this->text($target);
            }
        }

        return trim($text);
    }

    /** Accessible name of a link or button: aria, then visible text and image alt, then title. */
    private function name(DOMElement $element): string
    {
        $name = $this->ariaName($element);
        if ($name === '') {
            $name = $this->text($element);
        }
        if ($name === '') {
            $name = trim($element->getAttribute('title'));
        }

        return $name;
    }

    /** Text content without aria-hidden parts, plus alt text of images. */
    private function text(DOMElement $element): string
    {
        $text = '';
        foreach ($element->childNodes as $child) {
            if ($child instanceof DOMElement) {
                if ($child->getAttribute('aria-hidden') === 'true' || $child->tagName === 'svg') {
                    continue;
                }
                $text .= ' '.($child->tagName === 'img' ? $child->getAttribute('alt') : $this->text($child));
            } else {
                $text .= ' '.$child->textContent;
            }
        }

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    private function labelled(DOMElement $field): bool
    {
        if ($this->ariaName($field) !== '' || trim($field->getAttribute('title')) !== '') {
            return true;
        }
        $id = $field->getAttribute('id');
        if ($id !== '' && $this->count('//label[@for="'.$id.'"]') > 0) {
            return true;
        }
        for ($node = $field->parentNode; $node instanceof DOMElement; $node = $node->parentNode) {
            if ($node->tagName === 'label') {
                return true;
            }
        }

        return false;
    }

    private function describe(DOMElement $element): string
    {
        $attributes = [];
        foreach (['id', 'name', 'href', 'class', 'type', 'src'] as $name) {
            if ($element->hasAttribute($name)) {
                $attributes[] = $name.'="'.mb_strimwidth($element->getAttribute($name), 0, 60, '…').'"';
            }
        }

        return '<'.$element->tagName.($attributes ? ' '.implode(' ', $attributes) : '').'>';
    }
}
