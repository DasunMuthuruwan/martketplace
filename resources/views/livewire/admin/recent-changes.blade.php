{{-- Price changes on live ads (MOD-03): before / after, newest first. --}}
<div>
    <x-admin.page-header :title="__('admin.nav.changes')" :description="__('admin.changes.intro')">
        <x-ui.button variant="ghost" size="sm" icon="shield-check" :href="route('admin.moderation')">{{ __('admin.nav.moderation') }}</x-ui.button>
    </x-admin.page-header>

    @if ($changes->isEmpty())
        <x-ui.empty-state icon="clock" :title="__('admin.changes.empty')" />
    @else
        <x-ui.table :caption="__('admin.nav.changes')">
            <thead>
                <tr>
                    <th scope="col">{{ __('admin.moderation.ad') }}</th>
                    <th scope="col" class="num">{{ __('admin.changes.before') }}</th>
                    <th scope="col" class="num">{{ __('admin.changes.after') }}</th>
                    <th scope="col">{{ __('admin.changes.when') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($changes as $change)
                    @php($listing = $listings[$change->subject_id] ?? null)
                    <tr wire:key="change-{{ $change->id }}">
                        <td data-label="{{ __('admin.moderation.ad') }}" class="min-w-0">
                            @if ($listing && $listing->isPublic())
                                <a href="{{ $listing->publicUrl() }}" target="_blank" rel="noopener" class="font-medium text-fg underline-offset-2 hover:underline">{{ $listing->title }}</a>
                            @else
                                <span class="text-fg">{{ $listing?->title ?? '#'.$change->subject_id }}</span>
                            @endif
                        </td>
                        @foreach (['before', 'after'] as $side)
                            <td data-label="{{ __('admin.changes.'.$side) }}" class="num">
                                <span class="text-fg">{{ $change->changes[$side]['price'] ?? '–' }} {{ $listing?->currency }}</span>
                                @if (! empty($change->changes[$side]['price_type']))<span class="block text-fg-subtle">{{ __('posting.price.types.'.$change->changes[$side]['price_type']) }}</span>@endif
                            </td>
                        @endforeach
                        <td data-label="{{ __('admin.changes.when') }}" class="whitespace-nowrap text-fg-muted">{{ $change->created_at->diffForHumans() }}</td>
                    </tr>
                @endforeach
            </tbody>
        </x-ui.table>
        {{ $changes->links('livewire.admin.pagination') }}
    @endif
</div>
