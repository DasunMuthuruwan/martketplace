<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Owner and Admin must have confirmed two-factor authentication before using the admin area
 * (CD-11, docs/architecture/12-authentication-and-2fa.md §2). Moderators may opt in.
 */
class EnsureStaffTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->mustSetUpTwoFactor()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => __('staff.two_factor_required')], 403);
            }

            return redirect()->route('account.two-factor')->with('status', __('staff.two_factor_required'));
        }

        return $next($request);
    }
}
