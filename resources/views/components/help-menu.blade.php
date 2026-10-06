{{--
    The Help menu in the app panel's top bar and the public header. Each item shows only
    when it is available: announcements (signed-in users), the changelog, Contact Support
    (signed-in users, when support is enabled), and the documentation link set in
    `support.documentation_url`.
--}}
@php
    use App\Filament\App\Pages\Announcements;
    use App\Filament\App\Pages\ContactSupport;
    use App\Providers\Filament\AppPanelProvider;
    use Filament\Support\Icons\Heroicon;
    use Illuminate\Support\Facades\Route;

    $signedIn = rescue(fn() => auth()->check(), false, report: false);

    $items = array_filter([
        $signedIn
            ? [
                'label' => 'Announcements',
                'url' => Announcements::getUrl(panel: AppPanelProvider::ID),
                'icon' => Heroicon::OutlinedMegaphone,
                'external' => false,
            ]
            : null,
        Route::has('support.changelog.index')
            ? [
                'label' => 'Changelog',
                'url' => route('support.changelog.index'),
                'icon' => Heroicon::OutlinedNewspaper,
                'external' => false,
            ]
            : null,
        $signedIn && ContactSupport::canAccess()
            ? [
                'label' => 'Contact Support',
                'url' => ContactSupport::getUrl(panel: AppPanelProvider::ID),
                'icon' => Heroicon::OutlinedLifebuoy,
                'external' => false,
            ]
            : null,
        filled(config('support.documentation_url'))
            ? [
                'label' => 'Documentation',
                'url' => config('support.documentation_url'),
                'icon' => Heroicon::OutlinedBookOpen,
                'external' => true,
            ]
            : null,
    ]);
@endphp

@if ($items !== [])
    <x-filament::dropdown data-testid="help-menu"
                          placement="bottom-end"
                          teleport>
        <x-slot name="trigger">
            <button class="flex h-9 items-center gap-1.5 rounded-lg px-2 text-sm font-medium text-white outline-none transition hover:bg-white/10 focus-visible:ring-2 focus-visible:ring-white sm:px-3"
                    data-testid="help-menu-trigger"
                    type="button"
                    aria-label="Help">
                <x-filament::icon class="size-5" :icon="Heroicon::OutlinedQuestionMarkCircle" />
                <span class="hidden sm:inline">Help</span>
                <x-filament::icon class="hidden size-4 sm:block" :icon="Heroicon::ChevronDown" />
            </button>
        </x-slot>

        <x-filament::dropdown.list>
            @foreach ($items as $item)
                <x-filament::dropdown.list.item tag="a"
                                                :href="$item['url']"
                                                :icon="$item['icon']"
                                                :target="$item['external'] ? '_blank' : null">
                    {{ $item['label'] }}
                </x-filament::dropdown.list.item>
            @endforeach
        </x-filament::dropdown.list>
    </x-filament::dropdown>
@endif
