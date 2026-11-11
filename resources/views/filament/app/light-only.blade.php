{{--
    Keeps the app panel's simple pages (sign-in
    and lockdown, see HasSiteHeader) light,
    like the public and error layouts, so
    dark mode starts inside the panel.

    Rendered at the end of their <head>.

    Filament adds `dark` to <html> from the person's theme setting: in its
    head script, at the end of the body, and after each SPA navigation.

    While a page has the site header, the observer
    takes it off again before anything paints.

    Livewire runs this script once, so the listener decides after each navigation:
    the panel's own pages have no site header, so they keep Filament's choice.
--}}
<script data-navigate-once>
    (() => {
        const root = document.documentElement;

        // Only if present: removing a class rewrites the attribute and wakes the observer.
        const removeDark = () => root.classList.contains('dark') && root.classList.remove('dark');
        const keepLight = new MutationObserver(removeDark);

        const stayLight = () => {
            removeDark();
            keepLight.observe(root, {
                attributes: true,
                attributeFilter: ['class']
            });
        };

        stayLight();

        document.addEventListener('livewire:navigated', () => {
            keepLight.disconnect();

            if (document.querySelector('.nu-site-header')) {
                stayLight();
            }
        });
    })();
</script>
