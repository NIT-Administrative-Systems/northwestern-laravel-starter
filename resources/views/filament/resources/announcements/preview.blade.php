{{-- The announcement banner as the form describes it, without dismissing or page links. --}}
<div class="flex flex-col gap-2">
    <x-announcement-banner :announcement="$announcement" />

    <p class="text-sm text-gray-500 dark:text-gray-400">
        @if ($announcement->isDismissible())
            People can dismiss it from the banner. It stays on their Announcements page.
        @else
            People can't dismiss a critical announcement. It stays in the banner until it ends.
        @endif
    </p>
</div>
