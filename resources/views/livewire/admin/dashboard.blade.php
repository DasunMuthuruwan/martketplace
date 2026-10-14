<div>
    <h1>{{ __('admin.dashboard.title') }}</h1>

    <ul class="stats">
        <li>
            <strong>{{ number_format($pending) }}</strong> {{ __('admin.dashboard.pending') }}
            @can(\App\Authorization\Permissions::LISTINGS_MODERATE)<a href="{{ route('admin.moderation') }}">{{ __('admin.dashboard.open') }}</a>@endcan
        </li>
        <li>
            <strong>{{ number_format($openReports) }}</strong> {{ __('admin.dashboard.reports') }}
            @can(\App\Authorization\Permissions::REPORTS_VIEW)<a href="{{ route('admin.reports') }}">{{ __('admin.dashboard.open') }}</a>@endcan
        </li>
        <li>
            <strong>{{ number_format($placeRequests) }}</strong> {{ __('admin.dashboard.place_requests') }}
            @can(\App\Authorization\Permissions::LOCATIONS_MANAGE)<a href="{{ route('admin.location-requests') }}">{{ __('admin.dashboard.open') }}</a>@endcan
        </li>
        <li><strong>{{ number_format($newUsers7) }}</strong> {{ __('admin.dashboard.new_users_7') }} · {{ number_format($newUsers30) }} {{ __('admin.dashboard.new_users_30') }}</li>
        <li><strong>{{ number_format($live) }}</strong> {{ __('admin.dashboard.live') }}</li>
    </ul>

    <h2>{{ __('admin.dashboard.by_category') }}</h2>
    @if ($byCategory === [])
        <p class="muted">{{ __('admin.dashboard.none') }}</p>
    @else
        <table>
            <tbody>
                @foreach ($byCategory as $name => $total)
                    <tr><td>{{ $name }}</td><td>{{ number_format($total) }}</td></tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>{{ __('admin.dashboard.by_country') }}</h2>
    @if ($byCountry === [])
        <p class="muted">{{ __('admin.dashboard.none') }}</p>
    @else
        <table>
            <tbody>
                @foreach ($byCountry as $code => $total)
                    <tr><td>{{ $code }}</td><td>{{ number_format($total) }}</td></tr>
                @endforeach
            </tbody>
        </table>
    @endif
    <p class="muted">{{ __('admin.dashboard.cached') }}</p>
</div>
