<?php

namespace App\Http\Controllers;

use App\Services\Geo\CountryContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * `POST /country` — the visitor picks a country (country chooser or switcher). The choice is
 * remembered in a cookie and the visitor goes to that country's home `/{cc}` (built in T5.2).
 */
class CountryController extends Controller
{
    public function choose(Request $request, CountryContext $context): RedirectResponse
    {
        $country = $context->find(is_string($request->input('country')) ? $request->input('country') : null);
        if ($country === null) {
            throw ValidationException::withMessages(['country' => __('geo.validation.unavailable', ['label' => __('geo.labels.country')])]);
        }

        return redirect()->to(url('/'.strtolower($country->iso2)))->withCookie($context->rememberCookie($country));
    }
}
