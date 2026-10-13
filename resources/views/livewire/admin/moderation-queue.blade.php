<div>
    <h1>{{ __('admin.nav.moderation') }}</h1>

    @if (session('status'))
        <p class="status" role="status">{{ session('status') }}</p>
    @endif

    <p>
        <label><input type="checkbox" wire:model.live="flaggedOnly"> {{ __('admin.moderation.flagged_only') }}</label>
        <label>{{ __('auth.ui.country') }}
            <select wire:model.live="country">
                <option value="">{{ __('public.any') }}</option>
                @foreach ($countries as $c)
                    <option value="{{ strtolower($c->iso2) }}">{{ $c->name }}</option>
                @endforeach
            </select>
        </label>
    </p>

    @if ($queue->isEmpty())
        <p>{{ __('admin.moderation.empty') }}</p>
    @else
        <table>
            <thead><tr><th>{{ __('admin.moderation.ad') }}</th><th>{{ __('admin.moderation.seller') }}</th><th>{{ __('admin.moderation.submitted') }}</th><th></th></tr></thead>
            <tbody>
                @foreach ($queue as $item)
                    <tr wire:key="queue-{{ $item->id }}">
                        <td>
                            @if ($item->flagged)<span class="flag">{{ __('admin.moderation.flagged') }}</span>@endif
                            {{ $item->title }}<br>
                            <span class="muted">{{ $item->category->name }} · {{ $item->country->name }}</span>
                        </td>
                        <td>{{ $item->user->name }}</td>
                        <td class="muted">{{ $item->submitted_at?->diffForHumans() }}</td>
                        <td><button type="button" wire:click="select({{ $item->id }})">{{ __('admin.moderation.review') }}</button></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        {{ $queue->links('livewire.admin.pagination') }}
    @endif

    @if ($selected)
        <section class="pane" aria-labelledby="review-title">
            <h2 id="review-title">{{ $selected->title }}</h2>
            <p class="muted">{{ $selected->category->name }} · {{ $present->location($selected, true) }} · {{ $present->price($selected) }}</p>

            @if ($reasons !== [])
                <p class="flag">{{ __('admin.moderation.reasons') }}:
                    @foreach ($reasons as $reason)
                        {{ str_starts_with($reason, 'term:') ? __('admin.moderation.reason_term', ['term' => substr($reason, 5)]) : __('admin.moderation.reason_'.$reason) }}@if (! $loop->last), @endif
                    @endforeach
                </p>
            @endif

            @foreach ($selected->images as $image)
                <img src="{{ $image->url('sm') }}" alt="" width="{{ min($image->width, 480) }}" loading="lazy">
            @endforeach

            <p>{!! nl2br(e($selected->description)) !!}</p>
            @if ($selected->attributes_summary)
                <ul>
                    @foreach ($selected->attributes_summary as $key => $value)
                        <li>{{ $key }}: {{ is_array($value) ? (isset($value['value']) ? $value['value'].' '.$value['unit'] : implode(', ', $value)) : (is_bool($value) ? ($value ? '✓' : '✗') : $value) }}</li>
                    @endforeach
                </ul>
            @endif

            <h3>{{ __('admin.moderation.seller') }}</h3>
            <p class="muted">
                {{ $seller['name'] }} · {{ __('public.member_since', ['date' => $seller['since']?->format('F Y')]) }}
                · {{ $seller['verified'] ? __('public.email_verified') : __('admin.moderation.unverified') }}
                · {{ __('admin.moderation.seller_stats', ['published' => $seller['published'], 'rejected' => $seller['rejected'], 'reports' => $seller['open_reports']]) }}
            </p>

            @error('status')<p class="error" role="alert">{{ $message }}</p>@enderror
            @error('reason')<p class="error" role="alert">{{ $message }}</p>@enderror

            <label for="template">{{ __('admin.moderation.template') }}</label>
            <select id="template" wire:model="template">
                <option value=""></option>
                @foreach ($templates as $key => $text)
                    <option value="{{ $key }}">{{ $text }}</option>
                @endforeach
            </select>
            <label for="note">{{ __('admin.moderation.note') }}</label>
            <textarea id="note" wire:model="note" rows="3" maxlength="500"></textarea>

            <p>
                <button type="button" wire:click="approve">{{ __('admin.moderation.approve') }}</button>
                <button type="button" wire:click="requestChanges">{{ __('admin.moderation.request_changes') }}</button>
                <button type="button" wire:click="reject">{{ __('admin.moderation.reject') }}</button>
            </p>
        </section>
    @endif
</div>
