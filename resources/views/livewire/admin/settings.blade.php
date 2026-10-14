<div>
    <h1>{{ __('admin.nav.settings') }}</h1>

    @if (session('status'))
        <p class="status" role="status">{{ session('status') }}</p>
    @endif
    <p class="muted">{{ __('admin.settings.help') }}</p>

    <form wire:submit="save">
        <fieldset>
            <legend>{{ __('admin.settings.site') }}</legend>
            <label for="s-name">{{ __('admin.settings.site_name') }}</label>
            <input id="s-name" type="text" wire:model="values.site_name" maxlength="60" required>
            @error('values.site_name')<p class="error">{{ $message }}</p>@enderror

            <label for="s-email">{{ __('admin.settings.contact_email') }}</label>
            <input id="s-email" type="email" wire:model="values.contact_email" maxlength="191">
            @error('values.contact_email')<p class="error">{{ $message }}</p>@enderror

            <label for="s-seo">{{ __('admin.settings.seo_country_description') }}</label>
            <textarea id="s-seo" wire:model="values.seo_country_description" rows="2" maxlength="300"></textarea>
            <p class="muted">{{ __('admin.settings.seo_help') }}</p>
            @error('values.seo_country_description')<p class="error">{{ $message }}</p>@enderror
        </fieldset>

        <fieldset>
            <legend>{{ __('admin.settings.ads') }}</legend>
            <label for="s-moderation">{{ __('admin.settings.moderation_mode') }}</label>
            <select id="s-moderation" wire:model="values.moderation_mode">
                @foreach (['all', 'new_users', 'none'] as $mode)
                    <option value="{{ $mode }}">{{ __('admin.settings.moderation_modes.'.$mode) }}</option>
                @endforeach
            </select>
            @error('values.moderation_mode')<p class="error">{{ $message }}</p>@enderror

            <label for="s-expiry">{{ __('admin.settings.listing_expiry_days') }}</label>
            <input id="s-expiry" type="number" wire:model="values.listing_expiry_days" min="7" max="365">
            @error('values.listing_expiry_days')<p class="error">{{ $message }}</p>@enderror

            <label for="s-active">{{ __('admin.settings.max_active_per_user') }}</label>
            <input id="s-active" type="number" wire:model="values.max_active_per_user" min="1" max="1000">
            @error('values.max_active_per_user')<p class="error">{{ $message }}</p>@enderror

            <label for="s-daily">{{ __('admin.settings.max_new_per_day') }}</label>
            <input id="s-daily" type="number" wire:model="values.max_new_per_day" min="1" max="200">
            @error('values.max_new_per_day')<p class="error">{{ $message }}</p>@enderror

            <label for="s-images">{{ __('admin.settings.max_images') }}</label>
            <input id="s-images" type="number" wire:model="values.max_images" min="1" max="20">
            <p class="muted">{{ __('admin.settings.max_images_help') }}</p>
            @error('values.max_images')<p class="error">{{ $message }}</p>@enderror
        </fieldset>

        <fieldset>
            <legend>{{ __('admin.settings.contact') }}</legend>
            <label><input type="checkbox" wire:model="values.contact_call"> {{ __('admin.settings.contact_call') }}</label>
            <label><input type="checkbox" wire:model="values.contact_whatsapp"> {{ __('admin.settings.contact_whatsapp') }}</label>
        </fieldset>

        <p><button type="submit">{{ __('staff.manage.save') }}</button></p>
    </form>
</div>
