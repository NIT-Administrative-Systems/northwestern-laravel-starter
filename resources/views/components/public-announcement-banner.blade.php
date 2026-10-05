{{--
    The most important live announcement for signed-out visitors, under the header on public
    pages and the sign-in pages. Visitors can't dismiss it, and the Announcements page needs
    sign-in, so it shows in full until it ends. A failing lookup shows nothing rather than
    failing the page.
--}}
@php
    use App\Domains\Support\Models\Announcement;

    $announcement = rescue(
        fn() => Announcement::query()->live()->visibleTo(null)->byImportance()->first(),
        null,
        report: false,
    );
@endphp

@if ($announcement)
    <div class="px-4 pt-4 md:px-6 lg:px-8">
        <x-announcement-banner :announcement="$announcement" />
    </div>
@endif
