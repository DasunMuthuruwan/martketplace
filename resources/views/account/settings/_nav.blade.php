<nav aria-label="{{ __('account.nav.label') }}">
    <a href="{{ route('account.settings.profile') }}" @if (request()->routeIs('account.settings.profile')) aria-current="page" @endif>{{ __('account.nav.profile') }}</a> ·
    <a href="{{ route('account.settings.contact') }}" @if (request()->routeIs('account.settings.contact')) aria-current="page" @endif>{{ __('account.nav.contact') }}</a> ·
    <a href="{{ route('account.settings.security') }}" @if (request()->routeIs('account.settings.security')) aria-current="page" @endif>{{ __('account.nav.security') }}</a>
</nav>
