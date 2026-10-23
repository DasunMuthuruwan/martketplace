{{-- Settings (ADM-05): values that override the server configuration, in three groups. Saving asks for the
     password again when the confirmation has expired. --}}
<div class="max-w-3xl">
    <x-admin.page-header :title="__('admin.nav.settings')" :description="__('admin.settings.help')" />

    @if (session('status'))
        <x-ui.alert variant="success" role="status" class="mb-4">{{ session('status') }}</x-ui.alert>
    @endif

    <form wire:submit="save" class="space-y-4">
        <section aria-labelledby="s-site" class="space-y-3 rounded-card border border-line bg-surface p-4 lg:p-5">
            <h2 id="s-site" class="text-h3 font-semibold">{{ __('admin.settings.site') }}</h2>
            <div class="grid gap-3 sm:grid-cols-2">
                <x-ui.input :label="__('admin.settings.site_name')" id="s-name" wire:model="values.site_name" maxlength="60" required :error="$errors->first('values.site_name')" />
                <x-ui.input type="email" :label="__('admin.settings.contact_email')" id="s-email" wire:model="values.contact_email" maxlength="191" :error="$errors->first('values.contact_email')" />
            </div>
            <x-ui.textarea :label="__('admin.settings.seo_country_description')" id="s-seo" wire:model="values.seo_country_description" rows="2" :maxlength="300"
                :hint="__('admin.settings.seo_help')" :error="$errors->first('values.seo_country_description')">{{ $values['seo_country_description'] ?? '' }}</x-ui.textarea>
        </section>

        <section aria-labelledby="s-ads" class="space-y-3 rounded-card border border-line bg-surface p-4 lg:p-5">
            <h2 id="s-ads" class="text-h3 font-semibold">{{ __('admin.settings.ads') }}</h2>
            <div class="max-w-sm">
                <x-ui.select :label="__('admin.settings.moderation_mode')" id="s-moderation" wire:model="values.moderation_mode" :selected="$values['moderation_mode'] ?? null"
                    :error="$errors->first('values.moderation_mode')"
                    :options="collect(['all', 'new_users', 'none'])->mapWithKeys(fn ($m) => [$m => __('admin.settings.moderation_modes.'.$m)])->all()" />
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <x-ui.input type="number" :label="__('admin.settings.listing_expiry_days')" id="s-expiry" wire:model="values.listing_expiry_days" min="7" max="365" :error="$errors->first('values.listing_expiry_days')" />
                <x-ui.input type="number" :label="__('admin.settings.max_images')" id="s-images" wire:model="values.max_images" min="1" max="20" :hint="__('admin.settings.max_images_help')" :error="$errors->first('values.max_images')" />
                <x-ui.input type="number" :label="__('admin.settings.max_active_per_user')" id="s-active" wire:model="values.max_active_per_user" min="1" max="1000" :error="$errors->first('values.max_active_per_user')" />
                <x-ui.input type="number" :label="__('admin.settings.max_new_per_day')" id="s-daily" wire:model="values.max_new_per_day" min="1" max="200" :error="$errors->first('values.max_new_per_day')" />
                <x-ui.input type="number" :label="__('admin.settings.top_ad_slots')" id="s-top" wire:model="values.top_ad_slots" min="0" max="4" :hint="__('admin.settings.top_ad_slots_help')" :error="$errors->first('values.top_ad_slots')" />
            </div>
        </section>

        <section aria-labelledby="s-boosts" class="space-y-3 rounded-card border border-line bg-surface p-4 lg:p-5">
            <h2 id="s-boosts" class="text-h3 font-semibold">{{ __('admin.nav.boosts') }}</h2>
            <x-ui.checkbox type="switch" :label="__('admin.settings.boost_sales')" :hint="__('admin.settings.boost_sales_help')" id="s-boost-sales" wire:model="values.boost_sales_enabled" :checked="(bool) ($values['boost_sales_enabled'] ?? false)" />
        </section>

        <section aria-labelledby="s-trust" class="space-y-3 rounded-card border border-line bg-surface p-4 lg:p-5">
            <h2 id="s-trust" class="text-h3 font-semibold">{{ __('admin.settings.trust') }}</h2>
            <div class="grid gap-3 sm:grid-cols-3">
                <x-ui.input type="number" :label="__('admin.settings.trusted_min_days')" id="s-trusted-days" wire:model="values.trusted_min_days" min="0" max="3650" :error="$errors->first('values.trusted_min_days')" />
                <x-ui.input type="number" :label="__('admin.settings.trusted_min_sold')" id="s-trusted-sold" wire:model="values.trusted_min_sold" min="0" max="1000" :error="$errors->first('values.trusted_min_sold')" />
                <x-ui.input type="number" :label="__('admin.settings.trusted_min_reviews')" id="s-trusted-reviews" wire:model="values.trusted_min_reviews" min="1" max="1000" :error="$errors->first('values.trusted_min_reviews')" />
            </div>
            <p class="text-small text-fg-subtle">{{ __('admin.settings.trusted_help') }}</p>
        </section>

        <section aria-labelledby="s-contact" class="space-y-3 rounded-card border border-line bg-surface p-4 lg:p-5">
            <h2 id="s-contact" class="text-h3 font-semibold">{{ __('admin.settings.contact') }}</h2>
            <x-ui.checkbox type="switch" :label="__('admin.settings.contact_call')" id="s-call" wire:model="values.contact_call" :checked="(bool) ($values['contact_call'] ?? false)" />
            <x-ui.checkbox type="switch" :label="__('admin.settings.contact_whatsapp')" id="s-whatsapp" wire:model="values.contact_whatsapp" :checked="(bool) ($values['contact_whatsapp'] ?? false)" />
        </section>

        <div class="flex justify-end">
            <x-ui.button type="submit" icon="check">{{ __('admin.settings.save') }}</x-ui.button>
        </div>
    </form>
</div>
