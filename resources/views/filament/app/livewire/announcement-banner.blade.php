{{--
    Livewire needs one root element, even when there's nothing to show. The top padding matches
    the page header's (`.fi-page-header-main-ctn`), so the banner sits as far below the top bar as
    the heading sits below the banner.
--}}
<div @class(['pt-8' => $announcement !== null])>
    @if ($announcement)
        <x-announcement-banner :announcement="$announcement"
                               :others="$others"
                               :dismissible="$dismissible && $announcement->isDismissible()"
                               :page-url="$pageUrl" />
    @endif
</div>
