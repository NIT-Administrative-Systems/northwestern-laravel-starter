{{--
    The page guests see at `/`. Replace this content with your application's own;
    signed-in users never see it, because HomeController redirects them.
--}}
@php
    use App\Providers\Filament\AppPanelProvider;
    use Filament\Facades\Filament;
@endphp

<x-layouts.public>
    <section class="mx-auto max-w-3xl px-4 py-20 text-center sm:px-6 lg:py-28">
        <h1 class="font-nu-heading text-nu-purple-100 text-4xl font-bold tracking-tight sm:text-5xl">
            {{ config('app.name') }}
        </h1>

        <p class="mt-6 text-lg text-gray-600">
            A comprehensive starter kit for Laravel projects at Northwestern University
        </p>

        <div class="mt-10 flex flex-wrap justify-center gap-3">
            <x-filament::button tag="a"
                                :href="Filament::getPanel(AppPanelProvider::ID)->getLoginUrl()"
                                size="lg"
                                icon="heroicon-m-arrow-right-end-on-rectangle">
                Sign In
            </x-filament::button>

            <x-filament::button href="https://laravel-starter.entapp.northwestern.edu/"
                                tag="a"
                                target="_blank"
                                color="gray"
                                outlined
                                size="lg"
                                icon="heroicon-m-book-open">
                Documentation
            </x-filament::button>

            <x-filament::button href="https://github.com/NIT-Administrative-Systems/northwestern-laravel-starter"
                                tag="a"
                                target="_blank"
                                color="gray"
                                outlined
                                size="lg"
                                icon="heroicon-m-code-bracket">
                GitHub
            </x-filament::button>
        </div>
    </section>
</x-layouts.public>
