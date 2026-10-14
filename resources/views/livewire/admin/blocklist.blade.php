<div>
    <h1>{{ __('admin.nav.blocklist') }}</h1>

    @if (session('status'))
        <p class="status" role="status">{{ session('status') }}</p>
    @endif
    <p class="muted">{{ __('admin.blocklist.help') }}</p>

    <form wire:submit="add" class="pane">
        <label for="b-term">{{ __('admin.blocklist.term') }}</label>
        <input id="b-term" type="text" wire:model="term" maxlength="100" required>
        @error('term')<p class="error">{{ $message }}</p>@enderror
        <label for="b-match">{{ __('admin.blocklist.match') }}</label>
        <select id="b-match" wire:model="match">
            <option value="word">{{ __('admin.blocklist.matches.word') }}</option>
            <option value="phrase">{{ __('admin.blocklist.matches.phrase') }}</option>
        </select>
        <label for="b-action">{{ __('admin.blocklist.action') }}</label>
        <select id="b-action" wire:model="action">
            <option value="flag">{{ __('admin.blocklist.actions.flag') }}</option>
            <option value="block">{{ __('admin.blocklist.actions.block') }}</option>
        </select>
        <label for="b-country">{{ __('geo.labels.country') }}</label>
        <select id="b-country" wire:model="countryId">
            <option value="">{{ __('admin.blocklist.all_countries') }}</option>
            @foreach ($countries as $c)
                <option value="{{ $c->id }}">{{ $c->iso2 }} · {{ $c->name }}</option>
            @endforeach
        </select>
        @error('countryId')<p class="error">{{ $message }}</p>@enderror
        <button type="submit">{{ __('admin.blocklist.add') }}</button>
    </form>

    <p>
        <label>{{ __('staff.accounts.search') }} <input type="search" wire:model.live.debounce.400ms="q" maxlength="100"></label>
        <label>{{ __('geo.labels.country') }}
            <select wire:model.live="scope">
                <option value="">{{ __('public.any') }}</option>
                <option value="global">{{ __('admin.blocklist.all_countries') }}</option>
                @foreach ($countries as $c)
                    <option value="{{ $c->id }}">{{ $c->iso2 }}</option>
                @endforeach
            </select>
        </label>
    </p>

    @if ($terms->isEmpty())
        <p class="muted">{{ __('admin.blocklist.empty') }}</p>
    @else
        <table>
            <thead><tr><th>{{ __('admin.blocklist.term') }}</th><th>{{ __('admin.blocklist.match') }}</th><th>{{ __('admin.blocklist.action') }}</th><th>{{ __('geo.labels.country') }}</th><th></th></tr></thead>
            <tbody>
                @foreach ($terms as $t)
                    <tr wire:key="term-{{ $t->id }}">
                        <td>{{ $t->term }}</td>
                        <td>{{ __('admin.blocklist.matches.'.$t->match) }}</td>
                        <td>{{ __('admin.blocklist.actions.'.$t->action) }}</td>
                        <td>{{ $t->country_id ? ($countryCodes[$t->country_id] ?? '') : __('admin.blocklist.all_countries') }}</td>
                        <td><button type="button" wire:click="remove({{ $t->id }})" wire:confirm="{{ __('admin.catalogue.confirm_delete') }}">{{ __('admin.catalogue.delete') }}</button></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        {{ $terms->links('livewire.admin.pagination') }}
    @endif
</div>
