<?php

// Only overrides; Livewire merges its own defaults for every other key.
return [
    // The site sends a strict Content-Security-Policy without 'unsafe-eval' (security doc §2):
    // use Livewire's CSP-safe build (simple expressions in wire:/x- attributes only).
    'csp_safe' => true,
];
