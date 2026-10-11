{{-- Honeypot + time trap (+ Turnstile when configured). Validated by CreateNewUser and other public forms. --}}
<div class="hp" aria-hidden="true">
    <label for="website">Website</label>
    <input type="text" id="website" name="website" value="" tabindex="-1" autocomplete="off">
</div>
<input type="hidden" name="{{ \App\Rules\HumanTiming::FIELD }}" value="{{ $formToken }}">
@if ($turnstileSiteKey)
    <div class="cf-turnstile" data-sitekey="{{ $turnstileSiteKey }}"></div>
    @push('head')
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer nonce="{{ Vite::cspNonce() }}"></script>
    @endpush
@endif
@error(\App\Services\Security\Turnstile::RESPONSE_FIELD)<p class="error" role="alert">{{ $message }}</p>@enderror
@error(\App\Rules\HumanTiming::FIELD)<p class="error" role="alert">{{ $message }}</p>@enderror
@error('website')<p class="error" role="alert">{{ $message }}</p>@enderror
