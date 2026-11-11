{{--
    The panel's name at the top of the sidebar, so a phone, where the top bar has no room for
    it, still says which panel you are in. Registered on the SIDEBAR_NAV_START render hook.
--}}
@props(['name'])

<div data-testid="sidebar-panel-name"
     {{ $attributes->class(['font-nu-heading text-nu-purple-100 px-2 text-sm font-bold dark:text-white']) }}
     x-show="$store.sidebar.isOpen"
     x-cloak>
    {{ $name }}
</div>
