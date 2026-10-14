{{-- Report dialog (components §5.2, routes doc §4.4): reason + details, sent as JSON to reports.store.
     Signed-in users only (the route also needs a verified email). --}}
@auth
    <x-ui.modal id="report-listing" :title="__('listings.detail.report_title')">
        <form method="POST" action="{{ route('reports.store') }}" data-report-form data-error="{{ __('listings.detail.error') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="reportable_type" value="listing">
            <input type="hidden" name="reportable_id" value="{{ $listing->id }}">
            <fieldset class="space-y-2">
                <legend class="mb-1 text-ui font-medium text-fg">{{ __('listings.detail.report_reason') }}</legend>
                @foreach (__('reports.reasons') as $value => $text)
                    <x-ui.radio :label="$text" name="reason" :value="$value" :id="'report-reason-'.$value" />
                @endforeach
            </fieldset>
            <x-ui.textarea :label="__('listings.detail.report_details')" name="details" id="report-details" maxlength="1000" rows="3" :hint="__('listings.detail.report_details_hint')" />
            @if ($turnstileSiteKey)
                <div class="cf-turnstile" data-sitekey="{{ $turnstileSiteKey }}"></div>
                @once
                    @push('head')
                        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer nonce="{{ Vite::cspNonce() }}"></script>
                    @endpush
                @endonce
            @endif
            <p data-report-error role="alert" class="text-small text-danger" hidden></p>
            <div class="flex justify-end gap-2">
                <x-ui.button variant="secondary" data-modal-close>{{ __('admin.catalogue.cancel') }}</x-ui.button>
                <x-ui.button type="submit">{{ __('listings.detail.report_send') }}</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
@endauth
