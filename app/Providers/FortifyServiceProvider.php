<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Http\Responses\GenericPasswordResetLinkResponse;
use App\Models\Country;
use App\Models\User;
use App\Rules\HumanTiming;
use App\Services\Security\Turnstile;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;
use Laravel\Fortify\Fortify;

/**
 * Authentication with Laravel Fortify (headless) and our own views (docs/architecture/12-authentication-and-2fa.md).
 */
class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Identical answer for known and unknown emails (no account enumeration).
        $this->app->bind(FailedPasswordResetLinkRequestResponse::class, GenericPasswordResetLinkResponse::class);
        $this->app->bind(SuccessfulPasswordResetLinkRequestResponse::class, GenericPasswordResetLinkResponse::class);
    }

    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::redirectUserForTwoFactorAuthenticationUsing(RedirectIfTwoFactorAuthenticatable::class);

        Fortify::authenticateUsing(fn (Request $request) => $this->authenticate($request));

        $this->registerViews();
        $this->registerRateLimiters();

        Event::listen(Login::class, function (Login $event): void {
            if ($event->user instanceof User) {
                // Signing in also cancels a pending inactive-account deletion (retention §1).
                $event->user->forceFill(['last_login_at' => now(), 'inactive_warned_at' => null])->saveQuietly();
            }
        });
    }

    private function authenticate(Request $request): ?User
    {
        $email = mb_strtolower(trim((string) $request->input(Fortify::username())));
        $password = (string) $request->input('password');
        $user = User::where('email', $email)->first();

        // Always hash-check so response time doesn't reveal whether the email exists.
        $valid = Hash::check($password, $user !== null ? $user->password : $this->dummyHash());

        if ($user === null || ! $valid) {
            return null;
        }

        if (! $user->isActive()) {
            throw ValidationException::withMessages([
                Fortify::username() => __('auth.inactive', ['reason' => $user->status_reason ?: __('auth.contact_support')]),
            ]);
        }

        return $user;
    }

    private function dummyHash(): string
    {
        static $hash = null;

        return $hash ??= Hash::make(Str::random(32));
    }

    private function registerViews(): void
    {
        $botProtection = fn () => [
            'formToken' => HumanTiming::token(),
            'turnstileSiteKey' => app(Turnstile::class)->enabled() ? app(Turnstile::class)->siteKey() : null,
        ];

        Fortify::loginView(fn () => view('auth.login'));
        Fortify::registerView(fn () => view('auth.register', [
            'countries' => Country::active()->get()->sortBy('name')->values(),
            'phoneCountries' => Country::phonePickerOptions(),
        ] + $botProtection()));
        Fortify::requestPasswordResetLinkView(fn () => view('auth.forgot-password'));
        Fortify::resetPasswordView(fn (Request $request) => view('auth.reset-password', ['request' => $request]));
        Fortify::verifyEmailView(fn () => view('auth.verify-email'));
        Fortify::confirmPasswordView(fn () => view('auth.confirm-password'));
        Fortify::twoFactorChallengeView(fn () => view('auth.two-factor-challenge'));
    }

    private function registerRateLimiters(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $email = Str::transliterate(Str::lower((string) $request->input(Fortify::username())));

            return [
                Limit::perMinute(5)->by('login:'.$email.'|'.$request->ip()),
                Limit::perMinute(30)->by('login-ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('two-factor', fn (Request $request) => Limit::perMinute(5)->by('2fa:'.$request->session()->get('login.id').'|'.$request->ip()));

        RateLimiter::for('verification', fn (Request $request) => Limit::perMinute(6)->by('verify:'.($request->user()?->id ?: $request->ip())));

        RateLimiter::for('register', fn (Request $request) => Limit::perHour(5)->by('register:'.$request->ip()));

        RateLimiter::for('password-reset', fn (Request $request) => [
            Limit::perMinute(3)->by('reset:'.$request->ip()),
            Limit::perHour(10)->by('reset-hour:'.$request->ip()),
        ]);
    }
}
