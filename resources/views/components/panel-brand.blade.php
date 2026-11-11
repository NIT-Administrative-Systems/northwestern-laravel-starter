{{--
    The application name after the wordmark in the panels' top bar, matching
    <x-site-header>, and registered on the TOPBAR_LOGO_AFTER render hook.

    Below sm the top bar only has room for the wordmark, so the name
    shows at the top of the sidebar, from <x-sidebar-panel-name>.
--}}
<span class="ms-3 hidden h-6 w-px shrink-0 bg-white/30 sm:block" aria-hidden="true"></span>

<a class="font-nu-heading ms-3 hidden min-w-0 text-lg font-semibold leading-tight text-white sm:line-clamp-2"
   href="{{ filament()->getHomeUrl() }}">
    {{ config('app.name') }}
</a>
