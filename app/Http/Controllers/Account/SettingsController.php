<?php

namespace App\Http\Controllers\Account;

use App\Actions\Account\DeleteAccount;
use App\Actions\Account\UpdateContactSettings;
use App\Enums\ContactVisibility;
use App\Http\Controllers\Controller;
use App\Models\Country;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Propaganistas\LaravelPhone\PhoneNumber;

/**
 * Account settings (routes doc §3). Profile and password forms post to Fortify's endpoints;
 * contact settings and deletion are handled here. Plain views until F3.2.
 */
class SettingsController extends Controller
{
    public function profile(Request $request): View
    {
        $user = $request->user();

        return view('account.settings.profile', [
            'user' => $user,
            'countries' => Country::active()->orWhere('id', $user->country_id)->get()->sortBy('name')->values(),
            'phoneCountries' => Country::phonePickerOptions(),
        ]);
    }

    public function contact(Request $request): View
    {
        $user = $request->user();

        return view('account.settings.contact', [
            'user' => $user,
            'visibilities' => ContactVisibility::cases(),
            'whatsappCountry' => $user->whatsapp_phone !== null ? (new PhoneNumber($user->whatsapp_phone))->getCountry() : null,
            'phoneCountries' => Country::phonePickerOptions(),
        ]);
    }

    public function updateContact(Request $request, UpdateContactSettings $action): RedirectResponse
    {
        $action->update($request->user(), $request->only(['contact_visibility', 'whatsapp_country', 'whatsapp_phone']));

        return to_route('account.settings.contact')->with('status', __('account.contact.saved'));
    }

    public function security(Request $request): View
    {
        return view('account.settings.security', ['user' => $request->user()]);
    }

    public function destroy(Request $request, DeleteAccount $action): RedirectResponse
    {
        $action->delete($request->user(), $request->only('password'));

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('home')->with('status', __('account.delete.done'));
    }
}
