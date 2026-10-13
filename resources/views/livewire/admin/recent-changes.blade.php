<div>
    <h1>{{ __('admin.nav.changes') }}</h1>

    @if ($changes->isEmpty())
        <p>{{ __('admin.changes.empty') }}</p>
    @else
        <table>
            <thead><tr><th>{{ __('admin.moderation.ad') }}</th><th>{{ __('admin.changes.before') }}</th><th>{{ __('admin.changes.after') }}</th><th>{{ __('admin.changes.when') }}</th></tr></thead>
            <tbody>
                @foreach ($changes as $change)
                    @php($listing = $listings[$change->subject_id] ?? null)
                    <tr wire:key="change-{{ $change->id }}">
                        <td>
                            @if ($listing && $listing->isPublic())
                                <a href="{{ $listing->publicUrl() }}">{{ $listing->title }}</a>
                            @else
                                {{ $listing?->title ?? '#'.$change->subject_id }}
                            @endif
                        </td>
                        <td>{{ $change->changes['before']['price'] ?? '–' }} {{ $listing?->currency }} <span class="muted">{{ $change->changes['before']['price_type'] ?? '' }}</span></td>
                        <td>{{ $change->changes['after']['price'] ?? '–' }} {{ $listing?->currency }} <span class="muted">{{ $change->changes['after']['price_type'] ?? '' }}</span></td>
                        <td class="muted">{{ $change->created_at->diffForHumans() }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        {{ $changes->links('livewire.admin.pagination') }}
    @endif
</div>
