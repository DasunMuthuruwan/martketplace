<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Staff session limits (docs/architecture/12-authentication-and-2fa.md §6): signed out after
 * an idle period (default 30 min) and after an absolute lifetime (default 8 h), whatever the
 * activity. "Remember me" cannot bypass this: a staff login restored from the remember cookie
 * is rejected, so staff always sign in with their password (and 2FA).
 */
class EnforceStaffSessionLimits
{
    public const STARTED_AT = 'staff_session.started_at';

    public const LAST_SEEN = 'staff_session.last_seen';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $request->hasSession() || ! $user->isStaff()) {
            return $next($request);
        }

        $session = $request->session();
        $now = now()->getTimestamp();
        $idle = (int) config('marketplace.staff_session.idle_minutes') * 60;
        $absolute = (int) config('marketplace.staff_session.absolute_minutes') * 60;

        $startedAt = (int) $session->get(self::STARTED_AT, $now);
        $lastSeen = (int) $session->get(self::LAST_SEEN, $now);

        if (Auth::guard('web')->viaRemember() || $now - $lastSeen > $idle || $now - $startedAt > $absolute) {
            return $this->signOut($request);
        }

        $session->put([self::STARTED_AT => $startedAt, self::LAST_SEEN => $now]);

        return $next($request);
    }

    private function signOut(Request $request): Response
    {
        $message = __('staff.session_expired');

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 401);
        }

        return redirect()->guest(route('login'))->with('status', $message);
    }
}
