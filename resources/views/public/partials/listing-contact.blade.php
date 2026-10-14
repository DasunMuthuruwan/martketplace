{{-- Contact (MSG-04, components §5.2): numbers are never in the HTML. Buttons POST to
     listings.contact; the reply fills this block (resources/js/ui/listing.js). On phones the buttons
     live in the sticky bar, which also fills this block. --}}
<div class="space-y-2" data-contact-box id="contact-box">
    @if ($buttons === [])
        <x-ui.alert>{{ __('public.no_contact') }}</x-ui.alert>
    @elseif (in_array('login_required', $buttons, true))
        <x-ui.button :href="route('login')" icon="phone" block class="max-lg:hidden">{{ __('public.login_to_contact') }}</x-ui.button>
    @else
        <div class="hidden gap-2 lg:grid">
            @if ($hasCall)
                <x-ui.button block size="lg" icon="phone" data-contact-channel="call" data-contact-url="{{ $contactUrl }}" data-contact-target="contact-box">{{ __('public.show_number') }}</x-ui.button>
            @endif
            @if ($hasWhatsapp)
                <x-ui.button block size="lg" variant="secondary" icon="message-circle" data-contact-channel="whatsapp" data-contact-url="{{ $contactUrl }}" data-contact-target="contact-box">{{ __('public.whatsapp') }}</x-ui.button>
            @endif
        </div>
        <div data-contact-result aria-live="polite" class="text-ui empty:hidden"></div>
        <div data-contact-challenge hidden class="space-y-2">
            <p class="text-small text-fg-muted">{{ __('listings.detail.challenge') }}</p>
            @if ($turnstileSiteKey)
                <div class="cf-turnstile" data-sitekey="{{ $turnstileSiteKey }}"></div>
                @once
                    @push('head')
                        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer nonce="{{ Vite::cspNonce() }}"></script>
                    @endpush
                @endonce
            @endif
        </div>
        <template data-contact-messages
            data-login="{{ __('public.login_to_contact') }}" data-login-url="{{ route('login') }}"
            data-unavailable="{{ __('listings.detail.unavailable') }}" data-limit="{{ __('listings.detail.too_many') }}"
            data-error="{{ __('listings.detail.error') }}" data-whatsapp="{{ __('listings.detail.whatsapp_opened') }}"
            data-call-label="{{ __('listings.detail.call') }}"></template>
    @endif
    <p class="flex items-start gap-2 text-small text-fg-subtle"><x-ui.icon name="shield-check" size="16" class="mt-0.5 text-success" />{{ __('public.safety') }}</p>
</div>
