<?php

namespace App\Actions\Listings;

use App\Exceptions\Listings\ContactChallengeRequired;
use App\Models\ContactReveal;
use App\Models\Listing;
use App\Models\User;
use App\Rules\PassesTurnstile;
use App\Services\Audit\AuditLogger;
use App\Services\Contact\ContactChannels;
use App\Services\Contact\ContactResolver;
use App\Services\Security\Turnstile;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Validator;
use Propaganistas\LaravelPhone\PhoneNumber;

/**
 * "Show number" / "WhatsApp" (MSG-04/05/06, routes doc §4.1): returns the number or a wa.me link
 * for one channel of a public listing, after the visibility checks, and logs the reveal (IP as
 * HMAC). Guests over the hourly threshold must pass Turnstile first. The seller's own reveals are
 * not logged.
 */
class RevealContact
{
    public function __construct(
        private readonly ContactResolver $resolver,
        private readonly ContactChannels $channels,
    ) {}

    /**
     * @param  array<string, mixed>  $input  channel, turnstile_token
     * @return array<string, string>
     *
     * @throws ModelNotFoundException listing not public
     * @throws AuthenticationException login required
     * @throws AuthorizationException channel not available
     * @throws ContactChallengeRequired
     */
    public function handle(Listing $listing, ?User $viewer, string $ip, array $input): array
    {
        $validated = Validator::make($input, [
            'channel' => ['required', 'in:'.ContactReveal::CALL.','.ContactReveal::WHATSAPP],
            'turnstile_token' => ['nullable', 'string'],
        ])->validate();
        $channel = $validated['channel'];

        if (! $listing->isPublic()) {
            throw (new ModelNotFoundException)->setModel(Listing::class, [$listing->id]);
        }

        match ($this->resolver->decide($listing, $viewer, $channel)) {
            ContactResolver::LOGIN_REQUIRED => throw new AuthenticationException('login_required'),
            ContactResolver::UNAVAILABLE => throw new AuthorizationException,
            default => null,
        };

        $ipHash = AuditLogger::hashIp($ip);
        $isOwner = $viewer !== null && $viewer->id === $listing->user_id;
        if ($viewer === null) {
            $this->challengeGuest($ipHash, $validated['turnstile_token'] ?? null);
        }

        if (! $isOwner) {
            ContactReveal::create(['listing_id' => $listing->id, 'user_id' => $viewer?->id, 'channel' => $channel, 'ip_hash' => $ipHash]);
        }

        return $channel === ContactReveal::CALL ? $this->call($listing) : $this->whatsapp($listing);
    }

    /** @throws ContactChallengeRequired */
    private function challengeGuest(string $ipHash, ?string $token): void
    {
        $turnstile = app(Turnstile::class);
        if (! $turnstile->enabled()) {
            return;
        }

        $recent = ContactReveal::whereNull('user_id')->where('ip_hash', $ipHash)->where('created_at', '>=', now()->subHour())->count();
        if ($recent < (int) config('marketplace.contact.guest_challenge_after')) {
            return;
        }

        // Fails open when Cloudflare is unreachable: the hourly rate limit still applies.
        if ($token === null || ! Validator::make(['t' => $token], ['t' => [new PassesTurnstile(failOpen: true)]])->passes()) {
            throw new ContactChallengeRequired;
        }
    }

    /** @return array<string, string> */
    private function call(Listing $listing): array
    {
        $seller = $listing->user;
        $number = new PhoneNumber((string) $seller->phone);
        // National format for buyers in the seller's country, international otherwise.
        $display = $number->getCountry() === $listing->country->iso2 ? $number->formatNational() : $number->formatInternational();

        return ['channel' => ContactReveal::CALL, 'phone' => (string) $seller->phone, 'display' => $display];
    }

    /** @return array<string, string> */
    private function whatsapp(Listing $listing): array
    {
        $digits = ltrim((string) $this->channels->whatsappNumber($listing), '+');
        $text = __('listings.whatsapp_message', ['title' => $listing->title, 'url' => $listing->publicUrl()]);

        return ['channel' => ContactReveal::WHATSAPP, 'url' => 'https://wa.me/'.$digits.'?text='.rawurlencode($text)];
    }
}
