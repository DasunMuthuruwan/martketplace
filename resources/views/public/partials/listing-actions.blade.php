{{-- Save, share and report (components §5.2). Guests are sent to log in for save and report. --}}
@auth
    <x-ui.button variant="secondary" size="sm" icon="heart" data-favourite-url="{{ route('listings.favourite', $listing) }}" aria-pressed="{{ $favourited ? 'true' : 'false' }}"
        data-label-on="{{ __('listings.detail.saved') }}" data-label-off="{{ __('public.save') }}" class="aria-pressed:bg-surface-muted">
        <span data-favourite-label>{{ $favourited ? __('listings.detail.saved') : __('public.save') }}</span>
    </x-ui.button>
@else
    <x-ui.button variant="secondary" size="sm" icon="heart" :href="route('login')">{{ __('public.save') }}</x-ui.button>
@endauth
<x-ui.button variant="secondary" size="sm" icon="share-2" data-share-url="{{ $listing->publicUrl() }}" data-share-title="{{ $listing->title }}" data-copied="{{ __('listings.detail.link_copied') }}">{{ __('listings.detail.share') }}</x-ui.button>
{{-- Share straight to WhatsApp and Facebook (G3, ADR-027): most local sharing happens there. New tab,
     no referrer or opener; the preview comes from the page's Open Graph tags. --}}
<x-ui.button variant="ghost" size="sm" icon="message-circle" target="_blank" rel="noopener noreferrer" data-share-whatsapp
    :href="config('marketplace.share.whatsapp').rawurlencode($listing->title.' '.$listing->publicUrl())" :aria-label="__('listings.detail.share_whatsapp_label')">{{ __('listings.detail.share_whatsapp') }}</x-ui.button>
<x-ui.button variant="ghost" size="sm" icon="external-link" target="_blank" rel="noopener noreferrer" data-share-facebook
    :href="config('marketplace.share.facebook').rawurlencode($listing->publicUrl())" :aria-label="__('listings.detail.share_facebook_label')">{{ __('listings.detail.share_facebook') }}</x-ui.button>
@auth
    <x-ui.button variant="ghost" size="sm" icon="flag" data-modal-open="report-listing">{{ __('listings.detail.report') }}</x-ui.button>
@else
    <x-ui.button variant="ghost" size="sm" icon="flag" :href="route('login')">{{ __('listings.detail.report') }}</x-ui.button>
@endauth
