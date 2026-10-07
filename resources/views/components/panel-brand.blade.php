{{--
    The application name after the wordmark in the panels' top bar, so the panels match
    <x-site-header>. Registered on the TOPBAR_LOGO_AFTER render hook.
--}}
<span class="ms-3 hidden h-6 w-px shrink-0 bg-white/30 sm:block" aria-hidden="true"></span>

<a class="font-nu-heading ms-3 line-clamp-2 min-w-0 max-w-[40vw] text-sm font-semibold leading-tight text-white sm:max-w-none sm:text-lg"
   href="{{ filament()->getHomeUrl() }}">
    {{ config('app.name') }}
</a>
